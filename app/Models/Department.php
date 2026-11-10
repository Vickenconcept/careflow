<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string $slug
 * @property string|null $code
 * @property string|null $description
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 */
#[Fillable([
    'team_id',
    'name',
    'slug',
    'code',
    'description',
    'is_active',
    'sort_order',
])]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use BelongsToTeam, HasFactory, SoftDeletes;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Department $department): void {
            if (empty($department->slug)) {
                $department->slug = static::uniqueSlugForTeam(
                    $department->team_id,
                    $department->name,
                );
            }

            if (blank($department->code) && filled($department->name)) {
                $department->code = static::codeFromName($department->name);
            }
        });

        static::updating(function (Department $department): void {
            if ($department->isDirty('name') && ! $department->isDirty('slug')) {
                $department->slug = static::uniqueSlugForTeam(
                    $department->team_id,
                    $department->name,
                    $department->id,
                );
            }
        });
    }

    /**
     * Generate a unique department slug within a team.
     */
    public static function uniqueSlugForTeam(int $teamId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'department';
        $slug = $base;
        $suffix = 1;

        while (static::query()
            ->where('team_id', $teamId)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->withTrashed()
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * Build a short uppercase code from a department name.
     */
    public static function codeFromName(string $name): string
    {
        $words = collect(preg_split('/\s+/', trim($name)) ?: [])
            ->map(fn (string $word): string => preg_replace('/[^a-zA-Z0-9]/', '', $word) ?? '')
            ->filter()
            ->values();

        if ($words->isEmpty()) {
            return 'DEPT';
        }

        if ($words->count() === 1) {
            return Str::upper(Str::substr($words->first(), 0, 6));
        }

        $initials = $words
            ->map(fn (string $word): string => Str::substr($word, 0, 1))
            ->implode('');

        return Str::upper(Str::substr($initials, 0, 8));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
