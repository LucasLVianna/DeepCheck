<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../Models/projetos.php';
require_once __DIR__ . '/../Models/pgq.php';

// Exibe o logo do projeto (só para membros). GET: ?projeto=<id>
exigir_login();

$projetoId = filter_input(INPUT_GET, 'projeto', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$projetoId) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../config/conexao.php';
$logo = projeto_do_membro($conexao, $projetoId, (int) $_SESSION['usuario']['id']) ? pgq_logo($conexao, $projetoId) : null;
$conexao->close();

// O tipo gravado já foi validado pelo conteúdo no envio; só PNG/JPG são servidos.
if ($logo === null || !isset(PGQ_LOGO_TIPOS[$logo['tipo']])) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $logo['tipo']);
header('Content-Length: ' . strlen($logo['dados']));
header('Cache-Control: private, no-cache');
echo $logo['dados'];
