<?php
    require_once __DIR__ . '/../../config/auth.php';
    exigir_login();
    require_once __DIR__ . '/../../config/conexao.php';
    require_once __DIR__ . '/../Models/usuarios.php';

    $usuario = usuario_por_id($conexao, (int) $_SESSION['usuario']['id']);
    $conexao->close();
    $cep = substr($usuario['cep'], 0, 5) . '-' . substr($usuario['cep'], 5);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title>Meu perfil — DeepCheck</title>
    <link rel="stylesheet" href="<?= asset('/public/css/perfil.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>

    <main class="perfil">
        <header class="perfil-cabecalho">
            <a href="/src/Views/menu.php">&larr; Meus projetos</a>
            <h1>Meu perfil</h1>
            <p class="dica">Conta criada em <?= e(date('d/m/Y', strtotime($usuario['criado_em']))) ?>.</p>
        </header>

        <form class="painel" id="formDados" data-acao="dados" novalidate>
            <h2>Dados pessoais</h2>
            <label for="nome">Nome completo</label>
            <input type="text" id="nome" name="nome" maxlength="<?= USUARIO_NOME_MAX ?>" autocomplete="name" value="<?= e($usuario['nome_usuario']) ?>" required>
            <label for="cep">CEP</label>
            <input type="text" id="cep" name="cep" maxlength="9" inputmode="numeric" autocomplete="postal-code" value="<?= e($cep) ?>" required>
            <p class="mensagem" role="status"></p>
            <button type="submit">Salvar dados</button>
        </form>

        <form class="painel" id="formEmail" data-acao="email" novalidate>
            <h2>E-mail de acesso</h2>
            <p class="dica">E-mail atual: <strong id="emailAtual"><?= e($usuario['email_usuario']) ?></strong></p>
            <label for="novoEmail">Novo e-mail</label>
            <input type="email" id="novoEmail" name="email" maxlength="<?= USUARIO_EMAIL_MAX ?>" autocomplete="email" required>
            <label for="senhaAtualEmail">Senha atual</label>
            <div class="campo-senha">
                <input type="password" id="senhaAtualEmail" name="senha_atual" autocomplete="current-password" maxlength="<?= USUARIO_SENHA_MAX ?>" required>
                <button type="button" class="botao-mostrar-senha" data-alvo="senhaAtualEmail" aria-label="Mostrar senha">Mostrar</button>
            </div>
            <p class="mensagem" role="status"></p>
            <button type="submit">Alterar e-mail</button>
        </form>

        <form class="painel" id="formSenha" data-acao="senha" novalidate>
            <h2>Senha</h2>
            <label for="senhaAtual">Senha atual</label>
            <div class="campo-senha">
                <input type="password" id="senhaAtual" name="senha_atual" autocomplete="current-password" maxlength="<?= USUARIO_SENHA_MAX ?>" required>
                <button type="button" class="botao-mostrar-senha" data-alvo="senhaAtual" aria-label="Mostrar senha">Mostrar</button>
            </div>
            <label for="novaSenha">Nova senha</label>
            <div class="campo-senha">
                <input type="password" id="novaSenha" name="nova_senha" autocomplete="new-password" maxlength="<?= USUARIO_SENHA_MAX ?>" required aria-describedby="requisitosSenha">
                <button type="button" class="botao-mostrar-senha" data-alvo="novaSenha" aria-label="Mostrar senha">Mostrar</button>
            </div>
            <ul class="requisitos-senha" id="requisitosSenha" data-senha="novaSenha" aria-live="polite">
                <li data-requisito="tamanho">8 a 72 caracteres</li>
                <li data-requisito="maiuscula">Uma letra maiúscula</li>
                <li data-requisito="minuscula">Uma letra minúscula</li>
                <li data-requisito="numero">Um número</li>
                <li data-requisito="especial">Um especial: @$!%*?&amp;</li>
            </ul>
            <label for="confirmarNovaSenha">Confirmar nova senha</label>
            <input type="password" id="confirmarNovaSenha" name="confirmar_senha" autocomplete="new-password" maxlength="<?= USUARIO_SENHA_MAX ?>" required>
            <p class="mensagem" role="status"></p>
            <button type="submit">Alterar senha</button>
        </form>
    </main>

    <script src="<?= asset('/public/js/api.js') ?>"></script>
    <script src="<?= asset('/public/js/autenticacao.js') ?>"></script>
    <script src="<?= asset('/public/js/perfil.js') ?>"></script>
</body>
</html>
