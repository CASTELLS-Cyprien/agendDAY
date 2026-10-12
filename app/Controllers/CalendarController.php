<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\EventOptions;

class CalendarController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();
        $user = $this->currentUser();

        $this->render('calendar/index', [
            'user'           => $user,
            'metaTitle'      => 'Mon calendrier — AgendDAY',
            'activePage'     => 'calendar',
            'calendarConfig' => EventOptions::toClientConfig() + [
                'today'           => (new \DateTimeImmutable())->format('Y-m-d'),
                'defaultReminder' => $user['default_reminder_minutes'] !== null
                    ? (int) $user['default_reminder_minutes']
                    : null,
                'emailReminders'  => (int) $user['email_reminders'] === 1,
            ],
        ], 'app');
    }
}
