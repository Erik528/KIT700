<?php
// === Admin Dashboard (Cycle + Audit Log) ====================================
// Notes:
// - Users filter in the Audit Log tab matches actor_user_id when numeric,
//   otherwise does a fuzzy match on users.email.
// - "Type" maps directly to audit_logs.action values.
// - CSV export for Audit Log is under ?export=audit using the same filters.
// ============================================================================

declare(strict_types=1);
session_start();
require __DIR__ . '/db_connection.php';
require __DIR__ . '/mailer.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php?err=unauthorized');
    exit;
}
$userId = (int) ($_SESSION['user_id'] ?? 0);
$role = (string) ($_SESSION['role'] ?? '');
$allowedRoles = ['admin', 'manager']; // only admin/manager can open this page
if (!in_array($role, $allowedRoles, true)) {
    header('Location: login.php?err=forbidden');
    exit;
}

date_default_timezone_set('Australia/Hobart');

/* ---------------------------- helpers ------------------------------------ */
function base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(dirname($_SERVER['PHP_SELF'] ?? '/'), '/\\') . '/';
    return $scheme . $host . $path;
}

function announce_cycle(PDO $pdo, string $type, ?string $startDate = null, ?string $endDate = null): int
{
    $submitUrl = base_url() . 'index.php';
    if ($type === 'open') {
        $subject = 'Affirmations cycle is OPEN';
        $body = 'Hello,<br><br>The collegial affirmations submission window is now <strong>OPEN</strong>'
            . ($startDate && $endDate ? (' from <strong>' . htmlspecialchars($startDate) . '</strong> to <strong>' . htmlspecialchars($endDate) . '</strong>') : '')
            . '.<br>Please submit here: <a href="' . htmlspecialchars($submitUrl) . '">' . htmlspecialchars($submitUrl) . '</a><br><br>'
            . 'Guidelines: one affirmation per cycle (subject + body).<br><br>Regards,<br>Affirmations Bot';
    } else { // close
        $subject = 'Affirmations cycle is now CLOSED';
        $body = 'Hello,<br><br>The collegial affirmations submission window has been <strong>CLOSED</strong>'
            . ($startDate && $endDate ? (' (' . htmlspecialchars($startDate) . ' → ' . htmlspecialchars($endDate) . ')') : '')
            . '.<br>You will be notified when the next window opens.<br><br>Regards,<br>Affirmations Bot';
    }

    $stmt = $pdo->query("SELECT DISTINCT email FROM users WHERE email IS NOT NULL AND email <> ''");
    $sent = 0;
    while ($email = $stmt->fetchColumn()) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if (send_mail($email, $subject, $body)) {
                $sent++;
            }
        }
    }
    return $sent;
}


function ymd_or_null(?string $s): ?string
{
    $s = trim((string) $s);
    if ($s === '')
        return null;
    $dt = date_create($s);
    return $dt ? $dt->format('Y-m-d') : null;
}
function weeks_from_dates(string $start, string $end): int
{
    $sd = new DateTime($start);
    $ed = new DateTime($end);
    $days = $sd->diff($ed)->days + 1;
    return (int) ceil($days / 7);
}
function is_open_today(string $start, string $end): bool
{
    $today = new DateTime('today');
    return ($today >= new DateTime($start) && $today <= new DateTime($end));
}
function csv_safe(string $s): string
{
    // normalize EOLs and escape quotes for RFC4180-style CSV
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = str_replace('"', '""', $s);
    return "\"{$s}\"";
}

/* ---------------------------- flash state --------------------------------- */
$flash = ['ok' => '', 'err' => ''];

/* ============================== POST actions ============================== */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- Force close current cycle (set is_active=0)
    if ($action === 'force_close_cycle') {
        try {
            $cur = $pdo->query("SELECT start_date, end_date FROM affirmation_cycle WHERE is_active=1 LIMIT 1")
                ->fetch(PDO::FETCH_ASSOC) ?: null;

            $stmt = $pdo->prepare("UPDATE affirmation_cycle SET is_active=0, updated_at=NOW() WHERE is_active=1");
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                announce_cycle($pdo, 'close', $cur['start_date'] ?? null, $cur['end_date'] ?? null);
            }

            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
        } catch (Throwable $e) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    // --- Save Cycle (creates or updates active cycle, and appends to history)
    if ($action === 'save_cycle') {
        $startDate = ymd_or_null($_POST['start_date'] ?? '');
        $weeks = (int) ($_POST['weeks'] ?? 0);

        if (!$startDate) {
            $flash['err'] = 'Invalid Start Date';
        } elseif ($weeks < 1 || $weeks > 520) {
            $flash['err'] = 'Weeks must be between 1 and 520';
        } else {
            $sd = new DateTime($startDate);
            $ed = (clone $sd)->modify('+' . ($weeks * 7 - 1) . ' days');
            $endDate = $ed->format('Y-m-d');

            try {
                $prev = $pdo->query("SELECT cycle_id,start_date,end_date,is_active 
                                 FROM affirmation_cycle WHERE is_active=1 
                                 ORDER BY cycle_id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: null;

                $prevWasActive = (bool) ($prev['is_active'] ?? 0);
                $prevOpenToday = false;
                if ($prevWasActive && !empty($prev['start_date']) && !empty($prev['end_date'])) {
                    $prevOpenToday = is_open_today($prev['start_date'], $prev['end_date']);
                }

                $pdo->beginTransaction();

                $id = $prev['cycle_id'] ?? null;
                if ($id) {
                    $stmt = $pdo->prepare("UPDATE affirmation_cycle 
                                       SET start_date=?, end_date=?, updated_at=NOW() 
                                       WHERE cycle_id=? LIMIT 1");
                    $stmt->execute([$startDate, $endDate, $id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO affirmation_cycle (start_date,end_date,is_active,created_at) 
                                       VALUES (?,?,1,NOW())");
                    $stmt->execute([$startDate, $endDate]);
                    $id = (int) $pdo->lastInsertId();
                }

                $isOpen = is_open_today($startDate, $endDate) ? 1 : 0;
                $stmt = $pdo->prepare("INSERT INTO affirmation_cycle_history 
                                   (start_date,end_date,weeks,is_open,saved_at,saved_by) 
                                   VALUES (?,?,?,?,NOW(),?)");
                $stmt->execute([$startDate, $endDate, $weeks, $isOpen, $userId]);

                $pdo->commit();
                $flash['ok'] = 'saved';

                $newOpenToday = ($isOpen === 1);

                if (!$prevWasActive && $newOpenToday) {
                    announce_cycle($pdo, 'open', $startDate, $endDate);
                }
                if ($prevWasActive && !$prevOpenToday && $newOpenToday) {
                    announce_cycle($pdo, 'open', $startDate, $endDate);
                }
                if ($prevWasActive && $prevOpenToday && !$newOpenToday) {
                    announce_cycle($pdo, 'close', $startDate, $endDate);
                }

            } catch (Throwable $e) {
                if ($pdo->inTransaction())
                    $pdo->rollBack();
                $flash['err'] = $e->getMessage();
            }
        }

        header('Content-Type: application/json');
        echo json_encode($flash['err'] ? ['ok' => false, 'msg' => $flash['err']] : ['ok' => true]);
        exit;
    }


    // --- Clear all Cycle history (truncate history table)
    if ($action === 'clear_history') {
        try {
            $pdo->exec("TRUNCATE TABLE affirmation_cycle_history");
            $flash['ok'] = 'cleared';
        } catch (Throwable $e) {
            $flash['err'] = $e->getMessage();
        }
        header('Content-Type: application/json');
        echo json_encode($flash['err'] ? ['ok' => false, 'msg' => $flash['err']] : ['ok' => true]);
        exit;
    }

    // --- Delete a single cycle history row by id
    if ($action === 'delete_history') {
        $hid = (int) ($_POST['id'] ?? 0);
        if ($hid <= 0) {
            header('Content-Type: application/json', true, 400);
            echo json_encode(['ok' => false, 'msg' => 'Invalid history id']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM affirmation_cycle_history WHERE id = ?");
            $stmt->execute([$hid]);
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
        } catch (Throwable $e) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    // --- Audit Log search (returns JSON rows from audit_logs)
    if ($action === 'audit_search') {
        $start = ymd_or_null($_POST['start'] ?? '');
        $end = ymd_or_null($_POST['end'] ?? '');
        $userQ = trim((string) ($_POST['user_q'] ?? '')); // numeric -> actor_user_id ; text -> email LIKE
        $type = trim((string) ($_POST['type'] ?? 'any'));

        $where = [];
        $args = [];

        if ($start) {
            $where[] = "al.created_at >= ?";
            $args[] = $start . " 00:00:00";
        }
        if ($end) {
            $where[] = "al.created_at <= ?";
            $args[] = $end . " 23:59:59";
        }

        if ($userQ !== '') {
            if (ctype_digit($userQ)) {
                $where[] = "al.actor_user_id = ?";
                $args[] = (int) $userQ;
            } else {
                // Only email exists in users table per screenshots
                $where[] = "(u.email LIKE ?)";
                $args[] = "%{$userQ}%";
            }
        }

        if ($type !== '' && $type !== 'any') {
            $where[] = "al.action = ?";
            $args[] = $type;
        }

        $wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "SELECT 
                    al.log_id,
                    al.affirmation_id,
                    al.actor_user_id,
                    al.target_user_id,
                    al.action,
                    al.reason,
                    al.created_at,
                    u.email   AS actor_email,
                    a.subject AS subject
                FROM audit_logs al
                LEFT JOIN users u        ON u.id = al.actor_user_id
                LEFT JOIN affirmations a ON a.affirmation_id = al.affirmation_id
                $wsql
                ORDER BY al.created_at DESC
                LIMIT 500";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($args);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $out = [];
            foreach ($rows as $r) {
                $out[] = [
                    'id' => (int) $r['log_id'],
                    'date' => (new DateTime($r['created_at']))->format('Y-m-d H:i'),
                    'user' => $r['actor_email'] ?: ('#' . (int) $r['actor_user_id']),
                    'action' => (string) $r['action'],
                    'subject' => (string) ($r['subject'] ?? ''),
                ];
            }

            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'rows' => $out]);
        } catch (Throwable $e) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['ok' => false, 'msg' => 'Search failed: ' . $e->getMessage()]);
        }
        exit;
    }
}

/* ============================== GET exports =============================== */

// --- NEW: Export current Audit Log results as CSV (?export=audit)
if (($_GET['export'] ?? '') === 'audit') {
    $start = ymd_or_null($_GET['start'] ?? '');
    $end = ymd_or_null($_GET['end'] ?? '');
    $userQ = trim((string) ($_GET['user_q'] ?? ''));
    $type = trim((string) ($_GET['type'] ?? 'any'));

    $where = [];
    $args = [];

    if ($start) {
        $where[] = "al.created_at >= ?";
        $args[] = $start . " 00:00:00";
    }
    if ($end) {
        $where[] = "al.created_at <= ?";
        $args[] = $end . " 23:59:59";
    }

    if ($userQ !== '') {
        if (ctype_digit($userQ)) {
            $where[] = "al.actor_user_id = ?";
            $args[] = (int) $userQ;
        } else {
            $where[] = "(u.email LIKE ?)";
            $args[] = "%{$userQ}%";
        }
    }

    if ($type !== '' && $type !== 'any') {
        $where[] = "al.action = ?";
        $args[] = $type;
    }

    $wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "SELECT 
                al.created_at,
                al.action,
                al.affirmation_id,
                al.actor_user_id,
                al.target_user_id,
                al.reason,
                u.email  AS actor_email,
                a.subject
            FROM audit_logs al
            LEFT JOIN users u        ON u.id = al.actor_user_id
            LEFT JOIN affirmations a ON a.affirmation_id = al.affirmation_id
            $wsql
            ORDER BY al.created_at DESC
            LIMIT 2000";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="audit_logs_export.csv"');

    echo "date,actor_email,action,subject,affirmation_id,actor_user_id,target_user_id,reason\n";
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo implode(',', [
            csv_safe((new DateTime($r['created_at']))->format('Y-m-d H:i:s')),
            csv_safe((string) ($r['actor_email'] ?? '')),
            csv_safe((string) $r['action']),
            csv_safe((string) ($r['subject'] ?? '')),
            (string) ($r['affirmation_id'] ?? ''),
            (string) ($r['actor_user_id'] ?? ''),
            (string) ($r['target_user_id'] ?? ''),
            csv_safe((string) ($r['reason'] ?? '')),
        ]) . "\n";
    }
    exit;
}

// --- Legacy: export affirmations (left as-is for compatibility)
if (($_GET['export'] ?? '') === 'affirmations') {
    $start = ymd_or_null($_GET['start'] ?? '');
    $end = ymd_or_null($_GET['end'] ?? '');
    $userQ = trim((string) ($_GET['user_q'] ?? ''));
    $type = trim((string) ($_GET['type'] ?? 'any'));

    $where = [];
    $args = [];

    if ($start) {
        $where[] = "a.submitted_at >= ?";
        $args[] = $start . " 00:00:00";
    }
    if ($end) {
        $where[] = "a.submitted_at <= ?";
        $args[] = $end . " 23:59:59";
    }

    if ($userQ !== '') {
        if (ctype_digit($userQ)) {
            $where[] = "a.sender_id = ?";
            $args[] = (int) $userQ;
        } else {
            $where[] = "(u.full_name LIKE ? OR u.email LIKE ?)";
            $args[] = "%{$userQ}%";
            $args[] = "%{$userQ}%";
        }
    }

    if ($type !== '' && $type !== 'any') {
        if ($type === 'flagged') {
            $where[] = "a.flag_reason IS NOT NULL AND a.flag_reason <> ''";
        } elseif ($type === 'returned') {
            $where[] = "a.return_reason IS NOT NULL AND a.return_reason <> ''";
        } else {
            $where[] = "a.status = ?";
            $args[] = $type;
        }
    }

    $wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "SELECT a.affirmation_id, a.sender_id, u.full_name AS sender_name, u.email AS sender_email,
                   a.subject, a.message, a.status, a.flag_reason, a.return_reason, a.submitted_at
            FROM affirmations a
            LEFT JOIN users u ON u.user_id = a.sender_id
            $wsql
            ORDER BY a.submitted_at DESC
            LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="affirmations_export.csv"');

    echo "affirmation_id,date,user_name,user_email,status,action,subject,message,flag_reason,return_reason\n";
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $action = (!empty($r['flag_reason'])) ? 'flagged'
            : ((!empty($r['return_reason'])) ? 'returned'
                : (string) $r['status']);
        echo implode(',', [
            (string) $r['affirmation_id'],
            csv_safe((new DateTime($r['submitted_at']))->format('Y-m-d H:i:s')),
            csv_safe((string) $r['sender_name']),
            csv_safe((string) $r['sender_email']),
            csv_safe((string) $r['status']),
            csv_safe($action),
            csv_safe((string) $r['subject']),
            csv_safe((string) $r['message']),
            csv_safe((string) $r['flag_reason']),
            csv_safe((string) $r['return_reason']),
        ]) . "\n";
    }
    exit;
}

/* ============================== page bootstrap ============================ */
$current = $pdo->query("SELECT cycle_id,start_date,end_date,is_active FROM affirmation_cycle WHERE is_active=1 ORDER BY cycle_id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: null;
$curStart = $current['start_date'] ?? '';
$curEnd = $current['end_date'] ?? '';
$curWeeks = ($curStart && $curEnd) ? weeks_from_dates($curStart, $curEnd) : 2;
$curOpen = (bool) ($current['is_active'] ?? 0);

$history = $pdo->query("SELECT id,start_date,end_date,weeks,is_open,saved_at FROM affirmation_cycle_history ORDER BY saved_at DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);

$summary = '';
if ($curStart && $curEnd) {
    $sd = new DateTime($curStart);
    $ed = new DateTime($curEnd);
    $summary = sprintf('%s → %s · %d days · %d weeks', $sd->format('Y-m-d'), $ed->format('Y-m-d'), $sd->diff($ed)->days + 1, $curWeeks);
}
?>

<?php include 'header.php'; ?>

<div class="body">
    <div class="container">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="cycle-tab" data-toggle="tab" data-target="#cycle" type="button"
                    role="tab" aria-controls="cycle" aria-selected="true">Cycle</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="audit-tab" data-toggle="tab" data-target="#audit" type="button" role="tab"
                    aria-controls="audit" aria-selected="false">Audit Log</button>
            </li>
        </ul>

        <div class="tab-content" id="myTabContent">
            <!-- Cycle Tab -->
            <div class="tab-pane fade show active" id="cycle" role="tabpanel" aria-labelledby="cycle-tab">
                <div class="mt-4">
                    <!-- flash -->
                    <div id="flashOk" class="alert alert-success d-none" role="alert"></div>
                    <div id="flashErr" class="alert alert-danger d-none" role="alert"></div>

                    <!-- Settings Card -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <h5 class="mb-3">Cycle Settings</h5>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="startDate">Start Date</label>
                                    <input id="startDate" type="date" class="form-control" autocomplete="off">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="repeatWeeks">Repeat Rule (weeks)</label>
                                    <input id="repeatWeeks" type="number" min="1" max="520" step="1"
                                        class="form-control">
                                    <small class="form-text text-muted">End date = start + (weeks × 7 – 1) days.</small>
                                </div>
                            </div>

                            <div class="d-flex">
                                <button id="btnReset" class="btn btn-secondary mr-2">RESET</button>
                                <button id="btnSave" class="btn btn-warning">SAVE CYCLE</button>
                                <div class="ml-auto d-flex align-items-center">
                                    <span>Current Cycle:&nbsp;</span>
                                    <span id="badgeOpen" class="badge badge-success mr-2 d-none">OPEN</span>
                                    <span id="badgeClosed" class="badge badge-secondary mr-2 d-none">CLOSED</span>
                                    <button id="btnForceClose" class="btn btn-sm btn-outline-danger d-none">Force
                                        Close</button>
                                </div>
                            </div>
                            <hr>
                            <div><strong>Summary:</strong> <span id="summaryText">—</span></div>
                        </div>
                    </div>

                    <!-- History Card -->
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2">
                                <h5 class="mb-0">Cycle History</h5>
                                <button id="btnClearHistory" class="btn btn-danger btn-sm ml-auto">CLEAR
                                    HISTORY</button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th style="width:72px;">#</th>
                                            <th>Start</th>
                                            <th>End (auto)</th>
                                            <th>Duration</th>
                                            <th>Weeks</th>
                                            <th>Open?</th>
                                            <th>Saved At</th>
                                            <th style="width:120px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="historyTbody">
                                        <tr class="text-muted">
                                            <td colspan="7">No cycles saved yet.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Audit Log Tab -->
            <div class="tab-pane fade" id="audit" role="tabpanel" aria-labelledby="audit-tab">
                <div class="mt-4">
                    <!-- Filters -->
                    <div class="card shadow-sm mb-3">
                        <div class="card-body">
                            <h5 class="mb-3">Filters</h5>
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label for="auditStart">Start Date</label>
                                    <input id="auditStart" type="date" class="form-control">
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="auditEnd">End Date</label>
                                    <input id="auditEnd" type="date" class="form-control">
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="auditUser">Users</label>
                                    <input id="auditUser" type="text" class="form-control"
                                        placeholder="e.g. ava@school.edu">
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="auditType">Type</label>
                                    <select id="auditType" class="form-control">
                                        <option value="any">Any</option>
                                        <option value="read">Read</option>
                                        <option value="unread">Unread</option>
                                        <option value="forwarded">Forwarded</option>
                                        <option value="flagged">Flagged</option>
                                        <option value="returned">Returned</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-flex">
                                <button id="btnAuditReset" class="btn btn-secondary mr-2">Reset</button>
                                <button id="btnAuditSearch" class="btn btn-primary">Search</button>
                                <button id="btnAuditExport" class="btn btn-outline-info ml-auto">Export CSV</button>
                            </div>
                        </div>
                    </div>

                    <!-- Results -->
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="mb-3">Results</h5>
                            <div class="table-responsive">
                                <table class="table table-hover" id="auditTable">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Subject</th>
                                        </tr>
                                    </thead>
                                    <tbody id="auditResults">
                                        <tr class="text-muted">
                                            <td colspan="4">No results.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div><!-- /mt-4 -->
            </div>
        </div>
    </div>
</div>

<?php
// Data for Cycle tab initial state
$payload = [
    'start' => $curStart,
    'weeks' => (int) $curWeeks,
    'open' => (bool) $curOpen,
    'summary' => $summary,
    'history' => array_map(function ($r) {
        $sd = new DateTime($r['start_date']);
        $ed = new DateTime($r['end_date']);
        $days = $sd->diff($ed)->days + 1;
        return [
            'id' => (int) $r['id'],
            'start' => $sd->format('Y-m-d'),
            'end' => $ed->format('Y-m-d'),
            'days' => $days,
            'weeks' => (int) $r['weeks'],
            'open' => (int) $r['is_open'] === 1,
            'saved' => (new DateTime($r['saved_at']))->format('Y-m-d H:i:s'),
        ];
    }, $history),
];
?>
<!-- === Page Scripts: wire both Cycle and Audit tabs ======================= -->
<script>
    (function () {
        const init = <?php echo json_encode($payload, JSON_UNESCAPED_UNICODE); ?>;
        const $ = (s) => document.querySelector(s);

        /* -------- Cycle tab wiring -------- */
        const startDate = $('#startDate');
        const repeatWeeks = $('#repeatWeeks');
        const badgeOpen = $('#badgeOpen');
        const badgeClosed = $('#badgeClosed');
        const summaryText = $('#summaryText');
        const historyTbody = $('#historyTbody');
        const btnReset = $('#btnReset');
        const btnSave = $('#btnSave');
        const btnClear = $('#btnClearHistory');
        const flashOk = $('#flashOk');
        const flashErr = $('#flashErr');
        const btnForceClose = $('#btnForceClose');

        function setBadge(open) {
            badgeOpen.classList.toggle('d-none', !open);
            badgeClosed.classList.toggle('d-none', open);
            btnForceClose.classList.toggle('d-none', !open);
        }

        btnForceClose.addEventListener('click', async (e) => {
            e.preventDefault();
            if (!confirm('Are you sure to force close the current cycle?')) return;
            const fd = new FormData();
            fd.append('action', 'force_close_cycle');
            try {
                const res = await fetch(location.href, { method: 'POST', body: fd });
                const j = await res.json();
                if (!j.ok) { alert(j.msg || 'Force close failed'); return; }
                location.reload();
            } catch (err) {
                alert(err.message || 'Network error');
            }
        });

        function renderHistory(rows) {
            historyTbody.innerHTML = '';
            if (!rows || rows.length === 0) {
                historyTbody.innerHTML = '<tr class="text-muted"><td colspan="7">No cycles saved yet.</td></tr>';
                return;
            }
            rows.forEach(r => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td>${r.id}</td>
                <td>${r.start}</td>
                <td>${r.end}</td>
                <td>${r.days} days</td>
                <td>${r.weeks}</td>
                <td>${r.open ? 'Yes' : 'No'}</td>
                <td>${r.saved}</td>
                <td>
                    <button class="btn btn-sm btn-outline-danger btn-del-history" data-id="${r.id}">
                    Delete
                    </button>
                </td>`;
                historyTbody.appendChild(tr);
            });
        }
        function setFlash(ok, msg) {
            flashOk.classList.add('d-none');
            flashErr.classList.add('d-none');
            if (ok) { flashOk.textContent = msg || 'Saved'; flashOk.classList.remove('d-none'); }
            else if (msg) { flashErr.textContent = msg; flashErr.classList.remove('d-none'); }
        }

        if (init.start) startDate.value = init.start;
        if (init.weeks) repeatWeeks.value = init.weeks;
        setBadge(!!init.open);
        summaryText.textContent = init.summary || '—';
        renderHistory(init.history);

        btnReset.addEventListener('click', (e) => {
            e.preventDefault();
            startDate.value = init.start || '';
            repeatWeeks.value = init.weeks || 2;
            setFlash(true, 'Reset to current values');
        });

        btnSave.addEventListener('click', async (e) => {
            e.preventDefault();
            const fd = new FormData();
            fd.append('action', 'save_cycle');
            fd.append('start_date', startDate.value || '');
            fd.append('weeks', repeatWeeks.value || '0');
            try {
                const res = await fetch(location.href, { method: 'POST', body: fd });
                const j = await res.json();
                if (!j.ok) { setFlash(false, j.msg || 'Save failed'); return; }
                location.reload();
            } catch (err) { setFlash(false, err.message || 'Network error'); }
        });

        btnClear.addEventListener('click', async (e) => {
            e.preventDefault();
            if (!confirm('Clear ALL cycle history?')) return;
            const fd = new FormData(); fd.append('action', 'clear_history');
            try {
                const res = await fetch(location.href, { method: 'POST', body: fd });
                const j = await res.json();
                if (!j.ok) { setFlash(false, j.msg || 'Clear failed'); return; }
                renderHistory([]); setFlash(true, 'History cleared');
            } catch (err) { setFlash(false, err.message || 'Network error'); }
        });

        // Delegate clicks on delete buttons in the history table
        historyTbody.addEventListener('click', async (e) => {
            const btn = e.target.closest('.btn-del-history');
            if (!btn) return;
            const id = btn.getAttribute('data-id');
            if (!id) return;

            if (!confirm('Delete this cycle history record?')) return;

            const fd = new FormData();
            fd.append('action', 'delete_history');
            fd.append('id', id);

            try {
                const res = await fetch(location.href, { method: 'POST', body: fd });
                const j = await res.json();
                if (!j.ok) {
                    setFlash(false, j.msg || 'Delete failed');
                    return;
                }
                // Remove row from DOM
                const tr = btn.closest('tr');
                if (tr) tr.remove();

                // If table is empty, show placeholder row
                if (!historyTbody.querySelector('tr')) {
                    historyTbody.innerHTML = '<tr class="text-muted"><td colspan="8">No cycles saved yet.</td></tr>';
                }

                setFlash(true, 'History record deleted');
            } catch (err) {
                setFlash(false, err.message || 'Network error');
            }
        });


        /* -------- Audit tab wiring -------- */
        const auditStart = $('#auditStart');
        const auditEnd = $('#auditEnd');
        const auditUser = $('#auditUser');
        const auditType = $('#auditType');
        const btnASearch = $('#btnAuditSearch');
        const btnAReset = $('#btnAuditReset');
        const btnAExport = $('#btnAuditExport');
        const auditBody = $('#auditResults');

        let lastQuery = null; // remember last search for export

        function renderAudit(rows) {
            auditBody.innerHTML = '';
            if (!rows || rows.length === 0) {
                auditBody.innerHTML = '<tr class="text-muted"><td colspan="4">No results.</td></tr>';
                return;
            }
            rows.forEach(r => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td>${r.date}</td>
                <td>${r.user}</td>
                <td>${r.action || ''}</td>
                <td>${r.subject ? r.subject : ''}</td>`;
                auditBody.appendChild(tr);
            });
        }

        async function doAuditSearch() {
            const fd = new FormData();
            fd.append('action', 'audit_search');
            fd.append('start', auditStart.value || '');
            fd.append('end', auditEnd.value || '');
            fd.append('user_q', auditUser.value || '');
            fd.append('type', auditType.value || 'any');

            lastQuery = {
                start: auditStart.value || '',
                end: auditEnd.value || '',
                user_q: auditUser.value || '',
                type: auditType.value || 'any'
            };

            try {
                const res = await fetch(location.href, { method: 'POST', body: fd });
                const j = await res.json();
                if (!j.ok) { renderAudit([]); alert(j.msg || 'Search failed'); return; }
                renderAudit(j.rows || []);
            } catch (err) {
                renderAudit([]); alert(err.message || 'Network error');
            }
        }

        btnASearch.addEventListener('click', (e) => { e.preventDefault(); doAuditSearch(); });
        btnAReset.addEventListener('click', (e) => {
            e.preventDefault();
            auditStart.value = ''; auditEnd.value = ''; auditUser.value = ''; auditType.value = 'any';
            renderAudit([]);
        });
        btnAExport.addEventListener('click', (e) => {
            e.preventDefault();
            const q = lastQuery || {
                start: auditStart.value || '',
                end: auditEnd.value || '',
                user_q: auditUser.value || '',
                type: auditType.value || 'any'
            };
            // This navigates to a GET that streams CSV with the same filters.
            const params = new URLSearchParams({ ...q, export: 'audit' });
            window.location.href = location.pathname + '?' + params.toString();
        });
    })();
</script>
<!-- ====================================================================== -->

<?php include 'footer.php'; ?>