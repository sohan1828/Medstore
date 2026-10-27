<?php
require_once __DIR__ . '/../includes/layout.php';
secure_headers(); session_boot();
$err = ''; $wait = ''; $okmsg = isset($_GET['registered']) ? 'Request sent. The owner must approve your account before you can sign in. You will get a text message when it is done.' : '';
if (isset($_GET['setup'])) $okmsg = 'Owner account created. You can sign in now.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = substr(trim($_POST['username'] ?? ''), 0, 60); $ip = client_ip();
    $n = db()->prepare("SELECT (SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND at > NOW() - INTERVAL 15 MINUTE) a,
                               (SELECT COUNT(*) FROM login_attempts WHERE username = ? AND at > NOW() - INTERVAL 15 MINUTE) b");
    $n->execute([$ip, $u]); $n = $n->fetch();
    if ($n['a'] >= 10 || $n['b'] >= 5) {
        $err = 'Too many attempts. Wait 15 minutes and try again.';
    } else {
        $q = db()->prepare('SELECT * FROM staff WHERE username = ?'); $q->execute([$u]); $row = $q->fetch();
        $hash = $row['password_hash'] ?? '$2y$10$usesomesillystringforsaltyoMKzJ7E5G7bZxFh3x5mZ0vZ6dQ1y1W';
        if ($row && password_verify($_POST['password'] ?? '', $hash)) {
            // Only people who proved they know the password are told where their account stands.
            if ($row['approval'] === 'pending')       $wait = 'Your account is waiting for the owner to approve it. You will get a text message when it is ready.';
            elseif ($row['approval'] === 'rejected')  $err  = 'Your request was not approved. Please contact the owner.';
            elseif (!$row['active'])                  $err  = 'This account has been switched off. Please contact the owner.';
            else {
                session_regenerate_id(true);
                $_SESSION['staff'] = ['id' => (int)$row['id'], 'username' => $row['username'], 'role' => $row['role']];
                $_SESSION['last'] = time();
                audit((int)$row['id'], null, 'login');
                header('Location: index.php'); exit;
            }
        } else {
            db()->prepare('INSERT INTO login_attempts (ip, username) VALUES (?,?)')->execute([$ip, $u]);
            $err = 'Wrong username or password.';
        }
    }
}
$noOwner = false;
try { $noOwner = (int)db()->query("SELECT COUNT(*) FROM staff WHERE role='owner'")->fetchColumn() === 0; } catch (Throwable $e) {}

auth_open('Staff sign in', 'Pharmacy, delivery and control room team.', '../');
auth_msg($okmsg, 'ok'); auth_msg($wait, 'wait'); auth_msg($err);
?>
<form method="post"><?= csrf_field() ?>
  <label for="u">Username</label><input id="u" name="username" type="text" autocomplete="username" autocapitalize="none" required autofocus>
  <label for="p">Password</label><input id="p" name="password" type="password" autocomplete="current-password" required>
  <button class="go" type="submit">Sign in</button>
</form>
<p class="alt">New here? <a href="register.php">Request an account</a></p>
<?php if ($noOwner): ?><p class="alt">First time setting up? <a href="setup.php">Create the owner account</a></p><?php endif; ?>
<?php auth_close();
