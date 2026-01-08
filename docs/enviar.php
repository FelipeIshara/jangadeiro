<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* =========================
   Helpers
========================= */

function abortRequest(int $statusCode, string $message): void
{
  http_response_code($statusCode);
  exit($message);
}

function redirectTo(string $path): void
{
  header("Location: {$path}");
  exit;
}

function loadEnvFile(string $path): void
{
  if (!file_exists($path) || !is_readable($path)) return;

  foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;

    [$k, $v] = explode('=', $line, 2);
    $k = trim($k);
    $v = trim($v);

    if (
      (str_starts_with($v, '"') && str_ends_with($v, '"')) ||
      (str_starts_with($v, "'") && str_ends_with($v, "'"))
    ) {
      $v = substr($v, 1, -1);
    }

    putenv("$k=$v");
    $_ENV[$k] = $v;
  }
}

function env(string $key, string $default = ''): string
{
  $val = getenv($key);
  return ($val === false || $val === '') ? $default : $val;
}

function requirePost(): void
{
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    abortRequest(405, 'Use o formulário.');
  }
}

function blockSpamHoneypot(string $field = 'website'): void
{
  if (!empty($_POST[$field] ?? '')) {
    abortRequest(400, 'Spam detectado.');
  }
}

function readContactPayload(): array
{
  $name    = trim((string)($_POST['name'] ?? ''));
  $cel     = trim((string)($_POST['cel'] ?? ''));
  $email   = trim((string)($_POST['email'] ?? ''));
  $content = trim((string)($_POST['content'] ?? ''));

  if ($name === '' || $cel === '' || $content === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    abortRequest(400, 'Dados inválidos. Verifique e tente novamente.');
  }

  if (mb_strlen($content) > 4000) {
    abortRequest(400, 'Mensagem muito longa.');
  }

  return compact('name', 'cel', 'email', 'content');
}

function escapeHtml(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function sanitizeFilename(string $name): string
{
  // remove caminho e caracteres perigosos
  $name = basename($name);
  $name = preg_replace('/[^\w.\- ]+/u', '_', $name) ?: 'arquivo.pdf';

  // garante .pdf no final
  if (!preg_match('/\.pdf$/i', $name)) {
    $name .= '.pdf';
  }

  return $name;
}

/**
 * Lê e valida um anexo PDF (opcional). Retorna null se não houver arquivo.
 * Valida por extensão + MIME real (finfo) + assinatura "%PDF" (fallback).
 */
function readPdfAttachment(string $field = 'attachment', int $maxBytes = 5242880): ?array
{
  if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
    return null;
  }

  $f = $_FILES[$field];

  if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    return null; // anexo opcional
  }

  if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
    abortRequest(400, 'Falha no upload do anexo.');
  }

  $tmp = (string)($f['tmp_name'] ?? '');
  $size = (int)($f['size'] ?? 0);
  $origName = (string)($f['name'] ?? 'arquivo.pdf');

  if ($tmp === '' || !is_uploaded_file($tmp)) {
    abortRequest(400, 'Upload inválido.');
  }

  if ($size <= 0 || $size > $maxBytes) {
    abortRequest(400, 'O anexo deve ser um PDF de até 5MB.');
  }

  // 1) Extensão
  $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
  if ($ext !== 'pdf') {
    abortRequest(400, 'Anexo inválido. Envie apenas PDF.');
  }

  // 2) MIME real via finfo
  $mime = '';
  if (function_exists('finfo_open')) {
    $fi = finfo_open(FILEINFO_MIME_TYPE);
    if ($fi) {
      $mime = (string)finfo_file($fi, $tmp);
      finfo_close($fi);
    }
  }

  // Alguns servidores retornam application/octet-stream mesmo sendo PDF.
  $mimeOk = in_array($mime, ['application/pdf', 'application/x-pdf'], true);

  // 3) Fallback: checa assinatura %PDF
  $header = '';
  $fh = @fopen($tmp, 'rb');
  if ($fh) {
    $header = (string)fread($fh, 4);
    fclose($fh);
  }
  $signatureOk = ($header === '%PDF');

  if (!$mimeOk && !$signatureOk) {
    abortRequest(400, 'Anexo inválido. Envie apenas PDF.');
  }

  return [
    'path' => $tmp,
    'name' => sanitizeFilename($origName),
    'mime' => ($mime !== '' ? $mime : 'application/pdf'),
  ];
}


function requirePhpMailer(): void
{
  $base = dirname(__DIR__) . '/libs/PHPMailer/src/';
  foreach (['Exception.php', 'PHPMailer.php', 'SMTP.php'] as $file) {
    $path = $base . $file;
    if (!file_exists($path)) {
      abortRequest(500, "Erro: arquivo não encontrado: " . htmlspecialchars($path));
    }
    require_once $path;
  }
}

/* =========================
   Config / Template
========================= */

function loadAppConfig(): array
{
  loadEnvFile(dirname(__DIR__) . '/.env');


  $config = [
    'SMTP_HOST'  => env('SMTP_HOST', 'smtp.kinghost.net'),
    'SMTP_USER'  => env('SMTP_USER'),
    'SMTP_PASS'  => env('SMTP_PASS'),
    'TO_EMAIL'   => env('TO_EMAIL'),
    'TO_NAME'    => env('TO_NAME', 'Brasilway'),
    'FROM_EMAIL' => env('FROM_EMAIL'), // fallback abaixo
    'FROM_NAME'  => env('FROM_NAME', 'Site Brasilway - Contato'),

    // redirects
    'SUCCESS_REDIRECT' => env('SUCCESS_REDIRECT', 'index.html'),
    'ERROR_REDIRECT'   => env('ERROR_REDIRECT', 'index2.html'),
  ];

  if ($config['FROM_EMAIL'] === '') {
    $config['FROM_EMAIL'] = $config['SMTP_USER'];
  }

  if ($config['SMTP_USER'] === '' || $config['SMTP_PASS'] === '' || $config['TO_EMAIL'] === '') {
    abortRequest(500, 'Configuração de email ausente no servidor.');
  }

  return $config;
}

function buildEmailTemplate(array $payload): array
{
  $safeName    = escapeHtml($payload['name']);
  $safeEmail   = escapeHtml($payload['email']);
  $safeCel     = escapeHtml($payload['cel']);
  $safeContent = nl2br(escapeHtml($payload['content']));

  $html = "
    <h2>Novo contato do site</h2>
    <p><strong>Nome:</strong> {$safeName}</p>
    <p><strong>Email:</strong> {$safeEmail}</p>
    <p><strong>Celular:</strong> {$safeCel}</p>
    <hr>
    <p>{$safeContent}</p>
  ";

  $text =
    "Novo contato do site\n\n" .
    "Nome: {$payload['name']}\n" .
    "Email: {$payload['email']}\n" .
    "Celular: {$payload['cel']}\n\n" .
    "Mensagem:\n{$payload['content']}";

  return [
    'subject' => 'Novo contato do site',
    'html' => $html,
    'text' => $text,
  ];
}

/* =========================
   Mailer
========================= */

function createMailer(array $config): PHPMailer
{
  $mail = new PHPMailer(true);

  $mail->SMTPDebug = 0;
  $mail->Timeout = 10;

  $mail->isSMTP();
  $mail->Host = $config['SMTP_HOST'];
  $mail->SMTPAuth = true;
  $mail->Username = $config['SMTP_USER'];
  $mail->Password = $config['SMTP_PASS'];

  // Produção (KingHost): SSL/465
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
  $mail->Port = 465;

  // Alternativa: TLS/587
  // $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  // $mail->Port = 587;

  $mail->CharSet = 'UTF-8';

  $mail->setFrom($config['FROM_EMAIL'], $config['FROM_NAME']);

  return $mail;
}

function sendContactEmail(array $config, array $payload, ?array $attachment): void
{
  $template = buildEmailTemplate($payload);

  $mail = createMailer($config);

  $mail->addAddress($config['TO_EMAIL'], $config['TO_NAME']);
  $mail->addReplyTo($payload['email'], $payload['name']);

  $mail->isHTML(true);
  $mail->Subject = $template['subject'];
  $mail->Body = $template['html'];
  $mail->AltBody = $template['text'];
  if ($attachment !== null) {
    $mail->addAttachment(
      $attachment['path'],
      $attachment['name'],
      'base64',
      $attachment['mime']
    );
  }
  $mail->send();
}

/* =========================
   Main
========================= */

function main(): void
{
  requirePost();
  blockSpamHoneypot('website');

  $config = loadAppConfig();
  $payload = readContactPayload();
  $attachment = readPdfAttachment('attachment', 5 * 1024 * 1024);
  requirePhpMailer();

  try {
    sendContactEmail($config, $payload, $attachment);
    redirectTo($config['SUCCESS_REDIRECT']);
  } catch (Exception $e) {
    redirectTo($config['ERROR_REDIRECT']);
  }
}

main();
