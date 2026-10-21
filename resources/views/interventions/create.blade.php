@extends('layouts.back')
@section('content')
<div class="content-heading"><div><span class="eyebrow">OPÉRATIONS</span><h1>Nouvelle intervention</h1><p>Reliez un incident à une équipe et, si besoin, à un technicien.</p></div></div>
<section class="panel form-panel"><form method="POST" action="{{ route('interventions.store') }}" novalidate>@include('interventions.form', ['submitLabel' => 'Créer l’intervention'])</form></section>
@endsection