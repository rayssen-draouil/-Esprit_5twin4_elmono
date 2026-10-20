<?php

namespace App\Services;

use App\Models\Maintenance;
use App\Models\MaintenanceAssignmentInvitation;
use App\Models\Intervention;
use App\Models\Technicien;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BrevoEmailService
{
    protected ?string $apiKey;
    protected string $senderName;
    protected string $senderEmail;
    protected int $timeout;
    protected string $apiUrl = 'https://api.brevo.com/v3/smtp/email';
    protected ?string $smtpHost;
    protected ?int $smtpPort;
    protected ?string $smtpUser;
    protected ?string $smtpPass;

    public function __construct()
    {
        $this->apiKey = config('services.brevo.api_key');
        $this->senderName = config('services.brevo.sender_name', config('mail.from.name', 'AquaSecure'));
        $this->senderEmail = config('services.brevo.sender_email', config('mail.from.address', 'miladiadem58@gmail.com'));
        $this->timeout = (int) config('services.brevo.timeout', 15);
        $this->smtpHost = config('services.brevo.smtp_host', config('mail.mailers.smtp.host'));
        $this->smtpPort = (int) config('services.brevo.smtp_port', config('mail.mailers.smtp.port', 587));
        $this->smtpUser = config('services.brevo.smtp_user', config('mail.mailers.smtp.username'));
        $this->smtpPass = config('services.brevo.smtp_pass', config('mail.mailers.smtp.password'));
    }

    /**
     * Send an assignment invitation to the technician.
     *
     * @return array{success: bool, message_id: ?string, error: ?string}
     */
    public function sendAssignmentInvitation(MaintenanceAssignmentInvitation $invitation, string $rawToken): array
    {
        $technician = $invitation->technician;
        $maintenance = $invitation->maintenance;
        $infra = $maintenance->infrastructure;

        if (empty($technician?->email) || !filter_var($technician->email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => "Adresse email du technicien invalide ou manquante.",
            ];
        }

        $invitationUrl = route('maintenances.invitation.show', ['token' => $rawToken]);
        $subject = "Nouvelle intervention à accepter — {$maintenance->reference_code}";

        $priorityLabel = match ($maintenance->priority) {
            'critical' => 'Critique',
            'high' => 'Haute',
            'medium' => 'Moyenne',
            'low' => 'Basse',
            default => ucfirst((string) $maintenance->priority),
        };

        $scheduledDate = $maintenance->scheduled_at
            ? $maintenance->scheduled_at->format('d/m/Y à H:i')
            : 'Non définie';

        $html = $this->buildInvitationHtml(
            technicianName: $technician->name,
            referenceCode: $maintenance->reference_code ?? ('MNT-#' . $maintenance->id),
            infraName: $infra?->name ?? 'Infrastructure non spécifiée',
            infraType: $infra?->type ?? 'Réseau hydraulique',
            zoneName: $infra?->zone?->name ?? 'Zone d\'intervention',
            maintenanceType: $maintenance->type ?? 'Corrective',
            priorityLabel: $priorityLabel,
            scheduledDate: $scheduledDate,
            description: $maintenance->description ?: 'Aucune consigne spécifique transmise.',
            actionUrl: $invitationUrl,
            expiresAt: $invitation->expires_at?->format('d/m/Y à H:i')
        );

        return $this->sendTransactionalEmail(
            recipientEmail: $technician->email,
            recipientName: $technician->name,
            subject: $subject,
            htmlContent: $html
        );
    }

    /**
     * Notify a technician when an intervention is assigned to them.
     *
     * @return array{success: bool, message_id: ?string, error: ?string}
     */
    public function sendInterventionAssignmentNotification(Intervention $intervention, Technicien $technician): array
    {
        if (empty($technician->email) || ! filter_var($technician->email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'Adresse email du technicien invalide ou manquante.',
            ];
        }

        $intervention->loadMissing('incident');
        $subject = "Nouvelle intervention assignée — #{$intervention->id}";
        $html = view('emails.interventions.assigned', [
            'technician' => $technician,
            'intervention' => $intervention,
            'interventionUrl' => route('interventions.show', $intervention),
        ])->render();

        return $this->sendTransactionalEmail(
            recipientEmail: $technician->email,
            recipientName: $technician->name,
            subject: $subject,
            htmlContent: $html,
        );
    }

    /**
     * Send a notification to the technician confirming that their completion was validated and closed.
     *
     * @return array{success: bool, message_id: ?string, error: ?string}
     */
    public function sendClosureNotification(Maintenance $maintenance, Technicien $technician): array
    {
        if (empty($technician->email) || !filter_var($technician->email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => "Adresse email du technicien invalide.",
            ];
        }

        $subject = "Intervention validée et clôturée — {$maintenance->reference_code}";

        $html = $this->buildClosureHtml(
            technicianName: $technician->name,
            referenceCode: $maintenance->reference_code ?? ('MNT-#' . $maintenance->id),
            infraName: $maintenance->infrastructure?->name ?? 'Infrastructure',
            closedAt: ($maintenance->completion_validated_at ?? now())->format('d/m/Y à H:i'),
            managerNotes: $maintenance->manager_validation_notes
        );

        return $this->sendTransactionalEmail(
            recipientEmail: $technician->email,
            recipientName: $technician->name,
            subject: $subject,
            htmlContent: $html
        );
    }

    /**
     * Send a notification when corrections are requested by the manager.
     *
     * @return array{success: bool, message_id: ?string, error: ?string}
     */
    public function sendCorrectionsRequestedNotification(Maintenance $maintenance, Technicien $technician, string $notes): array
    {
        if (empty($technician->email) || !filter_var($technician->email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => "Adresse email du technicien invalide.",
            ];
        }

        $subject = "Compléments demandés sur l'intervention — {$maintenance->reference_code}";

        $html = $this->buildCorrectionsHtml(
            technicianName: $technician->name,
            referenceCode: $maintenance->reference_code ?? ('MNT-#' . $maintenance->id),
            notes: $notes
        );

        return $this->sendTransactionalEmail(
            recipientEmail: $technician->email,
            recipientName: $technician->name,
            subject: $subject,
            htmlContent: $html
        );
    }

    /**
     * Core Brevo transactional dispatch: uses Brevo SMTP relay first, falls back to REST API if needed.
     *
     * @return array{success: bool, message_id: ?string, error: ?string}
     */
    public function sendTransactionalEmail(string $recipientEmail, string $recipientName, string $subject, string $htmlContent): array
    {
        $driver = config('services.brevo.driver', 'smtp');

        // 1. Primary: Send via SMTP (Brevo Relay / configured mailer)
        if ($driver !== 'api' && $this->isSmtpConfigured()) {
            try {
                $mailer = Mail::mailer('smtp');

                $sentMessage = $mailer->html($htmlContent, function ($message) use ($recipientEmail, $recipientName, $subject) {
                    $message->to($recipientEmail, $recipientName)
                        ->from($this->senderEmail, $this->senderName)
                        ->subject($subject);
                });

                $messageId = $sentMessage?->getMessageId() ?? ('brevo-smtp-' . uniqid('', true));

                Log::info('Brevo SMTP email dispatched successfully', [
                    'recipient' => $this->maskEmail($recipientEmail),
                    'subject' => $subject,
                    'messageId' => $messageId,
                ]);

                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'error' => null,
                ];
            } catch (\Throwable $e) {
                Log::warning('Brevo SMTP dispatch failed: ' . $e->getMessage(), [
                    'recipient' => $this->maskEmail($recipientEmail),
                ]);

                // If no REST API key is configured, return the SMTP error directly
                if (empty($this->apiKey)) {
                    return [
                        'success' => false,
                        'message_id' => null,
                        'error' => "Échec d'envoi via Brevo SMTP: " . $e->getMessage(),
                    ];
                }
            }
        }

        // 2. Secondary: Fallback to Brevo REST API
        if (empty($this->apiKey)) {
            Log::warning('Brevo email skipped: neither SMTP nor API key configured in services.brevo');
            return [
                'success' => false,
                'message_id' => null,
                'error' => "Clé API Brevo non configurée (services.brevo.api_key) et SMTP indisponible.",
            ];
        }

        $payload = [
            'sender' => [
                'name' => $this->senderName,
                'email' => $this->senderEmail,
            ],
            'to' => [
                [
                    'name' => $recipientName,
                    'email' => $recipientEmail,
                ],
            ],
            'subject' => $subject,
            'htmlContent' => $htmlContent,
        ];

        try {
            $sslVerify = (bool) config('services.brevo.ssl_verify', false);

            $client = Http::withHeaders([
                'api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout($this->timeout);

            if (!$sslVerify) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post($this->apiUrl, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $messageId = $data['messageId'] ?? null;

                Log::info('Brevo email dispatched successfully via REST API', [
                    'recipient' => $this->maskEmail($recipientEmail),
                    'subject' => $subject,
                    'messageId' => $messageId,
                ]);

                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'error' => null,
                ];
            }

            $status = $response->status();
            $body = $response->json();
            $errorMessage = $body['message'] ?? $response->body();

            Log::error('Brevo API error response', [
                'status' => $status,
                'error' => $errorMessage,
                'recipient' => $this->maskEmail($recipientEmail),
            ]);

            return [
                'success' => false,
                'message_id' => null,
                'error' => "Erreur Brevo [HTTP {$status}]: {$errorMessage}",
            ];
        } catch (\Throwable $e) {
            Log::error('Brevo HTTP client exception', [
                'error' => $e->getMessage(),
                'recipient' => $this->maskEmail($recipientEmail),
            ]);

            return [
                'success' => false,
                'message_id' => null,
                'error' => "Échec de connexion au service d'email Brevo: " . $e->getMessage(),
            ];
        }
    }

    protected function isSmtpConfigured(): bool
    {
        return !empty($this->smtpHost) && !empty($this->smtpUser) && !empty($this->smtpPass);
    }

    protected function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***';
        }
        $name = substr($parts[0], 0, 2) . '***';
        return $name . '@' . $parts[1];
    }

    protected function buildInvitationHtml(
        string $technicianName,
        string $referenceCode,
        string $infraName,
        string $infraType,
        string $zoneName,
        string $maintenanceType,
        string $priorityLabel,
        string $scheduledDate,
        string $description,
        string $actionUrl,
        ?string $expiresAt
    ): string {
        $expiresNotice = $expiresAt ? "<p style=\"font-size: 13px; color: #708388; margin-top: 15px;\">⏳ Cette invitation expire le <strong>{$expiresAt}</strong>.</p>" : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Affectation d'intervention — {$referenceCode}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f8f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #172c32; line-height: 1.6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f8f7; padding: 40px 15px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 620px; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 20px rgba(7, 63, 74, 0.08);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #073f4a; padding: 32px 36px; text-align: left;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <div style="font-size: 24px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px;">
                                            Aqua<span style="color: #16b7a3;">Secure</span>
                                        </div>
                                        <div style="font-size: 12px; font-weight: 700; color: #16b7a3; text-transform: uppercase; letter-spacing: 1.5px; margin-top: 6px;">
                                            Gestion Technique & Opérationnelle
                                        </div>
                                    </td>
                                    <td align="right">
                                        <span style="display: inline-block; background-color: rgba(22, 183, 163, 0.2); color: #16b7a3; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 700;">
                                            Proposition de mission
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 36px;">
                            <h2 style="font-size: 20px; color: #073f4a; margin-top: 0; margin-bottom: 12px;">
                                Bonjour {$technicianName},
                            </h2>
                            <p style="font-size: 15px; color: #526a6d; margin-top: 0; margin-bottom: 24px;">
                                Une nouvelle opération technique a été programmée sur le réseau hydraulique et vous a été assignée par le gestionnaire. Veuillez examiner les détails ci-dessous et confirmer votre disponibilité.
                            </p>

                            <!-- Mission Summary Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8faf9; border: 1px solid #dce7e5; border-radius: 6px; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table width="100%" cellpadding="6" cellspacing="0" style="font-size: 14px;">
                                            <tr>
                                                <td width="38%" style="color: #708388; font-weight: 600;">Référence :</td>
                                                <td style="color: #073f4a; font-weight: 700;">{$referenceCode}</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #708388; font-weight: 600;">Infrastructure :</td>
                                                <td style="color: #172c32; font-weight: 600;">{$infraName} <span style="color: #708388; font-size: 12px;">({$infraType})</span></td>
                                            </tr>
                                            <tr>
                                                <td style="color: #708388; font-weight: 600;">Zone / Localisation :</td>
                                                <td style="color: #172c32;">{$zoneName}</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #708388; font-weight: 600;">Type d'intervention :</td>
                                                <td style="color: #172c32;">{$maintenanceType}</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #708388; font-weight: 600;">Niveau de priorité :</td>
                                                <td>
                                                    <strong style="color: #bd5149;">{$priorityLabel}</strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #708388; font-weight: 600;">Date planifiée :</td>
                                                <td style="color: #073f4a; font-weight: 700;">{$scheduledDate}</td>
                                            </tr>
                                        </table>

                                        <div style="border-top: 1px dashed #dce7e5; margin-top: 14px; padding-top: 14px;">
                                            <div style="font-size: 12px; font-weight: 700; color: #708388; text-transform: uppercase; margin-bottom: 6px;">Ordre de mission :</div>
                                            <div style="font-size: 14px; color: #172c32; background: #ffffff; padding: 12px; border-radius: 4px; border: 1px solid #e5eeee;">
                                                {$description}
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <!-- Important Notice -->
                            <div style="background-color: #fff9eb; border-left: 4px solid #aa721c; padding: 14px 16px; border-radius: 4px; margin-bottom: 28px; font-size: 13px; color: #784a05;">
                                <strong>Protection & Sécurité :</strong> L'ouverture de cet email ou du lien ne valide pas automatiquement l'affectation. Vous devrez confirmer explicitement votre décision sur la page sécurisée.
                            </div>

                            <!-- CTA Buttons -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px;">
                                <tr>
                                    <td align="center">
                                        <a href="{$actionUrl}" style="display: inline-block; background-color: #073f4a; color: #ffffff; font-size: 15px; font-weight: 700; text-decoration: none; padding: 14px 28px; border-radius: 6px; box-shadow: 0 4px 12px rgba(7, 63, 74, 0.2);">
                                            Consulter & Répondre à l'invitation →
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            {$expiresNotice}

                            <p style="font-size: 12px; color: #8a9b9c; margin-top: 24px; border-top: 1px solid #edf2f1; padding-top: 16px;">
                                Si le bouton ne s'affiche pas correctement, vous pouvez copier et coller ce lien sécurisé dans votre navigateur :<br>
                                <a href="{$actionUrl}" style="color: #16b7a3; word-break: break-all;">{$actionUrl}</a>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f7faf9; border-top: 1px solid #dce7e5; padding: 20px 36px; text-align: center; font-size: 12px; color: #708388;">
                            AquaSecure — Plateforme de Gestion et de Supervision des Infrastructures Hydrauliques.<br>
                            Cet email opérationnel est strictement confidentiel et destiné à l'usage exclusif du technicien notifié.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    protected function buildClosureHtml(
        string $technicianName,
        string $referenceCode,
        string $infraName,
        string $closedAt,
        ?string $managerNotes
    ): string {
        $notesHtml = $managerNotes ? "<div style=\"margin-top: 14px; padding: 12px; background: #ffffff; border-radius: 4px; border: 1px solid #dce7e5;\"><strong>Commentaire du gestionnaire :</strong> {$managerNotes}</div>" : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Intervention Clôturée — {$referenceCode}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f8f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #172c32;">
    <table width="100%" cellpadding="0" cellspacing="0" style="padding: 40px 15px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 20px rgba(7, 63, 74, 0.08);">
                    <tr>
                        <td style="background-color: #073f4a; padding: 28px 36px;">
                            <div style="font-size: 22px; font-weight: 800; color: #ffffff;">Aqua<span style="color: #16b7a3;">Secure</span></div>
                            <div style="font-size: 13px; color: #16b7a3; font-weight: 700; margin-top: 4px;">Validation Définitive des Travaux</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px 36px;">
                            <h2 style="font-size: 18px; color: #13886f; margin-top: 0;">✓ Intervention validée et clôturée avec succès</h2>
                            <p style="color: #526a6d; font-size: 14px;">Bonjour {$technicianName},</p>
                            <p style="color: #526a6d; font-size: 14px;">
                                Votre rapport technique pour l'opération <strong>{$referenceCode}</strong> sur l'infrastructure <strong>{$infraName}</strong> a été revu et approuvé par le gestionnaire.
                            </p>
                            <div style="background-color: #def5ed; border-radius: 6px; padding: 16px; margin: 20px 0; font-size: 14px; color: #13886f;">
                                <div><strong>Statut final :</strong> Terminée & Validée</div>
                                <div><strong>Date de clôture :</strong> {$closedAt}</div>
                                {$notesHtml}
                            </div>
                            <p style="color: #708388; font-size: 13px;">Merci pour votre rigueur et votre contribution au maintien de la sécurité du réseau.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    protected function buildCorrectionsHtml(string $technicianName, string $referenceCode, string $notes): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Demande de compléments — {$referenceCode}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f8f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #172c32;">
    <table width="100%" cellpadding="0" cellspacing="0" style="padding: 40px 15px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 20px rgba(7, 63, 74, 0.08);">
                    <tr>
                        <td style="background-color: #073f4a; padding: 28px 36px;">
                            <div style="font-size: 22px; font-weight: 800; color: #ffffff;">Aqua<span style="color: #16b7a3;">Secure</span></div>
                            <div style="font-size: 13px; color: #f17d6f; font-weight: 700; margin-top: 4px;">Révision de Compte-Rendu Requise</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px 36px;">
                            <h2 style="font-size: 18px; color: #c2410c; margin-top: 0;">Compléments demandés par le gestionnaire</h2>
                            <p style="color: #526a6d; font-size: 14px;">Bonjour {$technicianName},</p>
                            <p style="color: #526a6d; font-size: 14px;">
                                Le gestionnaire a examiné votre rapport pour l'intervention <strong>{$referenceCode}</strong> et demande des précisions ou corrections avant validation finale :
                            </p>
                            <div style="background-color: #fff7ed; border-left: 4px solid #ea580c; border-radius: 4px; padding: 14px; margin: 18px 0; font-size: 14px; color: #9a3412;">
                                <strong>Instructions :</strong><br>
                                {$notes}
                            </div>
                            <p style="color: #526a6d; font-size: 14px;">
                                L'intervention a été réouverte en statut <em>« En cours d'exécution »</em> dans votre espace technicien afin que vous puissiez mettre à jour les éléments demandés.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Send email notification to manager when technician accepts an assignment.
     */
    public function sendTechnicianAcceptedNotificationToManager(Maintenance $maintenance, User $technician, User $manager): array
    {
        if (empty($manager->email) || !filter_var($manager->email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message_id' => null, 'error' => "Email gestionnaire manquant ou invalide."];
        }

        $ref = $maintenance->reference_code ?? ('MNT-#' . $maintenance->id);
        $subject = "⚡ Ordre d'intervention accepté par {$technician->name} — {$ref}";
        $html = $this->buildManagerAcceptedHtml(
            managerName: $manager->name,
            technicianName: $technician->name,
            referenceCode: $ref,
            infraName: $maintenance->infrastructure?->name ?? 'Infrastructure',
            scheduledAt: $maintenance->scheduled_at ? $maintenance->scheduled_at->format('d/m/Y à H:i') : 'Date convenue'
        );

        return $this->sendTransactionalEmail($manager->email, $manager->name, $subject, $html);
    }

    /**
     * Send email notification to manager when technician requests a prolongation.
     */
    public function sendProlongationNotificationToManager(Maintenance $maintenance, User $technician, User $manager, int $days, string $reason): array
    {
        if (empty($manager->email) || !filter_var($manager->email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message_id' => null, 'error' => "Email gestionnaire manquant ou invalide."];
        }

        $ref = $maintenance->reference_code ?? ('MNT-#' . $maintenance->id);
        $subject = "⏳ Demande de prolongation (+{$days}j) — {$ref}";
        $html = $this->buildManagerProlongationHtml(
            managerName: $manager->name,
            technicianName: $technician->name,
            referenceCode: $ref,
            infraName: $maintenance->infrastructure?->name ?? 'Infrastructure',
            days: $days,
            reason: $reason,
            newDate: $maintenance->scheduled_at ? $maintenance->scheduled_at->format('d/m/Y') : 'Non fixée'
        );

        return $this->sendTransactionalEmail($manager->email, $manager->name, $subject, $html);
    }

    /**
     * Send email notification to manager when technician completes an intervention.
     */
    public function sendTechnicianCompletedNotificationToManager(Maintenance $maintenance, User $technician, User $manager, int $healthScore = 100): array
    {
        if (empty($manager->email) || !filter_var($manager->email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message_id' => null, 'error' => "Email gestionnaire manquant ou invalide."];
        }

        $ref = $maintenance->reference_code ?? ('MNT-#' . $maintenance->id);
        $subject = "✓ Intervention achevée avec succès (Santé: {$healthScore}%) — {$ref}";
        $html = $this->buildManagerCompletedHtml(
            managerName: $manager->name,
            technicianName: $technician->name,
            referenceCode: $ref,
            infraName: $maintenance->infrastructure?->name ?? 'Infrastructure',
            healthScore: $healthScore,
            resultText: $maintenance->result ?? 'Travaux finalisés'
        );

        return $this->sendTransactionalEmail($manager->email, $manager->name, $subject, $html);
    }

    protected function buildManagerAcceptedHtml(string $managerName, string $technicianName, string $referenceCode, string $infraName, string $scheduledAt): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Intervention acceptée</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f8f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #172c32;">
    <table width="100%" cellpadding="0" cellspacing="0" style="padding: 40px 15px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 20px rgba(7, 63, 74, 0.08);">
                    <tr>
                        <td style="background-color: #073f4a; padding: 28px 36px;">
                            <div style="font-size: 22px; font-weight: 800; color: #ffffff;">Aqua<span style="color: #16b7a3;">Secure</span></div>
                            <div style="font-size: 13px; color: #16b7a3; font-weight: 700; margin-top: 4px;">Notification de Prise en Charge</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px 36px;">
                            <h2 style="font-size: 18px; color: #073f4a; margin-top: 0;">Le technicien a confirmé la mission</h2>
                            <p style="color: #526a6d; font-size: 14px;">Bonjour {$managerName},</p>
                            <p style="color: #526a6d; font-size: 14px;">
                                Le technicien <strong>{$technicianName}</strong> a accepté l'ordre d'intervention pour <strong>{$referenceCode}</strong> sur l'infrastructure <strong>{$infraName}</strong>.
                            </p>
                            <div style="background-color: #edfbf7; border-left: 4px solid #13886f; border-radius: 4px; padding: 14px; margin: 18px 0; font-size: 14px; color: #0f6250;">
                                <strong>Statut :</strong> En cours d'exécution (Étape 2/3)<br>
                                <strong>Date programmée :</strong> {$scheduledAt}
                            </div>
                            <p style="color: #526a6d; font-size: 13px;">
                                L'affectation est désormais verrouillée et consultable en temps réel dans votre tableau de bord.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    protected function buildManagerProlongationHtml(string $managerName, string $technicianName, string $referenceCode, string $infraName, int $days, string $reason, string $newDate): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Prolongation d'intervention</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f8f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #172c32;">
    <table width="100%" cellpadding="0" cellspacing="0" style="padding: 40px 15px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 20px rgba(7, 63, 74, 0.08);">
                    <tr>
                        <td style="background-color: #073f4a; padding: 28px 36px;">
                            <div style="font-size: 22px; font-weight: 800; color: #ffffff;">Aqua<span style="color: #16b7a3;">Secure</span></div>
                            <div style="font-size: 13px; color: #f59e0b; font-weight: 700; margin-top: 4px;">Ajustement de Calendrier Technique</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px 36px;">
                            <h2 style="font-size: 18px; color: #b45309; margin-top: 0;">Report d'échéance : +{$days} jour(s)</h2>
                            <p style="color: #526a6d; font-size: 14px;">Bonjour {$managerName},</p>
                            <p style="color: #526a6d; font-size: 14px;">
                                Le technicien <strong>{$technicianName}</strong> a prolongé l'intervention <strong>{$referenceCode}</strong> sur <strong>{$infraName}</strong> de <strong>{$days} jour(s)</strong>.
                            </p>
                            <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; border-radius: 4px; padding: 14px; margin: 18px 0; font-size: 14px; color: #92400e;">
                                <strong>Nouvelle échéance prévue :</strong> {$newDate}<br>
                                <strong>Motif indiqué :</strong> {$reason}
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    protected function buildManagerCompletedHtml(string $managerName, string $technicianName, string $referenceCode, string $infraName, int $healthScore, string $resultText): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Intervention terminée</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f8f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #172c32;">
    <table width="100%" cellpadding="0" cellspacing="0" style="padding: 40px 15px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 20px rgba(7, 63, 74, 0.08);">
                    <tr>
                        <td style="background-color: #073f4a; padding: 28px 36px;">
                            <div style="font-size: 22px; font-weight: 800; color: #ffffff;">Aqua<span style="color: #16b7a3;">Secure</span></div>
                            <div style="font-size: 13px; color: #10b981; font-weight: 700; margin-top: 4px;">Intervention Finalisée & Santé 100%</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px 36px;">
                            <h2 style="font-size: 18px; color: #047857; margin-top: 0;">Travaux achevés avec succès</h2>
                            <p style="color: #526a6d; font-size: 14px;">Bonjour {$managerName},</p>
                            <p style="color: #526a6d; font-size: 14px;">
                                Le technicien <strong>{$technicianName}</strong> a finalisé l'intervention <strong>{$referenceCode}</strong> sur l'infrastructure <strong>{$infraName}</strong>.
                            </p>
                            <div style="background-color: #ecfdf5; border-left: 4px solid #10b981; border-radius: 4px; padding: 14px; margin: 18px 0; font-size: 14px; color: #065f46;">
                                <strong>Santé de l'infrastructure :</strong> {$healthScore}% (Opérationnel / Excellent)<br>
                                <strong>Compte-rendu :</strong> {$resultText}
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}

