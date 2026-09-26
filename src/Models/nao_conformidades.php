<?php
// Não conformidades formalizadas (tabelas nao_conformidades, nc_escalonamentos e
// nc_emails_enviados). Uma NC é criada quando a "Solicitação de Resolução de Não
// Conformidade" é enviada por e-mail a partir de um item do checklist; depois pode ser
// escalonada ao superior (novo ciclo, com novo prazo) ou reenviada ao responsável.
require_once __DIR__ . '/checklist.php';

const NC_RESPONSAVEL_QA_MAX = 255;
const NC_OBSERVACOES_MAX = 5000;
const NC_CC_MAX = 10;
const NC_NOME_MAX = 150;

// Só NCs abertas podem ser escalonadas ou reenviadas.
const NC_STATUS_ABERTOS = ['pendente', 'nao_resolvida', 'escalonada'];

// Colunas de uma NC com os dados do item, do projeto e da classificação. Usar com NC_FROM.
const NC_COLUNAS = 'n.id, n.projeto_id, n.checklist_item_id, n.descricao, n.classificacao_nc_id,
    n.acao_corretiva_indicada, n.responsavel_resolucao, n.responsavel_email, n.responsavel_qa,
    n.data_primeira_solicitacao, n.prazo_resolucao, n.numero_escalonamento, n.status, n.observacoes,
    i.numero_item, i.data_prevista_resolucao AS prazo_atual, i.data_escalonamento, i.data_conclusao_nc,
    i.resultado, i.status_nc, p.nome AS projeto_nome,
    c.nome AS classificacao_nome, c.prazo_valor, c.prazo_unidade';
const NC_FROM = 'nao_conformidades n
    JOIN checklist_itens i ON i.id = n.checklist_item_id
    JOIN projetos p ON p.id = n.projeto_id
    JOIN classificacoes_nc c ON c.id = n.classificacao_nc_id';

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

function nc_nome_anexo(int $numeroItem, int $numeroEscalonamento = 0): string
{
    $sufixo = $numeroEscalonamento > 0 ? " - Escalonamento {$numeroEscalonamento}" : '';
    return "Solicitacao_Resolucao_Nao_Conformidade - Item {$numeroItem}{$sufixo}.pdf";
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

function nc_texto_escalonamento(string $projeto, string $superior, string $responsavelQa, int $numero): array
{
    return [
        'assunto' => "Escalonamento Nº {$numero} - Solicitação de Resolução de Não Conformidade - {$projeto}",
        'corpo'   => "Olá, {$superior},\n\n"
            . "Segue em anexo a Solicitação de Resolução de Não Conformidade escalonada, que foi enviada anteriormente (segue também em anexo o documento do primeiro envio) e não foi resolvida dentro do prazo requisitado.\n\n"
            . "O documento detalha a não conformidade encontrada, sua classificação e a ação corretiva indicada, além do histórico de escalonamento e do novo prazo estabelecido para resolução.\n\n"
            . "Pedimos que a correção seja realizada dentro do prazo indicado no documento. Qualquer dúvida sobre o apontamento, estamos à disposição.\n\n"
            . "Atenciosamente,\n"
            . "Equipe de QA - {$responsavelQa}\n",
    ];
}

function nc_texto_reenvio(string $projeto, string $responsavel, string $responsavelQa): array
{
    return [
        'assunto' => "Reenvio - Solicitação de Resolução de Não Conformidade - {$projeto}",
        'corpo'   => "Olá, {$responsavel},\n\n"
            . "Reenviamos em anexo a Solicitação de Resolução de Não Conformidade referente ao item identificado durante a revisão de QA do projeto.\n\n"
            . "O documento detalha a não conformidade encontrada, sua classificação e a ação corretiva indicada, além do prazo estabelecido para resolução.\n\n"
            . "Pedimos que a correção seja realizada dentro do prazo indicado no documento. Qualquer dúvida sobre o apontamento, estamos à disposição.\n\n"
            . "Atenciosamente,\n"
            . "Equipe de QA - {$responsavelQa}\n",
    ];
}

// Nome e e-mail do usuário que envia (remetente "<nome> via DeepCheck", Reply-To e CC).
function nc_usuario_remetente(mysqli $conexao, int $usuarioId): array
{
    $stmt = $conexao->prepare("SELECT nome_usuario, email_usuario FROM usuario WHERE id = ?");
    $stmt->bind_param("i", $usuarioId);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $usuario;
}

// Entrada de scripts/enviar_email.py (ver config/email.php).
function nc_entrada_script(array $usuario, string $para, string $paraNome, array $cc, array $texto,
                           array $documento, string $anexoNome, array $anexosExtras = []): array
{
    return [
        'anexo_nome'    => $anexoNome,
        'anexos_extras' => $anexosExtras,
        'email'         => [
            'de_nome'        => $usuario['nome_usuario'] . ' via ' . env('EMAIL_REMETENTE_NOME', 'DeepCheck'),
            'de_email'       => env('EMAIL_REMETENTE', ''),
            'para'           => $para,
            'para_nome'      => $paraNome,
            'cc'             => $cc,
            'responder_para' => $usuario['email_usuario'],
            'assunto'        => $texto['assunto'],
            'corpo'          => $texto['corpo'],
        ],
        'documento'     => $documento,
    ];
}

// Lista de e-mails para CC: minúsculos, sem repetição e sem o destinatário principal.
function nc_lista_cc(array $enderecos, string $para): array
{
    $lista = [];
    foreach ($enderecos as $endereco) {
        $endereco = mb_strtolower(trim($endereco));
        if ($endereco !== '' && $endereco !== mb_strtolower($para)) {
            $lista[$endereco] = $endereco;
        }
    }
    return array_values($lista);
}

// Documento "Solicitação de Resolução de Não Conformidade" de uma NC já enviada, com o
// histórico de escalonamento (linhas de nc_escalonamentos) e as observações informadas.
function nc_documento(array $nc, array $historico, ?string $observacoes): array
{
    return [
        'projeto'                   => $nc['projeto_nome'],
        'responsavel_resolucao'     => $nc['responsavel_resolucao'],
        'responsavel_qa'            => $nc['responsavel_qa'],
        'data_primeira_solicitacao' => nc_formatar_data($nc['data_primeira_solicitacao']),
        'prazo_resolucao'           => nc_formatar_data($nc['prazo_resolucao']),
        'numero_escalonamento'      => (int) $nc['numero_escalonamento'],
        'descricao'                 => $nc['descricao'],
        'classificacao'             => nc_classificacao_texto($nc),
        'acao_corretiva'            => $nc['acao_corretiva_indicada'],
        'historico'                 => array_map(fn($e) => [
            'superior'    => $e['superior_nome'],
            'responsavel' => $e['responsavel_resolucao'],
            'prazo'       => nc_formatar_data($e['novo_prazo_resolucao']),
        ], $historico),
        'observacoes'               => $observacoes,
    ];
}

function nc_classificacao_texto(array $nc): string
{
    return $nc['classificacao_nome'] . ' | ' . classificacao_prazo_texto($nc);
}

// Novo prazo sugerido para o próximo escalonamento: a mesma duração da classificação,
// contada a partir do dia útil seguinte ao prazo vigente (original ou do último
// escalonamento). Ex.: prazo vigente dia 4, 3 dias úteis → dias 5, 6, 7 → dia 7.
function nc_prazo_escalonamento(array $nc): string
{
    return checklist_calcular_prevista($nc['prazo_atual'], $nc);
}

// Valida o formulário de escalonamento. O novo prazo chega como 'aaaa-mm-dd' (prazo em
// dias: vale até 23:59:59) ou 'aaaa-mm-ddThh:mm' (prazo em horas).
// Retorna ['erro' => ...] ou os dados normalizados.
function nc_ler_escalonamento(array $post, string $unidade, string $agora): array
{
    $texto = fn($chave) => is_string($post[$chave] ?? null) ? trim($post[$chave]) : '';

    $superiorNome = $texto('superior_nome');
    if ($superiorNome === '' || mb_strlen($superiorNome) > NC_NOME_MAX) {
        return ['erro' => 'Informe o nome do superior (até ' . NC_NOME_MAX . ' caracteres).'];
    }
    $superiorEmail = mb_strtolower($texto('superior_email'));
    if (!filter_var($superiorEmail, FILTER_VALIDATE_EMAIL) || strlen($superiorEmail) > 255) {
        return ['erro' => 'Informe um e-mail válido para o superior.'];
    }
    $responsavel = $texto('responsavel_resolucao');
    if ($responsavel === '' || mb_strlen($responsavel) > NC_NOME_MAX) {
        return ['erro' => 'Informe o responsável pela resolução (até ' . NC_NOME_MAX . ' caracteres).'];
    }
    $observacoes = $texto('observacoes');
    if (mb_strlen($observacoes) > NC_OBSERVACOES_MAX) {
        return ['erro' => 'As observações podem ter no máximo ' . NC_OBSERVACOES_MAX . ' caracteres.'];
    }

    $prazo = $texto('novo_prazo');
    if ($unidade === 'dias') {
        $novoPrazo = nc_data_estrita($prazo, 'Y-m-d');
        $novoPrazo = $novoPrazo === null ? null : $novoPrazo . ' 23:59:59';
    } else {
        $data = DateTime::createFromFormat('!Y-m-d\TH:i', $prazo);
        $novoPrazo = $data && $data->format('Y-m-d\TH:i') === $prazo ? $data->format('Y-m-d H:i:s') : null;
    }
    if ($novoPrazo === null) {
        return ['erro' => 'Informe um novo prazo válido.'];
    }
    if ($novoPrazo <= $agora) {
        return ['erro' => 'O novo prazo precisa ser posterior ao momento do escalonamento.'];
    }

    return [
        'superior_nome'         => $superiorNome,
        'superior_email'        => $superiorEmail,
        'responsavel_resolucao' => $responsavel,
        'novo_prazo'            => $novoPrazo,
        'observacoes'           => $observacoes === '' ? null : $observacoes,
    ];
}

// 'aaaa-mm-dd' válida (estrita) ou null.
function nc_data_estrita(string $valor, string $formato): ?string
{
    $data = DateTime::createFromFormat('!' . $formato, $valor);
    return $data && $data->format($formato) === $valor ? $data->format('Y-m-d') : null;
}

// A NC com item, projeto e classificação, se o usuário for membro do projeto; null caso contrário.
function nc_do_membro(mysqli $conexao, int $ncId, int $usuarioId): ?array
{
    $stmt = $conexao->prepare(
        "SELECT " . NC_COLUNAS . " FROM " . NC_FROM . "
           JOIN projeto_membros m ON m.projeto_id = n.projeto_id AND m.usuario_id = ?
          WHERE n.id = ?"
    );
    $stmt->bind_param("ii", $usuarioId, $ncId);
    $stmt->execute();
    $nc = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $nc ?: null;
}

// NCs do projeto em ordem de item, cada uma com 'escalonamentos' e 'emails' (sem o PDF).
function nc_listar_do_projeto(mysqli $conexao, int $projetoId): array
{
    $stmt = $conexao->prepare("SELECT " . NC_COLUNAS . " FROM " . NC_FROM . " WHERE n.projeto_id = ? ORDER BY i.numero_item");
    $stmt->bind_param("i", $projetoId);
    $stmt->execute();
    $ncs = [];
    foreach ($stmt->get_result() as $nc) {
        $ncs[$nc['id']] = $nc + ['escalonamentos' => [], 'emails' => []];
    }
    $stmt->close();
    if (!$ncs) {
        return [];
    }

    $stmt = $conexao->prepare(
        "SELECT e.*, u.nome_usuario AS escalonado_por_nome
           FROM nc_escalonamentos e
           JOIN nao_conformidades n ON n.id = e.nao_conformidade_id
           JOIN usuario u ON u.id = e.escalonado_por
          WHERE n.projeto_id = ?
          ORDER BY e.numero_escalonamento"
    );
    $stmt->bind_param("i", $projetoId);
    $stmt->execute();
    foreach ($stmt->get_result() as $escalonamento) {
        $ncs[$escalonamento['nao_conformidade_id']]['escalonamentos'][] = $escalonamento;
    }
    $stmt->close();

    $stmt = $conexao->prepare(
        "SELECT e.id, e.nao_conformidade_id, e.escalonamento_id, e.destinatario, e.cc, e.assunto,
                e.anexo_nome, e.status_envio, e.enviado_em, u.nome_usuario AS enviado_por_nome,
                es.numero_escalonamento
           FROM nc_emails_enviados e
           JOIN nao_conformidades n ON n.id = e.nao_conformidade_id
           JOIN usuario u ON u.id = e.enviado_por
           LEFT JOIN nc_escalonamentos es ON es.id = e.escalonamento_id
          WHERE n.projeto_id = ?
          ORDER BY e.id"
    );
    $stmt->bind_param("i", $projetoId);
    $stmt->execute();
    foreach ($stmt->get_result() as $email) {
        $ncs[$email['nao_conformidade_id']]['emails'][] = $email;
    }
    $stmt->close();

    return array_values($ncs);
}

function nc_historico(mysqli $conexao, int $ncId): array
{
    $stmt = $conexao->prepare("SELECT * FROM nc_escalonamentos WHERE nao_conformidade_id = ? ORDER BY numero_escalonamento");
    $stmt->bind_param("i", $ncId);
    $stmt->execute();
    $historico = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $historico;
}

// Todos os endereços que já receberam e-mails da NC (destinatários e cópias).
function nc_envolvidos(mysqli $conexao, int $ncId): array
{
    $stmt = $conexao->prepare("SELECT destinatario, cc FROM nc_emails_enviados WHERE nao_conformidade_id = ? AND status_envio = 'sucesso'");
    $stmt->bind_param("i", $ncId);
    $stmt->execute();
    $enderecos = [];
    foreach ($stmt->get_result() as $email) {
        $enderecos[] = $email['destinatario'];
        foreach (explode(',', (string) $email['cc']) as $cc) {
            $enderecos[] = $cc;
        }
    }
    $stmt->close();
    return $enderecos;
}

// 1º envio da NC (PDF original e cópias), anexado ao escalonamento e usado no reenvio.
function nc_primeiro_email(mysqli $conexao, int $ncId): ?array
{
    $stmt = $conexao->prepare(
        "SELECT anexo_nome, anexo_pdf, cc FROM nc_emails_enviados
          WHERE nao_conformidade_id = ? AND escalonamento_id IS NULL AND status_envio = 'sucesso'
          ORDER BY id LIMIT 1"
    );
    $stmt->bind_param("i", $ncId);
    $stmt->execute();
    $email = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $email ?: null;
}

// PDF de um e-mail enviado, se o usuário for membro do projeto da NC.
function nc_email_pdf_do_membro(mysqli $conexao, int $emailId, int $usuarioId): ?array
{
    $stmt = $conexao->prepare(
        "SELECT e.anexo_nome, e.anexo_pdf
           FROM nc_emails_enviados e
           JOIN nao_conformidades n ON n.id = e.nao_conformidade_id
           JOIN projeto_membros m ON m.projeto_id = n.projeto_id AND m.usuario_id = ?
          WHERE e.id = ? AND e.anexo_pdf IS NOT NULL"
    );
    $stmt->bind_param("ii", $usuarioId, $emailId);
    $stmt->execute();
    $email = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $email ?: null;
}

// Registra o novo ciclo: linha em nc_escalonamentos, nº de escalonamento +1 e status
// 'escalonada' na NC, e no item: status, data do escalonamento e prazo vigente = novo prazo.
// Deve rodar dentro da transação do envio. Retorna o id do escalonamento.
function nc_registrar_escalonamento(mysqli $conexao, array $nc, array $escalonamento, int $usuarioId, string $agora): int
{
    $numero = (int) $nc['numero_escalonamento'] + 1;
    $stmt = $conexao->prepare(
        "INSERT INTO nc_escalonamentos
            (nao_conformidade_id, numero_escalonamento, superior_nome, superior_email,
             responsavel_resolucao, novo_prazo_resolucao, observacoes, escalonado_por, enviado_em)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "iisssssis",
        $nc['id'], $numero, $escalonamento['superior_nome'], $escalonamento['superior_email'],
        $escalonamento['responsavel_resolucao'], $escalonamento['novo_prazo'],
        $escalonamento['observacoes'], $usuarioId, $agora
    );
    $stmt->execute();
    $escalonamentoId = $conexao->insert_id;
    $stmt->close();

    $stmt = $conexao->prepare("UPDATE nao_conformidades SET numero_escalonamento = ?, status = 'escalonada' WHERE id = ?");
    $stmt->bind_param("ii", $numero, $nc['id']);
    $stmt->execute();
    $stmt->close();

    $stmt = $conexao->prepare(
        "UPDATE checklist_itens
            SET status_nc = 'escalonada', data_escalonamento = ?, data_prevista_resolucao = ?, data_conclusao_nc = NULL
          WHERE id = ?"
    );
    $stmt->bind_param("ssi", $agora, $escalonamento['novo_prazo'], $nc['checklist_item_id']);
    $stmt->execute();
    $stmt->close();

    return $escalonamentoId;
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
            (nao_conformidade_id, escalonamento_id, enviado_por, destinatario, cc, responder_para,
             assunto, corpo_snapshot, anexo_nome, anexo_pdf, status_envio, erro_envio)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $pdf = null; // enviado em partes por send_long_data (BLOB)
    $escalonamentoId = $registro['escalonamento_id'] ?? null;
    $stmt->bind_param(
        "iiissssssbss",
        $registro['nao_conformidade_id'], $escalonamentoId, $registro['enviado_por'], $registro['destinatario'],
        $registro['cc'], $registro['responder_para'], $registro['assunto'], $registro['corpo'],
        $registro['anexo_nome'], $pdf, $registro['status_envio'], $registro['erro_envio']
    );
    $stmt->send_long_data(9, $registro['anexo_pdf']);
    $stmt->execute();
    $stmt->close();
}
