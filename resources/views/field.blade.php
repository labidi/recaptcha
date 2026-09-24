<input type="hidden" id="{{ $id }}" name="{{ $name }}" {{ $attributes }}>
<script @if ($nonce) nonce="{{ $nonce }}" @endif>
{!! $bootstrap !!}
(function () {
    var input = document.getElementById(@js($id));
    var form = input.form;
    var submitting = false;
    if (!form) { return; }
    form.addEventListener('submit', function (event) {
        if (submitting) { return; }
        event.preventDefault();
        var submitter = event.submitter;
        window.__labidiRecaptcha.ready(function () {
            grecaptcha.execute(@js($siteKey), { action: @js($action) }).then(function (token) {
                input.value = token;
                submitting = true;
                form.requestSubmit ? form.requestSubmit(submitter || undefined) : form.submit();
                submitting = false;
            });
        });
    });
})();
</script>
