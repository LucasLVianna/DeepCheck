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
    <title>Criar conta — DeepCheck</title>
    <link rel="stylesheet" href="<?= asset('/public/css/autenticacao.css') ?>">
</head>
<body>
    <div class="auth">
        <?php include __DIR__ . '/autenticacao_marca.php'; ?>

        <main class="auth-conteudo">
            <div class="auth-cartao">
                <h1>Criar conta</h1>
                <p class="auth-subtitulo">Leva menos de um minuto. Todos os campos são obrigatórios.</p>

                <form id="formCadastro" class="auth-form" novalidate>
                    <p class="alerta" id="alerta" role="alert" hidden></p>

                    <div class="campo">
                        <label for="nome">Nome completo</label>
                        <input type="text" name="nome" id="nome" placeholder="Seu nome" autocomplete="name" maxlength="100" required aria-describedby="erro-nome">
                        <p class="erro-campo" id="erro-nome"></p>
                    </div>

                    <div class="campo">
                        <label for="email">E-mail</label>
                        <input type="email" name="email" id="email" placeholder="seu@email.com" autocomplete="email" maxlength="255" required aria-describedby="erro-email">
                        <p class="erro-campo" id="erro-email"></p>
                    </div>

                    <div class="campo">
                        <label for="cep">CEP</label>
                        <input type="text" name="cep" id="cep" placeholder="00000-000" autocomplete="postal-code" maxlength="9" inputmode="numeric" required aria-describedby="erro-cep">
                        <p class="erro-campo" id="erro-cep"></p>
                    </div>

                    <div class="campo">
                        <label for="senha">Senha</label>
                        <div class="campo-senha">
                            <input type="password" name="senha" id="senha" placeholder="Crie uma senha" autocomplete="new-password" maxlength="72" required aria-describedby="requisitosSenha erro-senha">
                            <button type="button" class="botao-mostrar-senha" data-alvo="senha" aria-label="Mostrar senha">Mostrar</button>
                        </div>
                        <ul class="requisitos-senha" id="requisitosSenha" data-senha="senha" aria-live="polite">
                            <li data-requisito="tamanho">8 a 72 caracteres</li>
                            <li data-requisito="maiuscula">Uma letra maiúscula</li>
                            <li data-requisito="minuscula">Uma letra minúscula</li>
                            <li data-requisito="numero">Um número</li>
                            <li data-requisito="especial">Um especial: @$!%*?&amp;</li>
                        </ul>
                        <p class="erro-campo" id="erro-senha"></p>
                    </div>

                    <div class="campo">
                        <label for="confirmarSenha">Confirmar senha</label>
                        <div class="campo-senha">
                            <input type="password" id="confirmarSenha" placeholder="Repita a senha" autocomplete="new-password" maxlength="72" required aria-describedby="erro-confirmarSenha">
                            <button type="button" class="botao-mostrar-senha" data-alvo="confirmarSenha" aria-label="Mostrar senha">Mostrar</button>
                        </div>
                        <p class="erro-campo" id="erro-confirmarSenha"></p>
                    </div>

                    <button type="submit" class="botao-primario" id="botaoCriarConta">Criar conta</button>
                </form>

                <p class="auth-alternativa">Já tem uma conta? <a class="link" href="/src/Views/login.php">Entrar</a></p>
            </div>
        </main>
    </div>

    <script src="<?= asset('/public/js/api.js') ?>"></script>
    <script src="<?= asset('/public/js/autenticacao.js') ?>"></script>
    <script src="<?= asset('/public/js/cadastrar.js') ?>"></script>
</body>
</html>
