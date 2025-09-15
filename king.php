<?php
include 'header.php';
?>

<div class="body">
    <div class="inbox mt-3 mt-lg-5">
        <div class="container">
            <?php foreach ($messages as $msg): 
                $aid = (int)$msg['affirmation_id'];
                $status = strtolower($msg['status'] ?? 'unread');
                $statusClass = 'status--'.$status;
            ?>
            <article class="msg" data-aid="<?= $aid ?>" data-status="<?= htmlspecialchars($status) ?>" aria-expanded="false">
                <div class="msg__head" role="button" aria-controls="msg-<?= $aid ?>-details" aria-expanded="false">
                    <div class="avatar"><?= strtoupper(substr($msg['sender_email'],0,1)) ?></div>
                    <div class="text">
                        <div class="to-line">
                            <span class="from"><?= htmlspecialchars($msg['sender_email']) ?></span>
                            <span class="sep">→</span>
                            <span class="to"><?= htmlspecialchars($msg['recipient_email']) ?></span>
                        </div>
                        <div class="subject"><?= htmlspecialchars($msg['subject'] ?? 'No Subject') ?></div>
                        <div class="snippet"><?= htmlspecialchars(mb_strimwidth($msg['message'] ?? '',0,160,'…')) ?></div>
                    </div>
                    <div class="state">
                        <div class="status status-pill <?= $statusClass ?>" title="<?= ucfirst($status) ?>">
                            <span class="dot"></span><span><?= ucfirst($status) ?></span>
                        </div>
                        <div class="meta" aria-label="Date">
                            <i class="fa-solid fa-clock"></i>
                            <span><?= date('M d', strtotime($msg['submitted_at'] ?? 'now')) ?></span>
                        </div>
                        <button class="arrow-btn" type="button" aria-label="Toggle details">
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                    </div>
                </div>

                <div class="msg__details" id="msg-<?= $aid ?>-details">
                    <p class="details-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>

                    <?php if($status==='flagged' && !empty($msg['flag_reason'])): ?>
                        <div class="alert alert-warning py-2 px-3 mb-3">
                            <strong>Flag reasons:</strong> <?= htmlspecialchars($msg['flag_reason']) ?>
                        </div>
                    <?php endif; ?>

                    <div class="actions">
                        <button class="btn btn-warning btn-lg action-flag" type="button" data-toggle="modal" data-target="#flagModal" data-aid="<?= $aid ?>">Flag as Abuse</button>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Reuse modals (flagModal, confirmDelete, etc.) same as in manager-dash -->

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
    <?php include 'footer.php'; ?>
