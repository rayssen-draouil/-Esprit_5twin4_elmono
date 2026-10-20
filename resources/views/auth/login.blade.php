@extends('layouts.auth', ['title' => 'Connexion', 'heading' => 'Se connecter', 'subtitle' => 'Accédez à votre espace AquaSecure.'])
@section('content')
<form method="POST" action="{{ route('login.store') }}" class="contact-form">@csrf
@if(session('success'))<div class="flash-message flash-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="flash-message flash-error">{{ $errors->first() }}</div>@endif
<label>Email<input type="email" name="email" value="{{ old('email') }}" required autofocus></label>
<label>Mot de passe<input type="password" name="password" required></label>
<label style="display:flex;gap:8px;align-items:center;font-weight:400"><input type="checkbox" name="remember" value="1"> Se souvenir de moi</label>
<button class="button" type="submit">Se connecter</button></form><p style="margin-bottom:0">Pas encore de compte ? <a class="text-link" href="{{ route('register') }}">Créer un compte</a></p>
@endsection
