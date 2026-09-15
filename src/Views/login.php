<?php
    session_start();
    include_once(__DIR__ . '/../../config/verifica_login_realizado.php');
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="/DeepCheck/public/css/login.css">
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
                        <label for="email">E-mail: <span style="color: red;">*</span></label>
                    </div>
                    <div>
                        <input type="text" name="email" id="email" placeholder="seu@email.com">
                        <p id="error-email"></p>
                    </div>
                </div>


                <div>
                    <div>
                        <label for="senha">Senha: <span style="color: red;">*</span></label> <br>
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
                <span id="significadoAspas" style= "font-size: 0.72rem; color: #555; text-align: left;">*: Campo obrigatório</span>
            </form>

            <div class="divider">
                <span>AINDA NÃO TEM CONTA?</span>
                <button type="button" id="createAccount">Criar uma conta</button>
            </div>

        </div>
        
    </section>

<script src="/DeepCheck/public/js/login.js"></script>
</body>
</html>