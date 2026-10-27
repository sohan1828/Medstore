<?php
// First-run page: works only while no owner exists. Once the owner is created it redirects away for good.
require_once __DIR__ . '/../includes/layout.php';
secure_headers(); session_boot();
if ((int)db()->query("SELECT COUNT(*) FROM staff WHERE role='owner'")->fetchColumn() > 0) { header('Location: login.php'); exit; }

$err = ''; $v = ['full_name' => '', 'username' => '', 'phone' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($v as $k => $_) $v[$k] = trim((string)($_POST[$k] ?? ''));
    $v['phone'] = norm_phone($v['phone']); $v['username'] = strtolower($v['username']);
    $pw = (string)($_POST['password'] ?? '');
    if (mb_strlen($v['full_name']) < 3) $err = 'Enter your full name.';
    elseif (!preg_match('/^[a-z0-9._-]{3,30}$/', $v['username'])) $err = 'Username: 3 to 30 letters, numbers, dot, dash or underscore.';
    elseif (!valid_phone($v['phone'])) $err = 'Enter a valid phone number.';
    elseif ($pw !== (string)($_POST['password2'] ?? '')) $err = 'The two passwords do not match.';
    elseif ($p = password_problem($pw, $v['username'])) $err = $p;
    else {
        db()->prepare("INSERT INTO staff (username, full_name, phone, password_hash, role, approval, approved_at) VALUES (?,?,?,?, 'owner', 'approved', NOW())")
            ->execute([$v['username'], $v['full_name'], $v['phone'], password_hash($pw, PASSWORD_DEFAULT)]);
        audit(null, null, 'owner_created', $v['username']);
        header('Location: login.php?setup=1'); exit;
    }
}
auth_open('Create the owner account', 'This page only works once, before any owner exists. The owner approves everyone else.', '../');
auth_msg($err);
?>
<form method="post"><?= csrf_field() ?>
  <label for="fn">Full name</label><input id="fn" name="full_name" type="text" maxlength="120" value="<?= e($v['full_name']) ?>" required>
  <div class="row2">
    <div><label for="un">Username</label><input id="un" name="username" type="text" maxlength="30" autocapitalize="none" value="<?= e($v['username']) ?>" required></div>
    <div><label for="ph">Phone</label><input id="ph" name="phone" type="tel" maxlength="20" value="<?= e($v['phone']) ?>" required></div>
  </div>
  <div class="row2">
    <div><label for="pw">Password</label><input id="pw" name="password" type="password" autocomplete="new-password" required></div>
    <div><label for="pw2">Repeat password</label><input id="pw2" name="password2" type="password" autocomplete="new-password" required></div>
  </div>
  <p class="hint">At least 10 characters.</p>
  <button class="go" type="submit">Create owner account</button>
</form>
<?php auth_close();
