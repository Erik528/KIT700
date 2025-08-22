<?php
error_log(message: '>>> LOADED mailer.php from ' . __FILE__);

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_mail(string $to, string $subject, string $html): bool
{
    error_log(message: 'LOADED mailer.php from ' . __FILE__);
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = 'error_log';
        error_log(message: '>>> USING SMTP host: smtp.sendgrid.net');

        $mail->isSMTP();
        $mail->Host = 'smtp.sendgrid.net';
        $mail->Port = 587;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPAuth = true;

        $apiKey = getenv('SENDGRID_API_KEY') ?: '';
        error_log('SENDGRID_API_KEY length = ' . strlen($apiKey));
        $mail->Username = 'apikey';
        $mail->Password = $apiKey;

        $mail->setFrom('booots2023@gmail.com', 'Collegial Affirmations Bot');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;

        return $mail->send();
    } catch (Exception $e) {
        error_log(message: 'Mailer exception: ' . $e->getMessage());
        error_log(message: 'Mailer errorInfo: ' . $mail->ErrorInfo);
        return false;
    }
}

