<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\CasePriority;
use App\Enums\CaseStatus;
use Database\Factories\PatientCaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $team_id
 * @property int $patient_id
 * @property int $patient_enquiry_id
 * @property int|null $department_id
 * @property string $reference
 * @property CaseStatus $status
 * @property CasePriority $priority
 * @property int|null $assigned_coordinator_id
 * @property int|null $assigned_doctor_id
 * @property string|null $portal_access_token_hash
 * @property Carbon|null $portal_access_token_expires_at
 * @property Carbon|null $last_activity_at
 * @property bool $is_demo
 */
#[Fillable([
    'team_id',
    'patient_id',
    'patient_enquiry_id',
    'department_id',
    'reference',
    'status',
    'priority',
    'assigned_coordinator_id',
    'assigned_doctor_id',
    'portal_access_token_hash',
    'portal_access_token_expires_at',
    'last_activity_at',
    'is_demo',
])]
#[Hidden(['portal_access_token_hash'])]
class PatientCase extends Model
{
    /** @use HasFactory<PatientCaseFactory> */
    use BelongsToTeam, HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<PatientEnquiry, $this>
     */
    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(PatientEnquiry::class, 'patient_enquiry_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedCoordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_coordinator_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedDoctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_doctor_id');
    }

    /**
     * @return HasMany<PatientDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(PatientDocument::class);
    }

    /**
     * @return HasMany<CaseActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(CaseActivity::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    /**
     * @return HasMany<DoctorReview, $this>
     */
    public function doctorReviews(): HasMany
    {
        return $this->hasMany(DoctorReview::class)->orderByDesc('started_at')->orderByDesc('id');
    }

    /**
     * @return HasMany<ClinicalObservation, $this>
     */
    public function clinicalObservations(): HasMany
    {
        return $this->hasMany(ClinicalObservation::class)->orderByDesc('observed_at')->orderByDesc('id');
    }

    /**
     * @return HasMany<ClinicalRecommendation, $this>
     */
    public function clinicalRecommendations(): HasMany
    {
        return $this->hasMany(ClinicalRecommendation::class)->orderByDesc('version');
    }

    /**
     * @return HasMany<TreatmentProposal, $this>
     */
    public function treatmentProposals(): HasMany
    {
        return $this->hasMany(TreatmentProposal::class)->orderByDesc('version')->orderByDesc('id');
    }

    /**
     * @return HasMany<CaseAppointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(CaseAppointment::class)->orderByDesc('proposed_starts_at')->orderByDesc('id');
    }

    /**
     * @return HasOne<TravelArrangement, $this>
     */
    public function travelArrangement(): HasOne
    {
        return $this->hasOne(TravelArrangement::class);
    }

    /**
     * @return HasMany<CaseTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(CaseTask::class)->orderByDesc('due_at')->orderByDesc('id');
    }

    /**
     * @return HasMany<CaseCommunication, $this>
     */
    public function communications(): HasMany
    {
        return $this->hasMany(CaseCommunication::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * @return HasMany<PatientFollowUp, $this>
     */
    public function followUps(): HasMany
    {
        return $this->hasMany(PatientFollowUp::class)->orderBy('due_at')->orderByDesc('id');
    }

    /**
     * Issue a new opaque portal access token. Returns the raw token once.
     */
    public function issuePortalAccessToken(?Carbon $expiresAt = null): string
    {
        $rawToken = Str::random(64);

        $this->forceFill([
            'portal_access_token_hash' => hash('sha256', $rawToken),
            'portal_access_token_expires_at' => $expiresAt ?? now()->addDays(
                (int) config('careflow.portal.access_token_ttl_days', 90),
            ),
        ])->save();

        return $rawToken;
    }

    public function portalAccessTokenIsValid(string $rawToken): bool
    {
        if ($this->portal_access_token_hash === null) {
            return false;
        }

        if ($this->portal_access_token_expires_at !== null && $this->portal_access_token_expires_at->isPast()) {
            return false;
        }

        return hash_equals($this->portal_access_token_hash, hash('sha256', $rawToken));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleToStaff(Builder $query, User $user, Team $team): Builder
    {
        $query->forTeam($team);

        $role = $user->teamRole($team);

        if ($role === null) {
            return $query->whereRaw('1 = 0');
        }

        if (in_array($role->value, ['owner', 'admin', 'coordinator', 'management_viewer'], true)) {
            return $query;
        }

        if ($role->value === 'doctor') {
            return $query->where('assigned_doctor_id', $user->id);
        }

        if ($role->value === 'nurse') {
            return $query->where(function (Builder $builder) use ($user): void {
                $builder
                    ->where('assigned_doctor_id', $user->id)
                    ->orWhere('assigned_coordinator_id', $user->id);
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * References are only unique within an organization, so resolve them inside the
     * organization named in the URL; never across tenants.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $team = request()->route('current_team');
        $teamId = $team instanceof Team
            ? $team->id
            : (is_string($team) ? Team::query()->where('slug', $team)->value('id') : null);

        if ($teamId === null) {
            return null;
        }

        return $this->newQuery()
            ->where('team_id', $teamId)
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CaseStatus::class,
            'priority' => CasePriority::class,
            'portal_access_token_expires_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }
}
