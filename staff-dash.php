<?php include 'header.php'; ?>

<div class="body">
    <div class="container">
        <div class="dashboard-history py-3 py-lg-5">
            <div class="row">
                <div class="col-lg-7 col-left">
                    <div class="aff-history">
                        <div class="aff-content">
                            <h5>Affirmation History</h5>

                            <div class="affirmation">
                                <img src="https://placehold.co/50" class="img-fluid" alt="">
                                <div class="content">
                                    <span>sent to <strong>Eric</strong></span>
                                    <p>Preview of subject <span>Aug 6</span></p>
                                    <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Soluta, odio!</p>
                                </div>
                                <!--/.content-->
                            </div>
                            <!--/.affirmation-->

                            <div class="affirmation">
                                <img src="https://placehold.co/50" class="img-fluid" alt="">
                                <div class="content">
                                    <span>sent to <strong>Eric</strong></span>
                                    <p>Preview of subject <span>Aug 6</span></p>
                                    <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Soluta, odio!</p>
                                </div>
                                <!--/.content-->
                            </div>
                            <!--/.affirmation-->

                            <div class="affirmation">
                                <img src="https://placehold.co/50" class="img-fluid" alt="">
                                <div class="content">
                                    <span>sent to <strong>Eric</strong></span>
                                    <p>Preview of subject <span>Aug 6</span></p>
                                    <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Soluta, odio!</p>
                                </div>
                                <!--/.content-->
                            </div>
                            <!--/.affirmation-->

                            <div class="affirmation">
                                <img src="https://placehold.co/50" class="img-fluid" alt="">
                                <div class="content">
                                    <span>sent to <strong>Eric</strong></span>
                                    <p>Preview of subject <span>Aug 6</span></p>
                                    <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Soluta, odio!</p>
                                </div>
                                <!--/.content-->
                            </div>
                            <!--/.affirmation-->

                            <div class="aff-modal">
                                <!-- Button trigger modal -->
                                <button type="button" class="btn btn-primary custom-btn" data-toggle="modal"
                                    data-target="#exampleModalCenter">
                                    <i class="fa-solid fa-plus"></i>
                                </button>

                                <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog"
                                    aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">New Affirmation</h5>
                                                <button type="button" class="close" data-dismiss="modal"
                                                    aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="aff-title d-sm-flex mb-2">
                                                    <small class="d-block mb-2">You can send one affirmation per cycle.
                                                        Your
                                                        message
                                                        will remain
                                                        anonymous.</small>
                                                    <button type="submit" form="affirmationForm"
                                                        class="btn btn-primary">Send</button>
                                                </div>
                                                <div class="aff-form">
                                                    <h6>Recipient</h6>
                                                    <form id="affirmationForm">
                                                        <div class="form-group dropdown">
                                                            <input type="email" class="form-control" id="searchInput"
                                                                aria-describedby="emailHelp"
                                                                placeholder="Search for a colleague....."
                                                                autocomplete="off" required>

                                                            <div class="dropdown-menu" aria-labelledby="searchInput"
                                                                id="dropdownList">
                                                                <a class="dropdown-item" href="#">Apple</a>
                                                                <a class="dropdown-item" href="#">Banana</a>
                                                                <a class="dropdown-item" href="#">Cherry</a>
                                                                <a class="dropdown-item" href="#">Date</a>
                                                                <a class="dropdown-item" href="#">Grapes</a>
                                                                <a class="dropdown-item" href="#">Mango</a>
                                                                <a class="dropdown-item" href="#">Orange</a>
                                                                <a class="dropdown-item" href="#">Peach</a>
                                                                <a class="dropdown-item" href="#">Strawberry</a>
                                                                <a class="dropdown-item" href="#">Watermelon</a>
                                                            </div>
                                                        </div>

                                                        <div class="form-group">
                                                            <label for="emailSubject">Subject</label>
                                                            <input type="text" class="form-control" id="emailSubject"
                                                                placeholder="Subject here...." autocomplete="off">
                                                        </div>

                                                        <div class="form-group">
                                                            <label for="exampleFormControlTextarea1">Message</label>
                                                            <textarea class="form-control"
                                                                id="exampleFormControlTextarea1"
                                                                placeholder="Write your message here...." rows="3"
                                                                required></textarea>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Confirmation modal -->
                                <div class="modal fade" id="confirmationModal" tabindex="-1" role="dialog"
                                    aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                        <div class="modal-content text-center p-4">
                                            <h5>Your affirmation was submitted — your manager will forward it soon.</h5>
                                            <button type="button" class="btn btn-primary mt-3"
                                                data-dismiss="modal">OK</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!--/.aff-modal-->
                        </div>
                        <!--/.aff-content-->
                    </div>
                    <!--/.aff-history-->
                </div>

                <div class="col-lg-5 col-right">
                    <div class="cycle open">
                        <div class="status">
                            <h5>Current Cycle</h5>
                            <h6>OPEN <i class="fa-solid fa-lock-open"></i></h6>
                        </div>

                        <div class="progress my-2" role="progressbar" aria-label="Info example" aria-valuenow="50"
                            aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar bg-info" style="width: 50%"></div>
                        </div>
                        <div class="rem-time">
                            <div class="status">
                                <h6>Time Remaining</h6>
                                <h6>3d 5h 22m</h6>
                            </div>
                        </div>
                    </div>
                    <!--/.cycle-open-->
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>