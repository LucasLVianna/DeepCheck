<?php
    require_once __DIR__ . '/../../config/auth.php';
    exigir_login();
    require_once __DIR__ . '/../../config/conexao.php';
    require_once __DIR__ . '/../Models/projetos.php';

    $usuarioId = (int) $_SESSION['usuario']['id'];
    $projetos = projetos_do_usuario($conexao, $usuarioId);
    $conexao->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title>Meus projetos — DeepCheck</title>
    <link rel="stylesheet" href="<?= asset('/public/css/menu.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>

    <main id="projetos" class="dashboard">
        <section class="dashboard-formularios">
            <form id="formNovoProjeto" class="painel">
                <h2>Novo projeto</h2>

                <label for="novoNome">Nome do projeto: <span class="obrigatorio">*</span></label>
                <input type="text" name="nome" id="novoNome" maxlength="<?= PROJETO_NOME_MAX ?>" required>

                <label for="novoSenha">Senha do projeto: <span class="obrigatorio">*</span></label>
                <input type="password" name="senha" id="novoSenha" minlength="<?= PROJETO_SENHA_MIN ?>" maxlength="<?= PROJETO_SENHA_MAX ?>" autocomplete="new-password" required>

                <label for="novoConfirmarSenha">Confirmar senha: <span class="obrigatorio">*</span></label>
                <input type="password" name="confirmar_senha" id="novoConfirmarSenha" minlength="<?= PROJETO_SENHA_MIN ?>" maxlength="<?= PROJETO_SENHA_MAX ?>" autocomplete="new-password" required>

                <p class="dica">O código de acesso é gerado automaticamente. Compartilhe o código e a senha com quem deve entrar no projeto. A senha não pode ser recuperada depois.</p>
                <p class="mensagem" id="mensagemNovoProjeto" role="status"></p>
                <button type="submit">Criar projeto</button>
            </form>

            <form id="formAcessarProjeto" class="painel">
                <h2>Entrar em um projeto</h2>

                <label for="acessoCodigo">Código do projeto: <span class="obrigatorio">*</span></label>
                <input type="text" name="codigo" id="acessoCodigo" maxlength="32" autocomplete="off" spellcheck="false" required>

                <label for="acessoSenha">Senha do projeto: <span class="obrigatorio">*</span></label>
                <input type="password" name="senha" id="acessoSenha" autocomplete="off" required>

                <p class="mensagem" id="mensagemAcessarProjeto" role="status"></p>
                <button type="submit">Entrar</button>
            </form>
        </section>

        <section class="dashboard-lista">
            <h1>Meus projetos</h1>

            <?php if (!$projetos): ?>
                <p class="vazio">Você ainda não participa de nenhum projeto. Crie um novo ou entre com o código e a senha de um projeto existente.</p>
            <?php else: ?>
                <ul class="projetos-grid" id="listaProjetos">
                    <?php foreach ($projetos as $projeto): ?>
                        <?php $ehCriador = (int) $projeto['criado_por'] === $usuarioId; ?>
                        <li class="projeto-card" data-id="<?= (int) $projeto['id'] ?>" data-nome="<?= e($projeto['nome']) ?>">
                            <h3><a href="/src/Views/projeto.php?id=<?= (int) $projeto['id'] ?>"><?= e($projeto['nome']) ?></a></h3>
                            <dl>
                                <dt>Código</dt>
                                <dd><code><?= e($projeto['projeto_codigo_acesso']) ?></code></dd>
                                <dt>Dono</dt>
                                <dd><?= $ehCriador ? 'Você' : e($projeto['criador_nome']) ?></dd>
                                <dt>Último acesso</dt>
                                <dd><?= e(date('d/m/Y H:i', strtotime($projeto['acesso_em']))) ?></dd>
                            </dl>
                            <?php if ($projeto['ncs_abertas'] > 0): ?>
                                <p class="projeto-card-ncs">
                                    <a href="/src/Views/projeto.php?id=<?= (int) $projeto['id'] ?>&amp;aba=nc"><?= (int) $projeto['ncs_abertas'] ?> NC(s) aberta(s)</a>
                                    <?php if ($projeto['ncs_vencidas'] > 0): ?>
                                        <span class="alerta-vencida"><?= (int) $projeto['ncs_vencidas'] ?> com prazo vencido</span>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                            <div class="projeto-card-acoes">
                                <a class="botao" href="/src/Views/projeto.php?id=<?= (int) $projeto['id'] ?>">Abrir</a>
                                <?php if ($ehCriador): ?>
                                    <button type="button" class="js-renomear">Renomear</button>
                                    <button type="button" class="js-excluir botao-perigo">Excluir</button>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </main>

    <script src="<?= asset('/public/js/api.js') ?>"></script>
    <script src="<?= asset('/public/js/menu.js') ?>"></script>
</body>
</html>
