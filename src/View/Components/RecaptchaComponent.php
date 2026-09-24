<?php

declare(strict_types=1);

namespace Labidi\Recaptcha\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Vite as FoundationVite;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Component;
use Labidi\Recaptcha\Recaptcha;

/**
 * Shared behaviour for the package's Blade components.
 *
 * Each component can also be rendered outside a Blade template through
 * html(), which is what the @recaptcha* directives call.
 */
abstract class RecaptchaComponent extends Component
{
    /**
     * Keeps the page-level JS registry idempotent: api.js reports readiness
     * to the onload callback, and widgets queue work until then.
     */
    public const BOOTSTRAP_JS = 'window.__labidiRecaptcha=window.__labidiRecaptcha||{loaded:false,queue:[],ready:function(c){this.loaded?c():this.queue.push(c)}};'
        .'window.'.Recaptcha::ONLOAD_CALLBACK.'=window.'.Recaptcha::ONLOAD_CALLBACK.'||function(){var r=window.__labidiRecaptcha;r.loaded=true;r.queue.splice(0).forEach(function(c){c()})};';

    public string $bootstrap = self::BOOTSTRAP_JS;

    /**
     * @var array<int, string>
     */
    protected $except = ['html'];

    /**
     * Render the component to a string, or an empty string when it should
     * not be displayed.
     */
    public static function html(mixed ...$arguments): string
    {
        // Every component's constructor takes only optional arguments, which
        // is what lets the directives forward theirs as written.
        // @phpstan-ignore new.static
        $component = new static(...$arguments);

        if (! $component->shouldRender()) {
            return '';
        }

        return $component->render()->with($component->data())->render();
    }

    public function shouldRender(): bool
    {
        return $this->recaptcha()->shouldRender();
    }

    abstract public function render(): View;

    protected function recaptcha(): Recaptcha
    {
        return app(Recaptcha::class);
    }

    /**
     * The CSP nonce for inline scripts: the explicit value, then the config
     * value, then the nonce Laravel's Vite integration generated, if any.
     */
    protected function resolveNonce(?string $nonce): ?string
    {
        $nonce ??= $this->recaptcha()->config('attributes.nonce');

        if (is_string($nonce) && $nonce !== '') {
            return $nonce;
        }

        if (! class_exists(FoundationVite::class) || ! app()->bound(FoundationVite::class)) {
            return null;
        }

        $viteNonce = Vite::cspNonce();

        return is_string($viteNonce) && $viteNonce !== '' ? $viteNonce : null;
    }
}
