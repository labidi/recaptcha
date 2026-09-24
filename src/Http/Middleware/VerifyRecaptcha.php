<?php

declare(strict_types=1);

namespace Labidi\Recaptcha\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Labidi\Recaptcha\Recaptcha;
use Labidi\Recaptcha\Rules\RecaptchaRule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects requests whose reCAPTCHA token does not verify.
 *
 *     Route::post('/login', ...)->middleware('recaptcha:login');
 *
 * Failures raise a ValidationException, which Laravel turns into the right
 * response for each client: a redirect with errors for Blade forms and
 * Inertia visits, and a 422 JSON body for API clients.
 */
class VerifyRecaptcha
{
    /**
     * Header an XHR client may use instead of a form field.
     */
    public const TOKEN_HEADER = 'X-Recaptcha-Token';

    public function handle(Request $request, Closure $next, ?string $action = null): Response
    {
        // Resolve per request so a fake swapped in after construction is used.
        $recaptcha = app(Recaptcha::class);
        $field = $recaptcha->fieldName();

        $token = $request->input($field) ?? $request->header(self::TOKEN_HEADER);

        $response = $recaptcha->verify(
            is_string($token) ? $token : null,
            $action,
            $request->ip(),
        );

        if ($response->isFailure()) {
            throw ValidationException::withMessages([
                $field => __(RecaptchaRule::messageKey($response)),
            ]);
        }

        $request->attributes->set('recaptcha', $response);

        return $next($request);
    }
}
