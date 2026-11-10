<?php

namespace App\Enums;

enum DocumentProcessingStatus: string
{
    case Uploaded = 'uploaded';
    case Pending = 'pending';
    case Validating = 'validating';
    case Extracting = 'extracting';
    case DetectingLanguage = 'detecting_language';
    case Translating = 'translating';
    case Structuring = 'structuring';
    case Summarizing = 'summarizing';
    case Completed = 'completed';
    case NeedsReview = 'needs_review';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Uploaded',
            self::Pending => 'Pending',
            self::Validating => 'Validating',
            self::Extracting => 'Extracting',
            self::DetectingLanguage => 'Detecting Language',
            self::Translating => 'Translating',
            self::Structuring => 'Structuring',
            self::Summarizing => 'Summarizing',
            self::Completed => 'Completed',
            self::NeedsReview => 'Needs Review',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isInProgress(): bool
    {
        return match ($this) {
            self::Uploaded,
            self::Pending,
            self::Validating,
            self::Extracting,
            self::DetectingLanguage,
            self::Translating,
            self::Structuring,
            self::Summarizing => true,
            default => false,
        };
    }

    /**
     * Ordered pipeline steps for staff progress UI.
     *
     * @return list<array{key: string, label: string, state: 'complete'|'current'|'upcoming'|'failed'}>
     */
    public function progressSteps(): array
    {
        $steps = [
            ['key' => 'queued', 'label' => 'Queued for AI'],
            ['key' => 'validating', 'label' => 'Validating file'],
            ['key' => 'extracting', 'label' => 'Extracting text'],
            ['key' => 'detecting_language', 'label' => 'Detecting language'],
            ['key' => 'structuring', 'label' => 'Structuring fields'],
            ['key' => 'translating', 'label' => 'Translating'],
            ['key' => 'summarizing', 'label' => 'Summarizing'],
            ['key' => 'needs_review', 'label' => 'Ready for review'],
        ];

        $currentIndex = match ($this) {
            self::Uploaded, self::Pending => 0,
            self::Validating => 1,
            self::Extracting => 2,
            self::DetectingLanguage => 3,
            self::Structuring => 4,
            self::Translating => 5,
            self::Summarizing => 6,
            self::Completed, self::NeedsReview => 7,
            self::Failed, self::Cancelled => null,
        };

        if ($this === self::Failed) {
            return array_map(
                fn (array $step, int $index): array => [
                    ...$step,
                    'state' => $index === 0 ? 'failed' : 'upcoming',
                ],
                $steps,
                array_keys($steps),
            );
        }

        if ($currentIndex === null) {
            return array_map(
                fn (array $step): array => [...$step, 'state' => 'upcoming'],
                $steps,
            );
        }

        return array_map(
            function (array $step, int $index) use ($currentIndex): array {
                $state = match (true) {
                    $index < $currentIndex => 'complete',
                    $index === $currentIndex => $currentIndex === 7 ? 'complete' : 'current',
                    default => 'upcoming',
                };

                return [...$step, 'state' => $state];
            },
            $steps,
            array_keys($steps),
        );
    }
}
