<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Labidi\Recaptcha\Tests\TestCase;

pest()->extend(TestCase::class)->in('Unit', 'Feature');

/**
 * Make Google's siteverify endpoint answer with the given payload.
 *
 * @param  array<string, mixed>  $payload
 */
function fakeGoogle(array $payload, int $status = 200): void
{
    Http::fake([TestCase::VERIFY_URL => Http::response($payload, $status)]);
}

/**
 * A successful v3 answer.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function v3Success(float $score = 0.9, string $action = 'login', array $overrides = []): array
{
    return array_merge([
        'success' => true,
        'score' => $score,
        'action' => $action,
        'challenge_ts' => gmdate('Y-m-d\TH:i:s\Z'),
        'hostname' => 'example.com',
    ], $overrides);
}

/**
 * Make Google's siteverify endpoint answer with each payload in turn.
 *
 * @param  array<string, mixed>  ...$payloads
 */
function fakeGoogleSequence(array ...$payloads): void
{
    $sequence = Http::sequence();

    foreach ($payloads as $payload) {
        $sequence->push($payload);
    }

    Http::fake([TestCase::VERIFY_URL => $sequence]);
}
