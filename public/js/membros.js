// Painel "Membros" do projeto (src/Views/projeto_membros.php).
const dialogMembros = document.getElementById('dialogMembros');
const mensagemMembros = document.getElementById('mensagemMembros');
const projetoIdMembros = dialogMembros.dataset.projetoId;

function mostrarMensagemMembros(texto, sucesso) {
    mensagemMembros.textContent = texto;
    mensagemMembros.classList.toggle('mensagem-sucesso', sucesso);
    mensagemMembros.classList.toggle('mensagem-erro', !sucesso);
}

async function acaoMembros(acao, dados = {}) {
    return enviarPost('/src/Controllers/projeto_membros.php', { ...dados, acao, projeto_id: projetoIdMembros });
}

document.getElementById('abrirMembros').addEventListener('click', () => {
    mensagemMembros.textContent = '';
    dialogMembros.showModal();
});
document.getElementById('fecharMembros').addEventListener('click', () => dialogMembros.close());

dialogMembros.addEventListener('click', async (event) => {
    const botao = event.target.closest('.js-remover-membro');
    if (!botao) return;
    const linha = botao.closest('tr');
    if (!window.confirm(`Remover ${linha.dataset.nome} do projeto? Para voltar, a pessoa vai precisar do código e da senha do projeto.`)) return;

    botao.disabled = true;
    const resposta = await acaoMembros('remover', { usuario_id: linha.dataset.usuarioId });
    botao.disabled = false;
    mostrarMensagemMembros(resposta.mensagem, resposta.status === 'ok');
    if (resposta.status === 'ok') {
        linha.remove();
        document.querySelector(`#formTransferir option[value="${linha.dataset.usuarioId}"]`)?.remove();
        document.getElementById('abrirMembros').textContent = `Membros (${dialogMembros.querySelectorAll('tbody tr').length})`;
    }
});

const formSenhaProjeto = document.getElementById('formSenhaProjeto');
if (formSenhaProjeto) {
    formSenhaProjeto.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (formSenhaProjeto.senha.value !== formSenhaProjeto.confirmar_senha.value) {
            mostrarMensagemMembros('A confirmação não confere com a nova senha.', false);
            return;
        }
        const resposta = await acaoMembros('trocar_senha', {
            senha: formSenhaProjeto.senha.value,
            confirmar_senha: formSenhaProjeto.confirmar_senha.value
        });
        mostrarMensagemMembros(resposta.mensagem, resposta.status === 'ok');
        if (resposta.status === 'ok') formSenhaProjeto.reset();
    });
}

const formTransferir = document.getElementById('formTransferir');
if (formTransferir && formTransferir.usuario_id) {
    formTransferir.addEventListener('submit', async (event) => {
        event.preventDefault();
        const nome = formTransferir.usuario_id.selectedOptions[0].textContent;
        if (!window.confirm(`Transferir a posse do projeto para ${nome}? Você deixa de ser o dono.`)) return;
        const resposta = await acaoMembros('transferir', { usuario_id: formTransferir.usuario_id.value });
        mostrarMensagemMembros(resposta.mensagem, resposta.status === 'ok');
        if (resposta.status === 'ok') window.location.reload();
    });
}

const botaoSair = document.getElementById('sairProjeto');
if (botaoSair) {
    botaoSair.addEventListener('click', async () => {
        if (!window.confirm('Sair deste projeto? Para voltar, você vai precisar do código e da senha do projeto.')) return;
        const resposta = await acaoMembros('sair');
        if (resposta.status === 'ok') {
            window.location.href = resposta.redirect;
            return;
        }
        mostrarMensagemMembros(resposta.mensagem, false);
    });
}
