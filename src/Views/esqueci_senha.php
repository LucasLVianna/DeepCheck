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
    <title>Esqueci a senha — DeepCheck</title>
    <link rel="stylesheet" href="<?= asset('/public/css/autenticacao.css') ?>">
</head>
<body>
    <div class="auth">
        <?php include __DIR__ . '/autenticacao_marca.php'; ?>

        <main class="auth-conteudo">
            <div class="auth-cartao">
                <h1>Esqueceu a senha?</h1>
                <p class="auth-subtitulo">Informe o e-mail da sua conta. Vamos enviar um link para você criar uma nova senha.</p>

                <form id="formEsqueci" class="auth-form" novalidate>
                    <p class="alerta" id="alerta" role="alert" hidden></p>

                    <div class="campo">
                        <label for="email">E-mail</label>
                        <input type="email" name="email" id="email" placeholder="seu@email.com" autocomplete="email" maxlength="255" required aria-describedby="erro-email">
                        <p class="erro-campo" id="erro-email"></p>
                    </div>

                    <button type="submit" class="botao-primario" id="botaoEnviarLink">Enviar link</button>
                </form>

                <p class="auth-alternativa">Lembrou a senha? <a class="link" href="/src/Views/login.php">Voltar para o login</a></p>
            </div>
        </main>
    </div>

    <script src="<?= asset('/public/js/api.js') ?>"></script>
    <script src="<?= asset('/public/js/autenticacao.js') ?>"></script>
    <script src="<?= asset('/public/js/senha.js') ?>"></script>
</body>
</html>
