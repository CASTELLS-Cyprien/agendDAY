<?php
declare(strict_types=1);
namespace App\Controllers;

class HomeController extends BaseController
{
    public function index(): void
    {
        $this->render('home/index', [
            'isLoggedIn' => isset($_SESSION['id']),
            'pageKey'    => 'accueil',
        ], 'base');
    }
}
