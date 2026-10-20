@extends('layouts.back')
@section('content')
<div class="content-heading"><div><span class="eyebrow">MON COMPTE</span><h1>Mon profil</h1><p>Modifiez vos informations personnelles. Votre rôle est géré par l'administration.</p></div></div>
<section class="panel form-panel"><form method="POST" action="{{ route('profile.update') }}">@csrf @method('PUT')
<div class="form-grid">
<label>Nom<input name="name" value="{{ old('name', $user->name) }}" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</label>
<label>Email<input type="email" name="email" value="{{ old('email', $user->email) }}" required>@error('email')<small class="field-error">{{ $message }}</small>@enderror</label>
<label>Rôle<input value="{{ ['admin'=>'Administrateur','manager'=>'Gestionnaire','citizen'=>'Citoyen'][$user->role] ?? $user->role }}" disabled></label>
</div>
<div class="form-actions"><button class="button" type="submit">Enregistrer</button></div>
</form></section>
@endsection
