<?php
    require_once __DIR__ . '/../../config/auth.php';
    redirecionar_se_logado();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title>Entrar — DeepCheck</title>
    <link rel="stylesheet" href="<?= asset('/public/css/autenticacao.css') ?>">
</head>
<body>
    <div class="auth">
        <?php include __DIR__ . '/autenticacao_marca.php'; ?>

        <main class="auth-conteudo">
            <div class="auth-cartao">
                <h1>Entrar</h1>
                <p class="auth-subtitulo">Acesse sua conta para gerenciar suas auditorias.</p>

                <form id="formLogin" class="auth-form" novalidate>
                    <p class="alerta" id="alerta" role="alert" hidden></p>

                    <div class="campo">
                        <label for="email">E-mail</label>
                        <input type="email" name="email" id="email" placeholder="seu@email.com" autocomplete="email" maxlength="255" required aria-describedby="erro-email">
                        <p class="erro-campo" id="erro-email"></p>
                    </div>

                    <div class="campo">
                        <div class="campo-cabecalho">
                            <label for="senha">Senha</label>
                            <a class="link link-pequeno" id="linkEsqueciSenha" href="/src/Views/esqueci_senha.php">Esqueceu a senha?</a>
                        </div>
                        <div class="campo-senha">
                            <input type="password" name="senha" id="senha" placeholder="Sua senha" autocomplete="current-password" maxlength="72" required aria-describedby="erro-senha">
                            <button type="button" class="botao-mostrar-senha" data-alvo="senha" aria-label="Mostrar senha">Mostrar</button>
                        </div>
                        <p class="erro-campo" id="erro-senha"></p>
                    </div>

                    <button type="submit" class="botao-primario" id="botaoEntrar">Entrar</button>
                </form>

                <p class="auth-alternativa">Ainda não tem conta? <a class="link" href="/src/Views/cadastro.php">Criar uma conta</a></p>
            </div>
        </main>
    </div>

    <script src="<?= asset('/public/js/api.js') ?>"></script>
    <script src="<?= asset('/public/js/autenticacao.js') ?>"></script>
    <script src="<?= asset('/public/js/login.js') ?>"></script>
</body>
</html>
