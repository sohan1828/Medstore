<?php
require_once __DIR__ . '/../includes/layout.php';
secure_headers(); session_boot();
$err = ''; $v = ['full_name' => '', 'username' => '', 'phone' => '', 'role' => 'worker', 'license_no' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($v as $k => $_) $v[$k] = trim((string)($_POST[$k] ?? ''));
    $v['phone'] = norm_phone($v['phone']); $v['username'] = strtolower($v['username']);
    $pw = (string)($_POST['password'] ?? ''); $pw2 = (string)($_POST['password2'] ?? '');

    $c = db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND username = 'register' AND at > NOW() - INTERVAL 1 HOUR");
    $c->execute([client_ip()]);
    if ($c->fetchColumn() >= 5) $err = 'Too many requests from this device. Try again later.';
    elseif (mb_strlen($v['full_name']) < 3 || mb_strlen($v['full_name']) > 120) $err = 'Enter your full name.';
    elseif (!preg_match('/^[a-z0-9._-]{3,30}$/', $v['username'])) $err = 'Username: 3 to 30 letters, numbers, dot, dash or underscore.';
    elseif (!valid_phone($v['phone'])) $err = 'Enter a valid phone number.';
    elseif (!in_array($v['role'], ['worker', 'pharmacist', 'dispatcher'], true)) $err = 'Choose a role.';
    elseif ($v['role'] === 'pharmacist' && strlen($v['license_no']) < 3) $err = 'Pharmacists must enter their licence number.';
    elseif ($pw !== $pw2) $err = 'The two passwords do not match.';
    elseif ($p = password_problem($pw, $v['username'])) $err = $p;
    else {
        $d = db()->prepare('SELECT COUNT(*) FROM staff WHERE username = ?'); $d->execute([$v['username']]);
        if ($d->fetchColumn()) $err = 'That username is taken. Try another.';
        else {
            db()->prepare("INSERT INTO staff (username, full_name, phone, password_hash, role, license_no, approval) VALUES (?,?,?,?,?,?, 'pending')")
                ->execute([$v['username'], $v['full_name'], $v['phone'], password_hash($pw, PASSWORD_DEFAULT), $v['role'], $v['license_no'] ?: null]);
            db()->prepare("INSERT INTO login_attempts (ip, username) VALUES (?, 'register')")->execute([client_ip()]);
            audit(null, null, 'staff_requested', $v['username'] . ' as ' . $v['role']);
            send_sms(STAFF_ALERT_PHONE, "New staff account request from {$v['full_name']} ({$v['role']}). Approve it at " . SITE_URL . '/staff/users.php');
            header('Location: login.php?registered=1'); exit;
        }
    }
}
auth_open('Request a staff account', 'The owner checks every request before an account can be used.', '../');
auth_msg($err);
?>
<form method="post"><?= csrf_field() ?>
  <label for="fn">Full name</label><input id="fn" name="full_name" type="text" maxlength="120" value="<?= e($v['full_name']) ?>" required>
  <div class="row2">
    <div><label for="un">Username</label><input id="un" name="username" type="text" maxlength="30" autocapitalize="none" value="<?= e($v['username']) ?>" required></div>
    <div><label for="ph">Phone</label><input id="ph" name="phone" type="tel" maxlength="20" value="<?= e($v['phone']) ?>" required></div>
  </div>
  <label for="role">Role you are applying for</label>
  <select id="role" name="role">
    <option value="worker"<?= $v['role'] === 'worker' ? ' selected' : '' ?>>Worker: packs and delivers orders</option>
    <option value="pharmacist"<?= $v['role'] === 'pharmacist' ? ' selected' : '' ?>>Pharmacist: reviews prescriptions</option>
    <option value="dispatcher"<?= $v['role'] === 'dispatcher' ? ' selected' : '' ?>>Dispatcher: runs the emergency control room</option>
  </select>
  <div id="licBox" class="<?= $v['role'] === 'pharmacist' ? '' : 'hidden' ?>">
    <label for="lic">Pharmacist licence number</label><input id="lic" name="license_no" type="text" maxlength="60" value="<?= e($v['license_no']) ?>">
  </div>
  <div class="row2">
    <div><label for="pw">Password</label><input id="pw" name="password" type="password" autocomplete="new-password" required></div>
    <div><label for="pw2">Repeat password</label><input id="pw2" name="password2" type="password" autocomplete="new-password" required></div>
  </div>
  <p class="hint">At least 10 characters. Use a phrase only you know.</p>
  <button class="go" type="submit">Send request</button>
</form>
<p class="alt">Already approved? <a href="login.php">Sign in</a></p>
<script>
const r = document.getElementById('role'), b = document.getElementById('licBox');
r.addEventListener('change', () => b.classList.toggle('hidden', r.value !== 'pharmacist'));
</script>
<?php auth_close();
