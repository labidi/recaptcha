<?php

declare(strict_types=1);

namespace Labidi\Recaptcha\View\Components;

use Illuminate\Contracts\View\View;

/**
 * Loads Google's api.js.
 *
 *     <x-recaptcha::script />   or   @recaptchaScript
 */
class Script extends RecaptchaComponent
{
    public string $src;

    public bool $async;

    public bool $defer;

    public ?string $nonce;

    public function __construct(?bool $async = null, ?bool $defer = null, ?string $nonce = null)
    {
        $recaptcha = $this->recaptcha();

        $this->src = $recaptcha->scriptUrl();
        $this->async = $async ?? (bool) $recaptcha->config('attributes.async', true);
        $this->defer = $defer ?? (bool) $recaptcha->config('attributes.defer', true);
        $this->nonce = $this->resolveNonce($nonce);
    }

    public function render(): View
    {
        /** @var view-string $view */
        $view = 'recaptcha::script';

        return view($view);
    }
}
