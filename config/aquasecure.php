<?php

/**
 * Réglages publics du site citoyen AquaSecure.
 *
 * Toutes les informations affichées sur les pages publiques (contacts,
 * conseils de sécurité, FAQ) sont centralisées ici : aucun numéro ni
 * contenu éditorial n'est codé en dur dans les vues.
 */
return [

    'cache_ttl' => (int) env('AQUASECURE_CACHE_TTL', 120),

    'contacts' => [
        'service' => [
            'label' => 'Service des eaux AquaSecure',
            'phone' => env('AQUASECURE_WATER_PHONE', '+33 (0)4 67 00 00 00'),
            'email' => env('AQUASECURE_WATER_EMAIL', 'bonjour@aquasecure.fr'),
            'hours' => 'Du lundi au vendredi, 8 h – 18 h',
            'address' => env('AQUASECURE_WATER_ADDRESS', '12 quai des Lumières, 34000 Montpellier'),
        ],
        'emergency' => [
            ['label' => 'Urgences européennes', 'phone' => '112', 'note' => 'Depuis n’importe quel téléphone'],
            ['label' => 'SAMU', 'phone' => '15', 'note' => 'Urgence médicale'],
            ['label' => 'Police / Gendarmerie', 'phone' => '17', 'note' => 'Danger immédiat'],
            ['label' => 'Pompiers', 'phone' => '18', 'note' => 'Fuite, inondation, risque électrique'],
        ],
    ],

    'safety_tips' => [
        'low' => [
            'Aucune mesure particulière : la situation est normale.',
            'Signalez toute odeur ou couleur inhabituelle de l’eau.',
            'Pensez à fermer votre robinet si vous quittez votre logement.',
        ],
        'medium' => [
            'Restez attentif aux annonces de votre commune.',
            'Évitez de consommer l’eau du robinet en cas de changement d’aspect ou d’odeur.',
            'Préparez une petite réserve d’eau potable (2 litres par personne et par jour).',
        ],
        'high' => [
            'Limitez l’usage de l’eau du robinet pour la boisson et la cuisine.',
            'Utilisez de l’eau en bouteille jusqu’à nouvel ordre.',
            'Ne consommez pas l’eau si elle est trouble ou colorée.',
        ],
        'critical' => [
            'N’utilisez pas l’eau du robinet pour boire, cuisiner ou vous laver les dents.',
            'Suivez les consignes officielles de votre commune.',
            'En cas de doute ou de symptôme, contactez votre médecin ou le 15.',
        ],
    ],

    'faq' => [
        [
            'question' => 'Comment savoir si l’eau de ma zone est sûre ?',
            'answer' => 'Chaque zone affiche un niveau de risque mis à jour par nos équipes. Un niveau « Faible » signifie que la situation est normale. En cas de doute, privilégiez l’eau en bouteille et suivez les consignes de votre commune.',
        ],
        [
            'question' => 'Que signifient les niveaux de risque ?',
            'answer' => 'Faible : situation normale. Moyen : vigilance, quelques perturbations possibles. Élevé : restrictions d’usage recommandées. Critique : ne pas consommer l’eau et suivre les consignes officielles.',
        ],
        [
            'question' => 'Que faire si je remarque une fuite ou une eau colorée ?',
            'answer' => 'Signalez-le immédiatement via le bouton « Signaler un problème ». Décrivez le lieu et l’aspect de l’eau. Ne touchez pas aux équipements et éloignez-vous en cas de danger électrique.',
        ],
        [
            'question' => 'Comment suivre mon signalement ?',
            'answer' => 'Conservez la référence affichée après l’envoi (ou fournie par le bot Telegram) et saisissez-la dans la page « Suivre mon signalement ». Vous verrez l’avancement de votre dossier.',
        ],
        [
            'question' => 'Mes données personnelles sont-elles publiées ?',
            'answer' => 'Non. Les pages publiques n’affichent que des informations sur les zones et les alertes. Votre nom, votre e-mail et votre téléphone ne sont jamais visibles publiquement.',
        ],
    ],

    'map' => [
        'center' => [
            'lat' => (float) env('AQUASECURE_MAP_LAT', 46.2276),
            'lng' => (float) env('AQUASECURE_MAP_LNG', 2.2137),
        ],
        'zoom' => (int) env('AQUASECURE_MAP_ZOOM', 6),
    ],

    /*
     * Notifications Telegram des zones suivies (alertes critiques uniquement).
     * Désactivé par défaut : à activer explicitement en production.
     */
    'telegram_notify' => (bool) env('AQUASECURE_TELEGRAM_NOTIFY', false),

];
