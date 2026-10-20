@extends('layouts.back')

@section('content')
<div class="content-heading">
    <div><span class="eyebrow">ZONES</span><h1>Ajouter une zone</h1><p>Renseignez les informations de la nouvelle zone.</p></div>
</div>

<form method="POST" action="{{ route('back.zones.store') }}">
    @csrf
    @include('back.zones._form')
</form>
@endsection
