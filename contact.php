<?php
// contact.php
header('Content-Type: application/json');

// OPTIONAL: reCAPTCHA v3 secret key (G)
// Leave as '' to disable reCAPTCHA for now
$recaptchaSecret = ''; // 'YOUR_RECAPTCHA_SECRET_KEY_HERE';

// Gmail SMTP credentials (D)
// TODO: replace with your real Gmail + App Password
$mail->Username   = 'daydreamcollectiveart@gmail.com';      // TODO: your Gmail
$mail->Password   = 'evkanmrsdorsunfn'; 

// OPTIONAL: Google Sheets webhook (F)
// This will be a Google Apps Script Web App URL if/when you set it up
$googleSheetsWebhook = ''; // 'https://script.google.com/macros/s/XXXXX/exec';

// Helper: verify reCAPTCHA v3 (G)
function verify_recaptcha($token, $secret) {
    if (!$secret) {
        // reCAPTCHA disabled
        return true;
    }
    if (!$token) {
        return false;
    }

    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = http_build_query([
        'secret'   => $secret,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => $data,
            'timeout' => 5,
        ],
    ];

    $context = stream_context_create($options);
    $result  = @file_get_contents($url, false, $context);

    if ($result === false) {
        return false;
    }

    $json = json_decode($result, true);
    if (!is_array($json) || empty($json['success'])) {
        return false;
    }

    // For v3, optionally check the score
    if (isset($json['score']) && $json['score'] < 0.5) {
        return false;
    }

    return true;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

// Honeypot (H)
if (!empty($_POST['website'])) {
    // Pretend success to confuse bots
    echo json_encode(['ok' => true]);
    exit;
}

// reCAPTCHA check (G)
$recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
if (!verify_recaptcha($recaptchaToken, $recaptchaSecret)) {
    echo json_encode(['ok' => false, 'error' => 'reCAPTCHA verification failed. Please try again.']);
    exit;
}

// Get inputs
$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');
$agree   = isset($_POST['agree']);

// Basic validation (A, H)
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

// CSV log (C)
$logFile = __DIR__ . '/contact-log.csv';
$logData = [
    date('Y-m-d H:i:s'),
    $cleanName,
    $cleanEmail,
    $cleanSubject,
    preg_replace('/\s+/', ' ', $cleanMessage),
    $_SERVER['REMOTE_ADDR'] ?? '',
];

if ($fh = @fopen($logFile, 'a')) {
    fputcsv($fh, $logData);
    fclose($fh);
}

// PHPMailer (A, D)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// NOTE: adjust this path if your folder is named differently
require __DIR__ . '/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';

try {
    // 1) Email to you (site owner)
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

    $mailOwner->send();

    // 2) Auto-reply to the user (D)
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

    // 3) OPTIONAL: send to Google Sheets (F) via Apps Script webhook
    if (!empty($googleSheetsWebhook)) {
        $payload = http_build_query([
            'type'    => 'contact',
            'name'    => $cleanName,
            'email'   => $cleanEmail,
            'subject' => $cleanSubject,
            'message' => $cleanMessage,
            'time'    => date('c'),
        ]);

        @file_get_contents($googleSheetsWebhook, false, stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 3,
            ],
        ]));
    }

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    error_log('Contact mailer error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Mailer error. Please try again.']);
}