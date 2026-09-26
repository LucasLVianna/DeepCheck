<?php
// Ponte PHP → Python para o envio de e-mails (decisão: envio síncrono, na própria
// requisição). Executa scripts/enviar_email.py com proc_open — sem passar pelo shell —
// e troca dados por JSON no stdin/stdout, para a senha SMTP não aparecer na linha de comando.
require_once __DIR__ . '/env.php';

// Configuração SMTP da conta do sistema, lida do .env (ver .env.example).
function email_config_smtp(): array
{
    return [
        'host'      => env('SMTP_HOST', ''),
        'porta'     => env('SMTP_PORT', '587'),
        'seguranca' => env('SMTP_SEGURANCA', 'starttls'),
        'usuario'   => env('SMTP_USUARIO', ''),
        'senha'     => env('SMTP_SENHA', ''),
    ];
}

// Executa o script. $entrada: ['email' => ..., 'documento' => ..., 'anexo_nome' => ..., 'apenas_pdf' => bool].
// Retorna ['ok' => true, 'pdf' => bytes do PDF] ou ['ok' => false, 'erro' => mensagem para o usuário].
function email_executar_script(array $entrada): array
{
    $entrada['smtp'] = email_config_smtp();
    $processo = proc_open(
        ['python3', __DIR__ . '/../scripts/enviar_email.py'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($processo)) {
        error_log('DeepCheck: não foi possível executar scripts/enviar_email.py');
        return ['ok' => false, 'erro' => 'Não foi possível acionar o envio de e-mail.'];
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
        error_log("DeepCheck: resposta inválida de enviar_email.py (código {$codigo}): " . trim($erros ?: (string) $saida));
        return ['ok' => false, 'erro' => 'Falha interna ao gerar ou enviar o e-mail.'];
    }
    if (empty($resposta['ok'])) {
        error_log("DeepCheck: enviar_email.py falhou na etapa {$resposta['etapa']}: {$resposta['detalhe']}");
        return ['ok' => false, 'erro' => $resposta['erro']];
    }
    return ['ok' => true, 'pdf' => base64_decode($resposta['pdf_base64'])];
}
