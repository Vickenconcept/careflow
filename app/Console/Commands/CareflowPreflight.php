<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

#[Signature('careflow:preflight')]
#[Description('Check production readiness: debug mode, queues, mail, AI provider, storage and registration')]
class CareflowPreflight extends Command
{
    /**
     * Run every check and exit non-zero when any blocker is found.
     */
    public function handle(): int
    {
        $checks = $this->checks();
        $blockers = 0;

        foreach ($checks as [$label, $ok, $advice, $blocking]) {
            if ($ok) {
                $this->line("  <info>PASS</info>  {$label}");

                continue;
            }

            $tag = $blocking ? '<error> FAIL </error>' : '<comment> WARN </comment>';
            $this->line("  {$tag} {$label} — {$advice}");
            $blockers += $blocking ? 1 : 0;
        }

        $this->newLine();
        $this->line($blockers === 0 ? '<info>No blockers found.</info>' : "<error>{$blockers} blocker(s) must be fixed before a real hospital pilot.</error>");

        return $blockers === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<array{0: string, 1: bool, 2: string, 3: bool}>
     */
    public function checks(): array
    {
        $aiProvider = (string) config('careflow.ai.provider');

        return [
            ['APP_ENV is production', app()->environment('production'), 'Set APP_ENV=production.', true],
            ['APP_DEBUG is off', ! config('app.debug'), 'Set APP_DEBUG=false; debug pages leak secrets and patient data.', true],
            ['APP_URL uses https', str_starts_with((string) config('app.url'), 'https://'), 'Serve the app over HTTPS only.', true],
            ['Session cookies are secure', (bool) config('session.secure'), 'Set SESSION_SECURE_COOKIE=true.', true],
            ['Session data is encrypted', (bool) config('session.encrypt'), 'Set SESSION_ENCRYPT=true.', false],
            ['Queue runs in the background', ! in_array(config('queue.default'), ['sync', 'null'], true), 'Use database or redis queues and keep a worker running (php artisan queue:work).', true],
            ['Failed jobs table exists', Schema::hasTable('failed_jobs'), 'Run migrations so failed jobs are recorded.', true],
            ['Mail is delivered (not logged)', ! in_array(config('mail.default'), ['log', 'array'], true), 'Configure a real MAIL_MAILER; patient and staff emails are currently only written to the log.', true],
            ['AI provider is OpenAI', $aiProvider === 'openai', 'Set CAREFLOW_AI_PROVIDER=openai.', true],
            ['AI provider key is configured', filled(config('ai.providers.openai.key') ?? env('OPENAI_API_KEY')), 'Set OPENAI_API_KEY (use only approved safeguards for real patient documents).', false],
            ['Public registration is closed', ! config('careflow.allow_registration'), 'Set CAREFLOW_ALLOW_REGISTRATION=false so only invited staff can sign up.', false],
            ['Two-factor authentication is required', (bool) config('careflow.require_two_factor'), 'Set CAREFLOW_REQUIRE_2FA=true so every staff account must use an authenticator app.', true],
            ['Platform admin emails configured', config('careflow.admin_emails') !== [], 'Set ADMIN_EMAILS for platform administration.', false],
            ['Documents use a private disk', ! in_array(config('careflow.documents.disk'), ['public'], true), 'CAREFLOW_DOCUMENTS_DISK must never be the public disk.', true],
            ['Hospital information system connected', false, 'No HIS is connected; staff type booking references by hand. Do not claim live hospital integration.', false],
            ['Outbound communications are real', config('careflow.communications.provider') !== 'log', 'WhatsApp is record-only; no WhatsApp sender is connected. Email is sent through MAIL_MAILER.', false],
        ];
    }
}
