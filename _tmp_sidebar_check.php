<?php

use App\Models\User;
use Illuminate\Http\Request;

$user = User::where('role', 'admin')->first();
echo 'Utilisateur: '.$user?->name.' role='.$user?->role.PHP_EOL;

$kernel = app(Illuminate\Contracts\Http\Kernel::class);
$request = Request::create('/back', 'GET');
$request->setUserResolver(fn () => $user);
auth()->setUser($user);

$response = $kernel->handle($request);
$html = $response->getContent();

echo 'Status: '.$response->getStatusCode().PHP_EOL;

foreach (['Alertes', 'Zones', 'Incidents', 'Infrastructures'] as $item) {
    echo 'Sidebar contient « '.$item.' » : '.(str_contains($html, $item) ? 'OUI' : 'NON').PHP_EOL;
}

echo PHP_EOL.'Lien back/alerts présent : '.(str_contains($html, '/back/alerts') ? 'OUI' : 'NON').PHP_EOL;
echo 'Lien back/zones présent : '.(str_contains($html, '/back/zones') ? 'OUI' : 'NON').PHP_EOL;
