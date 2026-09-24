<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Labidi\Recaptcha\Facades\Recaptcha;
use Labidi\Recaptcha\RecaptchaResponse;
use Labidi\Recaptcha\Rules\RecaptchaRule;
use Labidi\Recaptcha\Testing\RecaptchaFake;
use PHPUnit\Framework\AssertionFailedError;

beforeEach(function () {
    Http::fake();
});

it('passes every verification by default without calling Google', function () {
    $fake = Recaptcha::fake();

    expect($fake)->toBeInstanceOf(RecaptchaFake::class)
        ->and(Recaptcha::verify('anything', 'login')->isSuccess())->toBeTrue()
        ->and(Recaptcha::verify(null)->isSuccess())->toBeTrue();

    Http::assertNothingSent();
});

it('is used by the validation rule and the middleware', function () {
    Recaptcha::fake()->failWith('timeout-or-duplicate');

    Route::post('/login', fn () => 'ok')->middleware(['web', 'recaptcha:login']);

    expect(Validator::make(['t' => 'x'], ['t' => [new RecaptchaRule('login')]])->fails())->toBeTrue();

    $this->post('/login', ['g-recaptcha-response' => 'x'])
        ->assertSessionHasErrors(['g-recaptcha-response' => __('recaptcha::recaptcha.expired')]);

    Recaptcha::assertVerified('login');
    Recaptcha::assertVerifiedTimes(2);
    Http::assertNothingSent();
});

it('can pass with a given score', function () {
    Recaptcha::fake()->passWith(0.4);

    expect(Recaptcha::verify('x')->score())->toBe(0.4);
});

it('can return a specific response', function () {
    Recaptcha::fake()->respondWith(RecaptchaResponse::failed(RecaptchaResponse::E_ACTION_MISMATCH));

    expect(Recaptcha::verify('x')->errorCodes())->toBe([RecaptchaResponse::E_ACTION_MISMATCH]);
});

it('fails with a default error code', function () {
    Recaptcha::fake()->failWith();

    expect(Recaptcha::verify('x')->errorCodes())->toBe([RecaptchaResponse::E_INVALID_INPUT_RESPONSE]);
});

it('asserts verifications by action and callback', function () {
    Recaptcha::fake();

    Recaptcha::verify('token-a', 'login', '127.0.0.1');

    Recaptcha::assertVerified();
    Recaptcha::assertVerified('login', fn (array $call) => $call['token'] === 'token-a' && $call['ip'] === '127.0.0.1');

    expect(fn () => Recaptcha::assertVerified('register'))->toThrow(AssertionFailedError::class);
    expect(fn () => Recaptcha::assertVerified('login', fn () => false))->toThrow(AssertionFailedError::class);
});

it('asserts that nothing was verified', function () {
    Recaptcha::fake();

    Recaptcha::assertNothingVerified();

    Recaptcha::verify('x');

    expect(fn () => Recaptcha::assertNothingVerified())->toThrow(AssertionFailedError::class);
});

it('keeps reading the real configuration for rendering', function () {
    Recaptcha::fake();

    expect(Recaptcha::siteKey())->toBe('test-site-key')
        ->and(Recaptcha::toArray()['version'])->toBe('v3');
});
