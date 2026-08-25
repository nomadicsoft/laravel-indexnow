<?php

namespace NomadicSoft\LaravelIndexNow;

use Illuminate\Support\ServiceProvider;
use NomadicSoft\LaravelIndexNow\Client\IndexNowClient;
use NomadicSoft\LaravelIndexNow\Configuration\HostRegistry;
use NomadicSoft\LaravelIndexNow\Console\DoctorCommand;
use NomadicSoft\LaravelIndexNow\Console\GenerateKeyCommand;
use NomadicSoft\LaravelIndexNow\Console\NotifyCommand;
use NomadicSoft\LaravelIndexNow\Console\SubmitSitemapCommand;
use NomadicSoft\LaravelIndexNow\Support\NotificationLedger;
use NomadicSoft\LaravelIndexNow\Support\SitemapReader;
use NomadicSoft\LaravelIndexNow\Support\UrlEligibility;
use NomadicSoft\LaravelIndexNow\Support\UrlNormalizer;

final class IndexNowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/indexnow.php', 'indexnow');

        $this->app->singleton(HostRegistry::class);
        $this->app->singleton(UrlNormalizer::class);
        $this->app->singleton(UrlEligibility::class);
        $this->app->singleton(NotificationLedger::class);
        $this->app->singleton(IndexNowClient::class);
        $this->app->singleton(SitemapReader::class);
        $this->app->singleton(IndexNowManager::class);
        $this->app->alias(IndexNowManager::class, 'indexnow');
    }

    public function boot(): void
    {
        if ((bool) config('indexnow.serve_key', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/indexnow.php' => config_path('indexnow.php'),
            ], 'indexnow-config');

            $this->commands([
                DoctorCommand::class,
                GenerateKeyCommand::class,
                NotifyCommand::class,
                SubmitSitemapCommand::class,
            ]);
        }
    }
}
