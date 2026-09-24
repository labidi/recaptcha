<?php

declare(strict_types=1);

namespace Labidi\Recaptcha\Tests;

use Illuminate\Foundation\Application;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Labidi\Recaptcha\RecaptchaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public const SITE_KEY = 'test-site-key';

    public const SECRET_KEY = 'test-secret-key';

    public const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            InertiaServiceProvider::class,
            RecaptchaServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('app.url', 'https://example.com');
        $app['config']->set('recaptcha.enabled', true);
        $app['config']->set('recaptcha.version', 'v3');
        $app['config']->set('recaptcha.site_key', self::SITE_KEY);
        $app['config']->set('recaptcha.secret_key', self::SECRET_KEY);
        // The package skips "testing" by default; the suite needs real checks.
        $app['config']->set('recaptcha.skip_environments', []);
    }
}
