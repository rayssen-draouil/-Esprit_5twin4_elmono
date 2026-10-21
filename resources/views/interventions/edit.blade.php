@extends('layouts.back')
@section('content')
<div class="content-heading"><div><span class="eyebrow">OPÉRATIONS</span><h1>Modifier l’intervention</h1><p>Intervention #{{ $intervention->id }}</p></div></div>
<section class="panel form-panel"><form method="POST" action="{{ route('interventions.update', $intervention) }}" novalidate>@method('PUT') @include('interventions.form', ['submitLabel' => 'Enregistrer'])</form></section>
@endsection