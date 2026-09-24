<?php

declare(strict_types=1);

namespace Labidi\Recaptcha\Exceptions;

use Labidi\Recaptcha\RecaptchaResponse;
use RuntimeException;

/**
 * Thrown by Recaptcha::verifyOrFail() when a token does not verify.
 */
class RecaptchaException extends RuntimeException
{
    public function __construct(
        public readonly RecaptchaResponse $response,
        string $message = 'The reCAPTCHA verification failed.',
    ) {
        parent::__construct($message);
    }

    public static function forResponse(RecaptchaResponse $response): self
    {
        $codes = $response->errorCodes();

        return new self($response, $codes === []
            ? 'The reCAPTCHA verification failed.'
            : 'The reCAPTCHA verification failed: '.implode(', ', $codes).'.');
    }
}
