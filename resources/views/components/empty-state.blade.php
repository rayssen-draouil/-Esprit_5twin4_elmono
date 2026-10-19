@props(['title' => 'Aucun résultat', 'message' => null, 'icon' => null])

<div {{ $attributes->merge(['class' => 'aq-empty', 'role' => 'status']) }}>
    <span class="aq-empty__icon" aria-hidden="true">
        @if($icon)
            {{ $icon }}
        @else
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2.7s6 6.1 6 10.3a6 6 0 1 1-12 0c0-4.2 6-10.3 6-10.3z" />
            </svg>
        @endif
    </span>
    <h3>{{ $title }}</h3>
    @if($message)<p>{{ $message }}</p>@endif
    @if($slot->isNotEmpty())<div class="aq-empty__actions">{{ $slot }}</div>@endif
</div>
