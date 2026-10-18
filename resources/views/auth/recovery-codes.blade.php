@extends('layouts.auth', ['title' => 'Codes de récupération', 'heading' => 'Codes de récupération', 'subtitle' => 'Chaque code ne peut être utilisé qu’une seule fois.'])
@section('content')
@if(isset($codes))
<p>Conservez ces codes dans un endroit sûr. Ils ne seront pas affichés à nouveau sous cette forme.</p>
<pre style="background:#f4f7f6;padding:16px;overflow:auto">{{ implode("\n", $codes) }}</pre>
<form method="POST" action="{{ route('two-factor.recovery-codes.regenerate') }}">
    @csrf
    <label>Mot de passe actuel<input type="password" name="password" required></label>
    <button class="button button-ghost" type="submit">Régénérer les codes</button>
</form>
@else
<form method="POST" action="{{ route('two-factor.recovery-codes.show') }}" class="contact-form">
    @csrf
    <label>Mot de passe actuel<input type="password" name="password" required autofocus></label>
    <button class="button" type="submit">Afficher mes codes</button>
</form>
@endif
<a class="button" href="{{ route('profile.edit') }}">Retour au profil</a>
@endsection
