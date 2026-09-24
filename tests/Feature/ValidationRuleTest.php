<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Labidi\Recaptcha\Facades\Recaptcha;
use Labidi\Recaptcha\Rules\RecaptchaRule;

it('passes with a valid token', function () {
    fakeGoogle(v3Success());

    $validator = Validator::make(
        ['g-recaptcha-response' => 'token'],
        ['g-recaptcha-response' => [new RecaptchaRule('login')]],
    );

    expect($validator->passes())->toBeTrue();
});

it('fails when the token is missing entirely', function () {
    Http::fake();

    $validator = Validator::make([], ['g-recaptcha-response' => [new RecaptchaRule('login')]]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('g-recaptcha-response'))->toBe('Please complete the reCAPTCHA challenge.');

    Http::assertNothingSent();
});

it('explains a low score', function () {
    fakeGoogle(v3Success(score: 0.1));

    $validator = Validator::make(
        ['g-recaptcha-response' => 'token'],
        ['g-recaptcha-response' => [new RecaptchaRule('login')]],
    );

    expect($validator->errors()->first('g-recaptcha-response'))
        ->toBe('We could not verify that you are human. Please try again.');
});

it('maps error codes to translated messages', function (array $codes, string $key) {
    fakeGoogle(['success' => false, 'error-codes' => $codes]);

    $validator = Validator::make(['token' => 'x'], ['token' => [new RecaptchaRule]]);

    expect($validator->errors()->first('token'))->toBe(__('recaptcha::recaptcha.'.$key));
})->with([
    'expired' => [['timeout-or-duplicate'], 'expired'],
    'invalid response' => [['invalid-input-response'], 'failed'],
    'misconfigured secret' => [['invalid-input-secret'], 'failed'],
]);

it('reports Google outages with a dedicated message', function () {
    fakeGoogle([], 503);

    $validator = Validator::make(['token' => 'x'], ['token' => [new RecaptchaRule]]);

    expect($validator->errors()->first('token'))->toBe(__('recaptcha::recaptcha.unavailable'));
});

it('passes the request IP to Google', function () {
    fakeGoogle(v3Success());
    $this->app['request']->server->set('REMOTE_ADDR', '198.51.100.7');

    Validator::make(['token' => 'x'], ['token' => [new RecaptchaRule('login')]])->passes();

    Http::assertSent(fn ($request) => $request['remoteip'] === '198.51.100.7');
});

it('is available from the facade', function () {
    fakeGoogle(v3Success());

    $validator = Validator::make(['token' => 'x'], ['token' => [Recaptcha::rule('login')]]);

    expect(Recaptcha::rule())->toBeInstanceOf(RecaptchaRule::class)
        ->and($validator->passes())->toBeTrue();
});

it('supports the string rule with an action parameter', function () {
    fakeGoogleSequence(v3Success(action: 'login'), v3Success(action: 'register'));

    expect(Validator::make(['token' => 'x'], ['token' => 'recaptcha:login'])->passes())->toBeTrue();

    $failing = Validator::make(['token' => 'x'], ['token' => 'recaptcha:login']);

    expect($failing->fails())->toBeTrue()
        ->and($failing->errors()->first('token'))->toBe(__('recaptcha::recaptcha.failed'));
});

it('runs the string rule when the field is missing', function () {
    Http::fake();

    expect(Validator::make([], ['token' => 'recaptcha'])->fails())->toBeTrue();
});

it('passes when reCAPTCHA is disabled, even without a token', function () {
    config(['recaptcha.enabled' => false]);

    expect(Validator::make([], ['token' => [new RecaptchaRule('login')]])->passes())->toBeTrue()
        ->and(Validator::make([], ['token' => 'recaptcha'])->passes())->toBeTrue();
});
