<?php
// contact.php - Day Dream Collective contact handler with optional image upload

header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';

$gmailUser = 'daydreamcollectiveart@gmail.com';
$gmailPass = 'evkanmrsdorsunfn'; 
// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

// Honeypot (hidden "website" field)
if (!empty($_POST['website'])) {
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

// === OPTIONAL IMAGE UPLOAD HANDLING ===
$attachmentTmpPath = null;
$attachmentName    = null;

// Check if a file was uploaded
if (isset($_FILES['reference_image']) && $_FILES['reference_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $fileError = $_FILES['reference_image']['error'];

    if ($fileError === UPLOAD_ERR_OK) {
        $fileSize = $_FILES['reference_image']['size'];
        $fileTmp  = $_FILES['reference_image']['tmp_name'];
        $fileName = $_FILES['reference_image']['name'];

        // Max 5MB
        $maxSize = 5 * 1024 * 1024;
        if ($fileSize > $maxSize) {
            echo json_encode(['ok' => false, 'error' => 'Image is too large. Max size is 5MB.']);
            exit;
        }

        // Check MIME type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($fileTmp);

        $allowed = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ];

        if (!in_array($mime, $allowed, true)) {
            echo json_encode(['ok' => false, 'error' => 'Invalid image type. Please upload JPG, PNG, GIF, or WEBP.']);
            exit;
        }

        // If all good, we'll attach this temp file directly in PHPMailer
        $attachmentTmpPath = $fileTmp;
        $attachmentName    = $fileName;
    } else {
        // Some upload error occurred
        echo json_encode(['ok' => false, 'error' => 'There was a problem uploading the image. Please try again.']);
        exit;
    }
}

// Log to CSV (contact-log.csv in the same folder)
$logFile = __DIR__ . '/contact-log.csv';
$logRow  = [
    date('Y-m-d H:i:s'),
    $cleanName,
    $cleanEmail,
    $cleanSubject,
    preg_replace('/\s+/', ' ', $cleanMessage),
    $_SERVER['REMOTE_ADDR'] ?? '',
    $attachmentName ?? '', // store original filename if provided
];

if ($fh = @fopen($logFile, 'a')) {
    fputcsv($fh, $logRow);
    fclose($fh);
}

try {
    // 1) Email to you
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
    $mailOwner->addReplyTo($cleanEmail, $cleanName);

    $bodyText = "Name: {$cleanName}\nEmail: {$cleanEmail}\nSubject: {$cleanSubject}\n\nMessage:\n{$cleanMessage}";

    $mailOwner->isHTML(true);
    $mailOwner->Subject = 'New Inquiry: ' . $cleanSubject;
    $mailOwner->Body    = nl2br($bodyText);
    $mailOwner->AltBody = $bodyText;

    // Attach image if present
    if ($attachmentTmpPath && $attachmentName) {
        $mailOwner->addAttachment($attachmentTmpPath, $attachmentName);
    }

    $mailOwner->send();

    // 2) Auto-reply to the visitor (no need to attach image back)
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
        "We’ve received your message" .
        ($attachmentName ? " along with your reference image" : "") .
        " and will respond as soon as we can.\n\n" .
        "With gratitude,\nDay Dream Collective"
    );
    $mailReply->AltBody =
        "Hi {$cleanName},\n\n" .
        "Thank you for reaching out to Day Dream Collective.\n" .
        "We’ve received your message" .
        ($attachmentName ? " along with your reference image" : "") .
        " and will respond as soon as we can.\n\n" .
        "With gratitude,\nDay Dream Collective";

    $mailReply->send();

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    error_log('Contact mailer error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Mailer error. Please try again.']);
}