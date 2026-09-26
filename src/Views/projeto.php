<?php
    require_once __DIR__ . '/../../config/auth.php';
    exigir_login();
    require_once __DIR__ . '/../../config/conexao.php';
    require_once __DIR__ . '/../Models/projetos.php';

    $usuarioId = (int) $_SESSION['usuario']['id'];
    $projetoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $projeto = $projetoId ? projeto_do_membro($conexao, $projetoId, $usuarioId) : null;

    // Sem vínculo em projeto_membros = sem acesso (entrar primeiro com código + senha).
    if ($projeto === null) {
        $conexao->close();
        header('Location: /src/Views/menu.php');
        exit;
    }

    projeto_registrar_acesso($conexao, $projetoId, $usuarioId);

    $abas = [
        'pgq'       => ['titulo' => 'Plano de Garantia da Qualidade', 'fase' => 4],
        'checklist' => ['titulo' => 'Checklist de Qualidade',         'fase' => 5],
        'nc'        => ['titulo' => 'Não Conformidades',              'fase' => 7],
    ];
    $abaAtual = $_GET['aba'] ?? 'pgq';
    if (!is_string($abaAtual) || !isset($abas[$abaAtual])) {
        $abaAtual = 'pgq';
    }

    // Dados e arquivos de cada aba já implementada (as demais mostram o aviso de "em construção").
    $arquivosAba = ['css' => null, 'js' => null];
    if ($abaAtual === 'pgq') {
        require_once __DIR__ . '/../Models/pgq.php';
        require_once __DIR__ . '/../Models/classificacoes_nc.php';
        $pgq = pgq_do_projeto($conexao, $projetoId);
        $classificacoes = classificacoes_do_projeto($conexao, $projetoId);
        $arquivosAba = ['css' => '/public/css/pgq.css', 'js' => '/public/js/pgq.js'];
    } elseif ($abaAtual === 'checklist') {
        require_once __DIR__ . '/../Models/checklist.php';
        require_once __DIR__ . '/../Models/classificacoes_nc.php';
        $checklist = checklist_do_projeto($conexao, $projetoId);
        $itens = $checklist ? checklist_itens($conexao, $checklist['id']) : [];
        $classificacoes = classificacoes_do_projeto($conexao, $projetoId);
        $indicadores = checklist_indicadores($itens);
        require_once __DIR__ . '/../Models/pgq.php';
        $responsavelQaPadrao = pgq_rq_nome($conexao, $projetoId) ?: $_SESSION['usuario']['nome'];
        $arquivosAba = ['css' => '/public/css/checklist.css', 'js' => '/public/js/checklist.js'];
    }
    $conexao->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($projeto['nome']) ?> — DeepCheck</title>
    <link rel="stylesheet" href="<?= asset('/public/css/projeto.css') ?>">
    <?php if ($arquivosAba['css']): ?>
        <link rel="stylesheet" href="<?= asset($arquivosAba['css']) ?>">
    <?php endif; ?>
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>

    <main class="projeto">
        <header class="projeto-cabecalho">
            <a href="/src/Views/menu.php">&larr; Meus projetos</a>
            <h1><?= e($projeto['nome']) ?></h1>
            <p>Código de acesso: <code><?= e($projeto['projeto_codigo_acesso']) ?></code></p>
        </header>

        <nav class="abas" aria-label="Seções do projeto">
            <?php foreach ($abas as $chave => $aba): ?>
                <a href="/src/Views/projeto.php?id=<?= (int) $projeto['id'] ?>&amp;aba=<?= $chave ?>"
                   class="aba<?= $chave === $abaAtual ? ' aba-ativa' : '' ?>"
                   <?= $chave === $abaAtual ? 'aria-current="page"' : '' ?>><?= e($aba['titulo']) ?></a>
            <?php endforeach; ?>
        </nav>

        <section class="aba-conteudo">
            <h2><?= e($abas[$abaAtual]['titulo']) ?></h2>
            <?php if ($abaAtual === 'pgq'): ?>
                <?php include __DIR__ . '/abas/pgq.php'; ?>
            <?php elseif ($abaAtual === 'checklist'): ?>
                <?php include __DIR__ . '/abas/checklist.php'; ?>
            <?php else: ?>
                <p class="em-construcao">Esta aba será implementada na Fase <?= $abas[$abaAtual]['fase'] ?>.</p>
            <?php endif; ?>
        </section>
    </main>

    <?php if ($arquivosAba['js']): ?>
        <script src="<?= asset('/public/js/api.js') ?>"></script>
        <script src="<?= asset($arquivosAba['js']) ?>"></script>
    <?php endif; ?>
</body>
</html>
