<?php
// send_otp.php
declare(strict_types=1);
session_start();

require 'db_connection.php';
require_once 'config.php';
require __DIR__ . '/mailer.php';

if (defined('OTP_ENABLED') && OTP_ENABLED === false) {
    header('Location: login.php');
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST' && $method !== 'GET') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$email = strtolower(trim(($method === 'POST') ? ($_POST['email'] ?? '') : ($_GET['email'] ?? '')));
if ($email === '') {
    header('Location: login.php?err=empty');
    exit;
}

$stmt = $pdo->prepare("SELECT id, email, role FROM users WHERE LOWER(email) = :email LIMIT 1");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['pending_email'] = $email;
    header('Location: login.php?ok=1&email=' . urlencode($email));
    exit;
}

$recent = $pdo->prepare("SELECT id, last_sent_at FROM otp_tokens WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$recent->execute([$user['id']]);
$last = $recent->fetch(PDO::FETCH_ASSOC);
if ($last && strtotime($last['last_sent_at']) > (time() - 30)) {
    header('Location: login.php?err=too_fast&email=' . urlencode($user['email']));
    exit;
}

$otp = (string) random_int(100000, 999999);
$hash = password_hash($otp, PASSWORD_DEFAULT);
$expiresAt = date('Y-m-d H:i:s', time() + 5 * 60);
$now = date('Y-m-d H:i:s');

$pdo->prepare("DELETE FROM otp_tokens WHERE user_id = ?")->execute([$user['id']]);

$ins = $pdo->prepare("
    INSERT INTO otp_tokens (user_id, otp_hash, expires_at, attempts, last_sent_at, ip)
    VALUES (?, ?, ?, 0, ?, ?)
");
$ins->execute([$user['id'], $hash, $expiresAt, $now, $_SERVER['REMOTE_ADDR'] ?? null]);

try {
    send_mail(
        $user['email'],
        'Your verification code',
        "<p>Your code is <strong>{$otp}</strong>. It expires in 5 minutes.</p>"
    );
} catch (Throwable $e) {
    error_log('Mailer exception: ' . $e->getMessage());
}

$_SESSION['pending_email'] = $user['email'];

header('Location: login.php?ok=1&email=' . urlencode($user['email']));
exit;
