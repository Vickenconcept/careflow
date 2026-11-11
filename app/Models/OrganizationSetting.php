<?php

namespace App\Models;

use Database\Factories\OrganizationSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string|null $legal_name
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $address_line_1
 * @property string|null $address_line_2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $country
 * @property string $timezone
 * @property string $default_currency
 * @property string $default_language
 * @property array<int, string>|null $supported_languages
 * @property string|null $logo_path
 * @property string|null $brand_primary_color
 * @property string|null $brand_secondary_color
 * @property bool $ai_processing_enabled
 * @property string $ai_provider
 * @property array<int, string>|null $enabled_modules
 * @property array<int, string>|null $disabled_modules
 * @property array<string, bool>|null $notification_settings
 * @property int|null $document_retention_months
 * @property string $plan
 * @property string $subscription_status
 * @property int|null $seat_limit
 * @property array<int, string>|null $locked_modules
 * @property string|null $platform_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 */
#[Fillable([
    'team_id',
    'legal_name',
    'contact_email',
    'contact_phone',
    'address_line_1',
    'address_line_2',
    'city',
    'state',
    'postal_code',
    'country',
    'timezone',
    'default_currency',
    'default_language',
    'supported_languages',
    'logo_path',
    'brand_primary_color',
    'brand_secondary_color',
    'ai_processing_enabled',
    'ai_provider',
    'enabled_modules',
    'disabled_modules',
    'notification_settings',
    'document_retention_months',
    'plan',
    'subscription_status',
    'seat_limit',
    'locked_modules',
    'platform_notes',
    'storage_driver',
    'storage_config',
    'assistant_enabled',
    'assistant_name',
    'assistant_knowledge',
])]
#[Hidden(['storage_config'])]
class OrganizationSetting extends Model
{
    /** @use HasFactory<OrganizationSettingFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Optional product modules a hospital can switch off. Everything is on by default.
     *
     * @return array<string, string>
     */
    public static function optionalModules(): array
    {
        return [
            'proposals' => 'Treatment proposals',
            'appointments' => 'Appointment coordination',
            'travel' => 'Travel coordination',
            'communications' => 'Patient communications',
        ];
    }

    /**
     * Notifications an organization can switch off. Everything is on by default.
     *
     * @return array<string, string>
     */
    public static function notificationOptions(): array
    {
        return [
            'staff_new_enquiry' => 'Staff: a new patient enquiry arrives',
            'staff_patient_update' => 'Staff: a patient uploads documents or replies',
            'staff_case_assigned' => 'Staff: a case is assigned to them',
            'patient_confirmation' => 'Patient: enquiry received confirmation email',
        ];
    }

    /**
     * Plans are manual records kept by platform admins; no payment processor is connected.
     *
     * @return array<string, string>
     */
    public static function plans(): array
    {
        return [
            'trial' => 'Trial',
            'standard' => 'Standard',
            'enterprise' => 'Enterprise',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function subscriptionStatuses(): array
    {
        return [
            'active' => 'Active',
            'suspended' => 'Suspended',
        ];
    }

    /**
     * A module is available only if the platform entitles the organization to it
     * and the organization has not switched it off itself.
     */
    public function moduleEnabled(string $module): bool
    {
        return ! $this->moduleLocked($module)
            && ! in_array($module, $this->disabled_modules ?? [], true);
    }

    public function moduleLocked(string $module): bool
    {
        return in_array($module, $this->locked_modules ?? [], true);
    }

    public function isSuspended(): bool
    {
        return $this->subscription_status === 'suspended';
    }

    public function notificationEnabled(string $key): bool
    {
        return (bool) (($this->notification_settings ?? [])[$key] ?? true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'supported_languages' => 'array',
            'enabled_modules' => 'array',
            'disabled_modules' => 'array',
            'locked_modules' => 'array',
            'storage_config' => 'encrypted:array',
            'notification_settings' => 'array',
            'ai_processing_enabled' => 'boolean',
            'assistant_enabled' => 'boolean',
        ];
    }
}
