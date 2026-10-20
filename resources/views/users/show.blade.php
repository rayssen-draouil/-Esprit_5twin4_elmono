@extends('layouts.back')
@section('content')
<div class="content-heading"><div><span class="eyebrow">UTILISATEUR</span><h1>{{ $user->name }}</h1><p>{{ $user->email }}</p></div><a class="button" href="{{ route('admin.users.edit', $user) }}">Modifier</a></div>
<section class="panel" style="padding:28px"><p><strong>Rôle :</strong> {{ $user->roleLabel() }}</p><p><strong>Inscrit le :</strong> {{ $user->created_at?->format('d/m/Y') }}</p><a class="text-link" href="{{ route('admin.users.index') }}">← Retour aux utilisateurs</a></section>
@endsection
