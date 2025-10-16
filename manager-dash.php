<?php
// manager-dash.php (with audit_logs writes and single-user forward)
// -----------------------------------------------------------------------------

declare(strict_types=1);

session_start();
require 'db_connection.php';
require_once 'mailer.php';

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'manager') {
    header('Location: login.php?err=unauthorized');
    exit;
}
$managerId = (int) $_SESSION['user_id'];

/* -------------------- one-off safety: ensure required columns -------------- */
try {
    $hasStatus = $pdo->query("
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='affirmations' AND COLUMN_NAME='status'
    ")->fetchColumn();

    $hasReason = $pdo->query("
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='affirmations' AND COLUMN_NAME='flag_reason'
    ")->fetchColumn();

    if (!$hasStatus) {
        $pdo->exec("
            ALTER TABLE affirmations
            ADD COLUMN status ENUM('unread','read','forwarded','flagged') NOT NULL DEFAULT 'unread' AFTER message
        ");
    }
    if (!$hasReason) {
        $pdo->exec("
            ALTER TABLE affirmations
            ADD COLUMN flag_reason TEXT NULL AFTER status
        ");
    }
} catch (Throwable $e) {
    // ignore; page should keep running even if this fails
}

/* -------------------- helpers --------------------------------------------- */
function assertTeamAffirmation(PDO $pdo, int $managerId, int $aid): void
{
    $sql = "
        SELECT 1
        FROM affirmations a
        JOIN manager_staff ms_r
          ON ms_r.staff_id = a.recipient_id
         AND ms_r.manager_id = :mid_rec
        LEFT JOIN manager_staff ms_s
          ON ms_s.staff_id = a.sender_id
         AND ms_s.manager_id = :mid_send
        WHERE a.affirmation_id = :aid
          AND ms_s.staff_id IS NULL
        LIMIT 1
    ";
    $st = $pdo->prepare($sql);
    $st->execute([
        'aid' => $aid,
        'mid_rec' => $managerId,
        'mid_send' => $managerId,
    ]);
    if (!$st->fetchColumn()) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function audit_log(PDO $pdo, int $affirmationId, int $actorUserId, string $action, ?string $reason = null): void
{
    $recip = $pdo->prepare("SELECT recipient_id FROM affirmations WHERE affirmation_id = ?");
    $recip->execute([$affirmationId]);
    $targetUserId = $recip->fetchColumn();
    if ($targetUserId !== false) {
        $targetUserId = (int) $targetUserId;
    } else {
        $targetUserId = null;
    }

    $ins = $pdo->prepare("
        INSERT INTO audit_logs (affirmation_id, action, actor_user_id, target_user_id, reason, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $ins->execute([$affirmationId, $action, $actorUserId, $targetUserId, $reason]);
}

/* -------------------- POST actions (mutations) ----------------------------- */
$action = $_POST['action'] ?? '';
if ($action) {
    $aid = (int) ($_POST['affirmation_id'] ?? 0);
    if ($aid <= 0) {
        http_response_code(400);
        exit('Bad Request');
    }
    assertTeamAffirmation($pdo, $managerId, $aid);

    if ($action === 'mark_read') {
        $st = $pdo->prepare("UPDATE affirmations SET status='read' WHERE affirmation_id=:aid AND status='unread'");
        $st->execute(['aid' => $aid]);
        if ($st->rowCount() > 0) {
            audit_log($pdo, $aid, $managerId, 'read', null);
        }
        exit('OK');
    }

    if ($action === 'forward') {
        $recipientId = (int) ($_POST['forward_recipient'] ?? 0);
        if ($recipientId <= 0) {
            header('Location: manager-dash.php?err=no_recipient');
            exit;
        }

        $st = $pdo->prepare("UPDATE affirmations SET status='forwarded' WHERE affirmation_id=:aid");
        $st->execute(['aid' => $aid]);

        audit_log($pdo, $aid, $managerId, 'forwarded', null);

        $stmtAff = $pdo->prepare("SELECT subject, message FROM affirmations WHERE affirmation_id = :aid");
        $stmtAff->execute(['aid' => $aid]);
        $msg = $stmtAff->fetch(PDO::FETCH_ASSOC);

        if ($msg) {
            $stmtUser = $pdo->prepare("SELECT email FROM users WHERE id=:id LIMIT 1");
            $stmtUser->execute(['id' => $recipientId]);
            $recipientEmail = $stmtUser->fetchColumn();

            if ($recipientEmail && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                $subject = "Forwarded Affirmation: " . htmlspecialchars($msg['subject'] ?? 'No Subject');
                $body = "
                    <p>Hello,</p>
                    <p>An affirmation has been forwarded to you by your manager.</p>
                    <p><strong>Subject:</strong> " . htmlspecialchars($msg['subject'] ?? 'No Subject') . "</p>
                    <p><strong>Message:</strong></p>
                    <blockquote>" . nl2br(htmlspecialchars($msg['message'] ?? '')) . "</blockquote>
                    <p>Please log in to your dashboard to view and respond.</p>
                    <p>– manager</p>
                ";
                send_mail($recipientEmail, $subject, $body);
            }
        }

        header('Location: manager-dash.php?ok=forwarded');
        exit;
    }

    if ($action === 'flag') {
        $reasons = trim((string) ($_POST['reasons'] ?? '')) ?: null;
        $st = $pdo->prepare("UPDATE affirmations SET status='flagged', flag_reason=:r WHERE affirmation_id=:aid");
        $st->execute(['aid' => $aid, 'r' => $reasons]);

        audit_log($pdo, $aid, $managerId, 'flagged', $reasons);

        header('Location: manager-dash.php?ok=flagged');
        exit;
    }

    if ($action === 'delete') {
        try {
            $pdo->beginTransaction();

            audit_log($pdo, $aid, $managerId, 'deleted', null);

            $pdo->prepare("DELETE FROM mail_logs WHERE affirmation_id = :aid")->execute(['aid' => $aid]);
            $pdo->prepare("DELETE FROM audit_logs WHERE affirmation_id = :aid")->execute(['aid' => $aid]);
            $pdo->prepare("DELETE FROM affirmations WHERE affirmation_id = :aid")->execute(['aid' => $aid]);

            $pdo->commit();
            header('Location: manager-dash.php?ok=deleted');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction())
                $pdo->rollBack();
            http_response_code(400);
            echo "Delete failed: " . htmlspecialchars($e->getMessage());
            exit;
        }
    }
}

/* -------------------- Query: team members & affirmations ------------------- */
$st = $pdo->prepare("
  SELECT u.id, u.email, ms.department
  FROM manager_staff ms
  JOIN users u ON u.id = ms.staff_id
  WHERE ms.manager_id = :mid
  ORDER BY u.email
");
$st->execute(['mid' => $managerId]);
$teamMembers = $st->fetchAll(PDO::FETCH_ASSOC);

$st = $pdo->prepare("
  SELECT a.*, ur.email AS recipient_email
  FROM affirmations a
  JOIN users ur ON ur.id = a.recipient_id
  JOIN manager_staff ms_r
    ON ms_r.staff_id = a.recipient_id
   AND ms_r.manager_id = :mid_rec
  LEFT JOIN manager_staff ms_s
    ON ms_s.staff_id = a.sender_id
   AND ms_s.manager_id = :mid_send
  WHERE ms_s.staff_id IS NULL
  ORDER BY a.submitted_at DESC
");
$st->execute([
    'mid_rec' => $managerId,
    'mid_send' => $managerId,
]);
$teamAffirmations = $st->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>

<div class="body">
    <div class="container mt-3">
        <?php if ($teamMembers): ?>
            <div class="small text-muted mb-3">
                Team:
                <?php foreach ($teamMembers as $i => $m): ?>
                    <?= $i > 0 ? ' , ' : '' ?><?= htmlspecialchars($m['email']) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="inbox mt-3 mt-lg-4">
        <div class="container">
            <?php foreach ($teamAffirmations as $row):
                $aid = (int) $row['affirmation_id'];
                $status = strtolower($row['status'] ?: 'unread');
                $statusClass = 'status--' . $status;
            ?>
                <article class="msg" data-aid="<?= $aid ?>" data-status="<?= htmlspecialchars($status) ?>"
                    aria-expanded="false">
                    <div class="msg__head" role="button">
                        <div class="avatar"><?= strtoupper(substr($row['recipient_email'], 0, 1)) ?></div>
                        <div class="text">
                            <div class="to-line">
                                to <span><?= htmlspecialchars($row['recipient_email']) ?></span>
                                <div class="status status-pill <?= $statusClass ?>" title="<?= ucfirst($status) ?>">
                                    <span class="dot"></span><span><?= ucfirst($status) ?></span>
                                </div>
                            </div>
                            <div class="subject"><?= htmlspecialchars($row['subject']) ?></div>
                            <div class="snippet"><?= htmlspecialchars(mb_strimwidth($row['message'], 0, 160, '…')) ?></div>
                        </div>
                        <div class="state">
                            <div class="meta">
                                <i class="fa-solid fa-clock"></i>
                                <span><?= date('M d', strtotime($row['submitted_at'])) ?></span>
                            </div>
                            <button type="button" class="icon-trash" title="Delete" data-toggle="modal"
                                data-target="#confirmDelete" data-aid="<?= $aid ?>">
                                <svg viewBox="0 0 24 24" class="trash-svg">
                                    <path
                                        d="M9 3h6a1 1 0 0 1 1 1v1h4a1 1 0 1 1 0 2h-1.1l-1.1 12.1A3 3 0 0 1 14.81 23H9.19a3 3 0 0 1-2.99-2.9L5.1 7H4a1 1 0 1 1 0-2h4V4a1 1 0 0 1 1-1Z" />
                                </svg>
                            </button>
                            <button class="arrow-btn" type="button"><i class="fa-solid fa-chevron-down"></i></button>
                        </div>
                    </div>

                    <div class="msg__details" id="msg-<?= $aid ?>-details">
                        <p class="details-text"><?= nl2br(htmlspecialchars($row['message'])) ?></p>

                        <?php if ($status === 'flagged' && !empty($row['flag_reason'])): ?>
                            <div class="alert alert-warning py-2 px-3 mb-3">
                                <strong>Flag reasons:</strong> <?= htmlspecialchars($row['flag_reason']) ?>
                            </div>
                        <?php endif; ?>

                        <div class="actions">
                            <button class="btn btn-warning action-flag" type="button" data-toggle="modal"
                                data-target="#flagModal" data-aid="<?= $aid ?>">
                                Flag as Abuse
                            </button>

                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="forward">
                                <input type="hidden" name="affirmation_id" value="<?= $aid ?>">

                                <div class="form-group my-2">
                                    <label for="forwardRecipient-<?= $aid ?>">Send to:</label>
                                    <select name="forward_recipient" id="forwardRecipient-<?= $aid ?>" class="form-control"
                                        required>
                                        <option value="" disabled selected>Select a user</option>
                                        <?php foreach ($teamMembers as $m): ?>
                                            <option value="<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['email']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-secondary btn-forward">Forward</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Flag modal -->
    <div class="modal fade" id="flagModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <form method="POST" id="flagForm">
                    <input type="hidden" name="action" value="flag">
                    <input type="hidden" name="affirmation_id" id="flagAid" value="">
                    <input type="hidden" name="reasons" id="flagReasons" value="">
                    <div class="modal-body">
                        <h5 class="mb-3">Flag as Abuse:</h5>
                        <div id="reasonsGroup" class="btn-group btn-group-toggle d-flex flex-wrap w-100"
                            data-toggle="buttons">
                            <?php
                            $flags = ['Inappropriate tone', 'Personal attack', 'Possible identity breach', 'Spam', 'Discriminatory language', 'Sexual content'];
                            foreach ($flags as $f): ?>
                                <label class="btn btn-outline m-1 flex-fill">
                                    <input type="checkbox" value="<?= htmlspecialchars($f) ?>"> <?= htmlspecialchars($f) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div id="flagHint" class="text-danger small d-none mt-2">Please select at least one reason.
                        </div>
                        <div class="d-flex justify-content-center gap-2 mt-4">
                            <button type="button" class="btn btn-warning mr-2" id="btnReset">Reset</button>
                            <button type="submit" class="btn btn-primary">Submit</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Confirm Delete modal -->
    <div class="modal fade" id="confirmDelete" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="affirmation_id" id="delAid" value="">
                <div class="modal-body text-center">
                    <h5>Delete this message?</h5>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Yes, delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
    $('#flagModal').on('show.bs.modal', function(ev) {
        var btn = ev.relatedTarget;
        var aid = btn && btn.getAttribute('data-aid');
        document.getElementById('flagAid').value = aid || '';
        $('#reasonsGroup input[type=checkbox]').prop('checked', false).parent().removeClass('active');
        document.getElementById('flagHint').classList.add('d-none');
    });

    document.getElementById('flagForm').addEventListener('submit', function(e) {
        var checks = Array.from(document.querySelectorAll('#reasonsGroup input[type=checkbox]:checked'));
        if (!checks.length) {
            e.preventDefault();
            document.getElementById('flagHint').classList.remove('d-none');
            return false;
        }
        document.getElementById('flagReasons').value = checks.map(c => c.value).join(', ');
    });

    $('#confirmDelete').on('show.bs.modal', function(ev) {
        var btn = ev.relatedTarget;
        var aid = btn && btn.getAttribute('data-aid');
        document.getElementById('delAid').value = aid || '';
    });

    document.querySelector('.inbox').addEventListener('click', function(e) {
        var btn = e.target.closest('.arrow-btn, .msg__head');
        if (!btn) return;

        var msg = btn.closest('.msg');
        var details = msg.querySelector('.msg__details');
        var wasOpen = msg.getAttribute('aria-expanded') === 'true';
        var nowOpen = !wasOpen;

        msg.setAttribute('aria-expanded', nowOpen ? 'true' : 'false');
        details.style.display = nowOpen ? 'block' : 'none';

        var cur = (msg.dataset.status || '').toLowerCase();
        if (nowOpen && cur === 'unread') {
            if (msg.dataset.busy === '1') return;
            msg.dataset.busy = '1';

            var aid = msg.getAttribute('data-aid');

            msg.dataset.status = 'read';
            var pill = msg.querySelector('.status-pill') || msg.querySelector('.status');
            if (pill) {
                pill.classList.remove('status--unread');
                pill.classList.add('status--read');
                var textEl = pill.querySelector('span:last-child');
                if (textEl) textEl.textContent = 'Read';
            }

            fetch('manager-dash.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=mark_read&affirmation_id=' + encodeURIComponent(aid)
            }).catch(function() {
                msg.dataset.status = 'unread';
                if (pill) {
                    pill.classList.add('status--unread');
                    pill.classList.remove('status--read');
                    var textEl2 = pill.querySelector('span:last-child');
                    if (textEl2) textEl2.textContent = 'Unread';
                }
            }).finally(function() {
                delete msg.dataset.busy;
            });
        }
    });
</script>