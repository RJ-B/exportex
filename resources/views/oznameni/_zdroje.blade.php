{{-- CSS a JS oznámení (zvoneček, pruh) – hotové soubory v public/, nic se nebuildí. Jednou na stránku. --}}
@once
    <link rel="stylesheet" href="{{ asset('css/oznameni.css') }}?v={{ @filemtime(public_path('css/oznameni.css')) }}">
    <script src="{{ asset('js/oznameni.js') }}?v={{ @filemtime(public_path('js/oznameni.js')) }}" defer></script>
@endonce
