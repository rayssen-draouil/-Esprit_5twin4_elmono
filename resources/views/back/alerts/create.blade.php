@extends('layouts.back')

@section('title', 'Créer une alerte')

@push('scripts')
    @vite(['resources/js/zones-alerts.js'])
@endpush

@section('content')
    <div class="aq-page">
        <x-page-header
            eyebrow="ALERTES"
            title="Créer une alerte"
            description="Associez une alerte à une zone et, si besoin, à un incident." />

        <form method="POST" action="{{ route('back.alerts.store') }}" class="aq-form">
            @csrf
            @include('back.alerts._form')
        </form>
    </div>
@endsection
