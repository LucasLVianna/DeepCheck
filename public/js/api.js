// POST para os endpoints JSON do DeepCheck, com o token CSRF da página.
// `dados` pode ser um FormData ou um objeto simples { campo: valor }.
// Sessão expirada redireciona para o login; os demais casos retornam { status, mensagem, ... }.
async function enviarPost(url, dados) {
    const corpo = dados instanceof FormData ? dados : new FormData();
    if (!(dados instanceof FormData)) {
        for (const [campo, valor] of Object.entries(dados)) {
            corpo.append(campo, valor);
        }
    }

    let resposta;
    try {
        resposta = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content },
            body: corpo
        });
    } catch (erro) {
        return { status: 'nok', mensagem: 'Não foi possível falar com o servidor. Verifique sua conexão.' };
    }

    let json;
    try {
        json = await resposta.json();
    } catch (erro) {
        return { status: 'nok', mensagem: 'Resposta inesperada do servidor. Tente novamente.' };
    }

    // exigir_login_api() responde 401 com o campo "expirado".
    if (resposta.status === 401 && 'expirado' in json) {
        window.location.href = '/src/Views/login.php' + (json.expirado ? '?motivo=expirado' : '');
    }

    return json;
}
