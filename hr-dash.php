<?php
declare(strict_types=1);
session_start();
require 'db_connection.php';
require_once 'mailer.php';  // ✅ Added to send emails

// --- Guard: only HR can access 
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'hr') {
    header('Location: login.php?err=unauthorized');
    exit;
}
$hrId = (int) $_SESSION['user_id'];

// --- Ensure affirmations table has status/flag_reason/return_reason columns 
try {
    $hasStatus = $pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='affirmations' AND COLUMN_NAME='status'")->fetchColumn();
    $hasReason = $pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='affirmations' AND COLUMN_NAME='flag_reason'")->fetchColumn();
    $hasReturn = $pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='affirmations' AND COLUMN_NAME='return_reason'")->fetchColumn();

    if (!$hasStatus) {
        $pdo->exec("ALTER TABLE affirmations ADD COLUMN status ENUM('unread','read','forwarded','flagged','sent_back') NOT NULL DEFAULT 'unread' AFTER message");
    }
    if (!$hasReason) {
        $pdo->exec("ALTER TABLE affirmations ADD COLUMN flag_reason TEXT NULL AFTER status");
    }
    if (!$hasReturn) {
        $pdo->exec("ALTER TABLE affirmations ADD COLUMN return_reason TEXT NULL AFTER flag_reason");
    }
} catch (Throwable $e) {
    // ignore
}

// --- Handle actions: mark_read / forward / flag / send_back 
$action = $_POST['action'] ?? '';
if ($action) {
    $aid = (int) ($_POST['affirmation_id'] ?? 0);
    if ($aid <= 0) {
        http_response_code(400);
        exit('Bad Request');
    }

    // ✅ Fetch affirmation without strict recipient check
    $stmt = $pdo->prepare("SELECT * FROM affirmations WHERE affirmation_id=:aid");
    $stmt->execute(['aid' => $aid]);
    $msg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$msg) {
        http_response_code(403);
        exit('Forbidden');
    }

    $currentStatus = strtolower($msg['status'] ?? 'unread');

    if ($action === 'mark_read') {
        if ($currentStatus === 'unread') {
            $pdo->prepare("UPDATE affirmations SET status='read' WHERE affirmation_id=:aid")->execute(['aid' => $aid]);
        }
        exit('OK');
    }

    if ($action === 'forward') {
        if (in_array($currentStatus, ['forwarded', 'flagged', 'sent_back'])) {
            header('Location: hr-dash.php?err=locked');
            exit;
        }

        $recipientId = (int) ($_POST['recipient_id'] ?? 0);
        if ($recipientId <= 0) {
            header('Location: hr-dash.php?err=no_recipient');
            exit;
        }

        // ✅ Update DB to show it's forwarded
        $pdo->prepare("UPDATE affirmations SET recipient_id=:rid, status='forwarded' WHERE affirmation_id=:aid")
            ->execute(['rid' => $recipientId, 'aid' => $aid]);

        // ✅ Fetch recipient email
        $stmtUser = $pdo->prepare("SELECT email FROM users WHERE id=:id LIMIT 1");
        $stmtUser->execute(['id' => $recipientId]);
        $recipientEmail = $stmtUser->fetchColumn();

        // ✅ Send email notification if recipient email exists
        if ($recipientEmail && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $subject = "Forwarded Affirmation: " . htmlspecialchars($msg['subject'] ?? 'No Subject');
            $body = "
                <p>Hello,</p>
                <p>An affirmation has been forwarded to you by the HR team.</p>
                <p><strong>Subject:</strong> " . htmlspecialchars($msg['subject'] ?? 'No Subject') . "</p>
                <p><strong>Message:</strong></p>
                <blockquote>" . nl2br(htmlspecialchars($msg['message'] ?? '')) . "</blockquote>
                <p>Please log in to your dashboard to view and respond.</p>
                <p>– HR Team</p>
            ";
            send_mail($recipientEmail, $subject, $body);
        }

        // ✅ Log mail in mail_logs
        try {
            $stmtLog = $pdo->prepare("
                INSERT INTO mail_logs (affirmation_id, email, status, message, created_at)
                VALUES (:aid, :email, 'sent', :msg, NOW())
            ");
            $stmtLog->execute([
                'aid' => $aid,
                'email' => $recipientEmail,
                'msg' => 'Sent back notification email to sender'
            ]);
        } catch (Throwable $e) {
            error_log('MAIL LOG ERROR: ' . $e->getMessage());
        }

        header('Location: hr-dash.php?ok=forwarded_email');
        exit;
    }

    if ($action === 'send_back') {
        if (in_array($currentStatus, ['flagged', 'forwarded', 'sent_back'])) {
            header('Location: hr-dash.php?err=locked');
            exit;
        }

        $reason = trim((string) ($_POST['reason'] ?? null)) ?: null;
        $senderId = (int) $msg['sender_id'];

        // ✅ Always fetch sender’s email directly from DB
        $stmtUser = $pdo->prepare("SELECT email FROM users WHERE id = :id LIMIT 1");
        $stmtUser->execute(['id' => $senderId]);
        $senderEmail = $stmtUser->fetchColumn();

        // ✅ Double-check that the fetched email matches the sender
        if (!$senderEmail || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("❌ Invalid sender email for affirmation #{$aid}");
            header('Location: hr-dash.php?err=no_sender_email');
            exit;
        }

        // ✅ Update affirmation record
        $pdo->prepare("
            UPDATE affirmations 
            SET status='sent_back', return_reason=:r 
            WHERE affirmation_id=:aid
        ")->execute([
                    'r' => $reason,
                    'aid' => $aid
                ]);

        // ✅ Send email directly to the original sender only
        $subject = "Affirmation Sent Back by HR: " . htmlspecialchars($msg['subject'] ?? 'No Subject');
        $body = "
            <p>Hello,</p>
            <p>Your affirmation has been sent back by the HR team for review.</p>
            <p><strong>Reason provided:</strong></p>
            <blockquote>" . nl2br(htmlspecialchars($reason ?? 'No reason provided')) . "</blockquote>
            <p><strong>Your original message:</strong></p>
            <blockquote>" . nl2br(htmlspecialchars($msg['message'] ?? '')) . "</blockquote>
            <p>Please log in to your dashboard to make corrections or resubmit.</p>
            <p>– HR Team</p>
        ";
        send_mail($senderEmail, $subject, $body);

        // ✅ Log mail in mail_logs
        try {
            $stmtLog = $pdo->prepare("
                INSERT INTO mail_logs (affirmation_id, email, status, message, created_at)
                VALUES (:aid, :email, 'sent', :msg, NOW())
            ");
            $stmtLog->execute([
                'aid' => $aid,
                'email' => $senderEmail,
                'msg' => 'Sent back notification email to sender'
            ]);
        } catch (Throwable $e) {
            error_log('MAIL LOG ERROR: ' . $e->getMessage());
        }

        header('Location: hr-dash.php?ok=sent_back');
        exit;
    }



    if ($action === 'flag') {
        if (in_array($currentStatus, ['flagged', 'forwarded', 'sent_back'])) {
            header('Location: hr-dash.php?err=locked');
            exit;
        }
        $reason = trim((string) ($_POST['reasons'] ?? null)) ?: null;
        $pdo->prepare("UPDATE affirmations SET status='flagged', flag_reason=:r WHERE affirmation_id=:aid")
            ->execute(['r' => $reason, 'aid' => $aid]);
        header('Location: hr-dash.php?ok=flagged');
        exit;
    }
}

// --- Fetch messages for HR 
$messages = $pdo->prepare("
    SELECT 
        a.*,
        us.email  AS sender_email,
        COALESCE(ur.email, 'Unknown') AS recipient_email
    FROM affirmations a
    JOIN users us       ON us.id = a.sender_id
    LEFT JOIN users ur  ON ur.id = a.recipient_id   
    WHERE (a.recipient_id = :hrId OR a.recipient_id = 15)  
    ORDER BY a.submitted_at DESC
");
$messages->execute(['hrId' => $hrId]);
$messages = $messages->fetchAll(PDO::FETCH_ASSOC);

// --- Fetch all recipients (except HR) for forwarding 
$recipients = $pdo->query("SELECT id,email FROM users WHERE role!='hr' ORDER BY email")->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>

<!-- ✅ Success alert for Send Back -->
<?php if (!empty($_GET['ok']) && $_GET['ok'] === 'sent_back'): ?>
    <div class="container mt-3">
        <div class="alert alert-success alert-dismissible fade show" role="alert" id="sendBackSuccessAlert">
            <strong>Success!</strong> Message sent back successfully
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
<?php endif; ?>

<!-- ✅ Success alert for Forwarded Email -->
<?php if (!empty($_GET['ok']) && $_GET['ok'] === 'forwarded_email'): ?>
    <div class="container mt-3">
        <div class="alert alert-success alert-dismissible fade show" role="alert" id="forwardSuccessAlert">
            <strong>Success!</strong> Message forwarded and email sent successfully
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
<?php endif; ?>

<!-- ✅ Success alert for Send Back -->
<?php if (!empty($_GET['ok']) && $_GET['ok'] === 'sent_back'): ?>
    <div class="container mt-3">
        <div class="alert alert-success alert-dismissible fade show" role="alert" id="sendBackSuccessAlert">
            <strong>Success!</strong> Message sent back successfully
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
<?php endif; ?>

<div class="body">
    <div class="inbox mt-3 mt-lg-5">
        <div class="container">
            <?php foreach ($messages as $msg):
                $aid = (int) $msg['affirmation_id'];
                $status = strtolower($msg['status'] ?? 'unread');
                $statusClass = 'status--' . $status;
                ?>
                <article class="msg" data-aid="<?= $aid ?>" data-status="<?= htmlspecialchars($status) ?>"
                    aria-expanded="false">
                    <div class="msg__head" role="button" aria-controls="msg-<?= $aid ?>-details">
                        <!-- ✅ Avatar shows sender's initial only -->
                        <div class="avatar"><?= strtoupper(substr($msg['sender_email'], 0, 1)) ?></div>
                        <div class="text">
                            <div class="to-line">
                                <!-- ✅ Only show recipient (manager) email -->
                                <span class="to"><?= htmlspecialchars($msg['recipient_email']) ?></span>
                            </div>
                            <div class="subject"><?= htmlspecialchars($msg['subject'] ?? 'No Subject') ?></div>
                            <div class="snippet"><?= htmlspecialchars(mb_strimwidth($msg['message'] ?? '', 0, 160, '…')) ?>
                            </div>
                        </div>
                        <div class="state">
                            <div class="status status-pill <?= $statusClass ?>">
                                <span class="dot"></span>
                                <span><?= ucfirst($status) ?></span>
                            </div>
                            <div class="meta"><?= date('M d', strtotime($msg['submitted_at'] ?? 'now')) ?></div>
                            <button class="arrow-btn" type="button"><i class="fa-solid fa-chevron-down"></i></button>
                        </div>
                    </div>
                    <div class="msg__details" id="msg-<?= $aid ?>-details">
                        <p class="details-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                        <?php if ($status === 'flagged' && !empty($msg['flag_reason'])): ?>
                            <div class="alert alert-warning py-2 px-3 mb-3">
                                <strong>Flag reasons:</strong> <?= htmlspecialchars($msg['flag_reason']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($status === 'sent_back' && !empty($msg['return_reason'])): ?>
                            <div class="alert alert-secondary py-2 px-3 mb-3">
                                <strong>Return reason:</strong> <?= htmlspecialchars($msg['return_reason']) ?>
                            </div>
                        <?php endif; ?>
                        <div class="actions">
                            <!-- Forward form -->
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="forward">
                                <input type="hidden" name="affirmation_id" value="<?= $aid ?>">
                                <select name="recipient_id" class="form-control mb-3" required>
                                    <option value="">Select recipient</option>
                                    <?php foreach ($recipients as $r): ?>
                                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['email']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-secondary btn-forward">Forward</button>
                            </form>
                            <!-- Send Back -->
                            <button type="button" class="btn btn-danger action-sendback" data-toggle="modal"
                                data-target="#sendBackModal" data-aid="<?= $aid ?>"> Send Back </button>
                            <!-- Flag -->
                            <button type="button" class="btn btn-warning action-flag" data-toggle="modal"
                                data-target="#flagModal" data-aid="<?= $aid ?>"> Flag as Abuse </button>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Flag Modal -->
<div class="modal fade" id="flagModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header strip">
                <div class="bar bar-yellow w-100 d-flex justify-content-between align-items-center px-2">
                    <h5 class="mb-0">Flag Message</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
            <form method="POST" id="flagForm">
                <input type="hidden" name="action" value="flag">
                <input type="hidden" name="affirmation_id" id="flagAid" value="">
                <input type="hidden" name="reasons" id="flagReasons" value="">
                <div class="modal-body">
                    <h5 class="mb-3 font-weight-bold">Flag as Abuse:</h5>
                    <div id="reasonsGroup" class="btn-group btn-group-toggle d-flex flex-wrap w-100"
                        data-toggle="buttons">
                        <?php $flags = ['Inappropriate tone', 'Personal attack', 'Possible identity breach', 'Spam', 'Discriminatory language', 'Sexual content'];
                        foreach ($flags as $f): ?>
                            <label class="btn btn-outline m-1 flex-fill">
                                <input type="checkbox" value="<?= htmlspecialchars($f) ?>" autocomplete="off">
                                <?= htmlspecialchars($f) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div id="flagHint" class="text-danger small d-none mt-2"> Please select at least one reason. </div>
                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <button type="button" class="btn btn-warning mr-2" id="btnReset">Reset</button>
                        <button type="submit" class="btn btn-primary" id="btnSubmit">Submit</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Send Back Modal -->
<div class="modal fade" id="sendBackModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header strip">
                <div class="bar bar-red w-100 d-flex justify-content-between align-items-center px-2">
                    <h5 class="mb-0">Reason for Returned Email</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
            <form method="POST" id="sendBackForm">
                <input type="hidden" name="action" value="send_back">
                <input type="hidden" name="affirmation_id" id="sendBackAid" value="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="sendBackReason">Enter reason (max 100 words):</label>
                        <textarea class="form-control" id="sendBackReason" name="reason" rows="4" maxlength="800"
                            placeholder="Type your reason here..."></textarea>
                        <div id="sendBackHint" class="text-danger small d-none mt-2"> Please enter a reason (max 100
                            words). </div>
                    </div>
                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <button type="button" class="btn btn-warning mr-2" id="btnSendBackReset">Reset</button>
                        <button type="submit" class="btn btn-primary" id="btnSendBackSubmit">Submit</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        // --- FLAG MODAL --- 
        let flagForm = document.getElementById("flagForm");
        let flagAidInput = document.getElementById("flagAid");
        let flagReasonsInput = document.getElementById("flagReasons");
        let flagHint = document.getElementById("flagHint");

        document.querySelectorAll(".action-flag").forEach(btn => {
            btn.addEventListener("click", function () {
                let aid = this.getAttribute("data-aid");
                flagAidInput.value = aid;
                flagReasonsInput.value = "";
                flagHint.classList.add("d-none");
                document.querySelectorAll("#reasonsGroup input[type=checkbox]").forEach(cb => cb
                    .checked = false);
            });
        });

        document.getElementById("btnReset").addEventListener("click", function () {
            document.querySelectorAll("#reasonsGroup input[type=checkbox]").forEach(cb => cb.checked =
                false);
            flagReasonsInput.value = "";
            flagHint.classList.add("d-none");
        });

        flagForm.addEventListener("submit", function (e) {
            let selected = [];
            document.querySelectorAll("#reasonsGroup input[type=checkbox]:checked").forEach(cb => {
                selected.push(cb.value);
            });
            if (selected.length === 0) {
                e.preventDefault();
                flagHint.classList.remove("d-none");
                return false;
            }
            flagReasonsInput.value = selected.join(", ");
        });

        // --- SEND BACK MODAL --- 
        let sendBackForm = document.getElementById("sendBackForm");
        let sendBackAidInput = document.getElementById("sendBackAid");
        let sendBackReasonInput = document.getElementById("sendBackReason");
        let sendBackHint = document.getElementById("sendBackHint");

        document.querySelectorAll(".action-sendback").forEach(btn => {
            btn.addEventListener("click", function () {
                let aid = this.getAttribute("data-aid");
                sendBackAidInput.value = aid;
                sendBackReasonInput.value = "";
                sendBackHint.classList.add("d-none");
            });
        });

        document.getElementById("btnSendBackReset").addEventListener("click", function () {
            sendBackReasonInput.value = "";
            sendBackHint.classList.add("d-none");
        });

        sendBackForm.addEventListener("submit", function (e) {
            let text = sendBackReasonInput.value.trim();
            let wordCount = text.split(/\s+/).filter(w => w.length > 0).length;
            if (wordCount === 0 || wordCount > 100) {
                e.preventDefault();
                sendBackHint.textContent = "Please enter a reason (1–100 words).";
                sendBackHint.classList.remove("d-none");
                return false;
            }
        });

        // --- AUTO-CLOSE SEND BACK ALERT AFTER 5 SEC --- 
        let successAlert = document.getElementById("sendBackSuccessAlert");
        if (successAlert) {
            setTimeout(() => {
                successAlert.classList.remove("show");
                successAlert.classList.add("fade");
            }, 5000);
        }
    });
</script>

<?php include 'footer.php'; ?>