const formPgq = document.getElementById('formPgq');
const mensagemPgq = document.getElementById('mensagemPgq');
let alteracoesPendentes = false;

function adicionarLinha(chave) {
    const modelo = document.getElementById(`modelo-${chave}`);
    const linha = modelo.content.firstElementChild.cloneNode(true);
    document.getElementById(`linhas-${chave}`).appendChild(linha);
    linha.querySelector('input').focus();
}

formPgq.addEventListener('click', (event) => {
    const botaoAdicionar = event.target.closest('[data-adicionar-linha]');
    if (botaoAdicionar) {
        adicionarLinha(botaoAdicionar.dataset.adicionarLinha);
        alteracoesPendentes = true;
        return;
    }

    const botaoRemover = event.target.closest('.js-remover-linha');
    if (botaoRemover) {
        botaoRemover.closest('tr').remove();
        alteracoesPendentes = true;
    }
});

formPgq.addEventListener('input', (event) => {
    // O editor de classificações (seção 6) e o logo salvam na hora; não contam como alteração do plano.
    if (!event.target.closest('#classificacoes, #pgqLogo')) {
        alteracoesPendentes = true;
    }
});

// O PDF exportado usa a versão salva do plano.
document.getElementById('exportarPdf').addEventListener('click', (event) => {
    if (alteracoesPendentes && !window.confirm('Há alterações não salvas. O PDF será gerado com a última versão salva do plano. Continuar?')) {
        event.preventDefault();
    }
});

// Avisa antes de sair da página (ou trocar de aba) com alterações não salvas.
window.addEventListener('beforeunload', (event) => {
    if (alteracoesPendentes) {
        event.preventDefault();
        event.returnValue = '';
    }
});

formPgq.addEventListener('submit', async (event) => {
    event.preventDefault();
    const botao = formPgq.querySelector('button[type="submit"]');

    botao.disabled = true;
    mensagemPgq.textContent = 'Salvando...';
    mensagemPgq.classList.remove('mensagem-sucesso', 'mensagem-erro');

    let resposta = await enviarPost('/src/Controllers/pgq_salvar.php', new FormData(formPgq));
    // Edição simultânea: outra pessoa salvou depois que a página foi aberta.
    if (resposta.conflito && window.confirm(`${resposta.mensagem}\n\nOK = salvar mesmo assim (substituir)\nCancelar = não salvar agora (recarregue a página para ver a versão atual)`)) {
        const dados = new FormData(formPgq);
        dados.append('sobrescrever', '1');
        resposta = await enviarPost('/src/Controllers/pgq_salvar.php', dados);
    }
    botao.disabled = false;

    const sucesso = resposta.status === 'ok';
    mensagemPgq.textContent = resposta.mensagem;
    mensagemPgq.classList.toggle('mensagem-sucesso', sucesso);
    mensagemPgq.classList.toggle('mensagem-erro', !sucesso);

    if (sucesso) {
        alteracoesPendentes = false;
        document.getElementById('pgqAtualizadoEm').textContent = resposta.atualizado_em;
        formPgq.versao.value = resposta.versao;
    }
});

// --- Seção 6: classificações de NC (salvas na hora, separadas do botão "Salvar") ---
const classificacoes = document.getElementById('classificacoes');
const linhasClassificacoes = document.getElementById('linhasClassificacoes');
const mensagemClassificacoes = document.getElementById('mensagemClassificacoes');
const dialogExcluir = document.getElementById('dialogExcluirClassificacao');
const formExcluir = document.getElementById('formExcluirClassificacao');
let listaClassificacoes = JSON.parse(classificacoes.dataset.classificacoes);
let historicoClassificacoes = JSON.parse(classificacoes.dataset.historico);

function renderizarHistorico() {
    const lista = document.getElementById('historicoClassificacoes');
    if (!historicoClassificacoes.length) {
        const vazio = document.createElement('li');
        vazio.textContent = 'Nenhuma alteração registrada.';
        lista.replaceChildren(vazio);
        return;
    }
    lista.replaceChildren(...historicoClassificacoes.map((h) => {
        const item = document.createElement('li');
        const quando = document.createElement('span');
        quando.className = 'historico-quando';
        quando.textContent = `${h.quando} · ${h.nome_usuario}`;
        item.append(quando, document.createTextNode(` — ${h.descricao}`));
        return item;
    }));
}
let classificacaoExcluindo = null;

function mostrarMensagemClassificacoes(texto, sucesso) {
    mensagemClassificacoes.textContent = texto;
    mensagemClassificacoes.classList.toggle('mensagem-sucesso', sucesso === true);
    mensagemClassificacoes.classList.toggle('mensagem-erro', sucesso === false);
}

function textoUso(c) {
    const partes = [];
    if (c.itens_em_uso > 0) partes.push(`${c.itens_em_uso} item(ns) do checklist`);
    if (c.ncs_em_uso > 0) partes.push(`${c.ncs_em_uso} NC(s) enviada(s)`);
    return partes.length ? partes.join(', ') : 'Não';
}

function renderizarClassificacoes() {
    const modelo = document.getElementById('modeloClassificacao');
    linhasClassificacoes.replaceChildren(...listaClassificacoes.map((c) => {
        const linha = modelo.content.firstElementChild.cloneNode(true);
        linha.dataset.id = c.id;
        linha.querySelector('[data-campo="nome"]').value = c.nome;
        linha.querySelector('[data-campo="prazo_valor"]').value = c.prazo_valor;
        linha.querySelector('[data-campo="prazo_unidade"]').value = c.prazo_unidade;
        linha.querySelector('.classificacao-uso').textContent = textoUso(c);
        linha.querySelector('.js-excluir-classificacao').disabled = listaClassificacoes.length <= 1;
        return linha;
    }));
}

function dadosDaLinha(linha) {
    const valor = (campo) => linha.querySelector(`[data-campo="${campo}"]`).value;
    return {
        projeto_id: classificacoes.dataset.projetoId,
        id: linha.dataset.id || '',
        nome: valor('nome'),
        prazo_valor: valor('prazo_valor'),
        prazo_unidade: valor('prazo_unidade')
    };
}

async function salvarClassificacao(linha, botao) {
    botao.disabled = true;
    const resposta = await enviarPost('/src/Controllers/classificacao_salvar.php', dadosDaLinha(linha));
    botao.disabled = false;
    if (resposta.status !== 'ok') {
        mostrarMensagemClassificacoes(resposta.mensagem, false);
        return;
    }
    listaClassificacoes = resposta.classificacoes;
    historicoClassificacoes = resposta.historico;
    renderizarClassificacoes();
renderizarHistorico();
    renderizarHistorico();
    if (!linha.dataset.id) {
        linha.querySelectorAll('input').forEach((campo) => { campo.value = ''; });
    }
    mostrarMensagemClassificacoes(resposta.mensagem, true);
}

async function excluirClassificacao(id, substitutaId) {
    const resposta = await enviarPost('/src/Controllers/classificacao_excluir.php', { id, substituta_id: substitutaId || '' });
    if (resposta.status === 'ok') {
        listaClassificacoes = resposta.classificacoes;
        historicoClassificacoes = resposta.historico;
        renderizarClassificacoes();
        renderizarHistorico();
        mostrarMensagemClassificacoes(resposta.mensagem, true);
    }
    return resposta;
}

classificacoes.addEventListener('click', async (event) => {
    const linha = event.target.closest('tr');
    if (event.target.closest('.js-salvar-classificacao, .js-adicionar-classificacao')) {
        salvarClassificacao(linha, event.target.closest('button'));
        return;
    }
    if (!event.target.closest('.js-excluir-classificacao')) return;

    const c = listaClassificacoes.find((item) => String(item.id) === linha.dataset.id);
    if (c.itens_em_uso === 0 && c.ncs_em_uso === 0) {
        if (!window.confirm(`Excluir a classificação "${c.nome}"?`)) return;
        const resposta = await excluirClassificacao(c.id);
        if (resposta.status !== 'ok') mostrarMensagemClassificacoes(resposta.mensagem, false);
        return;
    }

    // Em uso: escolher a substituta.
    classificacaoExcluindo = c;
    document.getElementById('textoExcluirClassificacao').textContent =
        `"${c.nome}" está em uso em ${textoUso(c)}. Escolha a classificação que vai substituí-la. `
        + 'Itens com NC ainda não enviada terão a data prevista recalculada; NCs já enviadas mantêm o prazo comunicado.';
    const select = document.getElementById('substitutaClassificacao');
    select.replaceChildren(...listaClassificacoes.filter((item) => item.id !== c.id).map((item) => {
        const opcao = document.createElement('option');
        opcao.value = item.id;
        opcao.textContent = `${item.nome} | ${item.prazo_texto}`;
        return opcao;
    }));
    document.getElementById('mensagemExcluirClassificacao').textContent = '';
    dialogExcluir.showModal();
});

// Enter num campo de classificação salva a linha, em vez de enviar o formulário do plano.
classificacoes.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' || !event.target.closest('[data-campo]')) return;
    event.preventDefault();
    const linha = event.target.closest('tr');
    salvarClassificacao(linha, linha.querySelector('button'));
});

document.getElementById('cancelarExcluirClassificacao').addEventListener('click', () => dialogExcluir.close());

formExcluir.addEventListener('submit', async (event) => {
    event.preventDefault();
    const substitutaId = document.getElementById('substitutaClassificacao').value;
    const resposta = await excluirClassificacao(classificacaoExcluindo.id, substitutaId);
    if (resposta.status === 'ok') {
        dialogExcluir.close();
        return;
    }
    const mensagem = document.getElementById('mensagemExcluirClassificacao');
    mensagem.classList.add('mensagem-erro');
    mensagem.textContent = resposta.mensagem;
});

renderizarClassificacoes();

// --- Logo do projeto (capa) — salvo na hora, separado do "Salvar" do plano ---
const pgqLogo = document.getElementById('pgqLogo');
const logoImagem = document.getElementById('pgqLogoImagem');
const logoMensagem = document.getElementById('pgqLogoMensagem');

async function acaoLogo(acao, arquivo) {
    const dados = new FormData();
    dados.append('projeto_id', pgqLogo.dataset.projetoId);
    dados.append('acao', acao);
    if (arquivo) dados.append('logo', arquivo);
    const resposta = await enviarPost('/src/Controllers/pgq_logo.php', dados);

    logoMensagem.textContent = resposta.mensagem;
    logoMensagem.classList.toggle('mensagem-sucesso', resposta.status === 'ok');
    logoMensagem.classList.toggle('mensagem-erro', resposta.status !== 'ok');
    if (resposta.status !== 'ok') return;

    // Se o plano ainda não existia, o envio do logo o criou: a tela passa a ter essa versão.
    if (formPgq.versao.value === '') formPgq.versao.value = resposta.versao;
    logoImagem.hidden = !resposta.tem_logo;
    document.getElementById('pgqLogoVazio').hidden = resposta.tem_logo;
    document.getElementById('pgqLogoRemover').hidden = !resposta.tem_logo;
    if (resposta.tem_logo) {
        logoImagem.src = `/src/Controllers/pgq_logo_imagem.php?projeto=${pgqLogo.dataset.projetoId}&v=${Date.now()}`;
    }
    document.getElementById('pgqLogoArquivo').value = '';
}

document.getElementById('pgqLogoEnviar').addEventListener('click', () => {
    const arquivo = document.getElementById('pgqLogoArquivo').files[0];
    if (!arquivo) {
        logoMensagem.textContent = 'Escolha uma imagem PNG ou JPG.';
        logoMensagem.classList.add('mensagem-erro');
        return;
    }
    if (arquivo.size > 1048576) {
        logoMensagem.textContent = 'O logo pode ter no máximo 1 MB.';
        logoMensagem.classList.add('mensagem-erro');
        return;
    }
    acaoLogo('enviar', arquivo);
});

document.getElementById('pgqLogoRemover').addEventListener('click', () => {
    if (window.confirm('Remover o logo do projeto?')) acaoLogo('remover');
});
