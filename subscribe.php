<?php
// subscribe.php - Day Dream Collective newsletter handler

header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// PHPMailer includes (folder: /phpmailer/src)
require __DIR__ . '/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';

$gmailUser = 'daydreamcollectiveart@gmail.com';      // <-- your Gmail
$gmailPass = 'evkanmrsdorsunfn';                    // <-- your 16-char app password (no spaces)

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

// Honeypot
if (!empty($_POST['website'])) {
    echo json_encode(['ok' => true]);
    exit;
}

// Only email is required for newsletter
$email = trim($_POST['email'] ?? '');

if ($email === '') {
    echo json_encode(['ok' => false, 'error' => 'Please enter your email.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}

$cleanEmail = strip_tags($email);

// Log to CSV (subscribers.csv in same folder)
$subsFile = __DIR__ . '/subscribers.csv';
$subsRow  = [
    date('Y-m-d H:i:s'),
    $cleanEmail,
    $_SERVER['REMOTE_ADDR'] ?? '',
];

if ($fh = @fopen($subsFile, 'a')) {
    fputcsv($fh, $subsRow);
    fclose($fh);
}

try {
    // 1) Email to you (new subscriber)
    $mailOwner = new PHPMailer(true);
    $mailOwner->isSMTP();
    $mailOwner->Host       = 'smtp.gmail.com';
    $mailOwner->SMTPAuth   = true;
    $mailOwner->Username   = $gmailUser;
    $mailOwner->Password   = $gmailPass;
    $mailOwner->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mailOwner->Port       = 587;

    $mailOwner->setFrom($gmailUser, 'Day Dream Collective');
    $mailOwner->addAddress($gmailUser, 'Day Dream Collective');

    $mailOwner->isHTML(true);
    $mailOwner->Subject = 'New Newsletter Subscriber';
    $bodyText = "New subscriber email: {$cleanEmail}";
    $mailOwner->Body    = nl2br($bodyText);
    $mailOwner->AltBody = $bodyText;

    $mailOwner->send();

    // 2) Auto-reply to subscriber
    $mailReply = new PHPMailer(true);
    $mailReply->isSMTP();
    $mailReply->Host       = 'smtp.gmail.com';
    $mailReply->SMTPAuth   = true;
    $mailReply->Username   = $gmailUser;
    $mailReply->Password   = $gmailPass;
    $mailReply->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mailReply->Port       = 587;

    $mailReply->setFrom($gmailUser, 'Day Dream Collective');
    $mailReply->addAddress($cleanEmail);

    $mailReply->isHTML(true);
    $mailReply->Subject = 'Welcome to Day Dream Collective';
    $mailReply->Body = nl2br(
        "Hi there,\n\n" .
        "Thank you for subscribing to Day Dream Collective.\n" .
        "You’ll receive updates, stories and new pieces as they’re released.\n\n" .
        "With gratitude,\nDay Dream Collective"
    );
    $mailReply->AltBody =
        "Hi there,\n\n" .
        "Thank you for subscribing to Day Dream Collective.\n" .
        "You’ll receive updates, stories and new pieces as they’re released.\n\n" .
        "With gratitude,\nDay Dream Collective";

    $mailReply->send();

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    error_log('Subscribe mailer error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Mailer error. Please try again.']);
}