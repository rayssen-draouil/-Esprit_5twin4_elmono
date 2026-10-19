@extends('layouts.back')

@section('title', 'Modifier une zone')

@push('scripts')
    @vite(['resources/js/zones-alerts.js'])
@endpush

@section('content')
    <div class="aq-page">
        <x-page-header
            eyebrow="ZONES"
            title="Modifier « {{ $zone->name }} »"
            description="Mettez à jour les informations, la position et le niveau de risque." />

        <form method="POST" action="{{ route('back.zones.update', $zone) }}" class="aq-form" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('back.zones._form', ['zone' => $zone])
        </form>
    </div>
@endsection
