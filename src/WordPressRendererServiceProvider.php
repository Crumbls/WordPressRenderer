<?php

namespace Crumbls\WordPressRenderer;

use Illuminate\Support\ServiceProvider;

class WordPressRendererServiceProvider extends ServiceProvider
{
    public function register() {
        /*
        $this->app->singleton('wordpress-shortcodes', function ($app) {
            return new ShortcodeManager();
        });
*/
        $this->mergeConfigFrom(
            __DIR__.'/../config/shortcodes.php', 'shortcodes'
        );
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/shortcodes.php' => config_path('shortcodes.php'),
            ], 'shortcodes-config');
        }
    }
}
