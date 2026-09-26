<?php
    require_once __DIR__ . '/../../config/auth.php';
    redirecionar_se_logado();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <title>Cadastro</title>
    <link rel="stylesheet" href="<?= asset('/public/css/cadastro.css') ?>">
</head>

<body>
    <section>
        <div>
            <div>
                <h2>Criar conta no DeepCheck</h2>
            </div>
            <div>
                <p>Preencha os dados abaixo para começar a controlar seu projeto</p>
            </div>

            <form id="formCadastro">
                <div>
                    <div>
                        <label for="nome">Nome completo: <span class="obrigatorio">*</span></label>
                    </div>
                    <div>
                        <input type="text" name="nome" id="nome" placeholder="Seu nome">
                        <p id="error-nome"></p>
                    </div>
                </div>
                <div>
                    <div>
                        <label for="email">E-mail: <span class="obrigatorio">*</span></label>
                    </div>
                    <div>
                        <input type="text" name="email" id="email" placeholder="seu@email.com">
                        <p id="error-email"></p>
                    </div>
                </div>

                <div>
                    <div>
                        <label for="cep">CEP: <span class="obrigatorio">*</span></label>
                    </div>
                    <div>
                        <input type="text" name="cep" id="cep" placeholder="00000-000" maxlength="9" inputmode="numeric">
                        <p id="error-cep"></p>
                    </div>
                </div>

                <div>
                    <div>
                        <label for="senha">Senha: <span class="obrigatorio">*</span></label> <br>
                    </div>
                    <div>
                        <input type="password" name="senha" id="senha" placeholder="••••••" minlength="8">
                        <p id="error-senha"></p>
                    </div>
                </div>
                <div>
                    <p id="error"></p>
                </div>
                <button type="submit">Criar conta</button>
                <span id="significadoAspas">*: Campo obrigatório</span>
            </form>
            
            <div class="divider">
                <span>JÁ TEM UMA CONTA?</span>
            </div>
            <button type="button" id="entrar">Entrar</button>
        </div>
    </section>
<script src="<?= asset('/public/js/cadastrar.js') ?>"></script>
</body>
</html>