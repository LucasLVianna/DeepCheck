<?php
// Rate limiting simples baseado em arquivos (não depende de tabela no banco). Guarda os
// timestamps das falhas recentes de cada chave dentro de uma janela deslizante.
// Pasta: RATE_LIMIT_DIR (no Docker, um volume persistente — ver docker-compose.yml);
// sem ela, o diretório temporário do sistema.

const RATE_LIMIT_JANELA = 900; // 15 minutos

function rate_limit_arquivo(string $chave): ?string
{
    $diretorio = getenv('RATE_LIMIT_DIR') ?: sys_get_temp_dir() . '/deepcheck_rate_limit';
    if (!is_dir($diretorio) && !@mkdir($diretorio, 0700, true) && !is_dir($diretorio)) {
        return null;
    }
    return $diretorio . '/' . hash('sha256', $chave) . '.json';
}

function rate_limit_ler_recentes($handle): array
{
    $dados = json_decode(stream_get_contents($handle) ?: '[]', true);
    $limite = time() - RATE_LIMIT_JANELA;
    $recentes = array_filter(is_array($dados) ? $dados : [], fn($t) => is_int($t) && $t > $limite);
    sort($recentes);
    return $recentes;
}

// Quantos segundos faltam para a chave voltar a ser aceita (0 = liberada).
function rate_limit_segundos_restantes(string $chave, int $maximo): int
{
    $arquivo = rate_limit_arquivo($chave);
    if ($arquivo === null || !is_file($arquivo) || !($handle = @fopen($arquivo, 'r'))) {
        return 0;
    }

    flock($handle, LOCK_SH);
    $recentes = rate_limit_ler_recentes($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    if (count($recentes) < $maximo) {
        return 0;
    }
    // Libera quando a falha que completou o limite sair da janela.
    return max(1, $recentes[count($recentes) - $maximo] + RATE_LIMIT_JANELA - time());
}

function rate_limit_registrar_falha(string $chave): void
{
    $arquivo = rate_limit_arquivo($chave);
    if ($arquivo === null || !($handle = @fopen($arquivo, 'c+'))) {
        error_log('DeepCheck: rate limit indisponível (sem escrita em ' . sys_get_temp_dir() . ')');
        return;
    }

    flock($handle, LOCK_EX);
    $recentes = rate_limit_ler_recentes($handle);
    $recentes[] = time();
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($recentes));
    flock($handle, LOCK_UN);
    fclose($handle);
}

function rate_limit_limpar(string $chave): void
{
    $arquivo = rate_limit_arquivo($chave);
    if ($arquivo !== null && is_file($arquivo)) {
        @unlink($arquivo);
    }
}
