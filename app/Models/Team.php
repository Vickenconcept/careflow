<?php

namespace App\Models;

use App\Actions\Organizations\EnsureDefaultMessageTemplates;
use App\Actions\Organizations\EnsureDefaultWorkflowDefinitions;
use App\Concerns\GeneratesUniqueTeamSlugs;
use App\Enums\TeamRole;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_personal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Department> $departments
 * @property-read Collection<int, TeamInvitation> $invitations
 * @property-read Collection<int, Membership> $memberships
 * @property-read Collection<int, User> $members
 * @property-read OrganizationSetting|null $organizationSetting
 */
#[Fillable(['name', 'slug', 'is_personal'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use GeneratesUniqueTeamSlugs, HasFactory, SoftDeletes;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Team $team) {
            if (empty($team->slug)) {
                $team->slug = static::generateUniqueTeamSlug($team->name);
            }
        });

        static::updating(function (Team $team) {
            if ($team->isDirty('name')) {
                $team->slug = static::generateUniqueTeamSlug($team->name, $team->id);
            }
        });
    }

    /**
     * Get the team owner.
     */
    public function owner(): ?Model
    {
        return $this->members()
            ->wherePivot('role', TeamRole::Owner->value)
            ->first();
    }

    /**
     * Get all members of this team.
     *
     * @return BelongsToMany<User, $this, Membership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
            ->using(Membership::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all memberships for this team.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get all invitations for this team.
     *
     * @return HasMany<TeamInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /**
     * Get the organization settings for this team tenant.
     *
     * @return HasOne<OrganizationSetting, $this>
     */
    public function organizationSetting(): HasOne
    {
        return $this->hasOne(OrganizationSetting::class);
    }

    /**
     * Whether an optional product module is switched on for this organization.
     */
    public function moduleEnabled(string $module): bool
    {
        return $this->organizationSetting?->moduleEnabled($module) ?? true;
    }

    /**
     * Whether an organization notification type is switched on.
     */
    public function notificationEnabled(string $key): bool
    {
        return $this->organizationSetting?->notificationEnabled($key) ?? true;
    }

    /**
     * Get the departments for this organization.
     *
     * @return HasMany<Department, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Ensure organization settings exist for this tenant.
     */
    public function ensureOrganizationSetting(): OrganizationSetting
    {
        $settings = $this->organizationSetting()->firstOrCreate([], [
            'legal_name' => $this->name,
            'supported_languages' => ['en', 'tr', 'ar', 'ru', 'az'],
            'enabled_modules' => ['intake', 'crm', 'documents'],
            'ai_provider' => 'openai',
        ]);

        app(EnsureDefaultMessageTemplates::class)->handle($this);
        app(EnsureDefaultWorkflowDefinitions::class)->handle($this);

        return $settings;
    }

    /**
     * @return HasMany<Patient, $this>
     */
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    /**
     * @return HasMany<PatientCase, $this>
     */
    public function patientCases(): HasMany
    {
        return $this->hasMany(PatientCase::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_personal' => 'boolean',
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
