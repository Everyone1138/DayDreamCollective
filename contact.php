<?php
// contact.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok' => false, 'error' => 'Invalid request']);
  exit;
}

// Basic honeypot
if (!empty($_POST['website'])) {
  echo json_encode(['ok' => true]); // pretend success for bots
  exit;
}

// Simple validation
$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');
$agree   = isset($_POST['agree']);

if ($name === '' || $email === '' || $subject === '' || $message === '' || !$agree) {
  echo json_encode(['ok' => false, 'error' => 'Please fill in all fields and agree to the policy.']);
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  echo json_encode(['ok' => false, 'error' => 'Invalid email address.']);
  exit;
}

// Load PHPMailer (no composer)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';

$mail = new PHPMailer(true);

try {
  // SMTP config (Gmail)
  $mail->isSMTP();
  $mail->Host       = 'smtp.gmail.com';
  $mail->SMTPAuth   = true;
  $mail->setFrom    = 
  $mail->Username   = 'daydreamcollectiveart@gmail.com';      // TODO: your Gmail
  $mail->Password   = 'qulyarrnciulparn';   // TODO: 16-char app password
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port       = 587;

  // From/To
  $mail->setFrom('yourgmail@gmail.com', 'Day Dream Collective'); // match your Gmail
  $mail->addAddress('yourgmail@gmail.com', 'Day Dream Collective'); // where you receive
  $mail->addReplyTo($email, $name); // visitor’s email for reply

  // Content
  $mail->isHTML(true);
  $mail->Subject = 'New Inquiry: ' . $subject;
  $mail->Body    = nl2br(
    "Name: {$name}\nEmail: {$email}\nSubject: {$subject}\n\nMessage:\n{$message}"
  );
  $mail->AltBody = "Name: {$name}\nEmail: {$email}\nSubject: {$subject}\n\nMessage:\n{$message}";

  $mail->send();

  echo json_encode(['ok' => true]);
} catch (Exception $e) {
  // In production you might log $mail->ErrorInfo
  echo json_encode(['ok' => false, 'error' => 'Mailer error. Please try again.']);
}