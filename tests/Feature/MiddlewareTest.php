<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::post('/login', fn () => 'logged in')->middleware(['web', 'recaptcha:login']);

    Route::post('/score', fn () => (string) request()->attributes->get('recaptcha')?->score())
        ->middleware(['web', 'recaptcha:login']);

    Route::post('/any', fn () => 'ok')->middleware(['web', 'recaptcha']);
});

it('lets a request with a valid token through', function () {
    fakeGoogle(v3Success());

    $this->post('/login', ['g-recaptcha-response' => 'token'])
        ->assertOk()
        ->assertSee('logged in');
});

it('passes the expected action from the middleware parameter', function () {
    fakeGoogle(v3Success(action: 'register'));

    $this->post('/login', ['g-recaptcha-response' => 'token'])
        ->assertSessionHasErrors('g-recaptcha-response');
});

it('does not require an action when none is given', function () {
    fakeGoogle(v3Success(action: 'whatever'));

    $this->post('/any', ['g-recaptcha-response' => 'token'])->assertOk();
});

it('redirects back with errors for form submissions', function () {
    fakeGoogle(v3Success(score: 0.1));

    $this->from('/login-form')
        ->post('/login', ['g-recaptcha-response' => 'token'])
        ->assertRedirect('/login-form')
        ->assertSessionHasErrors([
            'g-recaptcha-response' => 'We could not verify that you are human. Please try again.',
        ]);
});

it('returns 422 JSON for API clients', function () {
    Http::fake();

    $this->postJson('/login')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['g-recaptcha-response' => 'Please complete the reCAPTCHA challenge.']);

    Http::assertNothingSent();
});

it('accepts the token from a header', function () {
    fakeGoogle(v3Success());

    $this->postJson('/login', [], ['X-Recaptcha-Token' => 'header-token'])->assertOk();

    Http::assertSent(fn ($request) => $request['response'] === 'header-token');
});

it('reads the configured v3 field name', function () {
    config(['recaptcha.field_name' => 'captcha_token']);
    fakeGoogle(v3Success());

    $this->post('/login', ['captcha_token' => 'token'])->assertOk();

    $this->postJson('/login', ['g-recaptcha-response' => 'token'])
        ->assertJsonValidationErrors('captcha_token');
});

it('exposes the verification result to the route', function () {
    fakeGoogle(v3Success(score: 0.8));

    $this->post('/score', ['g-recaptcha-response' => 'token'])->assertSee('0.8');
});

it('lets requests through when reCAPTCHA is disabled', function () {
    config(['recaptcha.enabled' => false]);
    Http::fake();

    $this->post('/login')->assertOk();
    Http::assertNothingSent();
});

it('reports Google outages without a server error', function () {
    fakeGoogle([], 500);

    $this->postJson('/login', ['g-recaptcha-response' => 'token'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['g-recaptcha-response' => __('recaptcha::recaptcha.unavailable')]);
});

it('stores a skipped result on the request when verification is bypassed', function () {
    config(['recaptcha.skip_ips' => ['127.0.0.1']]);
    Http::fake();

    $this->post('/score')->assertSee('1');
});
