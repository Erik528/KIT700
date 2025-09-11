<?php
// verify_otp_action.php
declare(strict_types=1);
session_start();
require 'db_connection.php';
require_once 'config.php';

if (defined('OTP_ENABLED') && OTP_ENABLED === false) {
    header('Location: login.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$email = strtolower(trim($_POST['email'] ?? ($_SESSION['pending_email'] ?? '')));
$code = trim($_POST['otp'] ?? '');

if ($email === '' || $code === '') {
    header('Location: login.php?err=empty&email=' . urlencode($email));
    exit;
}

$stmt = $pdo->prepare("SELECT id, email, role FROM users WHERE LOWER(email) = :email LIMIT 1");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: login.php?err=invalid&email=' . urlencode($email));
    exit;
}

$q = $pdo->prepare("
    SELECT id, otp_hash, attempts, expires_at
    FROM otp_tokens
    WHERE user_id = :uid AND expires_at > NOW()
    ORDER BY id DESC
    LIMIT 1
");
$q->execute([':uid' => (int) $user['id']]);
$token = $q->fetch(PDO::FETCH_ASSOC);

if (!$token) {
    header('Location: login.php?err=expired&email=' . urlencode($email));
    exit;
}

if ((int) $token['attempts'] >= 5) {
    header('Location: login.php?err=locked&email=' . urlencode($email));
    exit;
}

if (!password_verify($code, $token['otp_hash'])) {
    $upd = $pdo->prepare("UPDATE otp_tokens SET attempts = attempts + 1 WHERE id = :id");
    $upd->execute([':id' => (int) $token['id']]);
    header('Location: login.php?err=wrong&email=' . urlencode($email));
    exit;
}

$pdo->prepare("DELETE FROM otp_tokens WHERE id = :id")->execute([':id' => (int) $token['id']]);

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = strtolower(trim($user['role'] ?? ''));
$_SESSION['name'] = explode('@', $user['email'])[0] ?? '';
unset($_SESSION['pending_email']);

switch ($_SESSION['role']) {
    case 'admin':
        header('Location: admin-dash.php');
        exit;
    case 'hr':
        header('Location: hr-choice.php');
        exit;
    case 'manager':
        header('Location: manager_choice.php');
        exit;
    default:
        header('Location: dashboard.php');
        exit;
}
