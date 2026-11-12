<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' – '.config('app.name', 'TelcoDash') : config('app.name', 'TelcoDash') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

{{-- The signal mark animates once per full page load, not on every wire:navigate. --}}
<script>
    document.documentElement.classList.add('signal-intro');
    setTimeout(() => document.documentElement.classList.remove('signal-intro'), 1200);
</script>

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
