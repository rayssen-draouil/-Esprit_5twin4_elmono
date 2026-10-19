@props(['compact' => false])

@php
    $service = (array) config('aquasecure.contacts.service', []);
    $emergency = (array) config('aquasecure.contacts.emergency', []);
@endphp

<section {{ $attributes->merge(['class' => 'aq-contacts'.($compact ? ' aq-contacts--compact' : '')]) }}
         aria-labelledby="aq-contacts-title">
    <h2 id="aq-contacts-title" class="aq-contacts__title">Contacts utiles</h2>

    <div class="aq-contacts__grid">
        @if(! empty($service))
            <div class="aq-contacts__card aq-contacts__card--service">
                <span class="aq-contacts__label">{{ $service['label'] ?? 'Service des eaux' }}</span>
                @if(! empty($service['phone']))
                    <a class="aq-contacts__phone" href="tel:{{ preg_replace('/[^0-9+]/', '', $service['phone']) }}">
                        {{ $service['phone'] }}
                    </a>
                @endif
                @if(! empty($service['hours']))
                    <small>{{ $service['hours'] }}</small>
                @endif
                @if(! empty($service['email']))
                    <a class="aq-contacts__mail" href="mailto:{{ $service['email'] }}">{{ $service['email'] }}</a>
                @endif
            </div>
        @endif

        @foreach($emergency as $contact)
            <div class="aq-contacts__card aq-contacts__card--emergency">
                <span class="aq-contacts__label">{{ $contact['label'] ?? 'Urgence' }}</span>
                <a class="aq-contacts__phone" href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone'] ?? '') }}">
                    {{ $contact['phone'] ?? '—' }}
                </a>
                @if(! empty($contact['note']))
                    <small>{{ $contact['note'] }}</small>
                @endif
            </div>
        @endforeach
    </div>

    @unless($compact)
        <p class="aq-contacts__note">
            En cas de danger immédiat, appelez le 112. Pour un problème sur le réseau, utilisez le
            <a href="{{ route('front.incidents.create') }}">formulaire de signalement</a>.
        </p>
    @endunless
</section>
