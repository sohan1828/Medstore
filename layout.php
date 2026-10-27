<?php
require_once __DIR__ . '/security.php';

/** Opens a centred card for sign-in and sign-up pages. $base is '' from the site root and '../' from /staff. */
function auth_open(string $title, string $sub = '', string $base = '', bool $wide = false): void {
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>' . e($title) . ' | ' . e(STORE_NAME) . '</title><link rel="stylesheet" href="' . $base . 'assets/auth.css"></head>'
       . '<body class="auth"><main class="auth-card' . ($wide ? ' wide' : '') . '"><div class="auth-brand">' . e(STORE_NAME) . '</div>'
       . '<h1>' . e($title) . '</h1>' . ($sub !== '' ? '<p class="auth-sub">' . e($sub) . '</p>' : '');
}
function auth_close(): void { echo '</main></body></html>'; }
function auth_msg(string $text, string $kind = 'err'): void { if ($text !== '') echo '<div class="msg ' . e($kind) . '" role="' . ($kind === 'err' ? 'alert' : 'status') . '">' . e($text) . '</div>'; }
