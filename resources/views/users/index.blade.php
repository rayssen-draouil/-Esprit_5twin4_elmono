@extends('layouts.back')
@section('content')
<div class="content-heading">
    <div>
        <span class="eyebrow">ADMINISTRATION</span>
        <h1>Utilisateurs</h1>
        <p>Gérez les comptes, les rôles et les demandes de compte technicien.</p>
    </div>
    <a class="button" href="{{ route('admin.users.create') }}">+ Ajouter un utilisateur</a>
</div>

@if(session('success'))
    <div class="flash-message flash-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="flash-message flash-error">{{ $errors->first() }}</div>
@endif

<form class="user-filters" method="GET">
    <input name="q" value="{{ request('q') }}" placeholder="Rechercher par nom ou email" aria-label="Rechercher un utilisateur">
    <select name="role" aria-label="Filtrer par rôle">
        <option value="">Tous les rôles</option>
        @foreach(['admin' => 'Administrateur', 'manager' => 'Gestionnaire', 'gestionnaire' => 'Gestionnaire réseau', 'citizen' => 'Citoyen', 'technician' => 'Technicien'] as $value => $label)
            <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <button class="button button-small" type="submit">Filtrer</button>
    @if(request()->hasAny(['q', 'role']))
        <a class="button button-outline button-small" href="{{ route('admin.users.index') }}">Réinitialiser</a>
    @endif
</form>

<section class="panel table-wrap">
    <table>
        <thead>
            <tr><th>Utilisateur</th><th>Rôle</th><th>Approbation technicien</th><th>Inscription</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse($users as $user)
                <tr>
                    <td>
                        <div class="user-cell">
                            <span class="avatar">{{ collect(explode(' ', $user->name))->map(fn ($name) => substr($name, 0, 1))->join('') }}</span>
                            <span><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></span>
                        </div>
                    </td>
                    <td><span class="badge badge-info">{{ ['admin' => 'Administrateur', 'manager' => 'Gestionnaire', 'gestionnaire' => 'Gestionnaire réseau', 'citizen' => 'Citoyen', 'technician' => 'Technicien'][$user->role] ?? ucfirst($user->role) }}</span></td>
                    <td>
                        @if($user->role === 'technician')
                            {{ ['pending' => 'En attente', 'approved' => 'Approuvé', 'rejected' => 'Refusé'][$user->technician_approval_status] ?? $user->technician_approval_status }}
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $user->created_at?->format('d/m/Y') }}</td>
                    <td class="table-actions">
                        <a class="button button-outline button-small" href="{{ route('admin.users.show', $user) }}">Voir</a>
                        <a class="button button-small" href="{{ route('admin.users.edit', $user) }}">Modifier</a>
                        @if($user->role === 'technician' && $user->technician_approval_status !== 'approved')
                            <form method="POST" action="{{ route('admin.users.approve-technician', $user) }}">
                                @csrf
                                @method('PATCH')
                                <button class="button button-small" type="submit">{{ $user->technician_approval_status === 'rejected' ? 'Approuver à nouveau' : 'Approuver' }}</button>
                            </form>
                        @endif
                        @if($user->role === 'technician' && $user->technician_approval_status === 'pending')
                            <form method="POST" action="{{ route('admin.users.reject-technician', $user) }}" onsubmit="return confirm('Refuser cette demande de compte technicien ?')">
                                @csrf
                                @method('PATCH')
                                <button class="button button-danger button-small" type="submit">Refuser</button>
                            </form>
                        @endif
                        @if(auth()->id() !== $user->id)
                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Supprimer cet utilisateur ?')">
                                @csrf
                                @method('DELETE')
                                <button class="button button-danger button-small" type="submit">Supprimer</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">Aucun utilisateur trouvé.</td></tr>
            @endforelse
        </tbody>
    </table>
</section>
{{ $users->links() }}
@endsection
