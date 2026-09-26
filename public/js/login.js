const formLogin = document.getElementById('formLogin');
const campoEmail = document.getElementById('email');
const campoSenha = document.getElementById('senha');

const avisos = {
    expirado: ['Sua sessão expirou por inatividade. Entre novamente.', 'aviso'],
    cadastro: ['Conta criada com sucesso! Entre com seu e-mail e senha.', 'sucesso'],
    senha_redefinida: ['Senha redefinida com sucesso! Entre com a nova senha.', 'sucesso']
};
const motivo = new URLSearchParams(window.location.search).get('motivo');
if (avisos[motivo]) {
    mostrarAlerta(...avisos[motivo]);
}

formLogin.addEventListener('submit', async (event) => {
    event.preventDefault();
    esconderAlerta();

    const email = campoEmail.value.trim();
    let valido = true;
    if (!emailValido(email)) {
        mostrarErroCampo(campoEmail, email ? 'Digite um e-mail válido.' : 'Informe seu e-mail.');
        valido = false;
    }
    if (!campoSenha.value) {
        mostrarErroCampo(campoSenha, 'Informe sua senha.');
        valido = false;
    }
    if (!valido) {
        formLogin.querySelector('[aria-invalid="true"]').focus();
        return;
    }

    const botao = document.getElementById('botaoEntrar');
    botaoCarregando(botao, true, 'Entrando...');
    const resposta = await enviarPost('/src/Controllers/login_backend.php', { email, senha: campoSenha.value });

    if (resposta.status === 'ok') {
        botao.textContent = 'Redirecionando...';
        window.location.href = resposta.redirect;
        return;
    }
    botaoCarregando(botao, false);
    mostrarAlerta(resposta.mensagem, 'erro');
    campoSenha.value = '';
    campoSenha.focus();
});
