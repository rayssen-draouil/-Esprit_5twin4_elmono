@extends('layouts.auth', ['title' => 'Inscription', 'heading' => 'Créer un compte', 'subtitle' => 'Rejoignez la communauté AquaSecure.'])
@section('content')
<form method="POST" action="{{ route('register.store') }}" class="contact-form">@csrf
<label>Nom<input type="text" name="name" value="{{ old('name') }}" required></label>
<label>Email<input type="email" name="email" value="{{ old('email') }}" required></label>
<label>Mot de passe<input type="password" name="password" required></label>
<label>Confirmer le mot de passe<input type="password" name="password_confirmation" required></label>
<button class="button" type="submit">Créer mon compte</button></form><p style="margin-bottom:0">Déjà inscrit ? <a class="text-link" href="{{ route('login') }}">Se connecter</a></p>
@endsection
