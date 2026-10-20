@extends('layouts.auth', ['title' => 'Mot de passe oublié', 'heading' => 'Réinitialiser le mot de passe', 'subtitle' => 'Recevez un lien sécurisé par e-mail.'])
@section('content')
<form method="POST" action="{{ route('password.email') }}" class="contact-form">
    @csrf
    <label>Adresse e-mail<input type="email" name="email" value="{{ old('email') }}" required autofocus></label>
    <button class="button" type="submit">Envoyer le lien</button>
</form>
<p style="margin-bottom:0"><a class="text-link" href="{{ route('login') }}">Retour à la connexion</a></p>
@endsection
