@extends('layouts.back')

@section('content')
<div class="content-heading">
    <div><span class="eyebrow">ALERTES</span><h1>Créer une alerte</h1><p>Associez une alerte à une zone et, si besoin, à un incident.</p></div>
</div>

<form method="POST" action="{{ route('back.alerts.store') }}">
    @csrf
    @include('back.alerts._form')
</form>
@endsection
