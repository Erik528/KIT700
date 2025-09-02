<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header('Location: login.php?err=unauthorized');
    exit;
}
include 'header.php';
?>

<div class="body">


    <div class="container">
        <div class="sign-in py-3 py-lg-5">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="login padding-box text-center">
                        <h2>Welcome, Manager</h2>
                        <p class="mb-4">Please choose how you want to log in:</p>

                        <div class="d-flex justify-content-center gap-3">
                            <a href="manager-dash.php" class="btn btn-primary mx-2">Login as Manager</a>
                            <a href="dashboard.php" class="btn btn-secondary mx-2">Login as Staff</a>
                        </div>

                    </div>
                </div>
            </div>
        </div><!-- /.container -->
    </div>

    <?php include 'footer.php'; ?>