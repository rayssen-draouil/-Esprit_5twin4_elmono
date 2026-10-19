@props(['action' => null, 'filters' => []])

<form method="GET" action="{{ $action ?? request()->url() }}" class="aq-filterbar" data-server-filter {{ $attributes }}>
    {{ $slot }}
    @foreach(array_filter($filters, fn ($value) => $value !== null && $value !== '') as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
</form>
