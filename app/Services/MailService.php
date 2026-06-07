<?php
declare(strict_types=1);
namespace App\Services;

// PHPMailer n'est pas géré par l'autoloader PSR-4 du projet — inclusion explicite au niveau du fichier
require_once __DIR__ . '/../../vendor/phpmailer/Exception.php';
require_once __DIR__ . '/../../vendor/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../../vendor/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

class MailService
{
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
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addReplyTo(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';

        return $mail;
    }

    public function sendConfirmation(string $email, string $nomUtilisateur, string $token): void
    {
        $link = APP_URL . '/confirmation?token=' . urlencode($token);

        $mail = $this->build();
        $mail->addAddress($email);
        $mail->Subject = '=?UTF-8?B?' . base64_encode('Confirmez votre inscription - AgendDAY') . '?=';
        $mail->Body    = $this->wrapHtml(
            'Confirmation d\'inscription',
            "<p>Bonjour " . htmlspecialchars($nomUtilisateur) . ",</p>
             <p>Merci de vous être inscrit sur AgendDAY. Cliquez ci-dessous pour confirmer votre adresse email :</p>
             <p><a href='{$link}' class='cta-button'>Confirmer mon compte</a></p>
             <p>Ou copiez ce lien dans votre navigateur :<br>
                <a href='{$link}' style='color:#485fc7;'>{$link}</a></p>
             <p><small>Ce lien expire dans 24 heures.</small></p>"
        );
        $mail->AltBody = "Bonjour {$nomUtilisateur},\n\nConfirmez votre compte AgendDAY :\n{$link}\n\nCe lien expire dans 24 heures.\n\nL'équipe AgendDAY";
        $mail->send();
    }

    public function sendResetPassword(string $email, string $nomUtilisateur, string $token): void
    {
        $link = APP_URL . '/reinitialiser-mot-de-passe?token=' . urlencode($token);

        $mail = $this->build();
        $mail->addAddress($email);
        $mail->Subject = '=?UTF-8?B?' . base64_encode('Réinitialisation de votre mot de passe - AgendDAY') . '?=';
        $mail->Body    = $this->wrapHtml(
            'Réinitialisation du mot de passe',
            "<p>Bonjour " . htmlspecialchars($nomUtilisateur) . ",</p>
             <p>Vous avez demandé la réinitialisation de votre mot de passe AgendDAY.</p>
             <p><a href='{$link}' class='cta-button'>Réinitialiser mon mot de passe</a></p>
             <p>Ou copiez ce lien :<br><a href='{$link}' style='color:#485fc7;'>{$link}</a></p>
             <p><small>Ce lien est valable <strong>1 heure</strong>. Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.</small></p>"
        );
        $mail->AltBody = "Bonjour {$nomUtilisateur},\n\nLien de réinitialisation (1 heure) :\n{$link}\n\nL'équipe AgendDAY";
        $mail->send();
    }

    public function sendContactEmail(string $nom, string $email, string $message): void
    {
        $mail = $this->build();
        $mail->clearReplyTos();
        $mail->addReplyTo($email, $nom);
        $mail->addAddress(EMAIL_FROM);
        $mail->Subject = '=?UTF-8?B?' . base64_encode("Nouveau message de contact de {$nom}") . '?=';
        $mail->Body    = "<!DOCTYPE html><html><head><meta charset='UTF-8'>
            <style>body{font-family:Arial,sans-serif;background:#f4f4f4}
            .container{max-width:600px;margin:20px auto;background:#f8fafc;border-radius:8px;overflow:hidden}
            .header{background:#485fc7;color:#fff;padding:20px;text-align:center}
            .content{padding:20px;color:#333;line-height:1.6}
            .details{background:#f9f9f9;padding:15px;border-radius:4px}
            .cta-button{display:inline-block;padding:12px 20px;background:#485fc7;color:#fff!important;text-decoration:none;border-radius:4px;margin:15px 0}
            </style></head><body>
            <div class='container'>
                <div class='header'><h1>Nouveau message de contact</h1></div>
                <div class='content'>
                    <div class='details'>
                        <p><strong>Nom :</strong> {$nom}</p>
                        <p><strong>Email :</strong> {$email}</p>
                        <p><strong>Message :</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>
                    </div>
                    <p><a href='mailto:{$email}' class='cta-button'>Répondre</a></p>
                </div>
            </div></body></html>";
        $mail->AltBody = "Nom : {$nom}\nEmail : {$email}\nMessage :\n{$message}";
        $mail->send();
    }

    public function sendReminder(
        string $email,
        string $nomUtilisateur,
        array $event,
        string $tempsRestant
    ): void {
        $desc = !empty($event['descriptionEvent'])
            ? "<p><strong>Description :</strong> " . nl2br(htmlspecialchars($event['descriptionEvent'])) . "</p>"
            : '';

        $dateFormatee = date('d/m/Y', strtotime($event['dateEvent']));
        $heure        = substr($event['time'], 0, 5);
        $calUrl       = APP_URL . '/calendrier';

        $mail = $this->build();
        $mail->addAddress($email);
        $mail->Subject = '=?UTF-8?B?' . base64_encode('Rappel : ' . $event['title']) . '?=';
        $mail->Body    = "<!DOCTYPE html><html><head><meta charset='UTF-8'>
            <style>body{font-family:Arial,sans-serif;background:#f4f4f4}
            .container{max-width:600px;margin:20px auto;background:#f8fafc;border-radius:8px;overflow:hidden;box-shadow:0 2px 4px rgba(0,0,0,.1)}
            .header{background:#485fc7;color:#fff;padding:20px;text-align:center}
            .badge{display:inline-block;background:rgba(255,255,255,.2);padding:4px 12px;border-radius:20px;font-size:13px;margin-top:8px}
            .content{padding:20px;color:#333;line-height:1.6}
            .details{background:#f0f4ff;border-left:4px solid #485fc7;padding:15px;border-radius:0 4px 4px 0;margin:15px 0}
            .cta{display:inline-block;padding:12px 24px;background:#485fc7;color:#fff!important;text-decoration:none;border-radius:4px;font-weight:bold;margin-top:10px}
            .footer{text-align:center;font-size:12px;color:#aaa;padding:15px}
            </style></head><body>
            <div class='container'>
                <div class='header'>
                    <h1>Rappel d'événement</h1>
                    <div class='badge'>" . htmlspecialchars($tempsRestant) . "</div>
                </div>
                <div class='content'>
                    <p>Bonjour <strong>" . htmlspecialchars($nomUtilisateur) . "</strong>,</p>
                    <p>Votre événement approche :</p>
                    <div class='details'>
                        <p><strong>" . htmlspecialchars($event['title']) . "</strong></p>
                        <p>{$dateFormatee} à {$heure}</p>
                        {$desc}
                    </div>
                    <a href='{$calUrl}' class='cta'>Voir mon calendrier</a>
                </div>
                <div class='footer'>AgendDAY · <a href='" . APP_URL . "' style='color:#aaa'>" . APP_URL . "</a></div>
            </div></body></html>";
        $mail->AltBody = "Rappel ({$tempsRestant}) : {$event['title']}\n"
            . "Le {$dateFormatee} à {$heure}\n"
            . (!empty($event['descriptionEvent']) ? "Description : {$event['descriptionEvent']}\n" : '')
            . "\n{$calUrl}\n\nAgendDAY";
        $mail->send();
    }

    private function wrapHtml(string $headerTitle, string $body): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'>
            <style>body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0}
            .container{max-width:600px;margin:20px auto;background:#f8fafc;border-radius:8px;overflow:hidden;box-shadow:0 2px 4px rgba(0,0,0,.1)}
            .header{background:#485fc7;color:#fff;padding:20px;text-align:center}
            .header h1{margin:0;font-size:24px}
            .content{padding:20px;color:#333;line-height:1.6}
            .cta-button{display:inline-block;padding:12px 20px;background:#485fc7;color:#fff!important;text-decoration:none;border-radius:4px;margin:15px 0}
            .footer{background:#f4f4f4;padding:15px;text-align:center;font-size:12px;color:#666}
            </style></head><body>
            <div class='container'>
                <div class='header'><h1>{$headerTitle}</h1></div>
                <div class='content'>{$body}</div>
                <div class='footer'>Cordialement,<br>L'équipe AgendDAY</div>
            </div></body></html>";
    }
}
