<?php
declare(strict_types=1);
namespace App\Services;

use DateTimeImmutable;
use PHPMailer\PHPMailer\PHPMailer;

class MailService implements ReminderMailer
{
    /** Délai maximal d'une opération SMTP : un serveur muet ne bloque pas le cron. */
    private const SMTP_TIMEOUT = 15;

    private function build(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        $mail->Timeout    = self::SMTP_TIMEOUT;
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addReplyTo(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';

        return $mail;
    }

    /**
     * MAIL_TRANSPORT = 'log' : le message est écrit dans MAIL_LOG_DIR au lieu d'être
     * envoyé (développement, tests). En 'smtp', send() lève une exception si le
     * serveur refuse le message ; un succès signifie « accepté par le serveur SMTP »,
     * pas « délivré dans la boîte de réception ».
     */
    private function dispatch(PHPMailer $mail): void
    {
        if (MAIL_TRANSPORT === 'log') {
            $mail->preSend();
            if (!is_dir(MAIL_LOG_DIR) && !mkdir(MAIL_LOG_DIR, 0775, true) && !is_dir(MAIL_LOG_DIR)) {
                throw new \RuntimeException('Dossier MAIL_LOG_DIR inaccessible.');
            }
            $file = MAIL_LOG_DIR . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.eml';
            if (file_put_contents($file, $mail->getSentMIMEMessage(), LOCK_EX) === false) {
                throw new \RuntimeException('Écriture du message impossible.');
            }
            return;
        }

        $mail->send();
    }

    public function sendConfirmation(string $email, string $nomUtilisateur, string $token): void
    {
        $link = APP_URL . '/confirmation?token=' . urlencode($token);
        $name = self::e($nomUtilisateur);

        $mail = $this->build();
        $mail->addAddress($email);
        $mail->Subject = 'Confirmez votre inscription - AgendDAY';
        $mail->Body    = $this->layout(
            'Confirmation d\'inscription',
            "<p>Bonjour {$name},</p>
             <p>Merci de vous être inscrit sur AgendDAY. Cliquez ci-dessous pour confirmer votre adresse e-mail :</p>
             <p><a href=\"" . self::e($link) . "\" class=\"cta\">Confirmer mon compte</a></p>
             <p class=\"muted\">Ou copiez ce lien dans votre navigateur :<br>" . self::e($link) . "</p>
             <p class=\"muted\">Ce lien expire dans 24 heures.</p>"
        );
        $mail->AltBody = "Bonjour {$nomUtilisateur},\n\nConfirmez votre compte AgendDAY :\n{$link}\n\n"
            . "Ce lien expire dans 24 heures.\n\nL'équipe AgendDAY";
        $this->dispatch($mail);
    }

    public function sendResetPassword(string $email, string $nomUtilisateur, string $token): void
    {
        $link = APP_URL . '/reinitialiser-mot-de-passe?token=' . urlencode($token);
        $name = self::e($nomUtilisateur);

        $mail = $this->build();
        $mail->addAddress($email);
        $mail->Subject = 'Réinitialisation de votre mot de passe - AgendDAY';
        $mail->Body    = $this->layout(
            'Réinitialisation du mot de passe',
            "<p>Bonjour {$name},</p>
             <p>Vous avez demandé la réinitialisation de votre mot de passe AgendDAY.</p>
             <p><a href=\"" . self::e($link) . "\" class=\"cta\">Réinitialiser mon mot de passe</a></p>
             <p class=\"muted\">Ou copiez ce lien :<br>" . self::e($link) . "</p>
             <p class=\"muted\">Ce lien est valable 1 heure. Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail.</p>"
        );
        $mail->AltBody = "Bonjour {$nomUtilisateur},\n\nLien de réinitialisation (valable 1 heure) :\n{$link}\n\nL'équipe AgendDAY";
        $this->dispatch($mail);
    }

    public function sendContactEmail(string $nom, string $email, string $message): void
    {
        $mail = $this->build();
        $mail->clearReplyTos();
        $mail->addReplyTo($email, $nom);
        $mail->addAddress(EMAIL_FROM);
        $mail->Subject = "Nouveau message de contact de {$nom}";
        $mail->Body    = $this->layout(
            'Nouveau message de contact',
            '<div class="card">
                <p><strong>Nom :</strong> ' . self::e($nom) . '</p>
                <p><strong>E-mail :</strong> ' . self::e($email) . '</p>
                <p><strong>Message :</strong><br>' . nl2br(self::e($message)) . '</p>
             </div>
             <p><a href="mailto:' . self::e($email) . '" class="cta">Répondre</a></p>'
        );
        $mail->AltBody = "Nom : {$nom}\nE-mail : {$email}\nMessage :\n{$message}";
        $this->dispatch($mail);
    }

    public function sendReminder(
        string $email,
        string $userName,
        array $event,
        DateTimeImmutable $occurrence,
        string $relative
    ): void {
        $title    = (string) $event['title'];
        $allDay   = !empty($event['all_day']);
        $dateText = DateText::longDate($occurrence);
        $timeText = $allDay
            ? 'Journée entière'
            : substr((string) $event['time'], 0, 5)
                . (!empty($event['end_time']) ? ' – ' . substr((string) $event['end_time'], 0, 5) : '');
        $location    = trim((string) ($event['location'] ?? ''));
        $description = trim((string) ($event['descriptionEvent'] ?? ''));
        $eventUrl    = APP_URL . '/calendrier?date=' . $occurrence->format('Y-m-d') . '&event=' . (int) $event['id'];
        $prefsUrl    = APP_URL . '/parametres';

        $details = '<p class="event-title">' . self::e($title) . '</p>'
            . '<p>' . self::e(ucfirst($dateText)) . ' · ' . self::e($timeText) . '</p>'
            . ($location !== '' ? '<p><strong>Lieu :</strong> ' . self::e($location) . '</p>' : '')
            . ($description !== '' ? '<p>' . nl2br(self::e($description)) . '</p>' : '');

        $mail = $this->build();
        $mail->addAddress($email);
        $mail->Subject = 'Rappel : ' . $title . ' — ' . $relative;
        $mail->Body    = $this->layout(
            'Rappel d\'événement',
            '<p>Bonjour ' . self::e($userName) . ',</p>
             <p>Votre événement commence <strong>' . self::e($relative) . '</strong> :</p>
             <div class="card">' . $details . '</div>
             <p><a href="' . self::e($eventUrl) . '" class="cta">Voir dans mon agenda</a></p>',
            'Vous recevez ce rappel car les rappels par e-mail sont activés sur votre compte AgendDAY.<br>'
            . '<a href="' . self::e($prefsUrl) . '">Gérer mes préférences de notification</a>'
        );
        $mail->AltBody = "Rappel : {$title} ({$relative})\n"
            . ucfirst($dateText) . " · {$timeText}\n"
            . ($location !== '' ? "Lieu : {$location}\n" : '')
            . ($description !== '' ? "\n{$description}\n" : '')
            . "\nVoir dans mon agenda : {$eventUrl}\n"
            . "Gérer mes préférences : {$prefsUrl}\n";
        $this->dispatch($mail);
    }

    private function layout(string $heading, string $body, string $footer = 'Cordialement,<br>L\'équipe AgendDAY'): string
    {
        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <style>
              body{margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a}
              .wrap{max-width:600px;margin:24px auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0}
              .header{background:#2554d4;color:#ffffff;padding:20px 24px}
              .header h1{margin:0;font-size:20px;font-weight:bold}
              .content{padding:24px;line-height:1.6;font-size:15px}
              .card{background:#f8fafc;border-left:4px solid #2554d4;border-radius:0 8px 8px 0;padding:12px 16px;margin:16px 0}
              .card p{margin:4px 0}
              .event-title{font-size:17px;font-weight:bold}
              .cta{display:inline-block;padding:12px 20px;background:#2554d4;color:#ffffff !important;text-decoration:none;border-radius:8px;font-weight:bold}
              .muted{color:#475569;font-size:13px;word-break:break-all}
              .footer{padding:16px 24px;font-size:12px;color:#475569;background:#f8fafc;border-top:1px solid #e2e8f0}
              .footer a{color:#2554d4}
            </style></head><body>
            <div class="wrap">
              <div class="header"><h1>' . self::e($heading) . '</h1></div>
              <div class="content">' . $body . '</div>
              <div class="footer">' . $footer . '</div>
            </div></body></html>';
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
