<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /dashboard',
            'Disallow: /packs',
            'Disallow: /account',
            'Disallow: /install',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /password',
            'Disallow: /email',
            'Disallow: /dl/',
            'Disallow: /captcha',
            'Disallow: /upload/',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
