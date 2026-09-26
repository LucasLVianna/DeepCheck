<?php
// Painel "Membros" do projeto — incluído por src/Views/projeto.php, que carrega $projeto,
// $membros (projeto_membros), $ehDono e $usuarioId.
if (!isset($projeto, $membros, $ehDono, $usuarioId)) {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../Models/projetos.php';
?>
<dialog id="dialogMembros" class="dialog-membros" aria-labelledby="tituloMembros" data-projeto-id="<?= (int) $projeto['id'] ?>">
    <div class="dialog-membros-cabecalho">
        <h2 id="tituloMembros">Membros do projeto</h2>
        <button type="button" id="fecharMembros" aria-label="Fechar">Fechar</button>
    </div>
    <p class="mensagem" id="mensagemMembros" role="status"></p>

    <div class="tabela-rolagem">
        <table class="tabela-membros">
            <thead><tr><th>Nome</th><th>E-mail</th><th>Último acesso</th><th>Papel</th><?php if ($ehDono): ?><th><span class="visualmente-oculto">Ações</span></th><?php endif; ?></tr></thead>
            <tbody>
                <?php foreach ($membros as $membro): ?>
                    <tr data-usuario-id="<?= (int) $membro['id'] ?>" data-nome="<?= e($membro['nome_usuario']) ?>">
                        <td><?= e($membro['nome_usuario']) ?><?= (int) $membro['id'] === $usuarioId ? ' (você)' : '' ?></td>
                        <td><?= e($membro['email_usuario']) ?></td>
                        <td><?= e(date('d/m/Y H:i', strtotime($membro['acesso_em']))) ?></td>
                        <td><?= $membro['dono'] ? 'Dono' : 'Membro' ?></td>
                        <?php if ($ehDono): ?>
                            <td><?php if (!$membro['dono']): ?><button type="button" class="js-remover-membro botao-perigo">Remover</button><?php endif; ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($ehDono): ?>
        <form class="membros-secao" id="formSenhaProjeto" novalidate>
            <h3>Trocar a senha do projeto</h3>
            <p class="dica">Quem já é membro continua com acesso. Novos membros entram com o código e a senha nova.</p>
            <div class="membros-linha">
                <input type="password" name="senha" minlength="<?= PROJETO_SENHA_MIN ?>" maxlength="<?= PROJETO_SENHA_MAX ?>" autocomplete="new-password" placeholder="Nova senha" aria-label="Nova senha do projeto" required>
                <input type="password" name="confirmar_senha" minlength="<?= PROJETO_SENHA_MIN ?>" maxlength="<?= PROJETO_SENHA_MAX ?>" autocomplete="new-password" placeholder="Confirmar" aria-label="Confirmar nova senha do projeto" required>
                <button type="submit">Trocar senha</button>
            </div>
        </form>

        <form class="membros-secao" id="formTransferir" novalidate>
            <h3>Transferir a posse</h3>
            <p class="dica">O novo dono passa a poder renomear e excluir o projeto e gerenciar os membros. Você continua como membro.</p>
            <?php $outros = array_filter($membros, fn($m) => (int) $m['id'] !== $usuarioId); ?>
            <?php if (!$outros): ?>
                <p class="dica">Ainda não há outros membros neste projeto.</p>
            <?php else: ?>
                <div class="membros-linha">
                    <select name="usuario_id" aria-label="Novo dono">
                        <?php foreach ($outros as $membro): ?>
                            <option value="<?= (int) $membro['id'] ?>"><?= e($membro['nome_usuario']) ?> (<?= e($membro['email_usuario']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Transferir</button>
                </div>
            <?php endif; ?>
        </form>
        <p class="dica">Para sair do projeto, transfira a posse antes.</p>
    <?php else: ?>
        <div class="membros-secao">
            <h3>Sair do projeto</h3>
            <p class="dica">Você perde o acesso. Para voltar, será preciso entrar de novo com o código e a senha do projeto.</p>
            <button type="button" id="sairProjeto" class="botao-perigo">Sair do projeto</button>
        </div>
    <?php endif; ?>
</dialog>
