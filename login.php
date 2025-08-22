<?php
declare(strict_types=1);
session_start();

include 'db_connection.php';

$stmt = $pdo->query("SELECT * FROM affirmation_cycle WHERE is_active = 1 LIMIT 1");
$currentCycle = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$cycle_id = $currentCycle['cycle_id'] ?? null;

$err = $_GET['err'] ?? '';
$ok = $_GET['ok'] ?? '';
$email = $_GET['email'] ?? '';

// ---- CSRF ----
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
?>
<script>
    (function () {
        const err = <?= json_encode($err) ?>;
        const ok = <?= json_encode($ok) ?>;
        const email = <?= json_encode($email) ?>;

        if (ok) {
            alert(email ? `If an account exists for ${email}, a code has been sent.` :
                "If an account exists for this email, a code has been sent.");
        }
        if (err) {
            const map = {
                empty: "Email cannot be empty.",
                invalid: "This email is not eligible to sign in.",
                too_fast: "You requested a code too recently. Please try again in a minute.",
                expired: "The code expired. Please request a new one.",
                wrong: "Incorrect code. Please try again.",
                locked: "Too many attempts. Please request a new code."
            };
            alert(map[err] || "Login error, please try again.");

            const url = new URL(window.location);
            url.searchParams.delete('err');
            url.searchParams.delete('ok');
            window.history.replaceState({}, '', url);
        }
    })();
</script>

<?php include 'header.php'; ?>

<div class="body">
    <div class="banner">
        <div class="container">
            <h1>Sign In</h1>
        </div>
    </div>

    <div class="container">
        <div class="sign-in py-3 py-lg-5">
            <div class="row">

                <div class="col-lg-7 col-left">
                    <div class="login padding-box text-center">
                        <h2>Welcome to Collegial Affirmations</h2>
                        <p>Enter your email to receive a 6-digit verification code.</p>

                        <form class="login-form text-center" method="POST" action="send_otp.php" autocomplete="email">
                            <span id="msg"></span>

                            <div class="form-group">
                                <label for="email" class="d-none">Email address</label>
                                <input type="email" class="form-control text-center" id="email" name="email"
                                    placeholder="you@example.edu" value="<?php echo htmlspecialchars($email); ?>"
                                    required>
                            </div>

                            <!-- CSRF -->
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">

                            <button type="submit" class="btn btn-primary mt-2">Send code</button>

                            <small id="emailHelp" class="form-text text-muted mt-3">
                                Trouble signing in? Contact <a href="mailto:support@signin">support@signin</a>.
                            </small>
                        </form>

                        <?php if ($email): ?>
                            <div class="mt-3">
                                <button type="button" class="btn btn-link p-0" id="open-verify-modal">
                                    Already have a code? Verify here
                                </button>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>

                <!-- Right Column: Current Cycle -->
                <div class="col-lg-5">
                    <h5>Current Cycle</h5>
                    <p>Status: <?= ($currentCycle['is_active'] ?? 0) ? 'OPEN' : 'CLOSED' ?></p>

                    <?php if ($cycle_id): ?>
                        <?php
                        $start = strtotime($currentCycle['start_date']);
                        $end = strtotime($currentCycle['end_date']);
                        $now = time();
                        $progress = $now > $end ? 100 : (($now - $start) / max(1, ($end - $start)) * 100);
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

            </div> <!-- /.row -->
        </div>
    </div><!-- /.container -->
</div>

<?php
$resendEmail = $email ?: ($_SESSION['pending_email'] ?? '');
?>
<div id="otpModal" class="otp-modal" aria-hidden="true">
    <div class="otp-modal__dialog">
        <button type="button" class="otp-modal__close" id="otpModalClose" aria-label="Close">×</button>
        <h5 class="mb-3">Enter verification code</h5>

        <form method="POST" action="verify_otp_action.php" id="otpForm">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($resendEmail); ?>">
            <div class="form-group mb-2">
                <label for="otpInput" class="d-none">Verification code</label>
                <input id="otpInput" class="form-control text-center" type="text" inputmode="numeric"
                    autocomplete="one-time-code" name="otp" pattern="\d{6}" maxlength="6" placeholder="6-digit code"
                    required />
            </div>

            <button type="submit" class="btn btn-primary w-100">Verify &amp; Sign in</button>
        </form>

        <div class="text-center mt-3">
            <?php if ($resendEmail): ?>
                <a href="send_otp.php?email=<?php echo urlencode($resendEmail); ?>">Resend code</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .otp-modal {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .45);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1050
    }

    .otp-modal[aria-hidden="false"] {
        display: flex
    }

    .otp-modal__dialog {
        position: relative;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, .2);
        width: 100%;
        max-width: 420px;
        padding: 20px 20px 24px
    }

    .otp-modal__close {
        position: absolute;
        right: 16px;
        top: 10px;
        border: 0;
        background: transparent;
        font-size: 24px;
        line-height: 1;
        cursor: pointer
    }
</style>

<script>
    (function () {
        const modal = document.getElementById('otpModal');
        const openBtn = document.getElementById('open-verify-modal');
        const closeBtn = document.getElementById('otpModalClose');
        const otpInput = document.getElementById('otpInput');

        function openModal() {
            if (!modal) return;
            modal.setAttribute('aria-hidden', 'false');
            setTimeout(() => otpInput && otpInput.focus(), 60);
        }
        function closeModal() {
            if (!modal) return;
            modal.setAttribute('aria-hidden', 'true');
        }

        openBtn && openBtn.addEventListener('click', openModal);
        closeBtn && closeBtn.addEventListener('click', closeModal);
        modal && modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });

        const ok = <?= json_encode($ok) ?>;
        const err = <?= json_encode($err) ?>;
        const email = <?= json_encode($email) ?>;
        const shouldAutoOpen = (ok || email) || ['expired', 'wrong', 'locked'].includes(err);
        if (shouldAutoOpen) openModal();
    })();
</script>

<?php include 'footer.php'; ?>