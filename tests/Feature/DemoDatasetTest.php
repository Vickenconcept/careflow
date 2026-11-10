<?php

use App\Demo\CareFlowDemoDataset;
use App\Enums\CaseStatus;
use App\Enums\TeamRole;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientDocument;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('demo dataset seeds hospital staff and at least fifteen synthetic cases', function () {
    $result = app(CareFlowDemoDataset::class)->seed();

    $team = $result['team'];

    expect($team->slug)->toBe(CareFlowDemoDataset::TEAM_SLUG)
        ->and($result['cases_created'])->toBeGreaterThanOrEqual(15)
        ->and(PatientCase::query()->where('team_id', $team->id)->where('is_demo', true)->count())
        ->toBeGreaterThanOrEqual(15)
        ->and(Patient::query()->where('team_id', $team->id)->where('is_demo', true)->count())
        ->toBeGreaterThanOrEqual(15);

    foreach (['admin', 'coordinator', 'doctor', 'nurse', 'viewer'] as $key) {
        expect($result['users'][$key])->toBeInstanceOf(User::class)
            ->and($team->members()->where('user_id', $result['users'][$key]->id)->exists())->toBeTrue();
    }

    expect($team->memberships()->where('role', TeamRole::Doctor->value)->exists())->toBeTrue();

    $processed = PatientDocument::query()
        ->where('team_id', $team->id)
        ->whereHas('currentExtraction')
        ->count();

    expect($processed)->toBeGreaterThan(0);

    expect(
        PatientCase::query()
            ->where('team_id', $team->id)
            ->where('status', CaseStatus::AwaitingDoctorReview)
            ->exists(),
    )->toBeTrue();
});

test('demo dataset is idempotent and remains labelled as demonstration data', function () {
    app(CareFlowDemoDataset::class)->seed();
    $firstCount = PatientCase::query()->where('is_demo', true)->count();

    $second = app(CareFlowDemoDataset::class)->seed();

    expect(PatientCase::query()->where('is_demo', true)->count())->toBe($firstCount)
        ->and($second['cases_created'])->toBe($firstCount)
        ->and(Patient::query()->where('is_demo', false)->where('email', 'like', '%@careflow-demo.test')->count())->toBe(0);
});
