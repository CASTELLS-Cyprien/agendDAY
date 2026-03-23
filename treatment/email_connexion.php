<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function getEmailConnection(): PHPMailer {
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_SECURE;   // 'ssl'
    $mail->Port       = SMTP_PORT;     // 465

    $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
    $mail->addReplyTo(EMAIL_FROM, EMAIL_FROM_NAME);
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';

    return $mail;
}