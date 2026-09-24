<script @if ($nonce) nonce="{{ $nonce }}" @endif>{!! $bootstrap !!}</script>
<script src="{{ $src }}" @if ($async) async @endif @if ($defer) defer @endif @if ($nonce) nonce="{{ $nonce }}" @endif {{ $attributes }}></script>
