@extends('layouts.auth', ['title' => 'Nouveau mot de passe', 'heading' => 'Créer un nouveau mot de passe', 'subtitle' => 'Choisissez un mot de passe difficile à deviner.'])
@section('content')
<form method="POST" action="{{ route('password.update') }}" class="contact-form">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <label>Adresse e-mail<input type="email" name="email" value="{{ old('email', $email) }}" required></label>
    <label>Nouveau mot de passe<input type="password" name="password" required></label>
    <label>Confirmation<input type="password" name="password_confirmation" required></label>
    <button class="button" type="submit">Réinitialiser</button>
</form>
@endsection
