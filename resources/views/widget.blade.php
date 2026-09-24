<div id="{{ $id }}" {{ $attributes->merge(['class' => 'recaptcha-widget']) }}></div>
<script @if ($nonce) nonce="{{ $nonce }}" @endif>
{!! $bootstrap !!}
(function () {
    var el = document.getElementById(@js($id));
    var params = @js($params);
@if ($invisible)
    var form = el.closest('form');
    var verified = false;
    params.callback = function () {
        verified = true;
        form && (form.requestSubmit ? form.requestSubmit() : form.submit());
    };
    params['expired-callback'] = function () { verified = false; };
    var widgetId = null;
    window.__labidiRecaptcha.ready(function () { widgetId = grecaptcha.render(el, params); });
    // Listen straight away so an early submit waits for the widget instead of
    // posting without a token; the ready queue runs the render first.
    form && form.addEventListener('submit', function (event) {
        if (verified) { return; }
        event.preventDefault();
        window.__labidiRecaptcha.ready(function () { grecaptcha.execute(widgetId); });
    });
@else
    window.__labidiRecaptcha.ready(function () { grecaptcha.render(el, params); });
@endif
})();
</script>
