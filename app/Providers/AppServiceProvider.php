<?php

namespace App\Providers;

use App\Services\AI\AIProvider;
use App\Services\AI\GeminiAIProvider;
use App\Services\AI\IntentClassifier;
use App\Services\AI\IntentClassifierContract;
use App\Services\Conversation\ResponseGenerator;
use App\Services\Conversation\ResponseGeneratorContract;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        $this->app->bind(
            AIProvider::class,
            GeminiAIProvider::class,
        );

        $this->app->bind(
            IntentClassifierContract::class,
            IntentClassifier::class,
        );

        $this->app->bind(
            ResponseGeneratorContract::class,
            ResponseGenerator::class,
        );
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        //
    }
}
