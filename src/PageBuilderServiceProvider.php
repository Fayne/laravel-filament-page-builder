<?php

namespace Fayne\PageBuilder;

use Illuminate\Support\ServiceProvider;

class PageBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-page-builder');
        $this->mergeConfigFrom(__DIR__.'/../config/filament-page-builder.php', 'filament-page-builder');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-page-builder');
        $this->publishes([__DIR__.'/../config/filament-page-builder.php' => config_path('filament-page-builder.php')], 'filament-page-builder-config');
        if (is_dir(__DIR__.'/../resources/lang')) {
            $this->loadJsonTranslationsFrom(__DIR__.'/../resources/lang');
        }
    }
}
