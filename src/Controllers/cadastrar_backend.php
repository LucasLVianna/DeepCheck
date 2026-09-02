<?php
    include_once(__DIR__ . '/../../config/headers.php');
    include_once(__DIR__ . '/../../config/conexao.php');

    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $cepInput = trim($_POST['cep'] ?? '');
    $cepNumerico = preg_replace('/\D/', '', $cepInput);

    if ($nome === '' || $email === '' || $senha === '' || strlen($cepNumerico) !== 8) {
        echo json_encode([
            'status' => 'nok',
            'mensagem' => 'Informe nome, e-mail, senha e um CEP válido no formato 00000-000.'
        ]);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'status' => 'nok',
            'mensagem' => 'Digite um e-mail válido.'
        ]);
        exit;
    }

    if (strlen($senha) < 8) {
        echo json_encode([
            'status' => 'nok',
            'mensagem' => 'A senha precisa ter pelo menos 8 caracteres.'
        ]);
        exit;
    }

    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
    $cep = substr($cepNumerico, 0, 5) . '-' . substr($cepNumerico, 5, 3);

    $stmt = $conexao->prepare("INSERT INTO usuario (nome, email, senha, cep) VALUES (?,?,?,?)");
    $stmt->bind_param("ssss", $nome, $email, $senhaHash, $cep);

    try {
        $stmt->execute();
        $retorno = [
            'status'   => 'ok',
            'mensagem' => 'Registro inserido com sucesso'
        ];
    } catch (mysqli_sql_exception $e) {
        $retorno = [
            'status'   => 'nok',
            'mensagem' => $e->getCode() == 1062
                ? 'Este email já está cadastrado'
                : 'Falha ao inserir: ' . $e->getMessage()
        ];
    }

    $stmt->close();
    $conexao->close();
    header('Content-type: application/json; charset=utf-8');
    echo json_encode($retorno);