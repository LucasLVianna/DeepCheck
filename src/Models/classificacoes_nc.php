<?php
// Acesso a dados das classificações de NC de cada projeto (tabela classificacoes_nc).
// A edição das classificações é a Fase 8; por enquanto só leitura.

// Classificações do projeto, do menor prazo para o maior.
function classificacoes_do_projeto(mysqli $conexao, int $projetoId): array
{
    $stmt = $conexao->prepare(
        "SELECT id, nome, prazo_valor, prazo_unidade
           FROM classificacoes_nc
          WHERE projeto_id = ?
          ORDER BY prazo_valor * IF(prazo_unidade = 'dias', 24, 1), nome"
    );
    $stmt->bind_param("i", $projetoId);
    $stmt->execute();
    $classificacoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $classificacoes;
}

// "24 horas", "1 dia", "3 dias"...
function classificacao_prazo_texto(array $classificacao): string
{
    $valor = (int) $classificacao['prazo_valor'];
    $unidade = $classificacao['prazo_unidade'] === 'dias' ? 'dia' : 'hora';
    return $valor . ' ' . $unidade . ($valor === 1 ? '' : 's');
}
