@extends('layouts.back')

@section('title', "Modifier l'alerte")

@push('scripts')
    @vite(['resources/js/zones-alerts.js'])
@endpush

@section('content')
    <div class="aq-page">
        <x-page-header
            eyebrow="ALERTES"
            title="Modifier l'alerte"
            description="Mettez à jour le message, la gravité ou l'état de lecture." />

        <form method="POST" action="{{ route('back.alerts.update', $alert) }}" class="aq-form">
            @csrf
            @method('PUT')
            @include('back.alerts._form', ['alert' => $alert])
        </form>
    </div>
@endsection
