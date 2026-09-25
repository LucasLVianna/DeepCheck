<?php
// Leitor do .env. Não usa parse_ini_file porque o formato INI não aceita
// comentários com "#" nem alguns caracteres, como "(", que o .env usa.
// Variáveis de ambiente reais (getenv) têm prioridade sobre o arquivo.

function env(string $chave, ?string $padrao = null): ?string
{
    static $valores = null;

    if ($valores === null) {
        $valores = [];
        $arquivo = __DIR__ . '/../.env';
        $linhas = is_readable($arquivo) ? file($arquivo, FILE_IGNORE_NEW_LINES) : [];

        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if ($linha === '' || $linha[0] === '#' || !str_contains($linha, '=')) {
                continue;
            }

            [$nome, $valor] = array_map('trim', explode('=', $linha, 2));
            if (strlen($valor) >= 2 && ($valor[0] === '"' || $valor[0] === "'") && $valor[-1] === $valor[0]) {
                $valor = substr($valor, 1, -1);
            }
            $valores[$nome] = $valor;
        }
    }

    $real = getenv($chave);
    if ($real !== false) {
        return $real;
    }

    return $valores[$chave] ?? $padrao;
}
