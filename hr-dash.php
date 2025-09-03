<?php include 'header.php'; ?>

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