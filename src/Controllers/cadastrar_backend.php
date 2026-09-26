<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../Models/usuarios.php';

exigir_post_com_csrf();

$texto = fn($chave) => is_string($_POST[$chave] ?? null) ? $_POST[$chave] : '';
$nome = trim($texto('nome'));
$email = usuario_normalizar_email($texto('email'));
$senha = $texto('senha');
$cepNumerico = usuario_normalizar_cep($texto('cep'));

$erro = usuario_validar_nome($nome) ?? usuario_validar_email($email) ?? usuario_validar_cep($cepNumerico) ?? usuario_validar_senha($senha);
if ($erro !== null) {
    responder_json(['status' => 'nok', 'mensagem' => $erro], 400);
}

require_once __DIR__ . '/../../config/conexao.php';

$senhaHash = password_hash($senha, PASSWORD_DEFAULT);

$stmt = $conexao->prepare("INSERT INTO usuario (nome_usuario, email_usuario, senha, cep) VALUES (?,?,?,?)");
$stmt->bind_param("ssss", $nome, $email, $senhaHash, $cepNumerico);

try {
    $stmt->execute();
    $retorno = [
        'status'   => 'ok',
        'mensagem' => 'Registro inserido com sucesso'
    ];
    $codigoHttp = 201;
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1062) {
        $retorno = ['status' => 'nok', 'mensagem' => 'Este email já está cadastrado'];
        $codigoHttp = 409;
    } else {
        error_log('DeepCheck: falha ao cadastrar usuário: ' . $e->getMessage());
        $retorno = ['status' => 'nok', 'mensagem' => 'Não foi possível concluir o cadastro. Tente novamente.'];
        $codigoHttp = 500;
    }
}

$stmt->close();
$conexao->close();
responder_json($retorno, $codigoHttp);
