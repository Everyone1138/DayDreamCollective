<?php
// subscribe.php
header('Content-Type: application/json');

// OPTIONAL: reCAPTCHA v3 secret key (G)
$recaptchaSecret = ''; // 'YOUR_RECAPTCHA_SECRET_KEY_HERE';

// Gmail SMTP credentials (D)
$mail->Username   = 'daydreamcollectiveart@gmail.com';      // TODO: your Gmail
$mail->Password   = 'evkanmrsdorsunfn';   // TODO: 16-char app password

// OPTIONAL: Google Sheets webhook (F)
$googleSheetsWebhook = ''; // 'https://script.google.com/macros/s/XXXXX/exec';

// Helper: verify reCAPTCHA v3 (G)
function verify_recaptcha($token, $secret) {
    if (!$secret) {
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

    if (isset($json['score']) && $json['score'] < 0.5) {
        return false;
    }

    return true;
}

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

// Honeypot (H)
if (!empty($_POST['website'])) {
    echo json_encode(['ok' => true]);
    exit;
}

// reCAPTCHA check (G)
$recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
if (!verify_recaptcha($recaptchaToken, $recaptchaSecret)) {
    echo json_encode(['ok' => false, 'error' => 'reCAPTCHA verification failed. Please try again.']);
    exit;
}

// Only email field for newsletter (B)
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

// CSV log of subscribers (C)
$subsFile = __DIR__ . '/subscribers.csv';
$subsData = [
    date('Y-m-d H:i:s'),
    $cleanEmail,
    $_SERVER['REMOTE_ADDR'] ?? '',
];

if ($fh = @fopen($subsFile, 'a')) {
    fputcsv($fh, $subsData);
    fclose($fh);
}

// PHPMailer (B, D)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// NOTE: adjust path if your folder is different
require __DIR__ . '/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';

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

    // 2) Auto-reply to the subscriber (D)
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

    // 3) OPTIONAL: send to Google Sheets (F)
    if (!empty($googleSheetsWebhook)) {
        $payload = http_build_query([
            'type'  => 'subscribe',
            'email' => $cleanEmail,
            'time'  => date('c'),
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
    error_log('Subscribe mailer error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Mailer error. Please try again.']);
}