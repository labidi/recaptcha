<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Labidi\Recaptcha\Tests\TestCase;

/**
 * The headers Inertia's client sends on every visit.
 *
 * @return array<string, string>
 */
function inertiaHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'text/html, application/xhtml+xml',
    ];
}

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);

    Route::get('/login', fn () => Inertia::render('Auth/Login'))->middleware('web');

    Route::post('/login', fn () => redirect('/dashboard'))->middleware(['web', 'recaptcha:login']);
});

it('shares the public settings with every Inertia page automatically', function () {
    $this->get('/login', inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'Auth/Login')
        ->assertJsonPath('props.recaptcha.enabled', true)
        ->assertJsonPath('props.recaptcha.version', 'v3')
        ->assertJsonPath('props.recaptcha.site_key', TestCase::SITE_KEY)
        ->assertJsonPath('props.recaptcha.field_name', 'g-recaptcha-response')
        ->assertJsonPath(
            'props.recaptcha.script_url',
            'https://www.google.com/recaptcha/api.js?render=test-site-key&onload=__labidiRecaptchaOnload',
        );
});

it('never shares the secret key', function () {
    $this->get('/login', inertiaHeaders())
        ->assertOk()
        ->assertDontSee(TestCase::SECRET_KEY);
});

it('reflects the configured version', function () {
    config(['recaptcha.version' => 'v2-invisible']);

    $this->get('/login', inertiaHeaders())
        ->assertJsonPath('props.recaptcha.version', 'v2-invisible')
        ->assertJsonPath('props.recaptcha.size', 'invisible');
});

it('shares a disabled flag the front end can act on', function () {
    config(['recaptcha.enabled' => false]);

    $this->get('/login', inertiaHeaders())->assertJsonPath('props.recaptcha.enabled', false);
});

it('can use a custom prop name', function () {
    config(['recaptcha.inertia.prop' => 'captcha']);

    $this->get('/login', inertiaHeaders())
        ->assertJsonPath('props.captcha.site_key', TestCase::SITE_KEY)
        ->assertJsonMissingPath('props.recaptcha');
});

it('can stop sharing the prop', function () {
    config(['recaptcha.inertia.share' => false]);

    $this->get('/login', inertiaHeaders())->assertJsonMissingPath('props.recaptcha');
});

it('redirects an Inertia visit back with errors when verification fails', function () {
    fakeGoogle(v3Success(score: 0.1));

    // Inertia's useForm reads errors from the session after a redirect; a raw
    // 422 JSON body would surface as a generic error modal instead.
    $this->from('/login')
        ->post('/login', ['g-recaptcha-response' => 'token'], inertiaHeaders())
        ->assertRedirect('/login')
        ->assertSessionHasErrors([
            'g-recaptcha-response' => 'We could not verify that you are human. Please try again.',
        ]);
});

it('lets a verified Inertia visit through', function () {
    fakeGoogle(v3Success());

    $this->post('/login', ['g-recaptcha-response' => 'token'], inertiaHeaders())
        ->assertRedirect('/dashboard')
        ->assertSessionHasNoErrors();

    Http::assertSent(fn ($request) => $request['response'] === 'token');
});
