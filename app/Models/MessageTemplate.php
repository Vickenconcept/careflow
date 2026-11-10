<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\CommunicationChannel;
use App\Enums\MessageTemplateKey;
use Database\Factories\MessageTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'team_id',
    'uuid',
    'key',
    'name',
    'channel',
    'is_active',
    'subject_translations',
    'body_translations',
    'notes',
])]
class MessageTemplate extends Model
{
    /** @use HasFactory<MessageTemplateFactory> */
    use BelongsToTeam, HasFactory, SoftDeletes;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => MessageTemplateKey::class,
            'channel' => CommunicationChannel::class,
            'is_active' => 'boolean',
            'subject_translations' => 'array',
            'body_translations' => 'array',
        ];
    }
}
