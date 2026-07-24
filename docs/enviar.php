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

function isAjaxRequest(): bool
{
  $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
  $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

  return strtolower($requestedWith) === 'xmlhttprequest' || strpos($accept, 'text/plain') !== false;
}

function sendSuccessResponse(array $config): void
{
  if (isAjaxRequest()) {
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Mensagem enviada com sucesso. Obrigado pelo contato!');
  }

  redirectTo($config['SUCCESS_REDIRECT']);
}

function loadEnvFile(string $path): void
{
  if (!file_exists($path) || !is_readable($path)) return;

  foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || startsWith($line, '#') || !contains($line, '=')) continue;

    [$k, $v] = explode('=', $line, 2);
    $k = trim($k);
    $v = trim($v);

    if (
      (startsWith($v, '"') && endsWith($v, '"')) ||
      (startsWith($v, "'") && endsWith($v, "'"))
    ) {
      $v = substr($v, 1, -1);
    }

    $_ENV[$k] = $v;
  }
}

function env(string $key, string $default = ''): string
{
  if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
    return (string)$_ENV[$key];
  }

  $val = getenv($key);
  return ($val === false || $val === '') ? $default : $val;
}

function startsWith(string $value, string $prefix): bool
{
  return $prefix === '' || strncmp($value, $prefix, strlen($prefix)) === 0;
}

function endsWith(string $value, string $suffix): bool
{
  if ($suffix === '') return true;
  return substr($value, -strlen($suffix)) === $suffix;
}

function contains(string $value, string $needle): bool
{
  return $needle === '' || strpos($value, $needle) !== false;
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

function postFormUrlEncoded(string $url, array $fields): string
{
  $body = http_build_query($fields);

  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $body,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CONNECTTIMEOUT => 5,
      CURLOPT_TIMEOUT => 10,
      CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
      throw new RuntimeException('Falha ao validar Turnstile: ' . $error);
    }

    return (string)$response;
  }

  $context = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
      'content' => $body,
      'timeout' => 10,
    ],
  ]);

  $response = file_get_contents($url, false, $context);

  if ($response === false) {
    throw new RuntimeException('Falha ao validar Turnstile.');
  }

  return (string)$response;
}

function verifyTurnstile(string $secret): void
{
  if ($secret === '') {
    abortRequest(500, 'Configuracao de seguranca ausente no servidor.');
  }

  $token = trim((string)($_POST['cf-turnstile-response'] ?? ''));

  if ($token === '') {
    abortRequest(400, 'Confirme que voce nao e um robo e tente novamente.');
  }

  $response = postFormUrlEncoded('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
    'secret' => $secret,
    'response' => $token,
    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
  ]);

  $result = json_decode($response, true);

  if (!is_array($result) || empty($result['success'])) {
    abortRequest(400, 'Nao foi possivel confirmar a verificacao de seguranca. Tente novamente.');
  }
}

function readContactPayload(): array
{
  $name    = trim((string)($_POST['name'] ?? ''));
  $cel     = trim((string)($_POST['cel'] ?? ''));
  $email   = trim((string)($_POST['email'] ?? ''));
  $content = trim((string)($_POST['content'] ?? 'Contato enviado pelo formulário Trabalhe Conosco.'));

  if ($name === '' || $cel === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    abortRequest(400, 'Dados inválidos. Verifique e tente novamente.');
  }

  if (textLength($content) > 4000) {
    abortRequest(400, 'Mensagem muito longa.');
  }

  return compact('name', 'cel', 'email', 'content');
}

function escapeHtml(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function textLength(string $value): int
{
  return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
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
    'TURNSTILE_SECRET_KEY' => env('TURNSTILE_SECRET_KEY'),

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
    'subject' => "{$safeName} - Site Jangadeiro - Trabalhe Conosco",
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
  try {
    requirePost();
    blockSpamHoneypot('website');

    $config = loadAppConfig();
    verifyTurnstile($config['TURNSTILE_SECRET_KEY']);
    $payload = readContactPayload();
    $attachment = readPdfAttachment('attachment', 5 * 1024 * 1024);
    requirePhpMailer();

    sendContactEmail($config, $payload, $attachment);
    sendSuccessResponse($config);
  } catch (\Throwable $e) {
    error_log('Erro ao enviar email: ' . $e->getMessage());
    abortRequest(500, 'Nao foi possivel enviar a mensagem no momento. Tente novamente mais tarde.');
    exit;
  }
}

main();
