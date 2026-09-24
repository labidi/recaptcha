<?php

declare(strict_types=1);

use Labidi\Recaptcha\RecaptchaResponse;

it('parses a v3 payload', function () {
    $response = RecaptchaResponse::fromArray([
        'success' => true,
        'score' => 0.7,
        'action' => 'login',
        'hostname' => 'example.com',
        'challenge_ts' => '2026-01-01T00:00:00Z',
    ]);

    expect($response->isSuccess())->toBeTrue()
        ->and($response->score())->toBe(0.7)
        ->and($response->action())->toBe('login')
        ->and($response->hostname())->toBe('example.com')
        ->and($response->challengeTs())->toBe('2026-01-01T00:00:00Z')
        ->and($response->errorCodes())->toBe([]);
});

it('treats a v2 payload without score or action as passing those checks', function () {
    $response = RecaptchaResponse::fromArray(['success' => true, 'hostname' => 'example.com']);

    expect($response->score())->toBeNull()
        ->and($response->passesThreshold(0.9))->toBeTrue()
        ->and($response->matchesAction('login'))->toBeTrue();
});

it('exposes error codes and ignores malformed ones', function () {
    $response = RecaptchaResponse::fromArray([
        'success' => false,
        'error-codes' => ['timeout-or-duplicate', 42, 'bad-request'],
    ]);

    expect($response->isFailure())->toBeTrue()
        ->and($response->errorCodes())->toBe(['timeout-or-duplicate', 'bad-request'])
        ->and($response->firstErrorCode())->toBe('timeout-or-duplicate')
        ->and($response->hasErrorCode('bad-request'))->toBeTrue()
        ->and($response->hasErrorCode('invalid-input-secret'))->toBeFalse();
});

it('compares scores against a threshold inclusively', function () {
    $response = RecaptchaResponse::fromArray(['success' => true, 'score' => 0.5]);

    expect($response->passesThreshold(0.5))->toBeTrue()
        ->and($response->passesThreshold(0.51))->toBeFalse();
});

it('matches actions exactly', function () {
    $response = RecaptchaResponse::fromArray(['success' => true, 'action' => 'login']);

    expect($response->matchesAction('login'))->toBeTrue()
        ->and($response->matchesAction('Login'))->toBeFalse()
        ->and($response->matchesAction(null))->toBeTrue();
});

it('marks a copy as failed without mutating the original', function () {
    $original = RecaptchaResponse::fromArray(['success' => true, 'score' => 0.2]);

    $failed = $original->failWith(RecaptchaResponse::E_SCORE_THRESHOLD_NOT_MET, RecaptchaResponse::E_SCORE_THRESHOLD_NOT_MET);

    expect($original->isSuccess())->toBeTrue()
        ->and($failed->isFailure())->toBeTrue()
        ->and($failed->score())->toBe(0.2)
        ->and($failed->errorCodes())->toBe([RecaptchaResponse::E_SCORE_THRESHOLD_NOT_MET]);
});

it('detects expired challenges', function () {
    $fresh = RecaptchaResponse::fromArray(['success' => true, 'challenge_ts' => gmdate('Y-m-d\TH:i:s\Z')]);
    $stale = RecaptchaResponse::fromArray(['success' => true, 'challenge_ts' => gmdate('Y-m-d\TH:i:s\Z', time() - 600)]);

    expect($fresh->isExpired(60))->toBeFalse()
        ->and($stale->isExpired(60))->toBeTrue()
        ->and(RecaptchaResponse::fromArray(['success' => true])->isExpired(60))->toBeFalse();
});

it('serialises to an array and JSON', function () {
    $response = RecaptchaResponse::fromArray(['success' => true, 'score' => 0.9, 'action' => 'login']);

    expect($response->toArray())->toMatchArray(['success' => true, 'score' => 0.9, 'action' => 'login', 'error_codes' => []])
        ->and(json_encode($response))->toContain('"score":0.9');
});

it('builds skipped and failed responses', function () {
    expect(RecaptchaResponse::skipped()->isSuccess())->toBeTrue()
        ->and(RecaptchaResponse::skipped()->score())->toBe(1.0)
        ->and(RecaptchaResponse::failed('missing-token')->errorCodes())->toBe(['missing-token']);
});
