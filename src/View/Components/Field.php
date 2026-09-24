<?php

declare(strict_types=1);

namespace Labidi\Recaptcha\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

/**
 * Adds reCAPTCHA to a form, whichever version is configured.
 *
 *     <x-recaptcha::field action="login" />   or   @recaptchaField('login')
 *
 * v3 renders a hidden input that is filled with a fresh token when the form
 * is submitted. v2 renders the checkbox, or the invisible widget which runs
 * its challenge when the form is submitted.
 */
class Field extends RecaptchaComponent
{
    public string $id;

    public string $name;

    public ?string $siteKey;

    public string $action;

    public bool $invisible;

    /**
     * Options passed to grecaptcha.render() for v2 widgets.
     *
     * @var array<string, string|int>
     */
    public array $params;

    public ?string $nonce;

    public function __construct(
        ?string $action = null,
        ?string $theme = null,
        ?string $size = null,
        ?string $badge = null,
        ?int $tabindex = null,
        ?string $id = null,
        ?string $nonce = null,
    ) {
        $recaptcha = $this->recaptcha();

        $this->id = $id ?? 'recaptcha-'.Str::lower(Str::random(8));
        $this->name = $recaptcha->fieldName();
        $this->siteKey = $recaptcha->siteKey();
        $this->action = $action ?? 'submit';
        $this->invisible = $recaptcha->isInvisible();
        $this->nonce = $this->resolveNonce($nonce);

        $this->params = array_filter([
            'sitekey' => (string) $this->siteKey,
            'theme' => $theme ?? $recaptcha->config('attributes.theme'),
            'size' => $this->invisible ? 'invisible' : ($size ?? $recaptcha->config('attributes.size')),
            'badge' => $this->invisible ? ($badge ?? $recaptcha->config('attributes.badge')) : null,
            'tabindex' => $tabindex,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    public function render(): View
    {
        /** @var view-string $view */
        $view = $this->recaptcha()->isV3() ? 'recaptcha::field' : 'recaptcha::widget';

        return view($view);
    }
}
