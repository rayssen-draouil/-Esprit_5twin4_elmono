<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nouvelle intervention assignée</title>
</head>
<body style="margin:0;padding:24px;background:#f3f8f7;font-family:Arial,sans-serif;color:#173c43">
    <div style="max-width:620px;margin:0 auto;padding:28px;background:#fff;border:1px solid #e1ece9;border-radius:12px">
        <p style="margin:0 0 8px;color:#0d9488;font-size:12px;font-weight:700;letter-spacing:1px">AQUASECURE · INTERVENTION</p>
        <h1 style="margin:0 0 16px;color:#073f4a;font-size:24px">Nouvelle intervention assignée</h1>
        <p>Bonjour {{ $technician->name }},</p>
        <p>Une intervention vient de vous être attribuée. Voici les informations principales :</p>

        <table role="presentation" style="width:100%;border-collapse:collapse;margin:20px 0">
            <tr>
                <td style="padding:10px 0;color:#708388;border-bottom:1px solid #edf2f1">Incident</td>
                <td style="padding:10px 0;color:#173c43;font-weight:700;border-bottom:1px solid #edf2f1">
                    {{ $intervention->incident?->type ?? 'Incident' }}
                </td>
            </tr>
            <tr>
                <td style="padding:10px 0;color:#708388;border-bottom:1px solid #edf2f1">Lieu</td>
                <td style="padding:10px 0;color:#173c43;border-bottom:1px solid #edf2f1">
                    {{ $intervention->incident?->location ?: 'Non renseigné' }}
                </td>
            </tr>
            <tr>
                <td style="padding:10px 0;color:#708388;border-bottom:1px solid #edf2f1">Date prévue</td>
                <td style="padding:10px 0;color:#173c43;border-bottom:1px solid #edf2f1">
                    {{ $intervention->scheduled_at?->format('d/m/Y à H:i') ?? 'Non définie' }}
                </td>
            </tr>
            <tr>
                <td style="padding:10px 0;color:#708388;border-bottom:1px solid #edf2f1">Durée prévue</td>
                <td style="padding:10px 0;color:#173c43;border-bottom:1px solid #edf2f1">
                    {{ $intervention->scheduled_duration_minutes ?: 60 }} minutes
                </td>
            </tr>
            <tr>
                <td style="padding:10px 0;color:#708388">Équipe</td>
                <td style="padding:10px 0;color:#173c43">{{ $intervention->team }}</td>
            </tr>
        </table>

        <p style="margin:24px 0">
            <a href="{{ $interventionUrl }}" style="display:inline-block;padding:12px 18px;border-radius:7px;background:#0d6970;color:#fff;text-decoration:none;font-weight:700">
                Consulter l’intervention
            </a>
        </p>
        <p style="margin-bottom:0;color:#708388;font-size:12px">Connectez-vous à AquaSecure pour consulter les détails et répondre à l’affectation.</p>
    </div>
</body>
</html>
