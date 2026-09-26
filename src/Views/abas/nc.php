<?php
// Aba "Não Conformidades" — incluída por src/Views/projeto.php, que carrega $projeto e
// $ncs (nc_listar_do_projeto). Lista as NCs enviadas do projeto, com status, histórico de
// escalonamento e de e-mails (download dos PDFs) e as ações Escalonar e Reenviar.
if (!isset($projeto, $ncs)) {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/checklist_linha.php';

$formatar = fn(?string $data) => $data === null ? '—' : e(checklist_formatar_prazo($data));
$abertas = 0;
$atrasadas = 0;
foreach ($ncs as $i => $nc) {
    $ncs[$i]['aberta'] = in_array($nc['status'], NC_STATUS_ABERTOS, true);
    $ncs[$i]['atrasada'] = checklist_item_atrasado([
        'resultado'               => 'nao_conformidade',
        'status_nc'               => $nc['status'],
        'data_prevista_resolucao' => $nc['prazo_atual'],
    ]);
    $abertas += $ncs[$i]['aberta'] ? 1 : 0;
    $atrasadas += $ncs[$i]['atrasada'] ? 1 : 0;
}
?>
<div class="nc-aba" id="abaNc">
    <div class="nc-barra">
        <p class="nc-resumo">
            <strong><?= count($ncs) ?></strong> NC(s) enviada(s) ·
            <strong><?= $abertas ?></strong> aberta(s) ·
            <strong class="<?= $atrasadas ? 'texto-alerta' : '' ?>"><?= $atrasadas ?></strong> com prazo vencido
        </p>
        <label class="nc-filtro">Mostrar
            <select id="filtroNc">
                <option value="todas">Todas</option>
                <option value="abertas">Abertas</option>
                <option value="atrasadas">Com prazo vencido</option>
                <option value="encerradas">Encerradas</option>
            </select>
        </label>
    </div>

    <p class="mensagem" id="statusNc" role="status"></p>

    <?php if (!$ncs): ?>
        <p class="vazio">Nenhuma não conformidade enviada ainda. As NCs aparecem aqui depois de enviadas pelo botão "Enviar NC" na aba Checklist de Qualidade.</p>
    <?php endif; ?>

    <?php foreach ($ncs as $nc): ?>
        <?php
            $prazoSugerido = '';
            if ($nc['aberta']) {
                $sugerido = strtotime(nc_prazo_escalonamento($nc));
                $prazoSugerido = $nc['prazo_unidade'] === 'dias' ? date('Y-m-d', $sugerido) : date('Y-m-d\TH:i', $sugerido);
            }
            $primeiroEmailId = $nc['emails'][0]['id'] ?? 0;
        ?>
        <article class="nc-card<?= $nc['atrasada'] ? ' nc-atrasada' : '' ?><?= $nc['aberta'] ? '' : ' nc-encerrada' ?>"
                 data-nc-id="<?= (int) $nc['id'] ?>"
                 data-item-id="<?= (int) $nc['checklist_item_id'] ?>"
                 data-numero-item="<?= (int) $nc['numero_item'] ?>"
                 data-numero-escalonamento="<?= (int) $nc['numero_escalonamento'] ?>"
                 data-responsavel="<?= e($nc['responsavel_resolucao']) ?>"
                 data-responsavel-email="<?= e($nc['responsavel_email']) ?>"
                 data-prazo-atual="<?= $formatar($nc['prazo_atual']) ?>"
                 data-prazo-sugerido="<?= e($prazoSugerido) ?>"
                 data-unidade="<?= e($nc['prazo_unidade']) ?>"
                 data-classificacao="<?= e(nc_classificacao_texto($nc)) ?>"
                 data-aberta="<?= $nc['aberta'] ? '1' : '0' ?>"
                 data-atrasada="<?= $nc['atrasada'] ? '1' : '0' ?>">
            <header class="nc-card-cabecalho">
                <h3>Item <?= (int) $nc['numero_item'] ?></h3>
                <span class="nc-status status-<?= e($nc['status']) ?>"><?= e(CHECKLIST_STATUS_NC[$nc['status']]) ?></span>
                <?php if ($nc['atrasada']): ?>
                    <span class="nc-alerta">Prazo vencido</span>
                <?php endif; ?>
            </header>

            <p class="nc-descricao"><?= e($nc['descricao']) ?></p>

            <dl class="nc-dados">
                <dt>Classificação</dt><dd><?= e(nc_classificacao_texto($nc)) ?></dd>
                <dt>Responsável pela resolução</dt><dd><?= e($nc['responsavel_resolucao']) ?> &lt;<?= e($nc['responsavel_email']) ?>&gt;</dd>
                <dt>Responsável por QA</dt><dd><?= e($nc['responsavel_qa']) ?></dd>
                <dt>Ação corretiva indicada</dt><dd><?= e($nc['acao_corretiva_indicada'] ?? '—') ?></dd>
                <dt>Data da 1ª solicitação</dt><dd><?= $formatar($nc['data_primeira_solicitacao']) ?></dd>
                <dt>Prazo original</dt><dd><?= $formatar($nc['prazo_resolucao']) ?></dd>
                <dt>Prazo atual</dt><dd class="<?= $nc['atrasada'] ? 'texto-alerta' : '' ?>"><?= $formatar($nc['prazo_atual']) ?></dd>
                <dt>Nº de escalonamento</dt><dd><?= (int) $nc['numero_escalonamento'] ?></dd>
                <?php if ($nc['data_escalonamento']): ?>
                    <dt>Último escalonamento</dt><dd><?= $formatar($nc['data_escalonamento']) ?></dd>
                <?php endif; ?>
                <?php if ($nc['data_conclusao_nc']): ?>
                    <dt>Conclusão</dt><dd><?= $formatar($nc['data_conclusao_nc']) ?></dd>
                <?php endif; ?>
            </dl>

            <div class="nc-acoes">
                <label>Status
                    <select class="js-status-nc" data-valor-salvo="<?= e($nc['status']) ?>">
                        <?= checklist_opcoes_html(CHECKLIST_STATUS_NC, $nc['status'], '', ['escalonada']) ?>
                    </select>
                </label>
                <?php if ($nc['aberta']): ?>
                    <button type="button" class="js-escalonar">Escalonar</button>
                    <button type="button" class="js-reenviar">Reenviar</button>
                <?php endif; ?>
            </div>

            <details class="nc-historico">
                <summary>Histórico — <?= count($nc['escalonamentos']) ?> escalonamento(s), <?= count($nc['emails']) ?> e-mail(s)</summary>

                <h4>Histórico de Escalonamento</h4>
                <?php if (!$nc['escalonamentos']): ?>
                    <p class="dica">Nenhum escalonamento.</p>
                <?php else: ?>
                    <div class="tabela-rolagem">
                        <table class="nc-tabela">
                            <thead><tr><th>Nº</th><th>Superior</th><th>Responsável</th><th>Prazo para Resolução</th><th>Enviado em</th><th>Por</th></tr></thead>
                            <tbody>
                                <?php foreach ($nc['escalonamentos'] as $escalonamento): ?>
                                    <tr>
                                        <td><?= (int) $escalonamento['numero_escalonamento'] ?></td>
                                        <td><?= e($escalonamento['superior_nome']) ?><br><span class="dica"><?= e($escalonamento['superior_email']) ?></span></td>
                                        <td><?= e($escalonamento['responsavel_resolucao']) ?></td>
                                        <td><?= $formatar($escalonamento['novo_prazo_resolucao']) ?></td>
                                        <td><?= $formatar($escalonamento['enviado_em']) ?></td>
                                        <td><?= e($escalonamento['escalonado_por_nome']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <h4>E-mails enviados</h4>
                <div class="tabela-rolagem">
                    <table class="nc-tabela">
                        <thead><tr><th>Enviado em</th><th>Tipo</th><th>Para</th><th>Cópia</th><th>Por</th><th>Documento</th></tr></thead>
                        <tbody>
                            <?php foreach ($nc['emails'] as $email): ?>
                                <tr>
                                    <td><?= $formatar($email['enviado_em']) ?></td>
                                    <td>
                                        <?php if ($email['escalonamento_id']): ?>
                                            Escalonamento Nº <?= (int) $email['numero_escalonamento'] ?>
                                        <?php elseif ((int) $email['id'] === (int) $primeiroEmailId): ?>
                                            1º envio
                                        <?php else: ?>
                                            Reenvio
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($email['destinatario']) ?></td>
                                    <td class="nc-cc"><?= e($email['cc'] ?: '—') ?></td>
                                    <td><?= e($email['enviado_por_nome']) ?></td>
                                    <td>
                                        <?php if ($email['anexo_nome']): ?>
                                            <a href="/src/Controllers/nc_email_pdf.php?id=<?= (int) $email['id'] ?>"><?= e($email['anexo_nome']) ?></a>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        </article>
    <?php endforeach; ?>

    <dialog id="dialogEscalonar" class="dialog-nc" aria-labelledby="tituloEscalonar">
        <form id="formEscalonar" method="dialog">
            <h3 id="tituloEscalonar">Escalonar não conformidade</h3>
            <p class="dica">A solicitação é enviada ao superior com o histórico de escalonamento preenchido e o documento do 1º envio em anexo, com cópia para o responsável pela resolução, todos os envolvidos nos envios anteriores e você.</p>
            <input type="hidden" name="nc_id">
            <input type="hidden" name="numero_escalonamento">

            <dl class="dialog-nc-resumo">
                <dt>Item</dt><dd id="escItem"></dd>
                <dt>Classificação</dt><dd id="escClassificacao"></dd>
                <dt>Prazo atual</dt><dd id="escPrazoAtual"></dd>
                <dt>Escalonamento</dt><dd id="escNumero"></dd>
            </dl>

            <label for="escSuperiorNome">Nome do superior <span class="obrigatorio">*</span></label>
            <input type="text" id="escSuperiorNome" name="superior_nome" maxlength="150" required>

            <label for="escSuperiorEmail">E-mail do superior <span class="obrigatorio">*</span></label>
            <input type="email" id="escSuperiorEmail" name="superior_email" maxlength="255" required>

            <label for="escResponsavel">Responsável pela resolução <span class="obrigatorio">*</span></label>
            <input type="text" id="escResponsavel" name="responsavel_resolucao" maxlength="150" required>

            <label for="escResponsavelEmail">E-mail do responsável pela resolução <span class="obrigatorio">*</span></label>
            <input type="email" id="escResponsavelEmail" name="responsavel_email" maxlength="255" required>

            <label for="escNovoPrazo">Novo prazo para resolução <span class="obrigatorio">*</span></label>
            <input type="date" id="escNovoPrazo" name="novo_prazo" required>
            <p class="dica" id="escPrazoDica"></p>

            <label for="escObservacoes">Observações</label>
            <textarea id="escObservacoes" name="observacoes" rows="3" maxlength="5000" placeholder="Nenhuma observação adicional."></textarea>

            <p class="mensagem" id="mensagemEscalonar" role="status"></p>
            <div class="dialog-nc-acoes">
                <button type="button" id="cancelarEscalonar">Cancelar</button>
                <button type="button" id="previsualizarEscalonamento">Pré-visualizar PDF</button>
                <button type="submit" id="confirmarEscalonar">Enviar escalonamento</button>
            </div>
        </form>
    </dialog>
</div>
