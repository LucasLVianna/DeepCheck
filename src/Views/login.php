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
    <title>Login</title>
    <link rel="stylesheet" href="/public/css/login.css">
</head>

<body>
    <section>
        <div>
            <div>
                <h2>Bem-vindo ao DeepCheck</h2>
            </div>
            <div>
                <p>Entre com sua conta para acessar seu gerenciador de auditorias</p>
            </div>

            <form id="formLogin">

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
                        <label for="senha">Senha: <span class="obrigatorio">*</span></label> <br>
                    </div>
                    <div>
                        <input type="password" name="senha" id="senha" placeholder="••••••" minLength="8">
                        <p id="error-senha"></p>
                    </div>
                    <h4 id="error"></h4>
                    <div>
                        <p>Esqueceu a senha? <a href="#">Redefinir</a></p>
                    </div>
                </div>

                <button type="submit">Entrar</button>
                <span id="significadoAspas">*: Campo obrigatório</span>
            </form>

            <div class="divider">
                <span>AINDA NÃO TEM CONTA?</span>
                <button type="button" id="createAccount">Criar uma conta</button>
            </div>

        </div>
        
    </section>

<script src="/public/js/login.js"></script>
</body>
</html>