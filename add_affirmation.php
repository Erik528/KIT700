<?php
include 'db_connection.php';

// Get current cycle
$cycle = $conn->query("SELECT cycle_id FROM affirmation_cycle WHERE is_active=1 ORDER BY start_date DESC LIMIT 1")->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $cycle_id   = $cycle['cycle_id'];
    $sender_id  = $_POST['sender_id'];
    $recipient_id = $_POST['recipient_id'];
    $manager_id = $_POST['manager_id'] ?: NULL;
    $subject    = $_POST['subject'];
    $body       = $_POST['body'];

    $stmt = $conn->prepare("INSERT INTO affirmations (cycle_id, sender_id, recipient_id, manager_id, subject, body) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("iiiiss", $cycle_id, $sender_id, $recipient_id, $manager_id, $subject, $body);
    $stmt->execute();

    header("Location: index.php");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>New Affirmation</title>
</head>
<body>
    <h2>Send New Affirmation</h2>
    <form method="post">
        <label>Sender ID:</label><br>
        <input type="number" name="sender_id" required><br><br>

        <label>Recipient ID:</label><br>
        <input type="number" name="recipient_id" required><br><br>

        <label>Manager ID (optional):</label><br>
        <input type="number" name="manager_id"><br><br>

        <label>Subject:</label><br>
        <input type="text" name="subject" required><br><br>

        <label>Message:</label><br>
        <textarea name="body" required></textarea><br><br>

        <button type="submit">Send</button>
    </form>
</body>
</html>
