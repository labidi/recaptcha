<?php

declare(strict_types=1);

namespace Labidi\Recaptcha\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Labidi\Recaptcha\Recaptcha;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the public reCAPTCHA settings with every Inertia response.
 *
 * The value is a closure, so it is only evaluated for Inertia responses and
 * always reflects the configuration of the current request.
 */
class ShareRecaptchaProps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (class_exists(Inertia::class) && config('recaptcha.inertia.share', true)) {
            $prop = config('recaptcha.inertia.prop', 'recaptcha');

            Inertia::share(
                is_string($prop) && $prop !== '' ? $prop : 'recaptcha',
                static fn (): array => app(Recaptcha::class)->toArray(),
            );
        }

        return $next($request);
    }
}
