<?php
require_once __DIR__ . '/../includes/security.php';
secure_headers(); session_boot();
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_check(); $_SESSION = []; session_destroy(); }
header('Location: login.php');
