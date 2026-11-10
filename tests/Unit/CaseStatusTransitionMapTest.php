<?php

use App\Domains\Cases\CaseStatusTransitionMap;
use App\Enums\CaseStatus;
use App\Exceptions\InvalidCaseStatusTransitionException;
use App\Models\PatientCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('allowed transitions reject illegal jumps', function () {
    $map = new CaseStatusTransitionMap;

    expect($map->canTransition(CaseStatus::NewEnquiry, CaseStatus::AwaitingDocuments))->toBeTrue()
        ->and($map->canTransition(CaseStatus::NewEnquiry, CaseStatus::TreatmentScheduled))->toBeFalse()
        ->and($map->canTransition(CaseStatus::Closed, CaseStatus::NewEnquiry))->toBeFalse();
});

test('treatment scheduled requires later-phase prerequisites', function () {
    $map = new CaseStatusTransitionMap;
    $case = new PatientCase;

    expect(fn () => $map->assertPrerequisites($case, CaseStatus::TreatmentScheduled))
        ->toThrow(InvalidCaseStatusTransitionException::class);
});
