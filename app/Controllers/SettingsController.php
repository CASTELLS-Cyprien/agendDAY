<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Models\UserModel;
use App\Services\EventOptions;

class SettingsController extends BaseController
{
    public function show(): void
    {
        $this->requireAuth();

        $this->render('settings/index', [
            'flash'           => $this->getFlash(),
            'user'            => $this->currentUser(),
            'reminderOffsets' => EventOptions::REMINDER_OFFSETS,
            'metaTitle'       => 'Préférences — AgendDAY',
            'activePage'      => 'settings',
        ], 'app');
    }

    public function update(): void
    {
        $this->requireAuth();
        $this->requireCsrf('/parametres');

        $emailReminders = ($_POST['email_reminders'] ?? '') === '1';
        $rawDefault     = (string) ($_POST['default_reminder'] ?? '');
        $defaultMinutes = null;

        if ($rawDefault !== '') {
            if (!preg_match('/^\d{1,5}$/', $rawDefault) || !array_key_exists((int) $rawDefault, EventOptions::REMINDER_OFFSETS)) {
                $this->flash('Rappel par défaut invalide.');
                $this->redirect('/parametres');
            }
            $defaultMinutes = (int) $rawDefault;
        }

        (new UserModel())->updatePreferences($this->userId(), $emailReminders, $defaultMinutes);

        $this->flash('Préférences enregistrées.', 'success');
        $this->redirect('/parametres');
    }

    /**
     * Suppression définitive du compte et de toutes ses données (RGPD, droit à l'effacement).
     */
    public function deleteAccount(): void
    {
        $this->requireAuth();
        $this->requireCsrf('/parametres');

        if (!$this->rateLimit('delete_account_' . $this->userId(), 5, 900)) {
            $this->flash('Trop de tentatives. Veuillez réessayer dans quelques minutes.');
            $this->redirect('/parametres#suppression');
        }

        $password = (string) ($_POST['password'] ?? '');
        $confirm  = ($_POST['confirm'] ?? '') === '1';
        $model    = new UserModel();
        $hash     = $model->passwordHash($this->userId());

        if (!$confirm) {
            $this->flash('Cochez la case de confirmation pour supprimer votre compte.');
            $this->redirect('/parametres#suppression');
        }
        if ($hash === null || !password_verify($password, $hash)) {
            $this->flash('Mot de passe incorrect : le compte n\'a pas été supprimé.');
            $this->redirect('/parametres#suppression');
        }

        $model->deleteWithData($this->userId());

        session_unset();
        session_regenerate_id(true);
        $this->flash('Votre compte et toutes vos données ont été supprimés.', 'success');
        $this->redirect('/connexion');
    }
}
