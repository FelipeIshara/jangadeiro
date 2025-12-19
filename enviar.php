<?php
declare(strict_types=1);

/**
 * enviar.php — Contato do site (PHPMailer + SMTP KingHost)
 *
 * Estrutura esperada no projeto:
 * - index.html (ou página com o form)
 * - enviar.php (este arquivo)
 * - libs/PHPMailer/src/PHPMailer.php
 * - libs/PHPMailer/src/SMTP.php
 * - libs/PHPMailer/src/Exception.php
 *
 * Form HTML deve ter:
 * <form action="enviar.php" method="POST"> ... </form>
 */

// =========================
// DEBUG (APENAS PARA TESTE)
// Remova/Desative em produção
// =========================
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// =========================
// Regras básicas
// =========================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Use o formulário para enviar (POST).');
}

// Honeypot anti-spam
if (!empty($_POST['website'] ?? '')) {
  http_response_code(400);
  exit('Spam detectado.');
}

// Coleta e validação
$name    = trim((string)($_POST['name'] ?? ''));
$cel     = trim((string)($_POST['cel'] ?? ''));
$email   = trim((string)($_POST['email'] ?? ''));
$content = trim((string)($_POST['content'] ?? ''));

if ($name === '' || $cel === '' || $content === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  exit('Dados inválidos. Verifique e tente novamente.');
}

// Limite para evitar abuso
if (mb_strlen($content) > 4000) {
  http_response_code(400);
  exit('Mensagem muito longa.');
}

// =========================
// PHPMailer (sem Composer)
// =========================
$base = __DIR__ . '/libs/PHPMailer/src/';
$reqFiles = [
  $base . 'Exception.php',
  $base . 'PHPMailer.php',
  $base . 'SMTP.php',
];

foreach ($reqFiles as $f) {
  if (!file_exists($f)) {
    http_response_code(500);
    exit("Erro: arquivo não encontrado: " . htmlspecialchars($f));
  }
  require_once $f;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// =========================
// CONFIG (AJUSTE AQUI)
// =========================
$SMTP_HOST = 'smtp.kinghost.net';

// IMPORTANTE: use uma conta real do domínio (criada no painel de emails)
$SMTP_USER = 'contato@brasilway.com.br';
$SMTP_PASS = 'COLE_A_SENHA_DESSA_CONTA_AQUI';

// Para onde você quer receber o formulário
$TO_EMAIL  = 'contato@brasilway.com.br';
$TO_NAME   = 'Brasilway';

// Remetente (deve ser a mesma conta do SMTP, para evitar bloqueio/spam)
$FROM_EMAIL = $SMTP_USER;
$FROM_NAME  = 'Site Brasilway - Contato';

// =========================
// Envio
// =========================
$mail = new PHPMailer(true);

try {
  // Debug SMTP para diagnóstico (depois deixe 0 em produção)
  $mail->SMTPDebug  = 2;
  $mail->Debugoutput = 'html';

  // Evita "carregamento infinito" quando a rede bloqueia SMTP
  $mail->Timeout = 10;

  $mail->isSMTP();

  // Dica: alguns ambientes (Codespace) podem ter problemas com resolução/IPv6
  // se travar, use gethostbyname (força IPv4):
  // $mail->Host = gethostbyname($SMTP_HOST);
  $mail->Host = $SMTP_HOST;

  $mail->SMTPAuth = true;
  $mail->Username = $SMTP_USER;
  $mail->Password = $SMTP_PASS;

  // ===== TENTE PRIMEIRO TLS/587 (mais compatível em ambientes dev)
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port       = 587;

  // ===== Se na KingHost você quiser SSL/465, troque para:
  // $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
  // $mail->Port       = 465;

  $mail->CharSet = 'UTF-8';

  // Remetente
  $mail->setFrom($FROM_EMAIL, $FROM_NAME);

  // Destino
  $mail->addAddress($TO_EMAIL, $TO_NAME);

  // Reply-To: responde direto para o visitante
  $mail->addReplyTo($email, $name);

  // Conteúdo
  $mail->isHTML(true);
  $mail->Subject = 'Novo contato do site';

  $safeName    = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
  $safeEmail   = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
  $safeCel     = htmlspecialchars($cel, ENT_QUOTES, 'UTF-8');
  $safeContent = nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));

  $mail->Body = "
    <h2>Novo contato do site</h2>
    <p><strong>Nome:</strong> {$safeName}</p>
    <p><strong>Email:</strong> {$safeEmail}</p>
    <p><strong>Celular:</strong> {$safeCel}</p>
    <hr>
    <p>{$safeContent}</p>
  ";

  $mail->AltBody =
    "Novo contato do site\n\n" .
    "Nome: {$name}\n" .
    "Email: {$email}\n" .
    "Celular: {$cel}\n\n" .
    "Mensagem:\n{$content}";

  // Envia
  $mail->send();

  // Em teste, mostrar OK na tela (facilita debug)
  echo "OK: email enviado.";
  exit;

  // Em produção, você pode redirecionar:
  // header('Location: obrigado.html');
  // exit;

} catch (Exception $e) {
  http_response_code(500);

  // Mostra erro (apenas durante debug)
  $msg = $mail->ErrorInfo ?: $e->getMessage();
  echo "Falha ao enviar. Detalhes: " . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
  exit;
}
