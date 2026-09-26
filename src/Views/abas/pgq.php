<?php
// Aba "Plano de Garantia da Qualidade" — incluída por src/Views/projeto.php, que carrega
// $projeto, $pgq (pgq_do_projeto) e $classificacoes (classificacoes_do_projeto).
// Seções e colunas seguem o template "Plano de Garantia da Qualidade".
if (!isset($projeto, $pgq, $classificacoes)) {
    http_response_code(404);
    exit;
}

$dados = $pgq['pgq'] ?? [];

// Uma linha editável de sub-tabela; com $linha vazia serve de modelo para novas linhas.
function pgq_linha_html(string $chave, array $linha = []): string
{
    $html = '<tr>';
    foreach (PGQ_SUBTABELAS[$chave]['colunas'] as $coluna => $regra) {
        $html .= sprintf(
            '<td><input type="%s" name="%s[%s][]" value="%s"%s aria-label="%s"></td>',
            empty($regra['data']) ? 'text' : 'date',
            $chave,
            $coluna,
            e($linha[$coluna] ?? ''),
            isset($regra['max']) ? ' maxlength="' . $regra['max'] . '"' : '',
            e($regra['rotulo'])
        );
    }
    return $html . '<td><button type="button" class="js-remover-linha" aria-label="Remover linha">Remover</button></td></tr>';
}
?>
<form id="formPgq" class="pgq" novalidate>
    <input type="hidden" name="projeto_id" value="<?= (int) $projeto['id'] ?>">

    <section class="pgq-secao">
        <h3>Capa</h3>
        <div class="pgq-grade">
            <label>Autor/Responsável por GQA
                <input type="text" name="autor_gqa" maxlength="<?= PGQ_CAMPOS_TEXTO['autor_gqa']['max'] ?>" value="<?= e($dados['autor_gqa'] ?? '') ?>">
            </label>
            <label>Versão
                <input type="text" name="versao_documento" maxlength="<?= PGQ_CAMPOS_TEXTO['versao_documento']['max'] ?>" value="<?= e($dados['versao_documento'] ?? '') ?>">
            </label>
            <label>Cidade
                <input type="text" name="cidade" maxlength="<?= PGQ_CAMPOS_TEXTO['cidade']['max'] ?>" value="<?= e($dados['cidade'] ?? '') ?>">
            </label>
            <?php
                // Mês e ano em duas listas: <input type="month"> não existe no Firefox nem no Safari.
                $mesSalvo = isset($dados['data_documento']) ? (int) substr($dados['data_documento'], 5, 2) : 0;
                $anoSalvo = isset($dados['data_documento']) ? (int) substr($dados['data_documento'], 0, 4) : 0;
                $anoAtual = (int) date('Y');
                $anos = range(min($anoAtual - 5, $anoSalvo ?: $anoAtual), max($anoAtual + 5, $anoSalvo));
            ?>
            <fieldset class="pgq-mes-ano">
                <legend>Mês/ano</legend>
                <select name="data_documento_mes" aria-label="Mês">
                    <option value="">Mês</option>
                    <?php foreach (PGQ_MESES as $numero => $nome): ?>
                        <option value="<?= $numero ?>"<?= $numero === $mesSalvo ? ' selected' : '' ?>><?= $nome ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="data_documento_ano" aria-label="Ano">
                    <option value="">Ano</option>
                    <?php foreach ($anos as $ano): ?>
                        <option value="<?= $ano ?>"<?= $ano === $anoSalvo ? ' selected' : '' ?>><?= $ano ?></option>
                    <?php endforeach; ?>
                </select>
            </fieldset>
        </div>
    </section>

    <section class="pgq-secao">
        <h3>Comprometimento</h3>
        <div class="tabela-rolagem">
            <table class="pgq-tabela">
                <thead>
                    <tr><th>Responsabilidade</th><th>Nome</th><th>Data</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <th scope="row">Responsável pelo Projeto</th>
                        <td><input type="text" name="responsavel_projeto" maxlength="<?= PGQ_CAMPOS_TEXTO['responsavel_projeto']['max'] ?>" value="<?= e($dados['responsavel_projeto'] ?? '') ?>" aria-label="Nome do Responsável pelo Projeto"></td>
                        <td><input type="date" name="data_comprometimento_responsavel" value="<?= e($dados['data_comprometimento_responsavel'] ?? '') ?>" aria-label="Data do Responsável pelo Projeto"></td>
                    </tr>
                    <tr>
                        <th scope="row">RQ (Representante da Qualidade)</th>
                        <td><input type="text" name="rq_nome" maxlength="<?= PGQ_CAMPOS_TEXTO['rq_nome']['max'] ?>" value="<?= e($dados['rq_nome'] ?? '') ?>" aria-label="Nome do RQ"></td>
                        <td><input type="date" name="data_comprometimento_rq" value="<?= e($dados['data_comprometimento_rq'] ?? '') ?>" aria-label="Data do RQ"></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="pgq-secao">
        <h3>1. Introdução</h3>
        <label for="pgqObjetivo">1.1 Objetivo</label>
        <textarea id="pgqObjetivo" name="objetivo" rows="4" maxlength="<?= PGQ_TEXTO_LONGO_MAX ?>" placeholder="Descrever o objetivo deste documento."><?= e($dados['objetivo'] ?? '') ?></textarea>
        <label for="pgqVisaoGeral">1.2 Visão Geral</label>
        <textarea id="pgqVisaoGeral" name="visao_geral" rows="4" maxlength="<?= PGQ_TEXTO_LONGO_MAX ?>" placeholder="Descrever a visão geral do documento."><?= e($dados['visao_geral'] ?? '') ?></textarea>
    </section>

    <?php
        $descricoes = [
            'documentos'       => 'Documentação, padrões e diretrizes utilizadas no desenvolvimento do projeto ' . $projeto['nome'] . ', para atender aos objetivos de qualidade estabelecidos para este projeto.',
            'itens_avaliados'  => 'Documentos do projeto ' . $projeto['nome'] . ' que serão objetos de avaliação, indicando o local de armazenamento.',
            'plano_avaliacoes' => 'Avaliações que serão realizadas.',
        ];
    ?>
    <?php foreach (PGQ_SUBTABELAS as $chave => $subtabela): ?>
        <section class="pgq-secao">
            <h3><?= e($subtabela['secao']) ?></h3>
            <p class="pgq-descricao"><?= e($descricoes[$chave]) ?></p>
            <div class="tabela-rolagem">
                <table class="pgq-tabela">
                    <thead>
                        <tr>
                            <?php foreach ($subtabela['colunas'] as $regra): ?>
                                <th><?= e($regra['rotulo']) ?></th>
                            <?php endforeach; ?>
                            <th><span class="visualmente-oculto">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody id="linhas-<?= $chave ?>">
                        <?php foreach ($pgq[$chave] ?: [[]] as $linha): ?>
                            <?= pgq_linha_html($chave, $linha) ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <template id="modelo-<?= $chave ?>"><?= pgq_linha_html($chave) ?></template>
            <button type="button" class="botao-secundario" data-adicionar-linha="<?= $chave ?>">+ Adicionar linha</button>
        </section>
    <?php endforeach; ?>

    <section class="pgq-secao">
        <h3>5. Registros de Qualidade</h3>
        <label for="pgqRegistros">Os registros das auditorias de qualidade para o projeto <?= e($projeto['nome']) ?> serão armazenados em:</label>
        <input type="text" id="pgqRegistros" name="registros_qualidade_local" maxlength="<?= PGQ_CAMPOS_TEXTO['registros_qualidade_local']['max'] ?>" value="<?= e($dados['registros_qualidade_local'] ?? '') ?>" placeholder="Indicar o local de armazenamento.">
    </section>

    <section class="pgq-secao">
        <h3>6. Definição das Não-Conformidades</h3>
        <p class="pgq-descricao">Gravidade de cada tipo de não conformidade e prazo de resolução:</p>
        <div class="tabela-rolagem">
            <table class="pgq-tabela pgq-tabela-leitura">
                <thead>
                    <tr><th>Classificação</th><th>Prazo de resolução</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($classificacoes as $classificacao): ?>
                        <tr>
                            <td><?= e($classificacao['nome']) ?></td>
                            <td><?= e(classificacao_prazo_texto($classificacao)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="dica">A edição das classificações e prazos estará disponível em uma próxima versão.</p>
        <label for="pgqDefinicaoNc">Regras e observações sobre as não-conformidades</label>
        <textarea id="pgqDefinicaoNc" name="definicao_nc_texto" rows="4" maxlength="<?= PGQ_TEXTO_LONGO_MAX ?>" placeholder="Estabelecer a gravidade para cada tipo de não conformidade e prazo de resolução."><?= e($dados['definicao_nc_texto'] ?? '') ?></textarea>
    </section>

    <section class="pgq-secao">
        <h3>7. Processo de escalonamento</h3>
        <label for="pgqEscalonamento" class="visualmente-oculto">Processo de escalonamento</label>
        <textarea id="pgqEscalonamento" name="processo_escalonamento_texto" rows="4" maxlength="<?= PGQ_TEXTO_LONGO_MAX ?>" placeholder="Estabelecer o processo de escalonamento no caso de não resolução das ações corretivas solicitadas."><?= e($dados['processo_escalonamento_texto'] ?? '') ?></textarea>
    </section>

    <div class="pgq-rodape">
        <p class="mensagem" id="mensagemPgq" role="status"></p>
        <span class="pgq-atualizado">
            <?php if ($dados): ?>
                Última atualização: <span id="pgqAtualizadoEm"><?= e(date('d/m/Y H:i', strtotime($dados['atualizado_em']))) ?></span>
            <?php else: ?>
                <span id="pgqAtualizadoEm">Ainda não salvo</span>
            <?php endif; ?>
        </span>
        <button type="submit">Salvar</button>
    </div>
</form>
