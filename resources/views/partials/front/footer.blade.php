@php
    $service = (array) config('aquasecure.contacts.service', []);
@endphp
<footer class="site-footer">
    <div>
        <a class="brand brand-light" href="{{ route('home') }}">
            <span class="brand-mark" aria-hidden="true">A</span>
            <span>Aqua<span>Secure</span></span>
        </a>
        <p>Protéger l'eau, préserver demain.</p>
    </div>

    <div>
        <strong>Explorer</strong>
        <a href="{{ route('front.about') }}">Notre mission</a>
        <a href="{{ route('front.zones.index') }}">Zones surveillées</a>
        <a href="{{ route('front.track') }}">Suivre mon signalement</a>
        <a href="{{ route('front.projects.index') }}">Nos projets</a>
        <a href="{{ route('front.funding') }}">Financements</a>
    </div>

    <div>
        <strong>Nous trouver</strong>
        @if(! empty($service['address']))
            <span>{!! nl2br(e($service['address'])) !!}</span>
        @endif
        @if(! empty($service['email']))
            <a href="mailto:{{ $service['email'] }}">{{ $service['email'] }}</a>
        @endif
        @if(! empty($service['phone']))
            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $service['phone']) }}">{{ $service['phone'] }}</a>
        @endif
    </div>

    <div>
        <strong>Urgences</strong>
        <span>Appelez le <a href="tel:112">112</a> en cas de danger immédiat.</span>
        <a href="{{ route('front.zones.index') }}">Voir l'état des zones</a>
        <span class="socials" aria-hidden="true">in&nbsp;&nbsp;◎&nbsp;&nbsp;𝕏</span>
    </div>

    <small>© {{ date('Y') }} AquaSecure. Tous droits réservés.</small>
</footer>
