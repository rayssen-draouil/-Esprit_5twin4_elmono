@extends('layouts.back')

@section('title', 'Ajouter une zone')

@push('scripts')
    @vite(['resources/js/zones-alerts.js'])
@endpush

@section('content')
    <div class="aq-page">
        <x-page-header
            eyebrow="ZONES"
            title="Ajouter une zone"
            description="Renseignez les informations de la nouvelle zone. La carte est facultative." />

        <form method="POST" action="{{ route('back.zones.store') }}" class="aq-form" enctype="multipart/form-data">
            @csrf
            @include('back.zones._form')
        </form>
    </div>
@endsection
