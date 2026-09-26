<?php
// Não conformidades formalizadas (tabelas nao_conformidades e nc_emails_enviados).
// Uma NC é criada quando a "Solicitação de Resolução de Não Conformidade" é enviada
// por e-mail a partir de um item do checklist marcado como não conformidade.
require_once __DIR__ . '/checklist.php';

const NC_RESPONSAVEL_QA_MAX = 255;
const NC_OBSERVACOES_MAX = 5000;
const NC_CC_MAX = 10;

// Valida os dados do formulário de envio. Retorna ['erro' => ...] ou os dados normalizados.
function nc_ler_envio(array $post): array
{
    $texto = fn($chave) => is_string($post[$chave] ?? null) ? trim($post[$chave]) : '';

    $email = mb_strtolower($texto('email_responsavel'));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        return ['erro' => 'Informe um e-mail válido para o responsável pela resolução.'];
    }

    $responsavelQa = $texto('responsavel_qa');
    if ($responsavelQa === '' || mb_strlen($responsavelQa) > NC_RESPONSAVEL_QA_MAX) {
        return ['erro' => 'Informe o responsável por QA (até ' . NC_RESPONSAVEL_QA_MAX . ' caracteres).'];
    }

    $observacoes = $texto('observacoes');
    if (mb_strlen($observacoes) > NC_OBSERVACOES_MAX) {
        return ['erro' => 'As observações podem ter no máximo ' . NC_OBSERVACOES_MAX . ' caracteres.'];
    }

    $cc = [];
    foreach (preg_split('/[,;\s]+/', mb_strtolower($texto('cc')), -1, PREG_SPLIT_NO_EMPTY) as $endereco) {
        if (!filter_var($endereco, FILTER_VALIDATE_EMAIL) || strlen($endereco) > 255) {
            return ['erro' => "E-mail inválido em \"Com cópia para\": {$endereco}"];
        }
        $cc[$endereco] = $endereco;
    }
    if (count($cc) > NC_CC_MAX) {
        return ['erro' => 'Informe no máximo ' . NC_CC_MAX . ' e-mails em "Com cópia para".'];
    }

    return [
        'email_responsavel' => $email,
        'responsavel_qa'    => $responsavelQa,
        'observacoes'       => $observacoes === '' ? null : $observacoes,
        'cc'                => array_values($cc),
    ];
}

// "03/09/2026 – 17:00" (ou só a data, para prazo em dias), como no template.
function nc_formatar_data(string $data): string
{
    return str_replace(' ', ' – ', checklist_formatar_prazo($data));
}

function nc_nome_anexo(int $numeroItem): string
{
    return "Solicitacao_Resolucao_Nao_Conformidade - Item {$numeroItem}.pdf";
}

// Assunto e corpo do e-mail, no padrão dos e-mails de exemplo do template.
function nc_texto_email(string $projeto, string $responsavel, string $responsavelQa): array
{
    return [
        'assunto' => "Solicitação de Resolução de Não Conformidade - {$projeto}",
        'corpo'   => "Olá, {$responsavel},\n\n"
            . "Segue em anexo a Solicitação de Resolução de Não Conformidade referente ao item identificado durante a revisão de QA do projeto.\n\n"
            . "O documento detalha a não conformidade encontrada, sua classificação e a ação corretiva indicada, além do prazo estabelecido para resolução.\n\n"
            . "Pedimos que a correção seja realizada dentro do prazo indicado no documento. Qualquer dúvida sobre o apontamento, estamos à disposição.\n\n"
            . "Atenciosamente,\n"
            . "Equipe de QA - {$responsavelQa}\n",
    ];
}

function nc_criar(mysqli $conexao, array $nc): int
{
    $stmt = $conexao->prepare(
        "INSERT INTO nao_conformidades
            (projeto_id, checklist_item_id, descricao, classificacao_nc_id, acao_corretiva_indicada,
             responsavel_resolucao, responsavel_email, responsavel_qa, data_primeira_solicitacao,
             prazo_resolucao, status, observacoes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "iisissssssss",
        $nc['projeto_id'], $nc['checklist_item_id'], $nc['descricao'], $nc['classificacao_nc_id'],
        $nc['acao_corretiva_indicada'], $nc['responsavel_resolucao'], $nc['responsavel_email'],
        $nc['responsavel_qa'], $nc['data_primeira_solicitacao'], $nc['prazo_resolucao'],
        $nc['status'], $nc['observacoes']
    );
    $stmt->execute();
    $id = $conexao->insert_id;
    $stmt->close();
    return $id;
}

function nc_registrar_email(mysqli $conexao, array $registro): void
{
    $stmt = $conexao->prepare(
        "INSERT INTO nc_emails_enviados
            (nao_conformidade_id, enviado_por, destinatario, cc, responder_para, assunto,
             corpo_snapshot, anexo_nome, anexo_pdf, status_envio, erro_envio)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $pdf = null; // enviado em partes por send_long_data (BLOB)
    $stmt->bind_param(
        "iissssssbss",
        $registro['nao_conformidade_id'], $registro['enviado_por'], $registro['destinatario'],
        $registro['cc'], $registro['responder_para'], $registro['assunto'], $registro['corpo'],
        $registro['anexo_nome'], $pdf, $registro['status_envio'], $registro['erro_envio']
    );
    $stmt->send_long_data(8, $registro['anexo_pdf']);
    $stmt->execute();
    $stmt->close();
}
