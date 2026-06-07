<?php
declare(strict_types=1);
namespace App\Controllers;

class CalendarController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth();
        $this->render('calendar/index', [], 'app');
    }
}
