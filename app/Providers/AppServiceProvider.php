<?php

namespace App\Providers;

use App\Services\ReadingAssistant;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ReadingAssistant::class, fn (): ReadingAssistant => new ReadingAssistant(
            apiKey: config('services.anthropic.key'),
            model: config('services.anthropic.model'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
