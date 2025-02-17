<?php

namespace Crumbls\WordPressRenderer\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Crumbls\WordPressRenderer\WordPressRendererServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            WordPressRendererServiceProvider::class,
        ];
    }
}
