<?php
include 'db_connection.php';
header('Content-Type: application/json; charset=utf-8');

$dept = $_GET['department'] ?? '';
if ($dept === '') {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.email
    FROM manager_staff ms
    JOIN users u ON u.id = ms.staff_id   
    WHERE ms.department = :department
    ORDER BY u.email ASC
");
$stmt->execute(['department' => $dept]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
