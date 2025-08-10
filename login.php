<?php include 'header.php'; ?>

<div class="body">
    <div class="banner">
        <div class="container">
            <h1>Sign In Page</h1>
        </div>
    </div>

    <div class="container">
        <div class="sign-in py-3 py-lg-5">
            <div class="row">
                <div class="col-lg-7 col-left">
                    <div class="login padding-box text-center">
                        <h2>Welcome to College Affirmations Portal</h2>
                        <p>Please fill the form below for registering with us as a charity partner.</p>
                        <form class="login-form text-center">
                            <span id="msg"></span>

                            <div class="form-group">
                                <label for="email" class="d-none">Email address</label>
                                <input type="email" class="form-control text-center" id="email"
                                    aria-describedby="emailHelp" name="email"
                                    placeholder="Sign in with Microsoft Entra ID" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Submit</button>

                            <small id="emailHelp" class="form-text text-muted mt-3">If youre having trouble signing in
                                please
                                contact <a href="#">support@signin</a></small>
                        </form>
                    </div>
                    <!--/.login-->
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