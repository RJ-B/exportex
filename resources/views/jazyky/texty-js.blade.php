{{-- Texty pro statické skripty v public/js (datum.js, passkeys.js…) v jazyce stránky – lang/<jazyk>/js.php.
     Skript je čte přes simrenText('datum.vyber_data'); vložit PŘED skript, který je používá (docs/jazyky.md). --}}
<script>
    window.simrenTexty = @js(__('js'));
    window.simrenText = function (klic) {
        var hodnota = klic.split('.').reduce(function (o, k) { return o && o[k]; }, window.simrenTexty);
        return hodnota === undefined ? klic : hodnota;
    };
</script>
