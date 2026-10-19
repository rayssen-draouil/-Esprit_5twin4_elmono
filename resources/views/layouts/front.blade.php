@php
    $followedZoneIds = auth()->check()
        ? \App\Models\UserZoneSubscription::where('user_id', auth()->id())->pluck('zone_id')->all()
        : [];
    $pageTitle = trim($__env->yieldContent('title') ?: ($title ?? 'AquaSecure'));
    $pageDescription = $__env->yieldContent('meta_description') ?: ($metaDescription ?? "AquaSecure, la protection intelligente de l'eau.");
@endphp
<!doctype html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }} · AquaSecure</title>
    <meta name="description" content="{{ $pageDescription }}">
    <meta name="theme-color" content="#073f4a">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="AquaSecure">
    <meta property="og:title" content="{{ $pageTitle }} · AquaSecure">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="fr_FR">
    <meta name="twitter:card" content="summary">
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('aquasecure:theme');
                var theme = stored || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.dataset.theme = theme;
            } catch (error) { /* stockage indisponible */ }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/css/enhancements.css', 'resources/css/premium-overrides.css', 'resources/css/signalement.css', 'resources/css/front-zones-alerts.css', 'resources/js/app.js', 'resources/js/front-zones-alerts.js'])
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @stack('styles')
</head>
<body class="front-body"
      data-authenticated="{{ auth()->check() ? '1' : '0' }}"
      data-followed-zones="{{ json_encode($followedZoneIds) }}"
      data-subscribe-url="{{ url('/mes-zones') }}"
      data-login-url="{{ route('login') }}">
    @include('partials.front.header')
    <main id="contenu">
        @include('partials.front.flash')
        @yield('content')
    </main>
    @include('partials.front.footer')
    <script>
        (function () {
            var buttons = document.querySelectorAll('[data-theme-toggle]');
            var root = document.documentElement;

            var paint = function () {
                var dark = root.dataset.theme === 'dark';
                buttons.forEach(function (button) {
                    button.setAttribute('aria-pressed', dark ? 'true' : 'false');
                    button.setAttribute('aria-label', dark ? 'Passer au thème clair' : 'Passer au thème sombre');
                    var icon = button.querySelector('[data-theme-icon]');
                    if (icon) icon.textContent = dark ? '☀' : '◐';
                });
            };

            buttons.forEach(function (button) {
                button.addEventListener('click', function () {
                    root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
                    try { localStorage.setItem('aquasecure:theme', root.dataset.theme); } catch (error) { /* stockage indisponible */ }
                    paint();
                });
            });

            paint();
        })();
    </script>
    @stack('scripts')
</body>
</html>
