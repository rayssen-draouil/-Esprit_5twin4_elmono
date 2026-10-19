@props(['label', 'value', 'hint' => null, 'accent' => 'info', 'liveKey' => null])

<div {{ $attributes->merge(['class' => 'aq-stat', 'data-accent' => $accent]) }}>
    <span class="aq-stat__label">{{ $label }}</span>
    <strong class="aq-stat__value" data-stat-value @if($liveKey) data-live-kpi="{{ $liveKey }}" @endif>{{ $value }}</strong>
    <span class="aq-stat__hint">{{ $hint }}</span>
</div>
