<?php
session_start();
include 'db_connection.php';

// --- Guard: logged-in user
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$logged_in_user_id = (int) $_SESSION['user_id'];

// --- Fetch current active cycle
$stmt = $pdo->query("SELECT * FROM affirmation_cycle WHERE is_active=1 LIMIT 1");
$currentCycle = $stmt->fetch();
$cycle_id = $currentCycle['cycle_id'] ?? null;

$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient_id = isset($_POST['recipient_id']) && $_POST['recipient_id'] !== ''
        ? (int) $_POST['recipient_id']
        : 15;

    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    if ($recipient_id !== 15) {
        $stmtChk = $pdo->prepare("
            SELECT role
            FROM users
            WHERE id = :rid
            LIMIT 1
        ");
        $stmtChk->execute(['rid' => $recipient_id]);
        $role = $stmtChk->fetchColumn();

        $allowed = ['user', 'manager', 'hr'];
        if (!$role || !in_array($role, $allowed, true)) {
            $recipient_id = 15;
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO affirmations (sender_id, recipient_id, cycle_id, subject, message)
        VALUES (:sender_id, :recipient_id, :cycle_id, :subject, :message)
    ");
    $stmt->execute([
        'sender_id' => $logged_in_user_id,
        'recipient_id' => $recipient_id,
        'cycle_id' => $cycle_id,
        'subject' => $subject,
        'message' => $message
    ]);

    $success = true;
}


// --- Fetch affirmation history (only sent by this user)
$affirmations = [];
if ($cycle_id) {
    $sql = "SELECT a.*, u1.email AS sender_email, u2.email AS recipient_email
            FROM affirmations a
            JOIN users u1 ON a.sender_id = u1.id
            JOIN users u2 ON a.recipient_id = u2.id
            WHERE a.cycle_id = :cycle_id
              AND a.sender_id = :sender_id
            ORDER BY a.submitted_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'cycle_id' => $cycle_id,
        'sender_id' => $logged_in_user_id
    ]);
    $affirmations = $stmt->fetchAll();
}

// --- Fetch HR users
$hrStmt = $pdo->prepare("SELECT * FROM users WHERE role='hr'");
$hrStmt->execute();
$hrList = $hrStmt->fetchAll();

// --- Fetch all managers except self
$allManagersStmt = $pdo->prepare("SELECT * FROM users WHERE role='manager' AND id != :user_id");
$allManagersStmt->execute(['user_id' => $logged_in_user_id]);
$allManagers = $allManagersStmt->fetchAll();

// --- Fetch assigned managers (optional display in dropdown)
$assignedManagerStmt = $pdo->prepare("
    SELECT u.* 
    FROM users u
    JOIN manager_staff ms ON u.id = ms.manager_id
    WHERE ms.staff_id = :staff_id
");
$assignedManagerStmt->execute(['staff_id' => $logged_in_user_id]);
$assignedManagers = $assignedManagerStmt->fetchAll();

// --- Separate unassigned managers for dropdown
$assignedIds = array_column($assignedManagers, 'id');
$otherManagers = array_filter($allManagers, function ($mgr) use ($assignedIds) {
    return !in_array($mgr['id'], $assignedIds);
});
?>

<?php include 'header.php'; ?>

<div class="container py-5">
    <div class="row">
        <!-- Left Column: Affirmation History -->
        <div class="col-lg-7 order-2 order-lg-1">
            <div class="aff-content">
                <!-- Button to trigger modal -->
                <button class="btn btn-primary mb-3" data-toggle="modal" data-target="#affirmationModal">New
                    Affirmation</button>

                <!-- Success message -->
                <?php if ($success): ?>
                    <div class="alert alert-success">Affirmation sent successfully!</div>
                <?php endif; ?>

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
            </div>

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
                                    <label>Department</label>
                                    <select id="departmentSelect" class="form-control" required>
                                        <option value="">Select Department</option>
                                        <?php
                                        $deptStmt = $pdo->query("SELECT DISTINCT department FROM manager_staff ORDER BY department ASC");
                                        while ($dept = $deptStmt->fetch(PDO::FETCH_ASSOC)) {
                                            echo '<option value="' . htmlspecialchars($dept['department']) . '">' . $dept['department'] . '</option>';
                                        }
                                        ?>
                                        <option value="unknown">Not sure / Unknown department</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Recipient</label>
                                    <select name="recipient_id" id="recipientSelect" class="form-control" required>
                                        <option value="">Select Recipient</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Subject</label>
                                    <input type="text" name="subject" class="form-control" placeholder="Subject"
                                        required>
                                </div>
                                <div class="form-group">
                                    <label>Message</label>
                                    <textarea name="message" class="form-control" rows="3"
                                        placeholder="Write your message" required></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" data-dismiss="modal">Cancel</button>
                                <button class="btn btn-secondary" type="submit">Send</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Current Cycle -->
        <div class="col-lg-5 mb-4 mb-lg-0 order-1 order-lg-2">
            <div class="cycle open">
                <h5>Current Cycle</h5>
                <p>Status: <?= ($currentCycle['is_active'] ?? 0) ? 'OPEN' : 'CLOSED' ?></p>

                <?php if ($cycle_id): ?>
                    <?php
                    $start = strtotime($currentCycle['start_date']);
                    $end = strtotime($currentCycle['end_date']);
                    $now = time();
                    $progress = $now > $end ? 100 : (($now - $start) / ($end - $start) * 100);
                    $progress = max(0, min(100, $progress));
                    $remaining = max(0, $end - $now);
                    $days = floor($remaining / 86400);
                    $hours = floor(($remaining % 86400) / 3600);
                    $minutes = floor(($remaining % 3600) / 60);
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
</div>

<script>
    document.getElementById('departmentSelect').addEventListener('change', function () {
        const dept = this.value;
        const recipientSelect = document.getElementById('recipientSelect');
        recipientSelect.innerHTML = '<option>Loading...</option>';

        if (dept === 'unknown') {
            recipientSelect.innerHTML = '<option value="">Recipient not specified</option>';
            recipientSelect.disabled = true;
            return; // 不再加载staff列表
        } else {
            recipientSelect.disabled = false;
        }

        if (dept) {
            fetch('get_staff_by_department.php?department=' + encodeURIComponent(dept))
                .then(response => response.json())
                .then(data => {
                    recipientSelect.innerHTML = '<option value="">Select Recipient</option>';
                    if (data.length > 0) {
                        data.forEach(user => {
                            const option = document.createElement('option');
                            option.value = user.id;
                            option.textContent = user.email;
                            recipientSelect.appendChild(option);
                        });
                    } else {
                        recipientSelect.innerHTML = '<option value="">No recipients found</option>';
                    }
                })
                .catch(() => {
                    recipientSelect.innerHTML = '<option value="">Error loading</option>';
                });
        } else {
            recipientSelect.innerHTML = '<option value="">Select Recipient</option>';
        }
    });
</script>


<?php include 'footer.php'; ?>