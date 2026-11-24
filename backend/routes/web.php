<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/*
 * In production this same application serves the Vue SPA: the build output is
 * copied into public/ and this catch-all hands index.html to the client-side
 * router, which needs it for every deep link (history mode).
 *
 * API routes, uploaded media and the health check are excluded so each keeps
 * answering for itself. While the SPA is not built — local development, where
 * Vite serves it on 5173 — the route just 404s instead of pretending.
 */
Route::get('/{any?}', function () {
    $index = public_path('index.html');

    if (! is_file($index)) {
        return response(
            'الواجهة غير مبنية. نفّذ npm run build:spa داخل مجلد frontend/ أو استخدم خادم Vite في التطوير.',
            404,
        )->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    // Sent from memory (a few KB) so it behaves the same on every server and
    // is never cached: hashed assets carry the caching, the shell must not.
    return response(File::get($index), 200, [
        'Content-Type' => 'text/html; charset=UTF-8',
        'Cache-Control' => 'no-cache, must-revalidate',
    ]);
})->where('any', '^(?!api/|up$|storage/).*$');
