<?php

declare(strict_types=1);

namespace Labidi\Recaptcha\Testing;

use Closure;
use Labidi\Recaptcha\Recaptcha;
use Labidi\Recaptcha\RecaptchaResponse;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * A drop-in replacement for the Recaptcha service in application tests.
 *
 *     Recaptcha::fake()->failWith('timeout-or-duplicate');
 *
 *     $this->post('/login', [...])->assertSessionHasErrors('g-recaptcha-response');
 *
 *     Recaptcha::assertVerified('login');
 *
 * Every verification passes unless told otherwise, and no request is sent
 * to Google. Rendering helpers keep reading the real configuration.
 */
class RecaptchaFake extends Recaptcha
{
    /**
     * @var list<array{token: string|null, action: string|null, ip: string|null, response: RecaptchaResponse}>
     */
    protected array $verifications = [];

    protected ?RecaptchaResponse $nextResponse = null;

    public function verify(?string $token, ?string $action = null, ?string $ip = null): RecaptchaResponse
    {
        $response = $this->nextResponse
            ?? new RecaptchaResponse(success: true, score: 1.0, action: $action);

        $this->verifications[] = [
            'token' => $token,
            'action' => $action,
            'ip' => $ip,
            'response' => $response,
        ];

        return $response;
    }

    /**
     * Make every subsequent verification pass with the given score.
     */
    public function passWith(float $score = 1.0): static
    {
        $this->nextResponse = new RecaptchaResponse(success: true, score: $score);

        return $this;
    }

    /**
     * Make every subsequent verification fail with the given error codes.
     */
    public function failWith(string ...$errorCodes): static
    {
        $this->nextResponse = RecaptchaResponse::failed(
            ...($errorCodes === [] ? [RecaptchaResponse::E_INVALID_INPUT_RESPONSE] : $errorCodes),
        );

        return $this;
    }

    /**
     * Make every subsequent verification return the given response.
     */
    public function respondWith(RecaptchaResponse $response): static
    {
        $this->nextResponse = $response;

        return $this;
    }

    /**
     * Assert a verification happened, optionally for a given action or
     * matching a callback that receives the recorded verification.
     *
     * @param  (Closure(array{token: string|null, action: string|null, ip: string|null, response: RecaptchaResponse}): bool)|null  $callback
     */
    public function assertVerified(?string $action = null, ?Closure $callback = null): void
    {
        $matching = array_filter(
            $this->verifications,
            static fn (array $verification): bool => ($action === null || $verification['action'] === $action)
                && ($callback === null || $callback($verification)),
        );

        PHPUnit::assertNotEmpty(
            $matching,
            $action === null
                ? 'Expected a reCAPTCHA verification, but none was made.'
                : "Expected a reCAPTCHA verification for action [{$action}], but none was made.",
        );
    }

    public function assertVerifiedTimes(int $times): void
    {
        $count = count($this->verifications);

        PHPUnit::assertSame(
            $times,
            $count,
            "Expected {$times} reCAPTCHA verification(s), but {$count} were made.",
        );
    }

    public function assertNothingVerified(): void
    {
        $this->assertVerifiedTimes(0);
    }

    /**
     * @return list<array{token: string|null, action: string|null, ip: string|null, response: RecaptchaResponse}>
     */
    public function verifications(): array
    {
        return $this->verifications;
    }
}
