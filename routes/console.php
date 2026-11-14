<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

// Runs hourly; each hospital is handled once, at 07:00 in its own time zone.
Schedule::command('careflow:daily-reminders --local-hour=7')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Send follow-up and overdue task reminders and expire out-of-date proposals');
