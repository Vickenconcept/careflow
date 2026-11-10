<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\EnsureDefaultMessageTemplates;
use App\Actions\Organizations\UpsertMessageTemplate;
use App\Enums\CommunicationChannel;
use App\Enums\MessageTemplateKey;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\UpsertMessageTemplateRequest;
use App\Models\MessageTemplate;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessageTemplateController extends Controller
{
    public function index(
        Request $request,
        Team $current_team,
        EnsureDefaultMessageTemplates $ensureDefaultMessageTemplates,
    ): Response {
        abort_unless(
            $request->user()?->belongsToTeam($current_team)
                && $request->user()->hasTeamPermission($current_team, TeamPermission::ManageCommunications),
            403,
        );

        $ensureDefaultMessageTemplates->handle($current_team);

        $templates = MessageTemplate::query()
            ->where('team_id', $current_team->id)
            ->orderBy('name')
            ->get()
            ->map(fn (MessageTemplate $template) => [
                'uuid' => $template->uuid,
                'key' => $template->key->value,
                'key_label' => $template->key->label(),
                'name' => $template->name,
                'channel' => $template->channel->value,
                'channel_label' => $template->channel->label(),
                'is_active' => $template->is_active,
                'subject_translations' => $template->subject_translations ?? [],
                'body_translations' => $template->body_translations ?? [],
                'notes' => $template->notes,
            ]);

        return Inertia::render('organizations/message-templates/index', [
            'team' => [
                'id' => $current_team->id,
                'name' => $current_team->name,
                'slug' => $current_team->slug,
            ],
            'templates' => $templates,
            'languages' => config('careflow.portal.supported_languages'),
            'channel_options' => CommunicationChannel::options(),
            'key_options' => collect(MessageTemplateKey::cases())
                ->map(fn (MessageTemplateKey $key) => [
                    'value' => $key->value,
                    'label' => $key->label(),
                ])
                ->values(),
            'urls' => [
                'upsert' => route('organizations.message-templates.upsert', [
                    'current_team' => $current_team->slug,
                ]),
            ],
        ]);
    }

    public function upsert(
        UpsertMessageTemplateRequest $request,
        Team $current_team,
        UpsertMessageTemplate $upsertMessageTemplate,
    ): RedirectResponse {
        $upsertMessageTemplate->handle(
            team: $current_team,
            actor: $request->user(),
            data: $request->validated(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Message template saved.'),
        ]);

        return to_route('organizations.message-templates.index', [
            'current_team' => $current_team->slug,
        ]);
    }
}
