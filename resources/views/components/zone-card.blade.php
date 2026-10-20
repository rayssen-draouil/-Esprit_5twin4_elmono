@props([
    'name',
    'address' => null,
    'description' => null,
    'risk' => 'low',
    'riskLabel' => null,
    'aiRisk' => null,
    'image' => null,
    'alerts' => 0,
    'incidents' => 0,
    'infrastructures' => 0,
    'url' => null,
    'zoneId' => null,
    'lastUpdate' => null,
    'followable' => true,
])

@php
    $level = strtolower((string) $risk);
    $guards = ['low' => 25, 'medium' => 55, 'high' => 80, 'critical' => 100];
    $gauge = $guards[$level] ?? 25;
    $labels = array_merge(\App\Models\Zone::AI_RISK_LEVELS, \App\Models\Zone::RISK_LEVELS);
    $label = $riskLabel ?? ($labels[$level] ?? ucfirst($level));
@endphp

<article {{ $attributes->merge(['class' => 'zone-card-front', 'data-zone-card' => true]) }}
         @if($zoneId) data-zone-id="{{ $zoneId }}" @endif>
    <div class="zone-card-front__media">
        @if($image)
            <img src="{{ $image }}" alt="Photo de la zone {{ $name }}" loading="lazy">
        @else
            <span class="zone-card-front__art" aria-hidden="true">◉</span>
        @endif
        <span class="zone-card-front__tag">{{ $label }}</span>

        @if($followable && $zoneId)
            <button type="button"
                    class="zone-card-front__follow"
                    data-favorite-zone="{{ $zoneId }}"
                    aria-pressed="false"
                    aria-label="Suivre la zone {{ $name }}">
                <span data-favorite-icon aria-hidden="true">☆</span>
                <span data-favorite-text>Suivre</span>
            </button>
        @endif
    </div>

    <div class="zone-card-front__body">
        <div class="zone-card-front__head">
            <h3>
                @if($url)
                    <a href="{{ $url }}">{{ $name }}</a>
                @else
                    {{ $name }}
                @endif
            </h3>
            <x-risk-badge :level="$level" :label="$label" />
        </div>

        @if($address)
            <p class="zone-card-front__address">◎ {{ $address }}</p>
        @endif

        @if($description)
            <p class="zone-card-front__desc">{{ \Illuminate\Support\Str::limit($description, 110) }}</p>
        @endif

        @if($aiRisk)
            <div class="zone-card-front__badges">
                <x-risk-badge :level="strtolower((string) $aiRisk)" :ai="true" />
            </div>
        @endif

        <div class="zone-card-front__gauge" role="img" aria-label="Niveau de risque : {{ $label }}">
            <span class="zone-card-front__gauge-fill" style="width:{{ $gauge }}%"></span>
        </div>

        <ul class="zone-card-front__meta">
            <li><strong>{{ (int) $alerts }}</strong> alerte(s) active(s)</li>
            <li><strong>{{ (int) $incidents }}</strong> incident(s)</li>
            <li><strong>{{ (int) $infrastructures }}</strong> infrastructure(s)</li>
        </ul>

        <div class="zone-card-front__foot">
            @if($lastUpdate)
                <small>Dernière alerte : {{ $lastUpdate }}</small>
            @endif
            @if($url)
                <a class="zone-card-front__link" href="{{ $url }}">Voir la zone <span aria-hidden="true">→</span></a>
            @endif
        </div>
    </div>
</article>
