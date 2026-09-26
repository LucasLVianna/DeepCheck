// Utilitários das telas de autenticação (login, cadastro, esqueci/redefinir senha).

// Mesmas regras de senha do backend (cadastrar_backend.php).
const REQUISITOS_SENHA = {
    tamanho: (senha) => senha.length >= 8 && senha.length <= 72,
    maiuscula: (senha) => /[A-Z]/.test(senha),
    minuscula: (senha) => /[a-z]/.test(senha),
    numero: (senha) => /[0-9]/.test(senha),
    especial: (senha) => /[@$!%*?&]/.test(senha)
};

function senhaForte(senha) {
    return Object.values(REQUISITOS_SENHA).every((regra) => regra(senha));
}

function emailValido(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function mostrarErroCampo(campo, mensagem) {
    const erro = document.getElementById(`erro-${campo.id}`);
    erro.textContent = mensagem;
    campo.setAttribute('aria-invalid', 'true');
}

function limparErroCampo(campo) {
    const erro = document.getElementById(`erro-${campo.id}`);
    if (erro) erro.textContent = '';
    campo.removeAttribute('aria-invalid');
}

// tipo: 'erro' | 'sucesso' | 'aviso'
function mostrarAlerta(texto, tipo) {
    const alerta = document.getElementById('alerta');
    alerta.className = `alerta alerta-${tipo}`;
    alerta.textContent = texto;
    alerta.hidden = false;
}

function esconderAlerta() {
    document.getElementById('alerta').hidden = true;
}

function botaoCarregando(botao, carregando, textoCarregando) {
    if (carregando) {
        botao.dataset.textoOriginal = botao.textContent;
        botao.textContent = textoCarregando;
    } else if (botao.dataset.textoOriginal) {
        botao.textContent = botao.dataset.textoOriginal;
    }
    botao.disabled = carregando;
    botao.classList.toggle('carregando', carregando);
}

// Botões "Mostrar/Ocultar" dos campos de senha.
document.querySelectorAll('.botao-mostrar-senha').forEach((botao) => {
    botao.addEventListener('click', () => {
        const campo = document.getElementById(botao.dataset.alvo);
        const mostrar = campo.type === 'password';
        campo.type = mostrar ? 'text' : 'password';
        botao.textContent = mostrar ? 'Ocultar' : 'Mostrar';
        botao.setAttribute('aria-label', mostrar ? 'Ocultar senha' : 'Mostrar senha');
    });
});

// Lista de requisitos marcada conforme a senha é digitada.
document.querySelectorAll('.requisitos-senha').forEach((lista) => {
    const campo = document.getElementById(lista.dataset.senha);
    campo.addEventListener('input', () => {
        for (const item of lista.querySelectorAll('[data-requisito]')) {
            item.classList.toggle('atendido', REQUISITOS_SENHA[item.dataset.requisito](campo.value));
        }
    });
});

// O erro de um campo some assim que o usuário volta a digitar nele.
document.querySelectorAll('.campo input').forEach((campo) => {
    campo.addEventListener('input', () => limparErroCampo(campo));
});
