@php
    $isHome = request()->routeIs('home');
    $isZones = request()->routeIs('front.zones.*', 'front.alerts.*');
    $isTrack = request()->routeIs('front.track');
    $isIncidents = request()->routeIs('front.incidents.*');
    $isNetwork = request()->routeIs('front.infrastructures.*', 'front.projects.*', 'front.funding');
@endphp
<header class="site-header" data-site-header>
    <a class="brand" href="{{ route('home') }}" aria-label="AquaSecure, accueil">
        <span class="brand-mark" aria-hidden="true">A</span>
        <span>Aqua<span>Secure</span></span>
    </a>

    <nav id="site-nav" class="site-nav" aria-label="Navigation principale" data-site-nav>
        <a class="site-nav__link {{ $isHome ? 'is-active' : '' }}" href="{{ route('home') }}" @if($isHome) aria-current="page" @endif>État du réseau</a>
        <a class="site-nav__link {{ $isZones ? 'is-active' : '' }}" href="{{ route('front.zones.index') }}" @if($isZones) aria-current="page" @endif>Zones</a>
        <a class="site-nav__link {{ $isTrack ? 'is-active' : '' }}" href="{{ route('front.track') }}" @if($isTrack) aria-current="page" @endif>Suivi</a>
        <a class="site-nav__link {{ $isIncidents ? 'is-active' : '' }}" href="{{ route('front.incidents.index') }}" @if($isIncidents) aria-current="page" @endif>Incidents</a>

        <div class="site-nav__dropdown" data-nav-dropdown>
            <button type="button"
                    class="site-nav__link site-nav__toggle {{ $isNetwork ? 'is-active' : '' }}"
                    data-nav-dropdown-toggle
                    aria-expanded="false"
                    aria-haspopup="true"
                    aria-controls="site-nav-network">
                Réseau &amp; projets
                <span class="site-nav__chevron" aria-hidden="true"></span>
            </button>
            <div class="site-nav__menu" id="site-nav-network" data-nav-dropdown-menu hidden>
                <a href="{{ route('front.infrastructures.index') }}" @if(request()->routeIs('front.infrastructures.*')) aria-current="page" @endif>Infrastructures</a>
                <a href="{{ route('front.projects.index') }}" @if(request()->routeIs('front.projects.*')) aria-current="page" @endif>Projets</a>
                <a href="{{ route('front.funding') }}" @if(request()->routeIs('front.funding')) aria-current="page" @endif>Financement</a>
            </div>
        </div>

        @auth
            <a class="site-nav__link site-nav__account" href="{{ route('dashboard') }}">Mon espace</a>
        @else
            <a class="site-nav__link site-nav__account" href="{{ route('login') }}">Connexion</a>
        @endauth
    </nav>

    <div class="header-actions">
        <button type="button" class="theme-toggle" data-theme-toggle aria-pressed="false" aria-label="Passer au thème sombre">
            <span data-theme-icon aria-hidden="true">◐</span>
        </button>

        @auth
            <a class="header-login header-user" href="{{ route('dashboard') }}" title="{{ Auth::user()->name }}">
                <span class="header-user__name">{{ Auth::user()->name }}</span>
            </a>
        @else
            <a class="header-login" href="{{ route('login') }}">Connexion</a>
        @endauth

        <a class="button button-small header-cta" href="{{ route('front.incidents.create') }}">Signaler</a>
    </div>

    <button type="button"
            class="menu-toggle"
            data-nav-toggle
            aria-label="Ouvrir le menu"
            aria-expanded="false"
            aria-controls="site-nav">
        <span aria-hidden="true">☰</span>
    </button>
</header>
