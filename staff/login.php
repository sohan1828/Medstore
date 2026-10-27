<?php
require_once __DIR__ . '/../includes/security.php';
secure_headers(); session_boot();
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = substr(trim($_POST['username'] ?? ''), 0, 60); $ip = client_ip();
    $n = db()->prepare("SELECT (SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND at > NOW() - INTERVAL 15 MINUTE) a,
                               (SELECT COUNT(*) FROM login_attempts WHERE username = ? AND at > NOW() - INTERVAL 15 MINUTE) b");
    $n->execute([$ip, $u]); $n = $n->fetch();
    if ($n['a'] >= 10 || $n['b'] >= 5) {
        $err = 'Too many attempts. Wait 15 minutes and try again.';
    } else {
        $q = db()->prepare('SELECT * FROM staff WHERE username = ? AND active = 1'); $q->execute([$u]); $row = $q->fetch();
        $hash = $row['password_hash'] ?? '$2y$10$usesomesillystringforsaltyoMKzJ7E5G7bZxFh3x5mZ0vZ6dQ1y1W';  // constant-time-ish when user is unknown
        if (password_verify($_POST['password'] ?? '', $hash) && $row) {
            session_regenerate_id(true);
            $_SESSION['staff'] = ['id' => (int)$row['id'], 'username' => $row['username'], 'role' => $row['role']];
            $_SESSION['last'] = time();
            audit((int)$row['id'], null, 'login');
            header('Location: index.php'); exit;
        }
        db()->prepare('INSERT INTO login_attempts (ip, username) VALUES (?,?)')->execute([$ip, $u]);
        $err = 'Wrong username or password.';
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Staff sign in</title><link rel="stylesheet" href="../assets/app.css"></head><body>
<main class="wrap"><h1>Staff sign in</h1>
<?php if ($err): ?><div class="err" role="alert"><?= e($err) ?></div><?php endif; ?>
<form method="post" class="box"><?= csrf_field() ?>
  <label for="u">Username</label><input id="u" name="username" type="text" autocomplete="username" required>
  <label for="p">Password</label><input id="p" name="password" type="password" autocomplete="current-password" required>
  <p><button class="btn" type="submit">Sign in</button></p>
</form></main></body></html>
