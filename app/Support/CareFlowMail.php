<?php

namespace App\Support;

use App\Models\Team;
use Illuminate\Notifications\Messages\MailMessage;

class CareFlowMail
{
    /**
     * @param  list<string>  $introLines
     * @param  array<string, string|null>  $meta
     * @param  list<string>  $outroLines
     */
    public static function message(
        string $subject,
        string $title,
        string $greeting,
        array $introLines,
        ?string $actionText,
        ?string $actionUrl,
        array $meta,
        array $outroLines,
        Team $team,
        string $eyebrow = 'International patient care',
        ?string $preheader = null,
    ): MailMessage {
        $organizationName = $team->organizationSetting?->legal_name ?: $team->name;
        $organizationContact = $team->organizationSetting?->contact_email;

        return (new MailMessage)
            ->subject($subject)
            ->view('mail.careflow.layout', [
                'subject' => $subject,
                'title' => $title,
                'greeting' => $greeting,
                'introLines' => $introLines,
                'actionText' => $actionText,
                'actionUrl' => $actionUrl,
                'meta' => array_filter(
                    $meta,
                    static fn (?string $value): bool => filled($value),
                ),
                'outroLines' => $outroLines,
                'organizationName' => $organizationName,
                'organizationContact' => $organizationContact,
                'eyebrow' => $eyebrow,
                'preheader' => $preheader,
            ]);
    }
}
