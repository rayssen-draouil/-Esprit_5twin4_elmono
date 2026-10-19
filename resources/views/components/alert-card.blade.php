@props([
    'type',
    'message' => null,
    'zone' => null,
    'zoneId' => null,
    'zoneUrl' => null,
    'zoneImage' => null,
    'severity' => 'medium',
    'severityLabel' => null,
    'status' => null,
    'statusLevel' => 'open',
    'date' => null,
    'url' => null,
    'aiSeverity' => null,
])

@php
    $level = strtolower((string) $severity);
    $stateLabels = [
        'open' => 'En cours de traitement',
        'acknowledged' => 'Prise en charge',
        'resolved' => 'Situation rétablie',
    ];
    $state = $status ?? ($stateLabels[$statusLevel] ?? 'En cours de traitement');
    $stateIcon = ['open' => '◉', 'acknowledged' => '◐', 'resolved' => '✓'][$statusLevel] ?? '◉';
@endphp

<article {{ $attributes->merge(['class' => 'alert-card-front', 'data-severity' => $level]) }}
         @if($zoneId) data-zone-id="{{ $zoneId }}" @endif>
    <div class="alert-card-front__top">
        <x-severity-badge :severity="$level" :label="$severityLabel" />
        @if($aiSeverity)
            <x-severity-badge :severity="$aiSeverity" :ai="true" />
        @endif
        @if($date)
            <time class="alert-card-front__date">{{ $date }}</time>
        @endif
    </div>

    <h3 class="alert-card-front__title">
        @if($url)
            <a href="{{ $url }}">{{ $type }}</a>
        @else
            {{ $type }}
        @endif
    </h3>

    @if($message)
        <p class="alert-card-front__message">{{ \Illuminate\Support\Str::limit($message, 150) }}</p>
    @endif

    <div class="alert-card-front__meta">
        @if($zone)
            <span class="alert-card-front__zone">
                @if($zoneImage)
                    <img src="{{ $zoneImage }}" alt="" loading="lazy" aria-hidden="true">
                @else
                    <span class="alert-card-front__zone-dot" aria-hidden="true">◉</span>
                @endif
                @if($zoneUrl)
                    <a href="{{ $zoneUrl }}">{{ $zone }}</a>
                @else
                    {{ $zone }}
                @endif
            </span>
        @endif
        <span class="alert-card-front__state" data-status="{{ $statusLevel }}">
            <span aria-hidden="true">{{ $stateIcon }}</span> {{ $state }}
        </span>
    </div>

    @if($url)
        <a class="alert-card-front__link" href="{{ $url }}">Comprendre et agir <span aria-hidden="true">→</span></a>
    @endif
</article>
