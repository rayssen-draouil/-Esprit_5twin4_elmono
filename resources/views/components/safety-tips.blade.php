@props(['level' => 'medium', 'title' => 'Conseils de sécurité'])

@php
    $key = strtolower((string) $level);
    $key = in_array($key, ['low', 'medium', 'high', 'critical'], true) ? $key : 'medium';
    $tips = (array) config('aquasecure.safety_tips.'.$key, []);
@endphp

@if(! empty($tips))
    <section {{ $attributes->merge(['class' => 'aq-safety']) }} data-risk-level="{{ $key }}">
        <h2 class="aq-safety__title">{{ $title }}</h2>
        <ul class="aq-safety__list">
            @foreach($tips as $tip)
                <li><span aria-hidden="true">✓</span>{{ $tip }}</li>
            @endforeach
        </ul>
    </section>
@endif
