<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../Models/nao_conformidades.php';

// Download do PDF exatamente como foi enviado num e-mail de NC (só membros do projeto).
exigir_login();

$emailId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$emailId) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../config/conexao.php';
$email = nc_email_pdf_do_membro($conexao, $emailId, (int) $_SESSION['usuario']['id']);
$conexao->close();

if ($email === null) {
    http_response_code(404);
    exit;
}

$nomeAscii = preg_replace('/[^A-Za-z0-9 ._-]/', '_', $email['anexo_nome']);
header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($email['anexo_pdf']));
header('Content-Disposition: attachment; filename="' . $nomeAscii . '"; filename*=UTF-8\'\'' . rawurlencode($email['anexo_nome']));
header('Cache-Control: private, no-store');
echo $email['anexo_pdf'];
