<link rel="stylesheet" href="/DeepCheck/public/css/navbar.css">

<header class="navbar">
    <div>
        <h2>DeepCheck</h2>
    </div>
    <div>
        <nav>
            <button id="inicioButtonLink">Início</button>


            <button id="perfilButtonLink">Perfil</button>


            <button id="logoffButtonLink">Sair</button>

            <?php if (isset($_SESSION['usuario']['nome_usuario'])): ?>
                <span class="usuarioLogado"><?= htmlspecialchars($_SESSION['usuario']['nome_usuario']) ?></span>
            <?php endif; ?>

        </nav>
    </div>
</header>