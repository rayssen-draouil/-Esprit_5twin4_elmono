@extends('layouts.auth', ['title' => 'Vérifier l’e-mail', 'heading' => 'Vérifiez votre adresse e-mail', 'subtitle' => 'Un lien de vérification vous a été envoyé.'])
@section('content')
<p>Consultez votre boîte de réception et cliquez sur le lien reçu pour continuer.</p>
<form method="POST" action="{{ route('verification.send') }}" class="contact-form">
    @csrf
    <button class="button" type="submit">Renvoyer le lien</button>
</form>
<form method="POST" action="{{ route('logout') }}">
    @csrf
    <button class="button button-ghost" type="submit">Se déconnecter</button>
</form>
@endsection
