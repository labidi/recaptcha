<?php

declare(strict_types=1);

namespace Labidi\Recaptcha;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * An immutable view over Google's siteverify response.
 *
 * Alongside the codes Google returns, the package appends its own codes for
 * the checks it performs locally (score, action, hostname, freshness) and for
 * transport failures, so callers only ever inspect one list.
 *
 * @implements Arrayable<string, mixed>
 */
final class RecaptchaResponse implements Arrayable, JsonSerializable
{
    /** Google: the secret parameter was missing. */
    public const E_MISSING_INPUT_SECRET = 'missing-input-secret';

    /** Google: the secret parameter was invalid or malformed. */
    public const E_INVALID_INPUT_SECRET = 'invalid-input-secret';

    /** Google: the response parameter was missing. */
    public const E_MISSING_INPUT_RESPONSE = 'missing-input-response';

    /** Google: the response parameter was invalid or malformed. */
    public const E_INVALID_INPUT_RESPONSE = 'invalid-input-response';

    /** Google: the request was invalid or malformed. */
    public const E_BAD_REQUEST = 'bad-request';

    /** Google: the response is no longer valid, or has already been used. */
    public const E_TIMEOUT_OR_DUPLICATE = 'timeout-or-duplicate';

    /** Local: the v3 score was below the configured threshold. */
    public const E_SCORE_THRESHOLD_NOT_MET = 'score-threshold-not-met';

    /** Local: the v3 action did not match the expected action. */
    public const E_ACTION_MISMATCH = 'action-mismatch';

    /** Local: the hostname did not match the application host. */
    public const E_HOSTNAME_MISMATCH = 'hostname-mismatch';

    /** Local: the challenge was solved longer ago than the configured window. */
    public const E_CHALLENGE_TIMEOUT = 'challenge-timeout';

    /** Local: the token was empty, so no call to Google was made. */
    public const E_MISSING_TOKEN = 'missing-token';

    /** Local: Google could not be reached, or answered with a non 2xx status. */
    public const E_CONNECTION_FAILED = 'connection-failed';

    /** Local: Google's body could not be decoded as JSON. */
    public const E_INVALID_JSON = 'invalid-json';

    /**
     * @param  list<string>  $errorCodes
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        private readonly bool $success,
        private readonly ?float $score = null,
        private readonly ?string $action = null,
        private readonly ?string $hostname = null,
        private readonly ?string $challengeTs = null,
        private readonly ?string $apkPackageName = null,
        private readonly array $errorCodes = [],
        private readonly array $raw = [],
    ) {}

    /**
     * Build a response from Google's decoded JSON payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        /** @var list<string> $errorCodes */
        $errorCodes = array_values(array_filter(
            (array) ($payload['error-codes'] ?? []),
            static fn (mixed $code): bool => is_string($code),
        ));

        return new self(
            success: (bool) ($payload['success'] ?? false),
            score: isset($payload['score']) ? (float) $payload['score'] : null,
            action: isset($payload['action']) ? (string) $payload['action'] : null,
            hostname: isset($payload['hostname']) ? (string) $payload['hostname'] : null,
            challengeTs: isset($payload['challenge_ts']) ? (string) $payload['challenge_ts'] : null,
            apkPackageName: isset($payload['apk_package_name']) ? (string) $payload['apk_package_name'] : null,
            errorCodes: $errorCodes,
            raw: $payload,
        );
    }

    /**
     * Build a failed response for an error raised before or instead of a
     * successful call to Google.
     */
    public static function failed(string ...$errorCodes): self
    {
        return new self(success: false, errorCodes: array_values($errorCodes));
    }

    /**
     * Build a passing response for a verification that was skipped, so callers
     * that read the score still get a usable value.
     */
    public static function skipped(): self
    {
        return new self(success: true, score: 1.0);
    }

    /**
     * Return a copy marked as failed, with the given codes appended.
     *
     * Used for the checks performed after Google has answered successfully.
     */
    public function failWith(string ...$errorCodes): self
    {
        return new self(
            success: false,
            score: $this->score,
            action: $this->action,
            hostname: $this->hostname,
            challengeTs: $this->challengeTs,
            apkPackageName: $this->apkPackageName,
            errorCodes: array_values(array_unique([...$this->errorCodes, ...$errorCodes])),
            raw: $this->raw,
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFailure(): bool
    {
        return ! $this->success;
    }

    public function score(): ?float
    {
        return $this->score;
    }

    public function action(): ?string
    {
        return $this->action;
    }

    public function hostname(): ?string
    {
        return $this->hostname;
    }

    public function challengeTs(): ?string
    {
        return $this->challengeTs;
    }

    public function apkPackageName(): ?string
    {
        return $this->apkPackageName;
    }

    /**
     * @return list<string>
     */
    public function errorCodes(): array
    {
        return $this->errorCodes;
    }

    public function firstErrorCode(): ?string
    {
        return $this->errorCodes[0] ?? null;
    }

    public function hasErrorCode(string $code): bool
    {
        return in_array($code, $this->errorCodes, strict: true);
    }

    /**
     * Whether the v3 score reaches the threshold.
     *
     * A response without a score (v2) is considered to pass, because v2 is a
     * pass or fail challenge with no score to weigh.
     */
    public function passesThreshold(float $threshold): bool
    {
        return $this->score === null || $this->score >= $threshold;
    }

    /**
     * Whether the returned action matches the expected one.
     *
     * Responses without an action (v2) always match.
     */
    public function matchesAction(?string $expected): bool
    {
        if ($expected === null || $this->action === null) {
            return true;
        }

        return hash_equals($expected, $this->action);
    }

    /**
     * Whether the challenge was solved more than the given number of seconds ago.
     */
    public function isExpired(int $seconds): bool
    {
        if ($this->challengeTs === null) {
            return false;
        }

        $solvedAt = strtotime($this->challengeTs);

        if ($solvedAt === false) {
            return false;
        }

        return (time() - $solvedAt) > $seconds;
    }

    /**
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }

    /**
     * @return array{success: bool, score: float|null, action: string|null, hostname: string|null, challenge_ts: string|null, apk_package_name: string|null, error_codes: list<string>}
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'score' => $this->score,
            'action' => $this->action,
            'hostname' => $this->hostname,
            'challenge_ts' => $this->challengeTs,
            'apk_package_name' => $this->apkPackageName,
            'error_codes' => $this->errorCodes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
