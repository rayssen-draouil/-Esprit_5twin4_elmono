@extends('layouts.auth', ['title' => 'Double authentification', 'heading' => 'Code de sécurité', 'subtitle' => 'Saisissez le code de votre application ou un code de récupération.'])
@section('content')
<form method="POST" action="{{ route('two-factor.login.store') }}" class="contact-form">
    @csrf
    <label>Code<input name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus></label>
    <button class="button" type="submit">Vérifier</button>
</form>
@endsection
