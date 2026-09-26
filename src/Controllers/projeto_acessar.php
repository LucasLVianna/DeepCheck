<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/rate_limit.php';
require_once __DIR__ . '/../Models/projetos.php';

const ACESSO_MAX_FALHAS_CODIGO  = 5;  // por usuário + código, na janela do rate limit
const ACESSO_MAX_FALHAS_USUARIO = 20; // por usuário, qualquer código

exigir_login_api();
exigir_post_com_csrf();

$codigo = projeto_normalizar_codigo((string) ($_POST['codigo'] ?? ''));
$senha = (string) ($_POST['senha'] ?? '');

if ($codigo === '' || $senha === '') {
    responder_json(['status' => 'nok', 'mensagem' => 'Informe o código e a senha do projeto.'], 400);
}

$usuarioId = (int) $_SESSION['usuario']['id'];
$chaveCodigo  = 'projeto-acesso|' . $usuarioId . '|' . $codigo;
$chaveUsuario = 'projeto-acesso-usuario|' . $usuarioId;

$espera = max(
    rate_limit_segundos_restantes($chaveCodigo, ACESSO_MAX_FALHAS_CODIGO),
    rate_limit_segundos_restantes($chaveUsuario, ACESSO_MAX_FALHAS_USUARIO)
);
if ($espera > 0) {
    header('Retry-After: ' . $espera);
    responder_json([
        'status'   => 'nok',
        'mensagem' => 'Muitas tentativas de acesso a projetos. Tente novamente em ' . (int) ceil($espera / 60) . ' minuto(s).'
    ], 429);
}

require_once __DIR__ . '/../../config/conexao.php';

$projeto = projeto_por_codigo($conexao, $codigo);
$senhaCorreta = password_verify($senha, $projeto['projeto_senha_hash'] ?? HASH_FICTICIO) && $projeto !== null;

if (!$senhaCorreta) {
    $conexao->close();
    rate_limit_registrar_falha($chaveCodigo);
    rate_limit_registrar_falha($chaveUsuario);
    responder_json(['status' => 'nok', 'mensagem' => 'Código ou senha do projeto incorretos.'], 401);
}

rate_limit_limpar($chaveCodigo);
projeto_registrar_acesso($conexao, (int) $projeto['id'], $usuarioId);
$conexao->close();

responder_json([
    'status'   => 'ok',
    'mensagem' => 'Acesso liberado',
    'redirect' => '/src/Views/projeto.php?id=' . (int) $projeto['id']
]);
