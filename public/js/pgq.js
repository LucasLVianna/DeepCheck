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

formPgq.addEventListener('input', () => {
    alteracoesPendentes = true;
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

    const resposta = await enviarPost('/src/Controllers/pgq_salvar.php', new FormData(formPgq));
    botao.disabled = false;

    const sucesso = resposta.status === 'ok';
    mensagemPgq.textContent = resposta.mensagem;
    mensagemPgq.classList.toggle('mensagem-sucesso', sucesso);
    mensagemPgq.classList.toggle('mensagem-erro', !sucesso);

    if (sucesso) {
        alteracoesPendentes = false;
        document.getElementById('pgqAtualizadoEm').textContent = resposta.atualizado_em;
    }
});
