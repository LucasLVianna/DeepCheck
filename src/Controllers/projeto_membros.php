<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../Models/projetos.php';

// Ações sobre os membros do projeto:
//   sair          — qualquer membro, exceto o dono (que precisa transferir a posse antes)
//   remover       — só o dono; remove outro membro (usuario_id)
//   trocar_senha  — só o dono; nova senha do projeto (senha, confirmar_senha)
//   transferir    — só o dono; passa a posse para outro membro (usuario_id)
exigir_login_api();
exigir_post_com_csrf();

$texto = fn($chave) => is_string($_POST[$chave] ?? null) ? $_POST[$chave] : '';
$acao = $texto('acao');
$projetoId = filter_var($_POST['projeto_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($projetoId === false) {
    responder_json(['status' => 'nok', 'mensagem' => 'Projeto inválido.'], 400);
}

require_once __DIR__ . '/../../config/conexao.php';

$usuarioId = (int) $_SESSION['usuario']['id'];
$projeto = projeto_do_membro($conexao, $projetoId, $usuarioId);
if ($projeto === null) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => 'Projeto não encontrado.'], 404);
}
$ehDono = (int) $projeto['criado_por'] === $usuarioId;

$falhar = function (string $mensagem, int $status) use ($conexao): never {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => $mensagem], $status);
};

// Outro membro do projeto (para remover ou receber a posse).
$outroMembro = function () use ($conexao, $projetoId, $usuarioId, $falhar): array {
    $alvoId = filter_var($_POST['usuario_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $membros = array_column(projeto_membros($conexao, $projetoId), null, 'id');
    if ($alvoId === false || !isset($membros[$alvoId])) {
        $falhar('Membro não encontrado neste projeto.', 404);
    }
    if ($alvoId === $usuarioId) {
        $falhar('Escolha outro membro.', 400);
    }
    return $membros[$alvoId];
};

switch ($acao) {
    case 'sair':
        if ($ehDono) {
            $falhar('Você é o dono do projeto: transfira a posse para outro membro antes de sair.', 409);
        }
        projeto_remover_membro($conexao, $projetoId, $usuarioId);
        $conexao->close();
        responder_json(['status' => 'ok', 'mensagem' => 'Você saiu do projeto.', 'redirect' => '/src/Views/menu.php']);

    case 'remover':
        if (!$ehDono) {
            $falhar('Apenas o dono do projeto pode remover membros.', 403);
        }
        $alvo = $outroMembro();
        projeto_remover_membro($conexao, $projetoId, (int) $alvo['id']);
        $conexao->close();
        responder_json(['status' => 'ok', 'mensagem' => "{$alvo['nome_usuario']} foi removido(a) do projeto."]);

    case 'trocar_senha':
        if (!$ehDono) {
            $falhar('Apenas o dono do projeto pode trocar a senha.', 403);
        }
        $senha = $texto('senha');
        if (($erro = projeto_validar_senha($senha)) !== null) {
            $falhar($erro, 400);
        }
        if ($senha !== $texto('confirmar_senha')) {
            $falhar('A confirmação não confere com a nova senha.', 400);
        }
        projeto_trocar_senha($conexao, $projetoId, $senha);
        $conexao->close();
        responder_json(['status' => 'ok', 'mensagem' => 'Senha do projeto alterada. Quem já é membro continua com acesso; novos membros entram com a senha nova.']);

    case 'transferir':
        if (!$ehDono) {
            $falhar('Apenas o dono do projeto pode transferir a posse.', 403);
        }
        $alvo = $outroMembro();
        projeto_transferir_posse($conexao, $projetoId, (int) $alvo['id']);
        $conexao->close();
        responder_json(['status' => 'ok', 'mensagem' => "{$alvo['nome_usuario']} agora é o dono do projeto."]);

    default:
        $falhar('Ação inválida.', 400);
}
