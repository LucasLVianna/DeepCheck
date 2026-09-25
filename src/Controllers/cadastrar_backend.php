<?php
require_once __DIR__ . '/../../config/auth.php';

exigir_post_com_csrf();

$nome = trim((string) ($_POST['nome'] ?? ''));
$email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
$senha = (string) ($_POST['senha'] ?? '');
$cepNumerico = preg_replace('/\D/', '', (string) ($_POST['cep'] ?? ''));

if ($nome === '' || $email === '' || $senha === '' || strlen($cepNumerico) !== 8) {
    responder_json([
        'status' => 'nok',
        'mensagem' => 'Informe nome, e-mail, senha e um CEP válido no formato 00000-000.'
    ], 400);
}

if (mb_strlen($nome) > 100) {
    responder_json(['status' => 'nok', 'mensagem' => 'O nome pode ter no máximo 100 caracteres.'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    responder_json(['status' => 'nok', 'mensagem' => 'Digite um e-mail válido.'], 400);
}

// Mesma regra validada no front-end (public/js/cadastrar.js). O limite de 72 bytes
// é o máximo que o bcrypt considera; acima disso o restante seria ignorado.
if (strlen($senha) < 8 || strlen($senha) > 72
    || !preg_match('/[A-Z]/', $senha) || !preg_match('/[a-z]/', $senha)
    || !preg_match('/[0-9]/', $senha) || !preg_match('/[@$!%*?&]/', $senha)) {
    responder_json([
        'status' => 'nok',
        'mensagem' => 'A senha deve ter entre 8 e 72 caracteres, com letras maiúsculas, minúsculas, números e caracteres especiais (@$!%*?&).'
    ], 400);
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
