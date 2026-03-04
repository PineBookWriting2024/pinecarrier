<?php

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$configPath = __DIR__ . '/smtp-config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'SMTP config missing']);
    exit;
}

$smtp = require $configPath;

if (empty($smtp['password']) || $smtp['password'] === 'CHANGE_ME') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'SMTP credentials are not configured.']);
    exit;
}

$smtpHost = strtolower(trim((string)($smtp['host'] ?? '')));
$smtpUser = strtolower(trim((string)($smtp['username'] ?? '')));
$fromEmail = strtolower(trim((string)($smtp['from_email'] ?? '')));

if ($fromEmail === '') {
    $fromEmail = $smtpUser;
}
$toEmail = strtolower(trim((string)($smtp['to_email'] ?? '')));

$isGmailUser = (substr($smtpUser, -10) === '@gmail.com');
if (strpos($smtpHost, 'hostinger') !== false && $isGmailUser) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'SMTP mismatch: Hostinger SMTP cannot authenticate with a Gmail username.',
    ]);
    exit;
}

if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'SMTP config error: invalid from_email.']);
    exit;
}

if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'SMTP config error: invalid to_email.']);
    exit;
}

require_once __DIR__ . '/PHPMailer/class.phpmailer.php';
require_once __DIR__ . '/PHPMailer/class.smtp.php';

function post_value($key)
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
}

$fields = [
    'from_zip' => post_value('from_zip'),
    'to_zip' => post_value('to_zip'),
    'transport_type' => post_value('transport_type'),
    'vehicle_type' => post_value('vehicle_type'),
    'vehicle_year' => post_value('vehicle_year'),
    'vehicle_make' => post_value('vehicle_make'),
    'vehicle_model' => post_value('vehicle_model'),
    'is_running' => post_value('is_running'),
    'available_date' => post_value('available_date'),
    'full_name' => post_value('full_name'),
    'phone' => post_value('phone'),
    'email' => post_value('email'),
    'consent' => isset($_POST['consent']) ? 'Yes' : 'No',
    'page_url' => post_value('page_url'),
];

$required = [
    'from_zip', 'to_zip', 'transport_type', 'vehicle_type', 'vehicle_year',
    'vehicle_make', 'vehicle_model', 'is_running', 'available_date',
    'full_name', 'phone', 'email',
];

foreach ($required as $key) {
    if ($fields[$key] === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Please complete all required fields.']);
        exit;
    }
}

if (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Please provide a valid email address.']);
    exit;
}

$subject = 'New Quote Request - ' . $fields['full_name'];

$bodyRows = [
    'From ZIP' => $fields['from_zip'],
    'To ZIP' => $fields['to_zip'],
    'Transport Type' => $fields['transport_type'],
    'Vehicle Type' => $fields['vehicle_type'],
    'Vehicle Year' => $fields['vehicle_year'],
    'Vehicle Make' => $fields['vehicle_make'],
    'Vehicle Model' => $fields['vehicle_model'],
    'Running Condition' => $fields['is_running'],
    'Available Date' => $fields['available_date'],
    'Full Name' => $fields['full_name'],
    'Phone' => $fields['phone'],
    'Email' => $fields['email'],
    'Consent' => $fields['consent'],
    'Page URL' => $fields['page_url'],
];

$table = '';
foreach ($bodyRows as $label => $value) {
    $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $safeValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $table .= "<tr><td style=\"padding:8px;border:1px solid #dce2ea;\"><strong>{$safeLabel}</strong></td>"
        . "<td style=\"padding:8px;border:1px solid #dce2ea;\">{$safeValue}</td></tr>";
}

$htmlBody = '<html><body>'
    . '<h2 style="margin:0 0 14px;color:#121c45;font-family:Arial,sans-serif;">New Get A Quote Online Submission</h2>'
    . '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:760px;font-family:Arial,sans-serif;font-size:14px;">'
    . $table
    . '</table>'
    . '</body></html>';

try {
    if (!class_exists('PHPMailer')) {
        throw new RuntimeException('PHPMailer class not found.');
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtp['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $smtp['username'];
    $mail->Password = $smtp['password'];
    $mail->SMTPSecure = $smtp['encryption'];
    $mail->Port = (int) $smtp['port'];
    $mail->SMTPDebug = (int) ($smtp['debug'] ?? 0);

    $mail->setFrom($fromEmail, $smtp['from_name']);
    $mail->addAddress($toEmail, $smtp['to_name']);
    $mail->addReplyTo($fields['email'], $fields['full_name']);

    $mail->Subject = $subject;
    $mail->isHTML(true);
    $mail->Body = $htmlBody;
    $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

    $mail->send();

    echo json_encode(['ok' => true, 'message' => 'Quote request sent successfully.']);
} catch (Throwable $e) {
    error_log('Quote wizard SMTP error: ' . $e->getMessage());
    http_response_code(500);
    $message = 'Unable to send right now. Please try again.';
    if (!empty($smtp['debug'])) {
        $message = $e->getMessage();
    }
    echo json_encode(['ok' => false, 'message' => $message]);
}
