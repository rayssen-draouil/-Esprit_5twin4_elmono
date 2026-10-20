@extends('layouts.back')
@section('content')
<div class="content-heading"><div><span class="eyebrow">UTILISATEUR</span><h1>{{ $user->name }}</h1><p>{{ $user->email }}</p></div><a class="button" href="{{ route('admin.users.edit', $user) }}">Modifier</a></div>
<section class="panel" style="padding:28px">
    <p><strong>Rôle :</strong> {{ ['admin'=>'Administrateur','manager'=>'Gestionnaire','citizen'=>'Citoyen','technician'=>'Technicien'][$user->role] ?? ucfirst($user->role) }}</p>
    <p><strong>Inscrit le :</strong> {{ $user->created_at?->format('d/m/Y') }}</p>
    @if($user->role === 'technician')
        <p><strong>Approbation :</strong> {{ ['pending' => 'En attente', 'approved' => 'Approuvé', 'rejected' => 'Refusé'][$user->technician_approval_status] ?? $user->technician_approval_status }}</p>
        <p><strong>Téléphone :</strong> {{ $user->technicien?->phone ?: '—' }}</p>
        <p><strong>Spécialité :</strong> {{ $user->technicien?->speciality ?: '—' }}</p>
        <p><strong>Compétences :</strong><br>{{ $user->technicien?->skills ?: '—' }}</p>
        @if($user->technician_approval_status !== 'approved')
            <form method="POST" action="{{ route('admin.users.approve-technician', $user) }}" style="display:inline">
                @csrf
                @method('PATCH')
                <button class="button" type="submit">{{ $user->technician_approval_status === 'rejected' ? 'Approuver à nouveau' : 'Approuver le compte' }}</button>
            </form>
        @endif
        @if($user->technician_approval_status === 'pending')
            <form method="POST" action="{{ route('admin.users.reject-technician', $user) }}" style="display:inline" onsubmit="return confirm('Refuser cette demande de compte technicien ?')">
                @csrf
                @method('PATCH')
                <button class="button button-danger" type="submit">Refuser la demande</button>
            </form>
        @endif
    @endif
    <p><a class="text-link" href="{{ route('admin.users.index') }}">← Retour aux utilisateurs</a></p>
</section>
@endsection
