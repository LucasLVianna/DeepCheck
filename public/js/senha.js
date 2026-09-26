// "Esqueci a senha" (pedir o link) e "Criar nova senha" (a partir do link).
const formEsqueci = document.getElementById('formEsqueci');
if (formEsqueci) {
    formEsqueci.addEventListener('submit', async (event) => {
        event.preventDefault();
        esconderAlerta();
        const campo = document.getElementById('email');
        if (!emailValido(campo.value.trim())) {
            mostrarErroCampo(campo, campo.value.trim() ? 'Digite um e-mail válido.' : 'Informe seu e-mail.');
            campo.focus();
            return;
        }
        const botao = document.getElementById('botaoEnviarLink');
        botaoCarregando(botao, true, 'Enviando...');
        const resposta = await enviarPost('/src/Controllers/esqueci_senha_enviar.php', { email: campo.value.trim() });
        botaoCarregando(botao, false);
        mostrarAlerta(resposta.mensagem, resposta.status === 'ok' ? 'sucesso' : 'erro');
        if (resposta.status === 'ok') formEsqueci.reset();
    });
}

const formRedefinir = document.getElementById('formRedefinir');
if (formRedefinir) {
    formRedefinir.addEventListener('submit', async (event) => {
        event.preventDefault();
        esconderAlerta();
        const senha = document.getElementById('senha');
        const confirmar = document.getElementById('confirmarSenha');
        let valido = true;
        if (!senhaForte(senha.value)) {
            mostrarErroCampo(senha, 'A senha ainda não atende a todos os requisitos acima.');
            valido = false;
        }
        if (confirmar.value !== senha.value || !confirmar.value) {
            mostrarErroCampo(confirmar, confirmar.value ? 'As senhas não conferem.' : 'Confirme a senha.');
            valido = false;
        }
        if (!valido) return;

        const botao = document.getElementById('botaoRedefinir');
        botaoCarregando(botao, true, 'Salvando...');
        const resposta = await enviarPost('/src/Controllers/redefinir_senha_salvar.php', new FormData(formRedefinir));
        if (resposta.status === 'ok') {
            window.location.href = resposta.redirect;
            return;
        }
        botaoCarregando(botao, false);
        mostrarAlerta(resposta.mensagem, 'erro');
    });
}
