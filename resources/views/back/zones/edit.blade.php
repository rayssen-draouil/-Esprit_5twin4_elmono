@extends('layouts.back')

@section('content')
<div class="content-heading">
    <div><span class="eyebrow">ZONES</span><h1>Modifier « {{ $zone->name }} »</h1><p>Mettez à jour les informations et le niveau de risque.</p></div>
</div>

<form method="POST" action="{{ route('back.zones.update', $zone) }}">
    @csrf
    @method('PUT')
    @include('back.zones._form', ['zone' => $zone])
</form>
@endsection
