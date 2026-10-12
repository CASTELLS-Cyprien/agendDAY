<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\MailService;

class ContactController extends BaseController
{
    public function show(): void
    {
        $this->render('contact/index', [
            'flash'            => $this->getFlash(),
            'old'              => $this->oldInput(),
            'recaptchaSiteKey' => RECAPTCHA_SITE_KEY,
            'metaTitle'        => 'Contact — AgendDAY',
            'withRecaptcha'    => true,
        ], 'minimal');
    }

    public function send(): void
    {
        $this->requireCsrf('/contact');

        $nom     = trim($_POST['nom'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $this->rememberInput(['nom' => $nom, 'email' => $email, 'message' => $message]);

        if (empty($_POST['g-recaptcha-response']) || !is_string($_POST['g-recaptcha-response'])) {
            $this->flash('Veuillez compléter le reCAPTCHA.');
            $this->redirect('/contact');
        }

        if (!$this->verifyRecaptcha($_POST['g-recaptcha-response'])) {
            $this->flash('Vérification reCAPTCHA échouée. Veuillez réessayer.');
            $this->redirect('/contact');
        }

        if (empty($nom) || empty($email) || empty($message)) {
            $this->flash('Veuillez remplir tous les champs.');
            $this->redirect('/contact');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('Adresse e-mail invalide.');
            $this->redirect('/contact');
        }

        if (mb_strlen($nom) > 100 || mb_strlen($message) > 5000) {
            $this->flash('Message trop long (5 000 caractères maximum).');
            $this->redirect('/contact');
        }

        try {
            (new MailService())->sendContactEmail($nom, $email, $message);
            unset($_SESSION['old_input']);
            $this->flash('Votre message a été envoyé. Nous vous répondrons par e-mail.', 'success');
        } catch (\Exception $e) {
            error_log('Erreur envoi contact : ' . $e->getMessage());
            $this->flash('Erreur lors de l\'envoi. Veuillez réessayer.');
        }

        $this->redirect('/contact');
    }

    private function verifyRecaptcha(string $response): bool
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://www.google.com/recaptcha/api/siteverify',
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'secret'   => SECRET_KEY,
                'response' => $response,
                'remoteip' => $_SERVER['REMOTE_ADDR'],
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($result === false || $httpCode !== 200) {
            error_log('Erreur cURL reCAPTCHA : ' . $error);
            return false;
        }

        $data = json_decode($result, true);
        return isset($data['success']) && $data['success'] === true;
    }
}
