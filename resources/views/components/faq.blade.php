@props(['items' => null, 'title' => 'Questions fréquentes'])

@php
    $faq = $items ?? (array) config('aquasecure.faq', []);
@endphp

@if(! empty($faq))
    <section {{ $attributes->merge(['class' => 'aq-faq']) }} aria-labelledby="aq-faq-title">
        <h2 id="aq-faq-title" class="aq-faq__title">{{ $title }}</h2>
        <div class="aq-faq__list">
            @foreach($faq as $item)
                <details class="aq-faq__item">
                    <summary>{{ $item['question'] ?? '' }}</summary>
                    <p>{{ $item['answer'] ?? '' }}</p>
                </details>
            @endforeach
        </div>
    </section>
@endif
