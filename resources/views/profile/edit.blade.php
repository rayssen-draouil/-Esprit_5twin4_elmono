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
<section class="panel form-panel"><span class="eyebrow">SÉCURITÉ</span><h2>Mot de passe</h2>
<form method="POST" action="{{ route('profile.password.update') }}">
@csrf @method('PUT')
<div class="form-grid">
<label>Mot de passe actuel<input type="password" name="current_password" required></label>
<label>Nouveau mot de passe<input type="password" name="password" required></label>
<label>Confirmation<input type="password" name="password_confirmation" required></label>
</div>
<div class="form-actions"><button class="button" type="submit">Modifier le mot de passe</button></div>
</form></section>
<section class="panel form-panel"><span class="eyebrow">PHOTO</span><h2>Photo de profil</h2>
@if($user->profile_photo_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($user->profile_photo_path) }}" alt="Photo de profil" style="width:80px;height:80px;border-radius:50%;object-fit:cover">@endif
<form method="POST" action="{{ route('profile.photo.update') }}" enctype="multipart/form-data">
@csrf
<label>Choisir une image<input type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp" required></label>
<div class="form-actions"><button class="button" type="submit">Téléverser</button></div>
</form></section>
<section class="panel form-panel"><span class="eyebrow">DOUBLE AUTHENTIFICATION</span><h2>Protéger le compte</h2>
@if($user->two_factor_secret && $user->two_factor_confirmed_at)
    <p>La double authentification est active.</p>
    <a class="button button-ghost" href="{{ route('two-factor.recovery-codes') }}">Afficher les codes de récupération</a>
    <form method="POST" action="{{ route('two-factor.disable') }}" style="margin-top:16px">
        @csrf
        <label>Mot de passe actuel<input type="password" name="password" required></label>
        <label>Code actuel<input name="code" inputmode="numeric" required></label>
        <button class="button button-danger" type="submit">Désactiver la 2FA</button>
    </form>
@elseif($user->two_factor_secret && ! $user->two_factor_confirmed_at)
    <p>Scannez ce QR code avec votre application puis confirmez avec un code.</p>
    <div>{!! $user->twoFactorQrCodeSvg() !!}</div>
    <form method="POST" action="{{ route('two-factor.confirm') }}">
        @csrf
        <label>Code de confirmation<input name="code" inputmode="numeric" required></label>
        <button class="button" type="submit">Confirmer l’activation</button>
    </form>
@else
    <p>Utilisez Google Authenticator, Microsoft Authenticator ou une application TOTP compatible.</p>
    <form method="POST" action="{{ route('two-factor.enable') }}">@csrf<button class="button" type="submit">Configurer la 2FA</button></form>
@endif
</section>
<section class="panel form-panel"><span class="eyebrow">ZONE DANGEREUSE</span><h2>Supprimer le compte</h2>
<form method="POST" action="{{ route('profile.destroy') }}" onsubmit="return confirm('Confirmer la suppression définitive de votre compte ?')">
@csrf @method('DELETE')
<label>Mot de passe actuel<input type="password" name="password" required></label>
<button class="button button-danger" type="submit">Supprimer mon compte</button>
</form></section>
@endsection
