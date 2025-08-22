<?php $email = $_GET['email'] ?? ($_SESSION['pending_email'] ?? ''); ?>
<form method="POST" action="verify_otp_action.php">
  <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
  <label>Verification code</label>
  <input type="text" name="otp" pattern="\d{6}" maxlength="6" required />
  <button type="submit">Verify & Sign in</button>
</form>
<a href="send_otp.php?email=<?php echo urlencode($email); ?>">Resend code</a>
