<?php
declare(strict_types=1);
namespace App\Controllers;

class PageController extends BaseController
{
    public function mentions(): void
    {
        $this->render('pages/mentions-legales', [
            'isLoggedIn' => isset($_SESSION['id']),
            'pageKey'    => 'mentions-legales',
        ], 'base');
    }

    public function confidentialite(): void
    {
        $this->render('pages/politique-confidentialite', [
            'isLoggedIn' => isset($_SESSION['id']),
            'pageKey'    => 'politique-confidentialite',
        ], 'base');
    }
}
