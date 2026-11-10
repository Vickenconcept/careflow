<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\PatientGender;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $uuid
 * @property string $first_name
 * @property string $last_name
 * @property Carbon|null $date_of_birth
 * @property PatientGender|null $gender
 * @property string|null $country_of_residence
 * @property string $email
 * @property string|null $phone
 * @property string $preferred_language
 * @property bool $is_demo
 */
#[Fillable([
    'team_id',
    'uuid',
    'first_name',
    'last_name',
    'date_of_birth',
    'gender',
    'country_of_residence',
    'email',
    'phone',
    'preferred_language',
    'is_demo',
])]
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use BelongsToTeam, HasFactory, SoftDeletes;

    /**
     * @return HasMany<PatientEnquiry, $this>
     */
    public function enquiries(): HasMany
    {
        return $this->hasMany(PatientEnquiry::class);
    }

    /**
     * @return HasMany<PatientCase, $this>
     */
    public function cases(): HasMany
    {
        return $this->hasMany(PatientCase::class);
    }

    /**
     * @return HasMany<PatientDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(PatientDocument::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'gender' => PatientGender::class,
            'is_demo' => 'boolean',
        ];
    }
}
