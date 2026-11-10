<?php

namespace App\Providers;

use App\Contracts\Communications\OutboundCommunicationSender;
use App\Contracts\Documents\DocumentClassifier;
use App\Contracts\Documents\DocumentLanguageDetector;
use App\Contracts\Documents\DocumentSummarizer;
use App\Contracts\Documents\DocumentTextExtractor;
use App\Contracts\Documents\DocumentTranslator;
use App\Contracts\Documents\MedicalInformationExtractor;
use App\Domains\Communications\Providers\LogOutboundCommunicationSender;
use App\Domains\Documents\Providers\Mock\MockDocumentClassifier;
use App\Domains\Documents\Providers\Mock\MockDocumentLanguageDetector;
use App\Domains\Documents\Providers\Mock\MockDocumentSummarizer;
use App\Domains\Documents\Providers\Mock\MockDocumentTextExtractor;
use App\Domains\Documents\Providers\Mock\MockDocumentTranslator;
use App\Domains\Documents\Providers\Mock\MockMedicalInformationExtractor;
use App\Domains\Documents\Providers\OpenAi\OpenAiDocumentClassifier;
use App\Domains\Documents\Providers\OpenAi\OpenAiDocumentLanguageDetector;
use App\Domains\Documents\Providers\OpenAi\OpenAiDocumentSummarizer;
use App\Domains\Documents\Providers\OpenAi\OpenAiDocumentTextExtractor;
use App\Domains\Documents\Providers\OpenAi\OpenAiDocumentTranslator;
use App\Domains\Documents\Providers\OpenAi\OpenAiMedicalInformationExtractor;
use App\Domains\Integrations\His\Contracts\HisSchedulingAdapter;
use App\Domains\Integrations\His\Providers\MockHisSchedulingAdapter;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerDocumentAiProviders();
        $this->registerCommunicationProviders();
        $this->registerHisProviders();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('patient-intake', function (Request $request) {
            $email = Str::lower((string) $request->input('email', ''));

            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perMinute(3)->by($email !== '' ? $email.'|'.$request->ip() : $request->ip()),
            ];
        });
    }

    protected function registerDocumentAiProviders(): void
    {
        $this->app->bind(DocumentTextExtractor::class, fn () => $this->resolveDocumentAiProvider(
            mock: MockDocumentTextExtractor::class,
            openai: OpenAiDocumentTextExtractor::class,
        ));
        $this->app->bind(DocumentLanguageDetector::class, fn () => $this->resolveDocumentAiProvider(
            mock: MockDocumentLanguageDetector::class,
            openai: OpenAiDocumentLanguageDetector::class,
        ));
        $this->app->bind(DocumentClassifier::class, fn () => $this->resolveDocumentAiProvider(
            mock: MockDocumentClassifier::class,
            openai: OpenAiDocumentClassifier::class,
        ));
        $this->app->bind(MedicalInformationExtractor::class, fn () => $this->resolveDocumentAiProvider(
            mock: MockMedicalInformationExtractor::class,
            openai: OpenAiMedicalInformationExtractor::class,
        ));
        $this->app->bind(DocumentTranslator::class, fn () => $this->resolveDocumentAiProvider(
            mock: MockDocumentTranslator::class,
            openai: OpenAiDocumentTranslator::class,
        ));
        $this->app->bind(DocumentSummarizer::class, fn () => $this->resolveDocumentAiProvider(
            mock: MockDocumentSummarizer::class,
            openai: OpenAiDocumentSummarizer::class,
        ));
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $mock
     * @param  class-string<T>  $openai
     * @return T
     */
    protected function resolveDocumentAiProvider(string $mock, string $openai): object
    {
        $provider = strtolower((string) config('careflow.ai.provider', 'openai'));

        return match ($provider) {
            'mock' => $this->app->make($mock),
            'openai' => $this->resolveOpenAiProvider($openai),
            default => throw new RuntimeException(
                "Unsupported CareFlow AI provider [{$provider}]. Supported: openai (live), mock (tests).",
            ),
        };
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $openaiClass
     * @return T
     */
    protected function resolveOpenAiProvider(string $openaiClass): object
    {
        $key = (string) config('ai.providers.openai.key', '');

        if ($key === '') {
            throw new RuntimeException(
                'CAREFLOW_AI_PROVIDER is openai but OPENAI_API_KEY is empty. Set OPENAI_API_KEY in your .env.',
            );
        }

        return $this->app->make($openaiClass);
    }

    protected function registerCommunicationProviders(): void
    {
        $this->app->bind(OutboundCommunicationSender::class, function () {
            $provider = strtolower((string) config('careflow.communications.provider', 'log'));

            return match ($provider) {
                'log' => $this->app->make(LogOutboundCommunicationSender::class),
                default => throw new RuntimeException(
                    "Unsupported CareFlow communications provider [{$provider}]. Supported: log.",
                ),
            };
        });
    }

    protected function registerHisProviders(): void
    {
        $this->app->bind(HisSchedulingAdapter::class, function () {
            $provider = strtolower((string) config('careflow.his.provider', 'mock'));

            return match ($provider) {
                'mock' => $this->app->make(MockHisSchedulingAdapter::class),
                default => throw new RuntimeException(
                    "Unsupported CareFlow HIS provider [{$provider}]. Supported: mock.",
                ),
            };
        });
    }
}
