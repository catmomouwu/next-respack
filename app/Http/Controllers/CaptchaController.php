<?php

namespace App\Http\Controllers;

use App\Services\Security\CaptchaService;
use Illuminate\Http\Response;

class CaptchaController extends Controller
{
    public function __invoke(CaptchaService $captcha): Response
    {
        return response($this->render($captcha->issue()), 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function render(string $code): string
    {
        $width = 180;
        $height = 60;

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'" role="img" aria-label="CAPTCHA">';
        $svg .= '<rect width="100%" height="100%" rx="10" fill="#f8fafc"/>';

        for ($i = 0; $i < 6; $i++) {
            $svg .= sprintf(
                '<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#cbd5e1" stroke-width="1.4" opacity="0.7"/>',
                random_int(0, $width),
                random_int(0, $height),
                random_int(0, $width),
                random_int(0, $height),
            );
        }

        for ($i = 0; $i < 24; $i++) {
            $svg .= sprintf(
                '<circle cx="%d" cy="%d" r="%s" fill="#94a3b8" opacity="0.45"/>',
                random_int(0, $width),
                random_int(0, $height),
                random_int(1, 2),
            );
        }

        $palette = ['#0f766e', '#047857', '#065f46', '#1e40af', '#7c2d12', '#334155', '#9f1239'];

        foreach (mb_str_split($code) as $index => $character) {
            $x = 20 + $index * 32;
            $y = 42 + random_int(-4, 4);

            $svg .= sprintf(
                '<text x="%d" y="%d" font-family="DejaVu Sans, Verdana, Arial, sans-serif" font-size="31" font-weight="700" fill="%s" transform="rotate(%d %d %d)">%s</text>',
                $x,
                $y,
                $palette[array_rand($palette)],
                random_int(-20, 20),
                $x,
                $y,
                htmlspecialchars($character, ENT_XML1),
            );
        }

        return $svg.'</svg>';
    }
}
