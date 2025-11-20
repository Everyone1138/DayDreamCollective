<?php
// contact.php - Day Dream Collective contact handler

header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// PHPMailer includes (folder: /phpmailer/src)
require __DIR__ . '/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';

$gmailUser = 'daydreamcollectiveart@gmail.com';// <-- your Gmail
$gmailPass = 'evkanmrsdorsunfn'; // <-- your 16-char app password (no spaces)

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

// Honeypot (hidden "website" field)
if (!empty($_POST['website'])) {
    // Pretend success to bots
    echo json_encode(['ok' => true]);
    exit;
}

// Get and validate fields
$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');
$agree   = isset($_POST['agree']);

if ($name === '' || $email === '' || $subject === '' || $message === '') {
    echo json_encode(['ok' => false, 'error' => 'Please fill in all required fields.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}

if (!$agree) {
    echo json_encode(['ok' => false, 'error' => 'Please accept the terms before sending.']);
    exit;
}

// Sanitize
$cleanName    = strip_tags($name);
$cleanEmail   = strip_tags($email);
$cleanSubject = strip_tags($subject);
$cleanMessage = strip_tags($message);

// Log to CSV (contact-log.csv in the same folder)
$logFile = __DIR__ . '/contact-log.csv';
$logRow  = [
    date('Y-m-d H:i:s'),
    $cleanName,
    $cleanEmail,
    $cleanSubject,
    preg_replace('/\s+/', ' ', $cleanMessage),
    $_SERVER['REMOTE_ADDR'] ?? '',
];

if ($fh = @fopen($logFile, 'a')) {
    fputcsv($fh, $logRow);
    fclose($fh);
}

try {
    // 1) Email to you
    $mailOwner = new PHPMailer(true);
    $mailOwner->isSMTP();
    $mailOwner->Host = 'smtp.gmail.com';
    $mailOwner->SMTPAuth = true;
    $mailOwner->Username = $gmailUser;
    $mailOwner->Password = $gmailPass;
    $mailOwner->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mailOwner->Port  = 587;

    $mailOwner->setFrom($gmailUser, 'Day Dream Collective');
    $mailOwner->addAddress($gmailUser, 'Day Dream Collective');
    $mailOwner->addReplyTo($cleanEmail, $cleanName);

    $bodyText = "Name: {$cleanName}\nEmail: {$cleanEmail}\nSubject: {$cleanSubject}\n\nMessage:\n{$cleanMessage}";

    $mailOwner->isHTML(true);
    $mailOwner->Subject = 'New Inquiry: ' . $cleanSubject;
    $mailOwner->Body    = nl2br($bodyText);
    $mailOwner->AltBody = $bodyText;

    $mailOwner->send();

    // 2) Auto-reply to the visitor
    $mailReply = new PHPMailer(true);
    $mailReply->isSMTP();
    $mailReply->Host       = 'smtp.gmail.com';
    $mailReply->SMTPAuth   = true;
    $mailReply->Username   = $gmailUser;
    $mailReply->Password   = $gmailPass;
    $mailReply->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mailReply->Port       = 587;

    $mailReply->setFrom($gmailUser, 'Day Dream Collective');
    $mailReply->addAddress($cleanEmail, $cleanName);

    $mailReply->isHTML(true);
    $mailReply->Subject = 'Thank you for contacting Day Dream Collective';
    $mailReply->Body = nl2br(
        "Hi {$cleanName},\n\n" .
        "Thank you for reaching out to Day Dream Collective.\n" .
        "We’ve received your message and will respond as soon as we can.\n\n" .
        "With gratitude,\nDay Dream Collective"
    );
    $mailReply->AltBody =
        "Hi {$cleanName},\n\n" .
        "Thank you for reaching out to Day Dream Collective.\n" .
        "We’ve received your message and will respond as soon as we can.\n\n" .
        "With gratitude,\nDay Dream Collective";

    $mailReply->send();

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    error_log('Contact mailer error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Mailer error. Please try again.']);
}