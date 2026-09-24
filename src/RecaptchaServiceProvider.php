<?php

declare(strict_types=1);

namespace Labidi\Recaptcha;

use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Factory as ValidationFactory;
use Illuminate\View\Compilers\BladeCompiler;
use Inertia\Inertia;
use Labidi\Recaptcha\Http\Middleware\ShareRecaptchaProps;
use Labidi\Recaptcha\Http\Middleware\VerifyRecaptcha;
use Labidi\Recaptcha\View\Components\Field;
use Labidi\Recaptcha\View\Components\Script;
use Labidi\Recaptcha\View\Components\Widget;

class RecaptchaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/recaptcha.php', 'recaptcha');

        $this->app->singleton(Recaptcha::class, static fn (): Recaptcha => new Recaptcha);
        $this->app->alias(Recaptcha::class, 'recaptcha');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'recaptcha');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'recaptcha');

        $this->registerPublishing();
        $this->registerBlade();
        $this->registerValidation();
        $this->registerMiddleware();
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/recaptcha.php' => config_path('recaptcha.php'),
        ], 'recaptcha-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/recaptcha'),
        ], 'recaptcha-views');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/recaptcha'),
        ], 'recaptcha-lang');

        $this->publishes([
            __DIR__.'/../resources/js' => resource_path('js/vendor/recaptcha'),
        ], 'recaptcha-js');
    }

    /**
     * <x-recaptcha::script />, <x-recaptcha::field />, <x-recaptcha::widget />
     * and their @recaptchaScript, @recaptchaField, @recaptchaWidget twins.
     */
    protected function registerBlade(): void
    {
        $this->callAfterResolving('blade.compiler', static function (BladeCompiler $blade): void {
            $blade->componentNamespace('Labidi\\Recaptcha\\View\\Components', 'recaptcha');

            foreach ([
                'recaptchaScript' => Script::class,
                'recaptchaField' => Field::class,
                'recaptchaWidget' => Widget::class,
            ] as $directive => $component) {
                $blade->directive(
                    $directive,
                    static fn (string $expression): string => "<?php echo \\{$component}::html({$expression}); ?>",
                );
            }
        });
    }

    /**
     * The "recaptcha" string rule: 'g-recaptcha-response' => 'recaptcha:login'.
     */
    protected function registerValidation(): void
    {
        $this->callAfterResolving('validator', static function (ValidationFactory $validator): void {
            $validator->extendImplicit(
                'recaptcha',
                static fn (string $attribute, mixed $value, array $parameters): bool => app(Recaptcha::class)
                    ->verify(is_string($value) ? $value : null, $parameters[0] ?? null, request()->ip())
                    ->isSuccess(),
                'recaptcha::recaptcha.failed',
            );

            $validator->replacer(
                'recaptcha',
                static fn (string $message): string => $message === 'recaptcha::recaptcha.failed'
                    ? (string) __($message)
                    : $message,
            );
        });
    }

    /**
     * Middleware aliases, plus automatic sharing of the Inertia prop when
     * Inertia is installed.
     */
    protected function registerMiddleware(): void
    {
        $this->callAfterResolving('router', static function (Router $router): void {
            $router->aliasMiddleware('recaptcha', VerifyRecaptcha::class);
            $router->aliasMiddleware('recaptcha.share', ShareRecaptchaProps::class);
        });

        if (! class_exists(Inertia::class) || ! config('recaptcha.inertia.share', true)) {
            return;
        }

        // Go through the kernel rather than the router: the kernel re-syncs
        // its groups onto the router, which would drop a router-only push.
        $this->callAfterResolving(HttpKernelContract::class, static function (HttpKernelContract $kernel): void {
            if ($kernel instanceof HttpKernel && array_key_exists('web', $kernel->getMiddlewareGroups())) {
                $kernel->appendMiddlewareToGroup('web', ShareRecaptchaProps::class);
            }
        });
    }
}
