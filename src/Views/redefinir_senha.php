<?php
    require_once __DIR__ . '/../../config/auth.php';
    redirecionar_se_logado();
    require_once __DIR__ . '/../Models/usuarios.php';

    // O link é conferido já ao abrir a página, para não pedir a senha à toa.
    $token = filter_input(INPUT_GET, 'token') ?: '';
    require_once __DIR__ . '/../../config/conexao.php';
    $pedido = senha_redefinicao_valida($conexao, $token);
    $conexao->close();
    header('Referrer-Policy: no-referrer'); // o token está na URL
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title>Nova senha — DeepCheck</title>
    <link rel="stylesheet" href="<?= asset('/public/css/autenticacao.css') ?>">
</head>
<body>
    <div class="auth">
        <?php include __DIR__ . '/autenticacao_marca.php'; ?>

        <main class="auth-conteudo">
            <div class="auth-cartao">
                <?php if ($pedido === null): ?>
                    <h1>Link inválido</h1>
                    <p class="auth-subtitulo">Este link de redefinição é inválido, já foi usado ou expirou (ele vale por 1 hora).</p>
                    <a class="botao-primario" href="/src/Views/esqueci_senha.php">Pedir um novo link</a>
                <?php else: ?>
                    <h1>Criar nova senha</h1>
                    <p class="auth-subtitulo">Conta: <strong><?= e($pedido['email_usuario']) ?></strong></p>

                    <form id="formRedefinir" class="auth-form" novalidate>
                        <p class="alerta" id="alerta" role="alert" hidden></p>
                        <input type="hidden" name="token" value="<?= e($token) ?>">

                        <div class="campo">
                            <label for="senha">Nova senha</label>
                            <div class="campo-senha">
                                <input type="password" name="nova_senha" id="senha" placeholder="Crie uma senha" autocomplete="new-password" maxlength="<?= USUARIO_SENHA_MAX ?>" required aria-describedby="requisitosSenha erro-senha">
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
                            <label for="confirmarSenha">Confirmar nova senha</label>
                            <div class="campo-senha">
                                <input type="password" name="confirmar_senha" id="confirmarSenha" placeholder="Repita a senha" autocomplete="new-password" maxlength="<?= USUARIO_SENHA_MAX ?>" required aria-describedby="erro-confirmarSenha">
                                <button type="button" class="botao-mostrar-senha" data-alvo="confirmarSenha" aria-label="Mostrar senha">Mostrar</button>
                            </div>
                            <p class="erro-campo" id="erro-confirmarSenha"></p>
                        </div>

                        <button type="submit" class="botao-primario" id="botaoRedefinir">Salvar nova senha</button>
                    </form>
                <?php endif; ?>

                <p class="auth-alternativa"><a class="link" href="/src/Views/login.php">Voltar para o login</a></p>
            </div>
        </main>
    </div>

    <script src="<?= asset('/public/js/api.js') ?>"></script>
    <script src="<?= asset('/public/js/autenticacao.js') ?>"></script>
    <script src="<?= asset('/public/js/senha.js') ?>"></script>
</body>
</html>
