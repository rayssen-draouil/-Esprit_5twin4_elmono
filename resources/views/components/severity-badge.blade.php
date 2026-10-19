@props(['severity' => null, 'label' => null, 'ai' => false, 'prefix' => ''])

@php
    $toneMap = [
        'low' => 'success',
        'medium' => 'info',
        'high' => 'warning',
        'critical' => 'critical',
        'info' => 'info',
        'warning' => 'warning',
    ];
    $glyphs = [
        'low' => '✓',
        'medium' => '•',
        'high' => '▲',
        'critical' => '✕',
        'info' => 'i',
        'warning' => '!',
    ];
    $tone = $toneMap[$severity] ?? 'neutral';
    $labels = array_merge(\App\Models\Alert::SEVERITIES, \App\Models\Alert::AI_SEVERITIES);
    $text = $label ?? ($labels[$severity] ?? ucfirst((string) $severity));
    $text = trim($prefix.($ai ? 'IA : ' : '').$text);
@endphp

<span {{ $attributes->merge(['class' => 'aq-badge aq-badge--'.$tone, 'title' => $text]) }}>
    <span class="aq-badge__glyph" aria-hidden="true">{{ $glyphs[$severity] ?? '•' }}</span>
    <span>{{ $text }}</span>
</span>
