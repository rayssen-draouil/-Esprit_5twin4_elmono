<?php

namespace App\Services\Telegram;

use App\Models\Incident;
use App\Models\Signalement;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Bot Telegram AquaSecure — logique métier.
 *
 * Commandes : /start, /signaler, /mes_signalements, /statut, /aide (+ /annuler)
 * Les conversations en cours (association du compte, formulaire de
 * signalement) sont stockées en Cache avec une durée de vie limitée.
 */
class TelegramBotService
{
    /** Types de signalement — alignés sur le formulaire du site. */
    public const TYPES = [
        "Fuite d'eau",
        'Contamination',
        'Coupure liée à la sécheresse',
        'Équipement hors ligne',
        'Autre',
    ];

    /** Priorités — alignées sur le formulaire du site. */
    public const PRIORITIES = ['Faible', 'Moyenne', 'Élevée', 'Critique'];

    /** Commandes affichées par /aide et enregistrées via setMyCommands. */
    public const COMMANDS = [
        'start' => 'Accueil et association du compte',
        'signaler' => 'Créer un nouveau signalement',
        'mes_signalements' => 'Consulter vos signalements',
        'statut' => "Vérifier l'avancement d'un dossier",
        'aide' => 'Afficher les commandes disponibles',
        'annuler' => "Annuler l'action en cours",
    ];

    public function __construct(private readonly TelegramApi $api)
    {
    }

    /**
     * Traite une mise à jour Telegram (message ou callback de bouton).
     *
     * @param  array<string, mixed>  $update
     */
    public function handle(array $update): void
    {
        if (! empty($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);

            return;
        }

        $message = $update['message'] ?? null;
        if (! is_array($message)) {
            return;
        }

        $chatId = $message['chat']['id'] ?? null;
        if ($chatId === null) {
            return;
        }

        $text = trim((string) ($message['text'] ?? ''));

        if (str_starts_with($text, '/')) {
            [$command, $argument] = $this->parseCommand($text);
            $this->handleCommand($chatId, $command, $argument);

            return;
        }

        $this->handleConversation($chatId, $message, $text);
    }

    /**
     * @return array{0: string, 1: string} [commande sans « / », argument]
     */
    private function parseCommand(string $text): array
    {
        $parts = preg_split('/\s+/', $text, 2) ?: [$text];
        $command = ltrim($parts[0], '/');
        $command = strtolower(explode('@', $command)[0]);
        $argument = isset($parts[1]) ? trim($parts[1]) : '';

        return [$command, $argument];
    }

    private function handleCommand(int|string $chatId, string $command, string $argument): void
    {
        // Toute commande interrompt la conversation en cours.
        $this->clearState($chatId);

        match ($command) {
            'start' => $this->commandStart($chatId, $argument),
            'signaler' => $this->commandSignaler($chatId),
            'mes_signalements' => $this->commandMesSignalements($chatId),
            'statut' => $this->commandStatut($chatId, $argument),
            'aide', 'help' => $this->commandAide($chatId),
            'annuler', 'cancel' => $this->send($chatId, "✔️ Action annulée.\nTapez /aide pour voir les commandes disponibles."),
            default => $this->commandAide($chatId),
        };
    }

    /* ------------------------------------------------------------------ */
    /* Commandes                                                           */
    /* ------------------------------------------------------------------ */

    private function commandStart(int|string $chatId, string $argument = ''): void
    {
        $user = $this->linkedUser($chatId);

        if ($user) {
            $this->send($chatId, $this->welcomeText($user));

            return;
        }

        // Deep link : /start mon@email.com
        if (filled($argument) && filter_var($argument, FILTER_VALIDATE_EMAIL)) {
            if ($this->linkByEmail($chatId, $argument)) {
                return;
            }
        }

        $this->setState($chatId, 'await_email');

        $this->send($chatId,
            "👋 <b>Bienvenue sur AquaSecure !</b>\n\n".
            "Le service citoyen de signalement des incidents liés à l'eau : fuites, contaminations, coupures, équipements hors ligne…\n\n".
            "🔗 <b>Associez votre compte</b> pour créer et suivre vos signalements depuis Telegram.\n\n".
            "➡️ Envoyez l'<b>e-mail</b> utilisé pour votre compte AquaSecure."
        );
    }

    private function commandSignaler(int|string $chatId): void
    {
        if (! $this->requireAccount($chatId)) {
            return;
        }

        $this->setState($chatId, 'signaler_type');

        $rows = [];
        foreach (self::TYPES as $index => $type) {
            // Telegram attend un tableau de lignes : chaque bouton = une ligne.
            $rows[] = [['text' => $this->typeIcon($type).' '.$type, 'callback_data' => 'sgt:'.$index]];
        }

        $this->send($chatId,
            "🧭 <b>Nouveau signalement</b> — étape 1/4\n\n".
            "Quel type de problème souhaitez-vous signaler ?",
            ['reply_markup' => ['inline_keyboard' => $rows]]
        );
    }

    private function commandMesSignalements(int|string $chatId): void
    {
        $user = $this->requireAccount($chatId);
        if (! $user) {
            return;
        }

        $signalements = $user->signalements()->with('incident')->latest()->limit(10)->get();

        if ($signalements->isEmpty()) {
            $this->send($chatId,
                "🗂️ <b>Vos signalements</b>\n\n".
                "Vous n'avez encore signalé aucun problème.\n\n".
                "Créez-en un avec /signaler"
            );

            return;
        }

        $lines = ["🗂️ <b>Vos signalements</b> ({$signalements->count()} derniers)", ''];

        foreach ($signalements as $signalement) {
            $lines[] = "<b>{$this->signalementRef($signalement)}</b> — ".($signalement->type ?: 'Autre');
            $lines[] = '   📍 '.($signalement->location ?: 'Lieu non précisé');
            $lines[] = '   📌 '.$this->signalementStatusLabel($signalement->status);
            $lines[] = '   📅 '.($signalement->reported_at?->format('d/m/Y H:i') ?? '—');
            if ($signalement->incident_id) {
                $lines[] = '   🔗 Incident '.$this->incidentRef($signalement->incident_id);
            }
            $lines[] = '';
        }

        $lines[] = "ℹ️ Avancement détaillé : /statut {$this->signalementRef($signalements->first())}";

        $this->send($chatId, implode("\n", $lines));
    }

    private function commandStatut(int|string $chatId, string $argument = ''): void
    {
        if (! $this->requireAccount($chatId)) {
            return;
        }

        if (filled($argument)) {
            $this->showStatut($chatId, $argument);

            return;
        }

        $this->setState($chatId, 'await_statut_ref');

        $this->send($chatId,
            "🔎 <b>Vérification d'un dossier</b>\n\n".
            "Envoyez la référence du dossier, par exemple :\n".
            "• <code>SIG-0012</code> ou <code>12</code> (un de vos signalements)\n".
            "• <code>INC-0003</code> (un incident rattaché à vos signalements)"
        );
    }

    private function commandAide(int|string $chatId): void
    {
        $lines = ["🤖 <b>Commandes AquaSecure</b>", ''];

        foreach (self::COMMANDS as $command => $description) {
            $lines[] = "<b>/{$command}</b> — {$description}";
        }

        $lines[] = '';
        $lines[] = "ℹ️ Pendant un formulaire (/signaler), tapez /annuler pour l'interrompre.";

        $this->send($chatId, implode("\n", $lines));
    }

    /* ------------------------------------------------------------------ */
    /* Conversation : association du compte                                */
    /* ------------------------------------------------------------------ */

    private function handleConversation(int|string $chatId, array $message, string $text): void
    {
        $state = $this->state($chatId);

        if (! is_array($state) || ! isset($state['step'])) {
            $this->send($chatId,
                "🤖 Je n'ai pas compris ce message.\n\n".
                "Tapez /aide pour voir les commandes disponibles."
            );

            return;
        }

        $data = is_array($state['data'] ?? null) ? $state['data'] : [];

        match ($state['step']) {
            'await_email' => $this->stepAwaitEmail($chatId, $text),
            'signaler_description' => $this->stepDescription($chatId, $message, $text, $data),
            'signaler_location' => $this->stepLocation($chatId, $message, $text, $data),
            'await_statut_ref' => $this->stepStatutRef($chatId, $text),
            default => $this->clearState($chatId),
        };
    }

    private function stepAwaitEmail(int|string $chatId, string $text): void
    {
        if (! filter_var($text, FILTER_VALIDATE_EMAIL)) {
            $this->send($chatId, "✉️ Cet e-mail ne semble pas valide.\nExemple : <code>citoyen@aquasecure.tn</code>\n\nOu tapez /annuler.");

            return;
        }

        if (! $this->linkByEmail($chatId, $text)) {
            $this->send($chatId,
                "❌ Aucun compte AquaSecure trouvé avec l'adresse <b>{$this->esc($text)}</b>.\n\n".
                "Vérifiez l'adresse ou créez un compte sur le site, puis réessayez.\nOu tapez /annuler."
            );

            return;
        }
    }

    private function linkByEmail(int|string $chatId, string $email): bool
    {
        $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])->first();

        if (! $user) {
            return false;
        }

        $user->update([
            'telegram_chat_id' => (string) $chatId,
            'telegram_linked_at' => now(),
        ]);

        $this->clearState($chatId);

        $this->send($chatId, $this->welcomeText($user->refresh()));

        return true;
    }

    private function welcomeText(User $user): string
    {
        return
            "✅ <b>Compte associé</b>\n\n".
            "👋 Bonjour <b>{$this->esc($user->name)}</b> ({$this->esc($user->email)})\n".
            "Vous êtes connecté(e) au bot AquaSecure.\n\n".
            "📌 <b>Commandes disponibles</b>\n".
            "/signaler — Créer un nouveau signalement\n".
            "/mes_signalements — Consulter vos signalements\n".
            "/statut — Vérifier l'avancement d'un dossier\n".
            "/aide — Afficher les commandes disponibles";
    }

    /* ------------------------------------------------------------------ */
    /* Conversation : /signaler                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Étape 2/4 : description (et photo facultative).
     *
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>  $data
     */
    private function stepDescription(int|string $chatId, array $message, string $text, array $data): void
    {
        // Photo facultative — peut être envoyée avec ou sans légende.
        if (! empty($message['photo']) && is_array($message['photo'])) {
            $photoPath = $this->savePhoto($chatId, $message['photo']);
            $data['photo_path'] = $photoPath ?: ($data['photo_path'] ?? null);

            if ($photoPath === null) {
                $this->send($chatId, "⚠️ Impossible d'enregistrer la photo. Vous pouvez en envoyer une autre ou continuer sans.");

                return;
            }

            $this->setState($chatId, 'signaler_description', $data);
            $this->send($chatId, '📸 Photo enregistrée. Décrivez maintenant le problème en quelques mots.');

            return;
        }

        $caption = trim((string) ($message['caption'] ?? ''));
        $description = $text !== '' ? $text : $caption;

        if (mb_strlen($description) < 10) {
            $this->send($chatId,
                "📝 La description est trop courte (10 caractères minimum).\n\n".
                "Décrivez le problème : où, quand, quoi…\nOu tapez /annuler."
            );

            return;
        }

        $data['description'] = mb_substr($description, 0, 2000);
        $this->setState($chatId, 'signaler_location', $data);

        $this->send($chatId,
            "📍 <b>Nouveau signalement</b> — étape 3/4\n\n".
            "Indiquez la <b>localisation</b> du problème (adresse, commune, infrastructure…).\n".
            "Vous pouvez aussi envoyer votre position 📎 depuis Telegram."
        );
    }

    /**
     * Étape 3/4 : localisation.
     *
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>  $data
     */
    private function stepLocation(int|string $chatId, array $message, string $text, array $data): void
    {
        $location = $text;

        if (! empty($message['location']) && is_array($message['location'])) {
            $lat = round((float) ($message['location']['latitude'] ?? 0), 5);
            $lon = round((float) ($message['location']['longitude'] ?? 0), 5);
            $location = "{$lat}, {$lon}";
        }

        if (mb_strlen($location) < 3) {
            $this->send($chatId,
                "📍 Localisation trop courte.\n\n".
                "Exemple : <code>Avenue Habib Bourguiba, Tunis</code>\nOu tapez /annuler."
            );

            return;
        }

        $data['location'] = mb_substr($location, 0, 255);
        $this->setState($chatId, 'signaler_priority', $data);

        $rows = [];
        foreach (self::PRIORITIES as $index => $priority) {
            // Telegram attend un tableau de lignes : chaque bouton = une ligne.
            $rows[] = [['text' => $this->priorityIcon($priority).' '.$priority, 'callback_data' => 'sgp:'.$index]];
        }

        $this->send($chatId,
            "⚖️ <b>Nouveau signalement</b> — étape 4/4\n\n".
            "Quelle est la <b>priorité</b> de ce problème ?",
            ['reply_markup' => ['inline_keyboard' => $rows]]
        );
    }

    /**
     * Récapitulatif puis création en base.
     *
     * @param  array<string, mixed>  $data
     */
    private function createSignalement(int|string $chatId, User $user, array $data): void
    {
        $signalement = Signalement::create([
            'user_id' => $user->id,
            'reporter_name' => $user->name,
            'reporter_email' => $user->email,
            'type' => $data['type'] ?? 'Autre',
            'description' => $data['description'] ?? '',
            'location' => $data['location'] ?? null,
            'photo_path' => $data['photo_path'] ?? null,
            'priority' => $data['priority'] ?? null,
            'status' => 'new',
            'reported_at' => now(),
        ]);

        $reference = $this->signalementRef($signalement);

        $this->send($chatId,
            "✅ <b>Signalement enregistré !</b>\n\n".
            "📄 Référence : <b>{$reference}</b>\n".
            "📌 Statut : {$this->signalementStatusLabel($signalement->status)}\n\n".
            "Nos équipes vont vérifier votre signalement.\n\n".
            "🔎 Suivez son avancement : /statut {$reference}\n".
            "🗂️ Tous vos signalements : /mes_signalements"
        );
    }

    /* ------------------------------------------------------------------ */
    /* Boutons (callback queries)                                          */
    /* ------------------------------------------------------------------ */

    /**
     * @param  array<string, mixed>  $callback
     */
    private function handleCallback(array $callback): void
    {
        $callbackId = (string) ($callback['id'] ?? '');
        $chatId = $callback['message']['chat']['id'] ?? null;
        $data = (string) ($callback['data'] ?? '');

        try {
            $this->api->answerCallbackQuery($callbackId);
        } catch (TelegramException $exception) {
            Log::warning('Telegram answerCallbackQuery: '.$exception->getMessage());
        }

        if ($chatId === null || $data === '') {
            return;
        }

        [$prefix, $value] = array_pad(explode(':', $data, 2), 2, '');
        $state = $this->state($chatId);

        match ($prefix) {
            'sgt' => $this->callbackType($chatId, $state, $value),
            'sgp' => $this->callbackPriority($chatId, $state, $value),
            'sgc' => $this->callbackConfirm($chatId, $state, $value),
            default => null,
        };
    }

    private function callbackType(int|string $chatId, mixed $state, string $value): void
    {
        $type = self::TYPES[(int) $value] ?? null;

        if ($type === null || ($state['step'] ?? null) !== 'signaler_type') {
            $this->expired($chatId, 'signaler');

            return;
        }

        $data = is_array($state['data'] ?? null) ? $state['data'] : [];
        $data['type'] = $type;
        $this->setState($chatId, 'signaler_description', $data);

        $this->send($chatId,
            "📝 <b>Nouveau signalement</b> — étape 2/4\n\n".
            "Type retenu : <b>{$this->esc($type)}</b>\n\n".
            "Décrivez le problème (où, quand, quoi…).\n".
            "📎 Vous pouvez joindre une <b>photo</b> avec la description."
        );
    }

    private function callbackPriority(int|string $chatId, mixed $state, string $value): void
    {
        $priority = self::PRIORITIES[(int) $value] ?? null;

        if ($priority === null || ($state['step'] ?? null) !== 'signaler_priority') {
            $this->expired($chatId, 'signaler');

            return;
        }

        $data = is_array($state['data'] ?? null) ? $state['data'] : [];
        $data['priority'] = $priority;
        $this->setState($chatId, 'signaler_confirm', $data);

        $photo = ! empty($data['photo_path']) ? 'Oui 📎' : 'Non';

        $this->send($chatId,
            "✍️ <b>Récapitulatif</b>\n\n".
            "<b>Type :</b> {$this->esc($data['type'] ?? 'Autre')}\n".
            "<b>Description :</b> {$this->esc($data['description'] ?? '')}\n".
            "<b>Lieu :</b> {$this->esc($data['location'] ?? '—')}\n".
            "<b>Priorité :</b> {$this->esc($priority)}\n".
            "<b>Photo :</b> {$photo}\n\n".
            "Confirmez-vous l'envoi ?",
            ['reply_markup' => ['inline_keyboard' => [[
                ['text' => '✅ Confirmer', 'callback_data' => 'sgc:1'],
                ['text' => '❌ Annuler', 'callback_data' => 'sgc:0'],
            ]]]]
        );
    }

    private function callbackConfirm(int|string $chatId, mixed $state, string $value): void
    {
        if (($state['step'] ?? null) !== 'signaler_confirm') {
            $this->expired($chatId, 'signaler');

            return;
        }

        $this->clearState($chatId);

        if ($value !== '1') {
            $this->send($chatId, "❌ Signalement annulé.\nRelancez-en un avec /signaler.");

            return;
        }

        $user = $this->linkedUser($chatId);
        if (! $user) {
            $this->promptLink($chatId);

            return;
        }

        $this->createSignalement($chatId, $user, is_array($state['data'] ?? null) ? $state['data'] : []);
    }

    /* ------------------------------------------------------------------ */
    /* Conversation : /statut                                              */
    /* ------------------------------------------------------------------ */

    private function stepStatutRef(int|string $chatId, string $text): void
    {
        $this->clearState($chatId);
        $this->showStatut($chatId, $text);
    }

    private function showStatut(int|string $chatId, string $reference): void
    {
        $user = $this->linkedUser($chatId);
        if (! $user) {
            $this->promptLink($chatId);

            return;
        }

        $reference = strtoupper(str_replace(' ', '', $reference));

        // Référence incident → INC-0001
        if (preg_match('/^(?:INC|INCIDENT)-?(\d+)$/', $reference, $matches)) {
            $this->showIncidentStatut($chatId, $user, (int) $matches[1]);

            return;
        }

        // Référence signalement → SIG-0001 ou identifiant brut
        if (preg_match('/^(?:SIG|SGL|SIGNALEMENT)-?(\d+)$/', $reference, $matches) || ctype_digit($reference)) {
            $this->showSignalementStatut($chatId, $user, (int) ($matches[1] ?? $reference));

            return;
        }

        $this->send($chatId,
            "❌ Référence <b>{$this->esc($reference)}</b> non reconnue.\n\n".
            "Formats acceptés : <code>SIG-0012</code>, <code>12</code> ou <code>INC-0003</code>."
        );
    }

    private function showSignalementStatut(int|string $chatId, User $user, int $signalementId): void
    {
        $signalement = Signalement::with('incident')->where('user_id', $user->id)->find($signalementId);

        if (! $signalement) {
            $this->send($chatId,
                "❌ Aucun signalement <b>SIG-".str_pad((string) $signalementId, 4, '0', STR_PAD_LEFT)."</b> ".
                "n'existe pour votre compte.\n\nConsultez /mes_signalements"
            );

            return;
        }

        $lines = [
            "📄 <b>Dossier {$this->signalementRef($signalement)}</b>",
            '',
            '<b>Type :</b> '.($signalement->type ?: 'Autre'),
            '<b>Lieu :</b> '.($signalement->location ?: 'Non précisé'),
            '<b>Priorité :</b> '.($signalement->priority ?: 'Non précisée'),
            '<b>Statut :</b> '.$this->signalementStatusLabel($signalement->status),
            '<b>Signalé le :</b> '.$signalement->reported_at?->format('d/m/Y H:i'),
        ];

        if ($signalement->incident_id && $signalement->incident) {
            $lines[] = '<b>Incident rattaché :</b> '.$this->incidentRef($signalement->incident->id).' — '.$this->incidentStatusLabel($signalement->incident->status);
        } else {
            $lines[] = '<b>Incident rattaché :</b> ⏳ En attente de vérification par nos équipes';
        }

        if ($signalement->incident?->resolved_at) {
            $lines[] = '<b>Résolu le :</b> '.$signalement->incident->resolved_at->format('d/m/Y H:i');
        }

        $this->send($chatId, implode("\n", $lines));
    }

    private function showIncidentStatut(int|string $chatId, User $user, int $incidentId): void
    {
        $incident = Incident::with('signalements')
            ->whereHas('signalements', fn ($query) => $query->where('user_id', $user->id))
            ->find($incidentId);

        if (! $incident) {
            $this->send($chatId,
                "❌ Aucun incident <b>INC-".str_pad((string) $incidentId, 4, '0', STR_PAD_LEFT)."</b> ".
                "n'est rattaché à vos signalements.\n\nConsultez /mes_signalements"
            );

            return;
        }

        $lines = [
            "🚧 <b>Dossier {$this->incidentRef($incident->id)}</b>",
            '',
            '<b>Type :</b> '.($incident->type ?: 'Incident'),
            '<b>Lieu :</b> '.($incident->location ?: 'Non précisé'),
            '<b>Gravité :</b> '.$this->severityLabel($incident->severity),
            '<b>Statut :</b> '.$this->incidentStatusLabel($incident->status),
            '<b>Signalé le :</b> '.$incident->reported_at?->format('d/m/Y H:i'),
        ];

        if ($incident->resolved_at) {
            $lines[] = '<b>Résolu le :</b> '.$incident->resolved_at->format('d/m/Y H:i');
        }

        $lines[] = '';
        $lines[] = '📊 Étapes : 🔴 Ouvert → 🔄 En cours → ✅ Résolu';

        $this->send($chatId, implode("\n", $lines));
    }

    /* ------------------------------------------------------------------ */
    /* Utilitaires                                                          */
    /* ------------------------------------------------------------------ */

    private function linkedUser(int|string $chatId): ?User
    {
        return User::where('telegram_chat_id', (string) $chatId)->first();
    }

    private function requireAccount(int|string $chatId): ?User
    {
        $user = $this->linkedUser($chatId);

        if (! $user) {
            $this->promptLink($chatId);

            return null;
        }

        return $user;
    }

    private function promptLink(int|string $chatId): void
    {
        $this->send($chatId,
            "🔒 <b>Compte non associé</b>\n\n".
            "Associez d'abord votre compte AquaSecure avec /start"
        );
    }

    private function expired(int|string $chatId, string $command): void
    {
        $this->send($chatId,
            "⏰ Session expirée ou formulaire déjà validé.\n\n".
            "Relancez : /{$command}"
        );
    }

    /**
     * @param  array<string, mixed>|null  $options
     */
    private function send(int|string $chatId, string $text, array $options = []): void
    {
        try {
            $this->api->sendMessage($chatId, $text, $options);
        } catch (TelegramException $exception) {
            Log::error('Telegram sendMessage: '.$exception->getMessage());
        }
    }

    /** Télécharge et stocke une photo du message (retourne le chemin ou null). */
    private function savePhoto(int|string $chatId, array $photos): ?string
    {
        try {
            $photo = end($photos) ?: null; // dernière = meilleure résolution
            $fileId = is_array($photo) ? ($photo['file_id'] ?? null) : null;

            if (! $fileId) {
                return null;
            }

            $file = $this->api->getFile($fileId);
            $filePath = $file['file_path'] ?? null;

            if (! $filePath) {
                return null;
            }

            $content = $this->api->download($filePath);
            $safeChat = preg_replace('/[^0-9A-Za-z_-]/', '', (string) $chatId);
            $destination = "signalements/telegram_{$safeChat}_".time().'_'.substr(md5($content), 0, 6).'.jpg';

            Storage::disk('public')->put($destination, $content);

            return $destination;
        } catch (TelegramException $exception) {
            Log::warning('Telegram savePhoto: '.$exception->getMessage());

            return null;
        }
    }

    /* --- État de conversation (Cache) ---------------------------------- */

    /**
     * @return array{step: string, data: array<string, mixed>, expires_at: int}|null
     */
    private function state(int|string $chatId): ?array
    {
        $state = Cache::get($this->stateKey($chatId));

        return is_array($state) ? $state : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function setState(int|string $chatId, string $step, array $data = []): void
    {
        Cache::put($this->stateKey($chatId), [
            'step' => $step,
            'data' => $data,
            'expires_at' => time() + (int) config('telegram.state_ttl', 1800),
        ], (int) config('telegram.state_ttl', 1800));
    }

    private function clearState(int|string $chatId): void
    {
        Cache::forget($this->stateKey($chatId));
    }

    private function stateKey(int|string $chatId): string
    {
        return 'telegram:state:'.$chatId;
    }

    /* --- Formats -------------------------------------------------------- */

    private function signalementRef(Signalement $signalement): string
    {
        return 'SIG-'.str_pad((string) $signalement->id, 4, '0', STR_PAD_LEFT);
    }

    private function incidentRef(int $incidentId): string
    {
        return 'INC-'.str_pad((string) $incidentId, 4, '0', STR_PAD_LEFT);
    }

    private function signalementStatusLabel(?string $status): string
    {
        return match ($status) {
            'new', 'received' => '🆕 Nouveau',
            'reviewed', 'under_review' => '🔍 En vérification',
            'in_progress' => '🔄 En cours',
            'resolved' => '✅ Résolu',
            'closed' => '🛑 Fermé',
            default => '• '.ucfirst((string) $status),
        };
    }

    private function incidentStatusLabel(?string $status): string
    {
        return match ($status) {
            'reported' => '🔴 Ouvert',
            'in_progress' => '🔄 En cours',
            'resolved' => '✅ Résolu',
            'closed' => '🛑 Fermé',
            default => $status ? '• '.ucfirst($status) : '—',
        };
    }

    private function severityLabel(?string $severity): string
    {
        return match ($severity) {
            'low' => 'Faible',
            'medium' => 'Moyenne',
            'high' => 'Élevée',
            'critical' => 'Critique',
            default => $severity ? ucfirst($severity) : '—',
        };
    }

    private function typeIcon(string $type): string
    {
        return match ($type) {
            "Fuite d'eau" => '🚰',
            'Contamination' => '☣️',
            'Coupure liée à la sécheresse' => '☀️',
            'Équipement hors ligne' => '⚙️',
            default => '❓',
        };
    }

    private function priorityIcon(string $priority): string
    {
        return match ($priority) {
            'Faible' => '🟢',
            'Moyenne' => '🟡',
            'Élevée' => '🟠',
            'Critique' => '🔴',
            default => '•',
        };
    }

    private function esc(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
