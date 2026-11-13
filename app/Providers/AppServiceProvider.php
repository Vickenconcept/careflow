<?php

namespace App\Providers;

use App\Actions\Patients\NotifyPatient;
use App\Contracts\Communications\OutboundCommunicationSender;
use App\Contracts\Documents\DocumentClassifier;
use App\Contracts\Documents\DocumentLanguageDetector;
use App\Contracts\Documents\DocumentSummarizer;
use App\Contracts\Documents\DocumentTextExtractor;
use App\Contracts\Documents\DocumentTranslator;
use App\Contracts\Documents\MedicalInformationExtractor;
use App\Domains\Communications\Providers\LogOutboundCommunicationSender;
use App\Domains\Documents\Providers\OpenAi\OpenAiDocumentClassifier;
use App\Domains\Documents\Providers\OpenAi\OpenAiDocumentLanguageDetector;
use App\Domains\Documents\Providers\OpenAi\OpenAiDocumentSummarizer;
use App\Domains\Documents\Providers\OpenAi\OpenAiDocumentTextExtractor;
use App\Domains\Documents\Providers\OpenAi\OpenAiDocumentTranslator;
use App\Domains\Documents\Providers\OpenAi\OpenAiMedicalInformationExtractor;
use App\Domains\Integrations\His\Contracts\HisSchedulingAdapter;
use App\Domains\Integrations\His\Providers\ManualHisReferenceAdapter;
use App\Support\Storage\CloudinaryAdapter;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use League\Flysystem\Filesystem;
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

        Storage::extend('cloudinary', function ($app, array $config) {
            $adapter = new CloudinaryAdapter(
                (string) ($config['cloud_name'] ?? ''),
                (string) ($config['api_key'] ?? ''),
                (string) ($config['api_secret'] ?? ''),
            );

            return new FilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
        });
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

    /**
     * Document AI always runs on the live provider. Automated tests swap in
     * deterministic doubles from tests/Support; none ship with the application.
     */
    protected function registerDocumentAiProviders(): void
    {
        $this->app->bind(DocumentTextExtractor::class, fn () => $this->resolveOpenAiProvider(OpenAiDocumentTextExtractor::class));
        $this->app->bind(DocumentLanguageDetector::class, fn () => $this->resolveOpenAiProvider(OpenAiDocumentLanguageDetector::class));
        $this->app->bind(DocumentClassifier::class, fn () => $this->resolveOpenAiProvider(OpenAiDocumentClassifier::class));
        $this->app->bind(MedicalInformationExtractor::class, fn () => $this->resolveOpenAiProvider(OpenAiMedicalInformationExtractor::class));
        $this->app->bind(DocumentTranslator::class, fn () => $this->resolveOpenAiProvider(OpenAiDocumentTranslator::class));
        $this->app->bind(DocumentSummarizer::class, fn () => $this->resolveOpenAiProvider(OpenAiDocumentSummarizer::class));
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
            throw new RuntimeException('OPENAI_API_KEY is empty. Set it in your .env and restart the queue worker.');
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

        // One instance per request, so controllers can read what the patient was told.
        $this->app->scoped(NotifyPatient::class);
    }

    /**
     * No hospital information system is connected yet: staff type the booking
     * reference from the hospital's own system and it is stored as entered.
     */
    protected function registerHisProviders(): void
    {
        $this->app->bind(HisSchedulingAdapter::class, fn () => $this->app->make(ManualHisReferenceAdapter::class));
    }
}
