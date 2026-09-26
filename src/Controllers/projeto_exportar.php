<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/python.php';
require_once __DIR__ . '/../Models/projetos.php';
require_once __DIR__ . '/../Models/exportacao.php';

// Exporta o PGQ ou o checklist do projeto em PDF (download). GET: só leitura.
//   ?id=<projeto>&documento=pgq|checklist
exigir_login();

$projetoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$documento = filter_input(INPUT_GET, 'documento');
if (!$projetoId || !in_array($documento, ['pgq', 'checklist'], true)) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../config/conexao.php';
$projeto = projeto_do_membro($conexao, $projetoId, (int) $_SESSION['usuario']['id']);
if ($projeto === null) {
    $conexao->close();
    http_response_code(404);
    exit;
}

$dados = $documento === 'pgq' ? exportacao_pgq($conexao, $projeto) : exportacao_checklist($conexao, $projeto);
$conexao->close();

set_time_limit(60);
$resultado = python_gerar_pdf('documentos_pdf.py', ['tipo' => $documento, 'dados' => $dados]);
if (!$resultado['ok']) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Não foi possível gerar o PDF. Tente novamente em instantes.';
    exit;
}

$nome = exportacao_nome_arquivo($documento === 'pgq' ? 'PGQ' : 'Checklist', $projeto['nome']);
header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($resultado['pdf']));
header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9 ._-]/', '_', $nome) . '"; filename*=UTF-8\'\'' . rawurlencode($nome));
header('Cache-Control: private, no-store');
echo $resultado['pdf'];
