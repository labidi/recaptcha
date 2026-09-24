<?php

declare(strict_types=1);

namespace Labidi\Recaptcha\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Labidi\Recaptcha\Recaptcha;
use Labidi\Recaptcha\RecaptchaResponse;

/**
 * Validates a reCAPTCHA token.
 *
 *     $request->validate([
 *         'g-recaptcha-response' => ['required', new RecaptchaRule('login')],
 *     ]);
 */
class RecaptchaRule implements ValidationRule
{
    /**
     * Run even when the field is missing or empty, so omitting the token
     * cannot bypass verification.
     */
    public bool $implicit = true;

    /**
     * @param  string|null  $action  the v3 action the token must have been issued for
     */
    public function __construct(protected ?string $action = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $response = app(Recaptcha::class)->verify(
            is_string($value) ? $value : null,
            $this->action,
            request()->ip(),
        );

        if ($response->isFailure()) {
            $fail(self::messageKey($response))->translate();
        }
    }

    /**
     * The translation key describing why a response failed.
     */
    public static function messageKey(RecaptchaResponse $response): string
    {
        $key = match ($response->firstErrorCode()) {
            RecaptchaResponse::E_MISSING_TOKEN,
            RecaptchaResponse::E_MISSING_INPUT_RESPONSE => 'missing',
            RecaptchaResponse::E_SCORE_THRESHOLD_NOT_MET => 'low_score',
            RecaptchaResponse::E_ACTION_MISMATCH => 'action_mismatch',
            RecaptchaResponse::E_HOSTNAME_MISMATCH => 'hostname_mismatch',
            RecaptchaResponse::E_TIMEOUT_OR_DUPLICATE,
            RecaptchaResponse::E_CHALLENGE_TIMEOUT => 'expired',
            RecaptchaResponse::E_CONNECTION_FAILED,
            RecaptchaResponse::E_INVALID_JSON => 'unavailable',
            default => 'failed',
        };

        return 'recaptcha::recaptcha.'.$key;
    }
}
