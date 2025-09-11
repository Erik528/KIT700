<?php
declare(strict_types=1);
session_start();
require 'db_connection.php';

// --- Guard: only HR can access
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'hr') {
    header('Location: login.php?err=unauthorized');
    exit;
}
$hrId = (int) $_SESSION['user_id'];

// --- Actions: mark_read / flag / delete
$action = $_POST['action'] ?? '';
if ($action) {
    $mid = (int) ($_POST['message_id'] ?? 0);
    if ($mid <= 0) { http_response_code(400); exit('Bad Request'); }

    // Fetch message to ensure it belongs to this HR
    $st = $pdo->prepare("SELECT status FROM messages WHERE message_id=:mid AND recipient_id=:hr");
    $st->execute(['mid'=>$mid,'hr'=>$hrId]);
    $msg = $st->fetch(PDO::FETCH_ASSOC);
    if (!$msg) { http_response_code(403); exit('Forbidden'); }

    $status = strtolower((string)$msg['status'] ?? 'unread');

    if ($action === 'mark_read' && $status==='unread') {
        $pdo->prepare("UPDATE messages SET status='read' WHERE message_id=:mid")->execute(['mid'=>$mid]);
        exit('OK');
    }

    if ($action === 'flag' && $status!=='flagged' && $status!=='forwarded') {
        $reason = trim($_POST['reasons'] ?? '') ?: null;
        $pdo->prepare("UPDATE messages SET status='flagged', flag_reason=:r WHERE message_id=:mid")
            ->execute(['mid'=>$mid,'r'=>$reason]);
        header('Location: hr-dash.php?ok=flagged');
        exit;
    }

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM messages WHERE message_id=:mid")->execute(['mid'=>$mid]);
        header('Location: hr-dash.php?ok=deleted');
        exit;
    }
}

// --- Fetch messages for this HR
$st = $pdo->prepare("
    SELECT m.*, u.email AS sender_email
    FROM messages m
    JOIN users u ON u.id = m.sender_id
    WHERE m.recipient_id=:hr
    ORDER BY m.created_at DESC
");
$st->execute(['hr'=>$hrId]);
$messages = $st->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>

<div class="body">
    <div class="inbox mt-3 mt-lg-5">
        <div class="container">
            <?php foreach ($messages as $msg): 
                $mid = (int)$msg['message_id'];
                $status = strtolower($msg['status'] ?? 'unread');
                $statusClass = 'status--'.$status;
            ?>
            <article class="msg" data-mid="<?= $mid ?>" data-status="<?= htmlspecialchars($status) ?>" aria-expanded="false">
                <div class="msg__head" role="button" aria-controls="msg-<?= $mid ?>-details" aria-expanded="false">
                    <div class="avatar"><?= strtoupper(substr($msg['sender_email'],0,1)) ?></div>
                    <div class="text">
                        <div class="to-line">to <b><?= htmlspecialchars($_SESSION['email'] ?? 'HR') ?></b></div>
                        <div class="subject"><?= htmlspecialchars($msg['subject'] ?? 'No Subject') ?></div>
                        <div class="snippet"><?= htmlspecialchars(mb_strimwidth($msg['message'] ?? '',0,160,'…')) ?></div>
                    </div>
                    <div class="state">
                        <div class="status status-pill <?= $statusClass ?>" title="<?= ucfirst($status) ?>">
                            <span class="dot"></span><span><?= ucfirst($status) ?></span>
                        </div>
                        <div class="meta" aria-label="Date">
                            <i class="fa-solid fa-clock"></i>
                            <span><?= date('M d', strtotime($msg['created_at'] ?? 'now')) ?></span>
                        </div>
                        <button class="arrow-btn" type="button" aria-label="Toggle details">
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                    </div>
                </div>

                <div class="msg__details" id="msg-<?= $mid ?>-details">
                    <p class="details-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>

                    <?php if($status==='flagged' && !empty($msg['flag_reason'])): ?>
                        <div class="alert alert-warning py-2 px-3 mb-3">
                            <strong>Flag reasons:</strong> <?= htmlspecialchars($msg['flag_reason']) ?>
                        </div>
                    <?php endif; ?>

                    <div class="actions">
                        <button class="btn btn-warning btn-lg action-flag" type="button" data-toggle="modal" data-target="#flagModal" data-mid="<?= $mid ?>">Flag as Abuse</button>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Keep all modals from your existing HTML here: flagModal, reportedModal, forwardModal, confirmModal, etc. -->
</div>
<div class="body">

    <div class="inbox mt-3 mt-lg-5">
        <div class="container">
            <article class="msg" aria-expanded="false">
                <div class="msg__head" role="button" aria-controls="m1-details" aria-expanded="false">
                    <div class="avatar">E</div>

                    <div class="text">
                        <div class="to-line">to <b>Erik</b></div>
                        <div class="subject">Preview of Subject</div>
                        <div class="snippet">Lorem ipsum dolor sit amet, id sententiae intellegam ius, his et facer
                            reformidans intellegabat…</div>
                    </div>

                    <div class="state">
                        <div class="status status--unread" title="Unread">
                            <span class="dot" aria-hidden="true"></span><span>Unread</span>
                        </div>
                        <!-- Example alternatives:
                            <div class="status status--forwarded"><span class="dot"></span><span>Forwarded</span></div>
                            <div class="status status--flagged"><span class="dot"></span><span>Flagged</span></div>
                            -->

                        <div class="meta" aria-label="Date">
                            <!-- clock -->
                            <i class="fa-solid fa-clock"></i>
                            <span>Aug&nbsp;4</span>
                        </div>

                        <button class="arrow-btn" type="button" aria-label="Toggle details">
                            <!-- chevron down -->
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                    </div>

                </div>
                <div class="msg__details" id="m1-details">
                    <p class="details-text">
                        Lorem ipsum dolor sit amet, has ea vidit dolorem voluptaria, ut sea delenit vivendum.
                        Veri detraxit honestatis ei mel, paulo feugiat te has. Pri aeque aliquando tincidunt ei.
                        Mentitum persequeris at his, democritum intellegam ex est, qui graece prodesset repudiandae.
                    </p>
                    <div class="actions">
                        <button class="btn btn-outline" type="button" id="btnBack">Send Back</button>
                        <button class="btn btn-tertiary" type="button" data-toggle="modal" data-target="#flagModal">Flag
                            as Abuse</button>
                        <button class="btn btn-primary" type="button" id="btnForward">Forward</button>
                    </div>

                    <!-- Accordion (Assign Manager + Log) -->
                    <div id="m1-accordion" class="mt-3">
                        <!-- Assign Manager -->
                        <div class="border-bottom rounded mb-2">
                            <button
                                class="btn btn-link d-flex justify-content-between align-items-center w-100 px-3 py-2"
                                data-toggle="collapse" data-target="#m1-assign" aria-expanded="false"
                                aria-controls="m1-assign">
                                <span class="font-weight-bold">Assign Manager:</span>
                                <i class="fa fa-chevron-down"></i>
                            </button>

                            <div id="m1-assign" class="collapse" data-parent="#m1-accordion">
                                <div class="px-3 pb-3">
                                    <!-- Search box -->
                                    <input type="text" id="m1-manager-filter" class="form-control mb-2"
                                        placeholder="Search managers…">
                                </div>
                            </div>
                        </div>

                        <!-- Log -->
                        <div class="border-bottom rounded">
                            <button
                                class="btn btn-link d-flex justify-content-between align-items-center w-100 px-3 py-2"
                                data-toggle="collapse" data-target="#m1-log" aria-expanded="false"
                                aria-controls="m1-log">
                                <span class="font-weight-bold">Log:</span>
                                <i class="fa fa-chevron-down"></i>
                            </button>

                            <div id="m1-log" class="collapse" data-parent="#m1-accordion">
                                <div class="px-3 pb-3">
                                    <ul class="mb-0 pl-3" id="m1-log-list">
                                        <li>Aug 5 10:42 — Assigned to James Lin</li>
                                        <li>Aug 5 10:45 — Forwarded by James Lin</li>
                                        <li>Aug 5 10:49 — Flagged for tone</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </article>

        </div>
    </div>
    <!--/.inbox -->

    <!--/.Popup Modals -->
    <!-- Flag as Abuse (reasons) -->
    <div class="modal fade" id="flagModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header strip">
                    <div class="bar bar-yellow">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                </div>

                <div class="modal-body">
                    <h5 class="mb-3 font-weight-bold">Flag as Abuse:</h5>

                    <!-- BS4 toggle chips -->
                    <div id="reasonsGroup" class="btn-group btn-group-toggle d-flex flex-wrap w-100"
                        data-toggle="buttons">
                        <label class="btn btn-outline m-1 flex-fill">
                            <input type="checkbox" value="Inappropriate tone" autocomplete="off">
                            Inappropriate tone
                        </label>
                        <label class="btn btn-outline m-1 flex-fill">
                            <input type="checkbox" value="Personal attack" autocomplete="off"> Personal
                            attack
                        </label>
                        <label class="btn btn-outline m-1 flex-fill">
                            <input type="checkbox" value="Possible identity breach" autocomplete="off">
                            Possible
                            identity breach
                        </label>
                        <label class="btn btn-outline m-1 flex-fill">
                            <input type="checkbox" value="Spam" autocomplete="off"> Spam
                        </label>
                        <label class="btn btn-outline m-1 flex-fill">
                            <input type="checkbox" value="Discriminatory language" autocomplete="off">
                            Discriminatory
                            language
                        </label>
                        <label class="btn btn-outline m-1 flex-fill">
                            <input type="checkbox" value="Sexual content" autocomplete="off"> Sexual content
                        </label>
                    </div>

                    <div id="flagHint" class="text-danger small d-none mt-2">
                        Please select at least one reason.
                    </div>

                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <button type="button" class="btn btn-warning mr-2" id="btnReset">Reset</button>
                        <button type="button" class="btn btn-primary" id="btnSubmit">Submit</button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Confirm HR -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header strip">
                    <div class="bar bar-yellow">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                </div>

                <div class="modal-body text-center">
                    <h5 class="font-weight-bold mb-4">Are you sure you want to<br>report this message to HR?
                    </h5>
                    <div class="d-flex justify-content-center">
                        <button class="btn btn-light border mr-2" id="btnNo">No</button>
                        <button class="btn btn-primary" id="btnYes">Yes</button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Reported (success) -->
    <div class="modal fade" id="reportedModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header strip">
                    <div class="bar bar-green">
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true" class="text-white">&times;</span>
                        </button>
                    </div>
                </div>

                <div class="modal-body">
                    <h5 class="text-center font-weight-bold m-0">The message has been reported to HR.</h5>
                </div>

            </div>
        </div>
    </div>

    <!-- Forwarded -->
    <div class="modal fade" id="forwardModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header strip">
                    <div class="bar bar-blue">
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true" class="text-white">&times;</span>
                        </button>
                    </div>
                </div>

                <div class="modal-body">
                    <h5 class="text-center font-weight-bold m-0">
                        The message has been sent to the recipient’s inbox.
                    </h5>
                </div>

            </div>
        </div>
    </div>

    <!-- Return / Send Back (reason) -->
    <div class="modal fade" id="returnModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header strip">
                    <div class="bar bar-yellow w-100 d-flex justify-content-end">
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true" class="text-white">&times;</span>
                        </button>
                    </div>
                </div>

                <div class="modal-body">
                    <h5 class="mb-3 font-weight-bold">
                        Reason for returned email :
                        <small id="wordCounter" class="text-muted ml-1">( 0/100 )</small>
                    </h5>

                    <textarea id="returnReason" class="form-control" rows="6" placeholder="Type the reason here…"
                        aria-describedby="wordCounter"></textarea>

                    <div id="returnHint" class="text-danger small d-none mt-2">
                        Please write between 1 and 100 words.
                    </div>

                    <div class="d-flex justify-content-center mt-4">
                        <button type="button" class="btn btn-warning mr-3" id="returnReset">Reset</button>
                        <button type="button" class="btn btn-primary" id="returnSubmit" disabled>Submit</button>
                    </div>
                </div>

            </div>
        </div>
    </div>


</div>

<?php include 'footer.php'; ?>

<script>
(function() {
    var inbox = document.querySelector('.inbox');
    if(!inbox) return;
    inbox.addEventListener('click', function(e) {
        var clickTarget = e.target.closest('.arrow-btn, .msg__head');
        if(!clickTarget) return;
        var msg = clickTarget.closest('.msg');
        if(!msg) return;
        var details = msg.querySelector('.msg__details');
        var open = msg.getAttribute('aria-expanded')==='true';
        msg.setAttribute('aria-expanded', open ? 'false' : 'true');
        if(details) details.style.display = open ? 'none' : 'block';

        // mark read
        if(!open && msg.dataset.status==='unread') {
            var mid = msg.dataset.mid;
            fetch('hr-dash.php', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=mark_read&message_id='+encodeURIComponent(mid)
            }).then(()=>{ msg.dataset.status='read';
                var pill = msg.querySelector('.status-pill')||msg.querySelector('.status');
                if(pill){ pill.classList.remove('status--unread'); pill.classList.add('status--read'); pill.querySelector('span:last-child').textContent='Read'; }
            });
        }
    });

    // success modals
    var params = new URLSearchParams(location.search);
    if(params.get('ok')==='flagged') $('#reportedModal').modal('show');
    if(params.has('ok')||params.has('err')) window.history.replaceState({}, '', 'hr-dash.php');
})();
</script>
