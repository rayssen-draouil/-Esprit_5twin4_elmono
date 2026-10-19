@props(['level' => null, 'label' => null, 'ai' => false])

@php
    $tones = ['low', 'medium', 'high', 'critical', 'info', 'warning', 'success'];
    $glyphs = [
        'low' => '✓',
        'medium' => '•',
        'high' => '▲',
        'critical' => '✕',
        'info' => 'i',
        'warning' => '!',
        'success' => '✓',
    ];
    $known = array_key_exists($level, $tones);
    $tone = $known ? $level : 'neutral';
    $labels = array_merge(\App\Models\Zone::AI_RISK_LEVELS, \App\Models\Zone::RISK_LEVELS);
    $text = $label ?? ($labels[$level] ?? ucfirst((string) $level));
    $text = $ai ? 'Risque IA : '.$text : $text;
@endphp

<span {{ $attributes->merge(['class' => 'aq-badge aq-badge--'.$tone, 'title' => $text]) }}>
    <span class="aq-badge__glyph" aria-hidden="true">{{ $known ? ($glyphs[$level] ?? '•') : '•' }}</span>
    <span>{{ $text }}</span>
</span>
