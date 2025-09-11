<?php
// login_action.php (DB version)
declare(strict_types=1);

session_start();
require 'db_connection.php';
require_once 'config.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$email = strtolower(trim($_POST['email'] ?? ''));
if ($email === '') {
    header('Location: login.php?err=empty_email');
    exit;
}

function redirect_by_role(string $role): void
{
    $role = strtolower(trim($role));
    switch ($role) {
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
}

if (defined('OTP_ENABLED') && OTP_ENABLED === true) {
    header('Location: request_otp.php?email=' . urlencode($email));
    exit;
}

$stmt = $pdo->prepare('SELECT id, email, role FROM users WHERE LOWER(email) = :email LIMIT 1');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: login.php?err=invalid_email');
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = strtolower(trim($user['role'] ?? ''));
$_SESSION['name'] = explode('@', $user['email'])[0] ?? '';

redirect_by_role($_SESSION['role']);
