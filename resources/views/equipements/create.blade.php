@extends('layouts.back')
@section('content')
<div class="content-heading"><div><span class="eyebrow">MATÉRIEL</span><h1>Ajouter un équipement</h1></div></div>
<section class="panel" style="padding:28px"><form method="POST" action="{{ route('equipements.store') }}" enctype="multipart/form-data" novalidate class="validated-form">@include('equipements.form', ['submitLabel' => 'Ajouter'])</form></section>
@endsection
