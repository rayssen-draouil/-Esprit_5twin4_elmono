@props(['eyebrow' => null, 'title', 'description' => null, 'breadcrumbs' => []])

@if(count($breadcrumbs))
    <nav aria-label="Fil d'Ariane">
        <ol class="aq-breadcrumb">
            @foreach($breadcrumbs as $crumb)
                <li>
                    @if(! empty($crumb['url']) && ! $loop->last)
                        <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                    @else
                        <span aria-current="page">{{ $crumb['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif

<header class="aq-header">
    <div class="aq-header__main">
        @if($eyebrow)<span class="eyebrow">{{ $eyebrow }}</span>@endif
        <h1>{{ $title }}</h1>
        @if($description)<p class="aq-header__sub">{{ $description }}</p>@endif
    </div>
    @if($slot->isNotEmpty())
        <div class="aq-header__actions">{{ $slot }}</div>
    @endif
</header>
