<?php
session_start();
include 'db_connection.php';

// Example: logged-in user (replace with your session logic)
$logged_in_user_id = 4; // change according to login session

// Fetch current active cycle
$stmt = $pdo->query("SELECT * FROM affirmation_cycle WHERE is_active=1 LIMIT 1");
$currentCycle = $stmt->fetch();
$cycle_id = $currentCycle['cycle_id'] ?? null;

// Handle new affirmation submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient_id = $_POST['recipient_id'];
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    // ✅ Backend word count validation
    $wordCount = str_word_count($message);
    if ($wordCount > 250) {
        echo "<script>alert('Message cannot exceed 250 words!'); window.location.href='dashboard.php';</script>";
        exit;
    }

    $sql = "INSERT INTO affirmations (sender_id, recipient_id, cycle_id, subject, message)
            VALUES (:sender_id, :recipient_id, :cycle_id, :subject, :message)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'sender_id' => $logged_in_user_id,
        'recipient_id' => $recipient_id,
        'cycle_id' => $cycle_id,
        'subject' => $subject,
        'message' => $message
    ]);

    header("Location: dashboard.php?success=1");
    exit;
}

// Fetch affirmation history
$affirmations = [];
if ($cycle_id) {
    $sql = "SELECT a.*, u1.email AS sender_email, u2.email AS recipient_email
            FROM affirmations a
            JOIN users u1 ON a.sender_id = u1.id
            JOIN users u2 ON a.recipient_id = u2.id
            WHERE a.cycle_id = :cycle_id
            ORDER BY a.submitted_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['cycle_id' => $cycle_id]);
    $affirmations = $stmt->fetchAll();
}

// Fetch staff list for recipient dropdown
$staffStmt = $pdo->prepare("SELECT * FROM users WHERE role='user' AND id != :user_id");
$staffStmt->execute(['user_id' => $logged_in_user_id]);
$staffList = $staffStmt->fetchAll();
?>
<?php include 'header.php'; ?>

<div class="container py-5">
    <div class="row">
        <!-- Left Column: Affirmation History -->
        <div class="col-lg-7">
            <h5>Affirmation History</h5>

            <?php if (!empty($affirmations)): ?>
                <?php foreach ($affirmations as $row): ?>
                    <div class="affirmation mb-3 p-2 border">
                        <strong>Sent to:</strong> <?= htmlspecialchars($row['recipient_email']) ?><br>
                        <strong>Subject:</strong> <?= htmlspecialchars($row['subject']) ?><br>
                        <p><?= htmlspecialchars($row['message']) ?></p>
                        <small><?= date('M d, Y H:i', strtotime($row['submitted_at'])) ?></small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No affirmations sent yet.</p>
            <?php endif; ?>

            <!-- Button to trigger modal -->
            <button class="btn btn-primary mt-3" data-toggle="modal" data-target="#affirmationModal">New Affirmation</button>

            <!-- Modal Form -->
            <div class="modal fade" id="affirmationModal" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <form method="POST">
                            <div class="modal-header">
                                <h5 class="modal-title">New Affirmation</h5>
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>
                            <div class="modal-body">
                                <div class="form-group">
                                    <label>Recipient</label>
                                    <select name="recipient_id" class="form-control" required>
                                        <?php foreach ($staffList as $staff): ?>
                                            <option value="<?= $staff['id'] ?>"><?= htmlspecialchars($staff['email']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Subject</label>
                                    <input type="text" name="subject" class="form-control" placeholder="Subject" required>
                                </div>
                                <div class="form-group">
                                    <label>Message</label>
                                    <!-- ✅ Added word counter -->
                                    <textarea name="message" id="message" class="form-control" rows="3" placeholder="Write your message (max 250 words)" required></textarea>
                                    <small id="wordCount" class="text-muted">0 / 250 words</small>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-primary" type="submit">Send</button>
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column: Current Cycle -->
        <div class="col-lg-5">
            <h5>Current Cycle</h5>
            <p>Status: <?= ($currentCycle['is_active'] ?? 0) ? 'OPEN' : 'CLOSED' ?></p>

            <?php if ($cycle_id): ?>
                <?php
                $start = strtotime($currentCycle['start_date']);
                $end = strtotime($currentCycle['end_date']);
                $now = time();
                $progress = $now > $end ? 100 : (($now - $start)/($end - $start) * 100);
                $progress = max(0, min(100, $progress));
                $remaining = max(0, $end - $now);
                $days = floor($remaining / 86400);
                $hours = floor(($remaining % 86400)/3600);
                $minutes = floor(($remaining % 3600)/60);
                ?>
                <div class="progress mb-2">
                    <div class="progress-bar bg-info" style="width: <?= $progress ?>%;"></div>
                </div>
                <p>Time Remaining: <?= $days ?>d <?= $hours ?>h <?= $minutes ?>m</p>
            <?php else: ?>
                <p>No active cycle</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ✅ JS Word Counter -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const messageBox = document.getElementById("message");
    const wordCount = document.getElementById("wordCount");

    messageBox.addEventListener("input", function () {
        let words = messageBox.value.trim().split(/\s+/).filter(word => word.length > 0);
        let count = words.length;

        if (count > 250) {
            // Trim extra words
            messageBox.value = words.slice(0, 250).join(" ");
            count = 250;
        }

        wordCount.textContent = count + " / 250 words";
    });
});
</script>

<?php include 'footer.php'; ?>
