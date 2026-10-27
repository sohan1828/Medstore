<?php
require_once __DIR__ . '/lib.php';

function secure_headers(): void {
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'none'; form-action 'self'; base-uri 'none'");
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(self)');
    // Enable once the site is on HTTPS:
    // header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

function session_boot(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']),
                               'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? ''; }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check(): void {
    $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
    if (!$ok) { http_response_code(403); exit('Your session expired. Go back, refresh the page and try again.'); }
}

function current_staff(): ?array { return $_SESSION['staff'] ?? null; }

function require_staff(?array $roles = null): array {
    $s = current_staff();
    if ($s && isset($_SESSION['last']) && time() - $_SESSION['last'] > 1800) { $_SESSION = []; session_destroy(); $s = null; }
    if ($s) {   // deactivated accounts lose access immediately
        $q = db()->prepare('SELECT role FROM staff WHERE id = ? AND active = 1');
        $q->execute([$s['id']]);
        $row = $q->fetch();
        if (!$row) { $_SESSION = []; session_destroy(); $s = null; } else { $s['role'] = $row['role']; $_SESSION['staff']['role'] = $row['role']; }
    }
    if (!$s) { header('Location: login.php'); exit; }
    if ($roles && !in_array($s['role'], $roles, true)) { http_response_code(403); exit('Your role cannot do this.'); }
    $_SESSION['last'] = time();
    return $s;
}

function audit(?int $staffId, ?int $orderId, string $action, string $detail = ''): void {
    db()->prepare('INSERT INTO audit_log (staff_id, order_id, action, detail, ip) VALUES (?,?,?,?,?)')
        ->execute([$staffId, $orderId, $action, substr($detail, 0, 255), client_ip()]);
}

/** Validates and stores a prescription file. Returns the stored file name. Throws RuntimeException with a user-safe message. */
function save_rx_upload(array $f): string {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Please upload a photo of the prescription.');
    if ($f['size'] > RX_MAX_BYTES) throw new RuntimeException('That file is too large. The limit is 5 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'][$mime] ?? null;
    if (!$ext) throw new RuntimeException('Upload a JPG, PNG, WebP or PDF file.');
    if ($ext !== 'pdf' && !@getimagesize($f['tmp_name'])) throw new RuntimeException('That image could not be read. Try another photo.');
    if (!is_dir(RX_DIR) && !mkdir(RX_DIR, 0750, true)) throw new RuntimeException('Could not store the file. Please try again.');
    $name = bin2hex(random_bytes(16)) . '.' . $ext;     // never use the uploaded name
    if (!move_uploaded_file($f['tmp_name'], RX_DIR . '/' . $name)) throw new RuntimeException('Could not store the file. Please try again.');
    @chmod(RX_DIR . '/' . $name, 0640);
    return $name;
}

/** CSRF for fetch() calls: the page sends the session token in an X-CSRF header. */
function csrf_check_header(): void {
    $h = $_SERVER['HTTP_X_CSRF'] ?? '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $h)) json_out(['error' => 'Session expired. Reload the page.'], 403);
}

function require_driver_api(): array {
    session_boot();
    $a = null;
    if (!empty($_SESSION['driver_id'])) {
        $q = db()->prepare('SELECT * FROM ambulances WHERE id = ? AND active = 1');
        $q->execute([$_SESSION['driver_id']]); $a = $q->fetch();
    }
    if (!$a) json_out(['error' => 'Please sign in again.'], 401);
    return $a;
}
