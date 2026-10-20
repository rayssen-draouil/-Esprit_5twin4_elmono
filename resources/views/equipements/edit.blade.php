@extends('layouts.back')
@section('content')
<div class="content-heading"><div><span class="eyebrow">MATÉRIEL</span><h1>Modifier {{ $equipement->name }}</h1></div></div>
<section class="panel" style="padding:28px"><form method="POST" action="{{ route('equipements.update', $equipement) }}" enctype="multipart/form-data" novalidate class="validated-form">@method('PUT') @include('equipements.form', ['submitLabel' => 'Enregistrer'])</form></section>
@endsection
