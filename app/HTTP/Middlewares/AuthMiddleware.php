<?php

namespace App\HTTP\Middlewares;

use Lumin\Middleware;

class AuthMiddleware extends Middleware {
    public function handle(): void {
        $user = $this->getSession('user');
        if (!$user['value']) {
            header('Location: /login');
            exit;
        }
    }
}