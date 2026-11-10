<?php

namespace App\Models;

use Database\Factories\OrganizationSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
])]
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'supported_languages' => 'array',
            'enabled_modules' => 'array',
            'ai_processing_enabled' => 'boolean',
        ];
    }
}
