<?php
// Executa um script Python de scripts/ na própria requisição (decisão: integração síncrona).
// Usa proc_open sem passar pelo shell e troca dados por JSON no stdin/stdout — nada vai na
// linha de comando (a senha SMTP, por exemplo, não aparece na lista de processos).
// O script responde {"ok": true, ...} ou {"ok": false, "erro": "<para o usuário>", "detalhe": "<log>"}.
function python_executar(string $script, array $entrada): array
{
    $processo = proc_open(
        ['python3', __DIR__ . '/../scripts/' . $script],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($processo)) {
        error_log("DeepCheck: não foi possível executar scripts/{$script}");
        return ['ok' => false, 'erro' => 'Não foi possível acionar o gerador de documentos.'];
    }

    fwrite($pipes[0], json_encode($entrada, JSON_UNESCAPED_UNICODE));
    fclose($pipes[0]);
    $saida = stream_get_contents($pipes[1]);
    $erros = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $codigo = proc_close($processo);

    $resposta = json_decode((string) $saida, true);
    if (!is_array($resposta)) {
        error_log("DeepCheck: resposta inválida de scripts/{$script} (código {$codigo}): " . trim($erros ?: (string) $saida));
        return ['ok' => false, 'erro' => 'Falha interna ao gerar o documento.'];
    }
    if (empty($resposta['ok'])) {
        error_log("DeepCheck: scripts/{$script} falhou" . (isset($resposta['etapa']) ? " na etapa {$resposta['etapa']}" : '') . ': ' . ($resposta['detalhe'] ?? ''));
        return ['ok' => false, 'erro' => $resposta['erro'] ?? 'Falha ao gerar o documento.'];
    }
    return $resposta;
}

// PDF gerado por um script que responde {"ok": true, "pdf_base64": ...}.
// Retorna ['ok' => true, 'pdf' => bytes] ou ['ok' => false, 'erro' => ...].
function python_gerar_pdf(string $script, array $entrada): array
{
    $resposta = python_executar($script, $entrada);
    return $resposta['ok'] ? ['ok' => true, 'pdf' => base64_decode($resposta['pdf_base64'])] : $resposta;
}
