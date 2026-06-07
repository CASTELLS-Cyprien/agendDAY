<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Models\UserModel;
use App\Services\MailService;

class AuthController extends BaseController
{
    public function showLogin(): void
    {
        $this->requireGuest();
        $this->render('auth/login', [
            'flash'     => $this->getFlash(),
            'metaTitle' => 'Connexion — AgendDAY',
        ], 'minimal');
    }

    public function login(): void
    {
        $this->requireGuest();

        $email      = trim($_POST['email'] ?? '');
        $motDePasse = $_POST['motDePasse'] ?? '';

        if (empty($email) || empty($motDePasse)) {
            $this->flash('Veuillez remplir tous les champs.');
            $this->redirect('/connexion');
        }

        $user = (new UserModel())->findByEmail($email);

        if ($user === null) {
            $this->flash('Email ou mot de passe incorrect.');
            $this->redirect('/connexion');
        }

        if ((int) $user['is_confirmed'] === 0) {
            $this->flash('Votre compte n\'est pas confirmé. Veuillez vérifier votre email.');
            $this->redirect('/inscription');
        }

        if (!password_verify($motDePasse, $user['motDePasse'])) {
            $this->flash('Email ou mot de passe incorrect.');
            $this->redirect('/connexion');
        }

        $_SESSION['id'] = $user['id'];
        $this->redirect('/calendrier');
    }

    public function showRegister(): void
    {
        $this->requireGuest();
        $this->render('auth/register', [
            'flash'     => $this->getFlash(),
            'metaTitle' => 'Créer un compte — AgendDAY',
        ], 'minimal');
    }

    public function register(): void
    {
        $this->requireGuest();

        $nomUtilisateur      = trim($_POST['nomUtilisateur'] ?? '');
        $email               = trim($_POST['email'] ?? '');
        $motDePasse          = $_POST['motDePasse'] ?? '';
        $confirmerMotDePasse = $_POST['confirmerMotDePasse'] ?? '';

        if (empty($nomUtilisateur) || empty($email) || empty($motDePasse) || empty($confirmerMotDePasse)) {
            $this->flash('Veuillez remplir tous les champs obligatoires.');
            $this->redirect('/inscription');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('Adresse email invalide.');
            $this->redirect('/inscription');
        }

        if ($motDePasse !== $confirmerMotDePasse) {
            $this->flash('Les mots de passe ne correspondent pas.');
            $this->redirect('/inscription');
        }

        $model = new UserModel();

        if ($model->emailExists($email)) {
            $this->flash('Cet email est déjà enregistré.');
            $this->redirect('/connexion');
        }

        $hashedPassword = password_hash($motDePasse, PASSWORD_BCRYPT);
        $token          = bin2hex(random_bytes(50));

        try {
            $model->create($nomUtilisateur, $email, $hashedPassword, $token);
        } catch (\PDOException $e) {
            error_log('Erreur DB inscription : ' . $e->getMessage());
            $this->flash('Une erreur est survenue lors de l\'inscription. Veuillez réessayer.');
            $this->redirect('/inscription');
        }

        try {
            (new MailService())->sendConfirmation($email, $nomUtilisateur, $token);
            $this->flash('Inscription réussie ! Un email de confirmation a été envoyé.');
        } catch (\Exception $e) {
            error_log('Erreur email inscription : ' . $e->getMessage());
            $this->flash('Inscription réussie, mais erreur lors de l\'envoi de l\'email de confirmation.');
        }

        $this->redirect('/connexion');
    }

    public function logout(): void
    {
        session_unset();
        session_destroy();
        $this->redirect('/connexion');
    }

    public function showForgotPassword(): void
    {
        $this->render('auth/forgot-password', [
            'flash'     => $this->getFlash(),
            'metaTitle' => 'Mot de passe oublié — AgendDAY',
        ], 'minimal');
    }

    public function forgotPassword(): void
    {
        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $this->flash('Veuillez saisir votre adresse email.');
            $this->redirect('/mot-de-passe-oublie');
        }

        $user = (new UserModel())->findByEmail($email);

        if ($user === null) {
            $this->flash('Si cette adresse est associée à un compte, un email vous a été envoyé.');
            $this->redirect('/mot-de-passe-oublie');
        }

        try {
            $token      = bin2hex(random_bytes(50));
            $expiration = date('Y-m-d H:i:s', strtotime('+1 hour'));

            (new UserModel())->setResetToken($email, $token, $expiration);
            (new MailService())->sendResetPassword($email, $user['nomUtilisateur'], $token);
        } catch (\Exception $e) {
            error_log('Erreur reset password : ' . $e->getMessage());
        }

        $this->flash('Si cette adresse est associée à un compte, un email vous a été envoyé.');
        $this->redirect('/mot-de-passe-oublie');
    }

    public function showResetPassword(): void
    {
        $token = $_GET['token'] ?? '';

        if (empty($token)) {
            $this->flash('Aucun token fourni.');
            $this->redirect('/mot-de-passe-oublie');
        }

        $user = (new UserModel())->findByResetToken($token);

        if ($user === null) {
            $this->flash('Lien de réinitialisation invalide ou expiré.');
            $this->redirect('/mot-de-passe-oublie');
        }

        $this->render('auth/reset-password', [
            'flash'     => $this->getFlash(),
            'formError' => '',
            'token'     => $token,
            'metaTitle' => 'Nouveau mot de passe — AgendDAY',
        ], 'minimal');
    }

    public function resetPassword(): void
    {
        $token          = $_GET['token'] ?? '';
        $newPassword    = $_POST['newPassword'] ?? '';
        $confirmPassword = $_POST['confirmNewPassword'] ?? '';

        if (empty($token)) {
            $this->flash('Token manquant.');
            $this->redirect('/mot-de-passe-oublie');
        }

        $model = new UserModel();
        $user  = $model->findByResetToken($token);

        if ($user === null) {
            $this->flash('Lien de réinitialisation invalide ou expiré.');
            $this->redirect('/mot-de-passe-oublie');
        }

        $formError = '';

        if (strlen($newPassword) < 8) {
            $formError = 'Le mot de passe doit contenir au moins 8 caractères.';
        } elseif ($newPassword !== $confirmPassword) {
            $formError = 'Les mots de passe ne correspondent pas.';
        } elseif (password_verify($newPassword, $user['motDePasse'])) {
            $formError = 'Le nouveau mot de passe ne peut pas être identique à l\'ancien.';
        }

        if ($formError !== '') {
            $this->render('auth/reset-password', [
                'flash'     => null,
                'formError' => $formError,
                'token'     => $token,
                'metaTitle' => 'Nouveau mot de passe — AgendDAY',
            ], 'minimal');
            return;
        }

        try {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            $model->updatePassword((int) $user['id'], $hash);
            $this->flash('Mot de passe réinitialisé avec succès. Vous pouvez vous connecter.');
        } catch (\PDOException $e) {
            error_log('Erreur reset_password : ' . $e->getMessage());
            $this->flash('Erreur lors de la mise à jour. Veuillez réessayer.');
        }

        $this->redirect('/connexion');
    }

    public function confirm(): void
    {
        $token = $_GET['token'] ?? '';

        if (empty($token)) {
            $this->flash('Aucun token fourni.');
            $this->redirect('/connexion');
        }

        try {
            $confirmed = (new UserModel())->confirmAccount($token);
            if ($confirmed) {
                $this->flash('Votre compte a été confirmé ! Vous pouvez maintenant vous connecter.');
            } else {
                $this->flash('Lien de confirmation invalide ou compte déjà confirmé.');
            }
        } catch (\PDOException $e) {
            error_log('Erreur confirmation : ' . $e->getMessage());
            $this->flash('Une erreur est survenue. Veuillez réessayer.');
        }

        $this->redirect('/connexion');
    }
}
