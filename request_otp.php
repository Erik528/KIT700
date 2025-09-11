<?php
// request_otp.php
declare(strict_types=1);
session_start();
require_once 'config.php';

if (defined('OTP_ENABLED') && OTP_ENABLED === false) {
  header('Location: login.php');
  exit;
}

$email = strtolower(trim($_POST['email'] ?? $_GET['email'] ?? ''));

if ($email !== ''): ?>
  <!doctype html>
  <html>

  <body>
    <form id="relay" method="POST" action="send_otp.php">
      <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
    </form>
    <script>document.getElementById('relay').submit();</script>
  </body>

  </html>
  <?php exit; endif; ?>

<!doctype html>
<html>

<body>
  <form method="POST" action="send_otp.php">
    <label>Email</label>
    <input type="email" name="email" required />
    <button type="submit">Send code</button>
  </form>
</body