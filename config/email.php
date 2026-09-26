<?php
// Envio de e-mails das NCs: configuração SMTP (.env) e chamada de scripts/enviar_email.py
// pela ponte PHP → Python (config/python.php).
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/python.php';

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

// Gera o PDF e envia o e-mail (scripts/enviar_email.py). $entrada: ['email' => ...,
// 'documento' => ..., 'anexo_nome' => ..., 'anexos_extras' => ..., 'apenas_pdf' => bool].
// Retorna ['ok' => true, 'pdf' => bytes do PDF] ou ['ok' => false, 'erro' => mensagem para o usuário].
function email_executar_script(array $entrada): array
{
    $entrada['smtp'] = email_config_smtp();
    return python_gerar_pdf('enviar_email.py', $entrada);
}

// E-mail só com texto (sem PDF), em nome do sistema — ex.: link de redefinição de senha.
// Retorna ['ok' => true] ou ['ok' => false, 'erro' => mensagem].
function email_enviar_texto(string $para, string $paraNome, string $assunto, string $corpo): array
{
    return python_executar('enviar_email.py', [
        'smtp'  => email_config_smtp(),
        'email' => [
            'de_nome'   => env('EMAIL_REMETENTE_NOME', 'DeepCheck'),
            'de_email'  => env('EMAIL_REMETENTE', ''),
            'para'      => $para,
            'para_nome' => $paraNome,
            'assunto'   => $assunto,
            'corpo'     => $corpo,
        ],
    ]);
}
