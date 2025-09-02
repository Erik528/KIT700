<?php
// login_action.php (DB version)
declare(strict_types=1);

session_start();
require 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$email = trim(strtolower($_POST['email'] ?? ''));
if ($email === '') {
    header('Location: login.php?err=empty');
    exit;
}

$stmt = $pdo->prepare("SELECT id, role, email FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: login.php?err=invalid');
    exit;
}

$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = $user['role'];
$_SESSION['name'] = explode('@', $user['email'])[0];

switch ($user['role']) {
    case 'admin':
        header('Location: admin-dash.php');
        break;
    case 'hr':
        header('Location: hr-dash.php');
        break;
    case 'manager':
        header('Location: manager_choice.php');
        break;
    case 'user':
    default:
        header('Location: dashboard.php');
        break;
}
exit;
