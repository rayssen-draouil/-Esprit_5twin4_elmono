@extends('layouts.back')

@section('content')
<div class="content-heading">
    <div><span class="eyebrow">ALERTES</span><h1>Modifier l'alerte</h1><p>Mettez à jour le message, la gravité ou l'état de lecture.</p></div>
</div>

<form method="POST" action="{{ route('back.alerts.update', $alert) }}">
    @csrf
    @method('PUT')
    @include('back.alerts._form', ['alert' => $alert])
</form>
@endsection
