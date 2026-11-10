<?php

use App\Models\Team;
use App\Support\CaseReferenceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('case references increment atomically per organization and year', function () {
    $team = Team::factory()->create();
    $generator = app(CaseReferenceGenerator::class);

    $first = $generator->next($team, 2026);
    $second = $generator->next($team, 2026);

    expect($first)->toBe('CF-2026-000001')
        ->and($second)->toBe('CF-2026-000002');
});

test('case reference sequences are isolated between organizations', function () {
    $alpha = Team::factory()->create();
    $beta = Team::factory()->create();
    $generator = app(CaseReferenceGenerator::class);

    expect($generator->next($alpha, 2026))->toBe('CF-2026-000001')
        ->and($generator->next($beta, 2026))->toBe('CF-2026-000001');
});
