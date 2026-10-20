@props([
    'label',
    'value',
    'hint' => null,
    'accent' => 'info',
    'liveKey' => null,
    'suffix' => '',
])

@php
    $numeric = is_numeric($value);
    $display = $numeric ? (string) $value : (string) $value;
@endphp

<div {{ $attributes->merge(['class' => 'aq-stat aq-stat--front', 'data-accent' => $accent]) }}
     @if($liveKey) data-kpi="{{ $liveKey }}" @endif>
    <span class="aq-stat__label">{{ $label }}</span>
    <strong class="aq-stat__value"
            data-stat-value
            @if($numeric) data-countup="{{ $value }}" @endif><span data-count-number>{{ $display }}</span>{{ $suffix }}</strong>
    @if($hint)
        <span class="aq-stat__hint">{{ $hint }}</span>
    @endif
</div>
