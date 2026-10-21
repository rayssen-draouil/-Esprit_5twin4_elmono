<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vérification de votre adresse e-mail</title>
</head>
<body style="margin:0;background:#f4f7f8;color:#17353a;font-family:Arial,sans-serif">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 12px">
        <tr><td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#fff;border-radius:12px;padding:32px">
                <tr><td>
                    <h1 style="margin:0 0 20px;font-size:24px">Vérifiez votre adresse e-mail</h1>
                    <p>Bonjour {{ $user->name }},</p>
                    <p>Merci d’avoir créé un compte AquaSecure. Confirmez votre adresse pour activer votre compte.</p>
                    <p style="margin:28px 0">
                        <a href="{{ $verificationUrl }}" style="display:inline-block;padding:13px 20px;background:#087f8c;color:#fff;text-decoration:none;border-radius:7px;font-weight:bold">Vérifier mon adresse</a>
                    </p>
                    <p>Ce lien sécurisé expirera dans 60 minutes.</p>
                    <p style="color:#60777b;font-size:13px;word-break:break-all">Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>{{ $verificationUrl }}</p>
                    <p style="margin-top:28px">L’équipe AquaSecure</p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
