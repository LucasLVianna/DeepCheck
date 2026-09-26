# DeepCheck

Sistema web de **auditoria de qualidade de projetos**: Plano de Garantia da Qualidade (PGQ),
checklist com cálculo de aderência em tempo real, não conformidades (NCs) enviadas por e-mail
com escalonamento e exportação dos documentos em PDF.

## Funcionalidades

- **Contas**: cadastro, login com proteção contra força bruta, "esqueci a senha" por e-mail
  (link válido por 1 hora) e página de perfil (nome, CEP, e-mail e senha).
- **Projetos** (dashboard "Meus projetos"): cada projeto tem **código + senha** próprios; qualquer
  usuário com essas credenciais entra no projeto. O dono renomeia, exclui, troca a senha do projeto,
  remove membros e transfere a posse; membros podem sair. O card mostra NCs abertas e vencidas.
- **Aba PGQ**: formulário no formato do template (capa com logo, comprometimento, seções 1 a 7,
  tabelas dinâmicas), classificações de NC editáveis (ex.: Alta 24 h, Média 3 dias) com histórico,
  aviso de edição simultânea e **exportação em PDF**.
- **Aba Checklist**: itens com salvamento automático, aderência calculada na hora
  (`NTA = NT − NA − não aplicáveis`, `Aderência = NC / NTA × 100`), marcação de NC com data de
  identificação e prazo em **dias úteis** (sem fins de semana e feriados nacionais), importação de
  itens (colar lista ou copiar de outro projeto) e **exportação em PDF**.
- **Não conformidades**: envio da "Solicitação de Resolução de Não Conformidade" em **PDF por e-mail**
  (com pré-visualização), escalonamento ao superior com cópia para os envolvidos, reenvio, histórico
  de escalonamentos e e-mails e download dos PDFs enviados.

## Tecnologias

PHP 8.2 + Apache, MySQL 8.0, HTML/CSS/JavaScript puro e Python 3 (reportlab + smtplib) para gerar
PDFs e enviar e-mails. Tudo roda em Docker.

## Como rodar

Pré-requisitos: Docker e Docker Compose.

1. Copie o arquivo de variáveis de ambiente e ajuste as senhas:

   ```bash
   cp .env.example .env
   ```

2. Suba os containers:

   ```bash
   docker compose up -d --build
   ```

3. Crie o banco (veja [Banco de dados](#banco-de-dados)) e preencha no `.env`:
   `DB_NAME=deepcheck`, `DB_USERNAME=deepcheck_app` e `DB_PASSWORD` (a senha usada no script).
   Mantenha `DB_HOST=db`.

4. Acesse **http://localhost:8080** (porta configurável em `APP_PORT`), crie uma conta e um projeto.

Serviços:

| Serviço | Endereço |
|---|---|
| Aplicação | http://localhost:8080 |
| MySQL (Workbench) | `127.0.0.1:3307` — usuário `root`, senha `DB_ROOT_PASSWORD` do `.env` |
| Mailpit (e-mails de teste) | http://localhost:8025 |

Comandos úteis:

```bash
docker compose down           # para os containers
docker compose down -v        # para e APAGA os dados do banco
docker compose up -d --build  # após mudar Dockerfile ou arquivos em docker/
docker compose logs app       # log do PHP/Apache (erros detalhados ficam aqui)
```

> A senha do root (`DB_ROOT_PASSWORD`) só vale na **primeira** criação do volume do banco.
> Para trocá-la depois, use `ALTER USER` no Workbench e atualize o `.env`.

## E-mail

O sistema envia e-mails por **uma conta do sistema** (SMTP configurado no `.env`). Cada e-mail sai
como "Nome do usuário via DeepCheck", com resposta e cópia para o usuário.

- **Desenvolvimento**: deixe os valores do Mailpit do `.env.example` (`SMTP_HOST=mailpit`). Nada é
  entregue de verdade; os e-mails aparecem em http://localhost:8025.
- **Envio real (Gmail)**: crie a conta, ative a verificação em duas etapas, gere uma
  [senha de app](https://myaccount.google.com/apppasswords) e use `SMTP_HOST=smtp.gmail.com`,
  `SMTP_PORT=587`, `SMTP_SEGURANCA=starttls`, `SMTP_USUARIO`/`EMAIL_REMETENTE` = a conta e
  `SMTP_SENHA` = a senha de app. O passo a passo está comentado no `.env.example`.
- `APP_URL` precisa ser o endereço pelo qual os usuários acessam o sistema (é usado no link de
  "esqueci a senha").

## Como usar

1. **Crie uma conta** e entre. No dashboard, crie um projeto (o código de acesso é gerado) ou entre
   num projeto existente com código + senha.
2. **PGQ**: preencha o plano e salve; ajuste as classificações de NC na seção 6; envie o logo na
   capa; use **Exportar PDF** no cabeçalho do projeto.
3. **Checklist**: adicione ou importe itens e marque o resultado de cada um. Em "Não conformidade",
   escolha a classificação (o prazo é calculado) e o responsável; clique em **Enviar NC**, informe o
   e-mail do responsável e confira com **Pré-visualizar PDF** antes de enviar.
4. **Não conformidades**: acompanhe o status, baixe os PDFs enviados, **Escalone** ao superior
   (o novo prazo é sugerido) ou **Reenvie** ao responsável.
5. **Membros** (cabeçalho do projeto): veja quem participa; o dono gerencia membros, senha e posse.

## Banco de dados

O schema é criado manualmente (não há migrations). Conecte o MySQL Workbench como root em
`127.0.0.1:3307`, cole o script abaixo, troque `<SENHA_DA_APLICACAO>` e rode **o script inteiro**
(Ctrl+Shift+Enter — o Ctrl+Enter roda só o comando sob o cursor).

<details>
<summary>Script completo de criação do banco (15 tabelas + usuário da aplicação)</summary>

```sql
-- DeepCheck — criação completa do banco (MySQL 8.0)
-- Rode inteiro no MySQL Workbench conectado como root (Ctrl+Shift+Enter).
-- Troque <SENHA_DA_APLICACAO> antes de rodar e use a mesma senha em DB_PASSWORD no .env.

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS deepcheck CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE deepcheck;

CREATE TABLE usuario (
  id int unsigned NOT NULL AUTO_INCREMENT,
  nome_usuario varchar(100) NOT NULL,
  email_usuario varchar(255) NOT NULL,
  senha varchar(255) NOT NULL,
  cep char(8) NOT NULL,
  conta_ativa tinyint(1) NOT NULL DEFAULT '1',
  criado_em datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY email_usuario (email_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE senha_redefinicoes (
  id int unsigned NOT NULL AUTO_INCREMENT,
  usuario_id int unsigned NOT NULL,
  token_hash char(64) NOT NULL,
  expira_em datetime NOT NULL,
  usado_em datetime DEFAULT NULL,
  criado_em datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_senha_redefinicoes_token (token_hash),
  KEY idx_senha_redefinicoes_usuario (usuario_id,criado_em),
  CONSTRAINT fk_senha_redefinicoes_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projetos (
  id int unsigned NOT NULL AUTO_INCREMENT,
  nome varchar(150) NOT NULL,
  projeto_codigo_acesso varchar(32) NOT NULL,
  projeto_senha_hash varchar(255) NOT NULL,
  criado_por int unsigned NOT NULL,
  criado_em datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_projetos_codigo_acesso (projeto_codigo_acesso),
  KEY idx_projetos_criado_por (criado_por),
  CONSTRAINT fk_projetos_criado_por FOREIGN KEY (criado_por) REFERENCES usuario (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projeto_membros (
  id int unsigned NOT NULL AUTO_INCREMENT,
  projeto_id int unsigned NOT NULL,
  usuario_id int unsigned NOT NULL,
  acesso_em datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_projeto_membros_projeto_usuario (projeto_id,usuario_id),
  KEY idx_projeto_membros_usuario_acesso (usuario_id,acesso_em),
  CONSTRAINT fk_projeto_membros_projeto FOREIGN KEY (projeto_id) REFERENCES projetos (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_projeto_membros_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE classificacoes_nc (
  id int unsigned NOT NULL AUTO_INCREMENT,
  projeto_id int unsigned NOT NULL,
  nome varchar(50) NOT NULL,
  prazo_valor smallint unsigned NOT NULL,
  prazo_unidade enum('horas','dias') NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_classificacoes_nc_projeto_nome (projeto_id,nome),
  CONSTRAINT fk_classificacoes_nc_projeto FOREIGN KEY (projeto_id) REFERENCES projetos (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_classificacoes_nc_prazo_positivo CHECK (prazo_valor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE classificacoes_nc_historico (
  id int unsigned NOT NULL AUTO_INCREMENT,
  projeto_id int unsigned NOT NULL,
  usuario_id int unsigned NOT NULL,
  acao enum('criada','alterada','excluida') NOT NULL,
  descricao varchar(500) NOT NULL,
  criado_em datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_classificacoes_nc_historico_projeto (projeto_id,criado_em),
  KEY idx_classificacoes_nc_historico_usuario (usuario_id),
  CONSTRAINT fk_classificacoes_nc_historico_projeto FOREIGN KEY (projeto_id) REFERENCES projetos (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_classificacoes_nc_historico_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pgq (
  id int unsigned NOT NULL AUTO_INCREMENT,
  projeto_id int unsigned NOT NULL,
  autor_gqa varchar(150) DEFAULT NULL,
  versao_documento varchar(30) DEFAULT NULL,
  cidade varchar(100) DEFAULT NULL,
  data_documento date DEFAULT NULL,
  logo mediumblob,
  logo_tipo varchar(20) DEFAULT NULL,
  responsavel_projeto varchar(150) DEFAULT NULL,
  data_comprometimento_responsavel date DEFAULT NULL,
  rq_nome varchar(150) DEFAULT NULL,
  data_comprometimento_rq date DEFAULT NULL,
  objetivo text,
  visao_geral text,
  registros_qualidade_local varchar(500) DEFAULT NULL,
  definicao_nc_texto text,
  processo_escalonamento_texto text,
  criado_em datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pgq_projeto (projeto_id),
  CONSTRAINT fk_pgq_projeto FOREIGN KEY (projeto_id) REFERENCES projetos (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pgq_documentos (
  id int unsigned NOT NULL AUTO_INCREMENT,
  pgq_id int unsigned NOT NULL,
  documento varchar(255) NOT NULL,
  versao varchar(30) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_pgq_documentos_pgq (pgq_id),
  CONSTRAINT fk_pgq_documentos_pgq FOREIGN KEY (pgq_id) REFERENCES pgq (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pgq_itens_avaliados (
  id int unsigned NOT NULL AUTO_INCREMENT,
  pgq_id int unsigned NOT NULL,
  documento varchar(255) NOT NULL,
  local_armazenamento varchar(500) DEFAULT NULL,
  versao varchar(30) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_pgq_itens_avaliados_pgq (pgq_id),
  CONSTRAINT fk_pgq_itens_avaliados_pgq FOREIGN KEY (pgq_id) REFERENCES pgq (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pgq_plano_avaliacoes (
  id int unsigned NOT NULL AUTO_INCREMENT,
  pgq_id int unsigned NOT NULL,
  artefato_avaliado varchar(255) NOT NULL,
  data_avaliacao date DEFAULT NULL,
  auditor varchar(150) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_pgq_plano_avaliacoes_pgq (pgq_id),
  CONSTRAINT fk_pgq_plano_avaliacoes_pgq FOREIGN KEY (pgq_id) REFERENCES pgq (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE checklists (
  id int unsigned NOT NULL AUTO_INCREMENT,
  projeto_id int unsigned NOT NULL,
  nome varchar(150) NOT NULL,
  ultimo_numero_item smallint unsigned NOT NULL DEFAULT '0',
  criado_em datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_checklists_projeto (projeto_id),
  CONSTRAINT fk_checklists_projeto FOREIGN KEY (projeto_id) REFERENCES projetos (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE checklist_itens (
  id int unsigned NOT NULL AUTO_INCREMENT,
  checklist_id int unsigned NOT NULL,
  numero_item smallint unsigned NOT NULL,
  descricao varchar(1000) NOT NULL,
  resultado enum('conforme','nao_conformidade','nao_se_aplica') DEFAULT NULL,
  data_identificacao_nc datetime DEFAULT NULL,
  responsavel_resolucao varchar(150) DEFAULT NULL,
  classificacao_nc_id int unsigned DEFAULT NULL,
  acao_corretiva_indicada varchar(1000) DEFAULT NULL,
  data_prevista_resolucao datetime DEFAULT NULL,
  data_escalonamento datetime DEFAULT NULL,
  data_conclusao_nc datetime DEFAULT NULL,
  status_nc enum('pendente','resolvida','nao_resolvida','escalonada','fechada_por_excecao') DEFAULT NULL,
  criado_em datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_checklist_itens_checklist_numero (checklist_id,numero_item),
  KEY idx_checklist_itens_classificacao (classificacao_nc_id),
  CONSTRAINT fk_checklist_itens_checklist FOREIGN KEY (checklist_id) REFERENCES checklists (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_checklist_itens_classificacao FOREIGN KEY (classificacao_nc_id) REFERENCES classificacoes_nc (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_checklist_itens_nc_consistente CHECK ((IFNULL(resultado, '') = 'nao_conformidade' OR (data_identificacao_nc IS NULL AND status_nc IS NULL)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE nao_conformidades (
  id int unsigned NOT NULL AUTO_INCREMENT,
  projeto_id int unsigned NOT NULL,
  checklist_item_id int unsigned NOT NULL,
  descricao varchar(1000) NOT NULL,
  classificacao_nc_id int unsigned NOT NULL,
  acao_corretiva_indicada varchar(1000) DEFAULT NULL,
  responsavel_resolucao varchar(150) NOT NULL,
  responsavel_email varchar(255) NOT NULL,
  responsavel_qa varchar(255) NOT NULL,
  data_primeira_solicitacao datetime NOT NULL,
  prazo_resolucao datetime NOT NULL,
  numero_escalonamento smallint unsigned NOT NULL DEFAULT '0',
  status enum('pendente','resolvida','nao_resolvida','escalonada','fechada_por_excecao') NOT NULL DEFAULT 'pendente',
  observacoes text,
  criado_em datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_nao_conformidades_checklist_item (checklist_item_id),
  KEY idx_nao_conformidades_projeto_status (projeto_id,status),
  KEY idx_nao_conformidades_classificacao (classificacao_nc_id),
  CONSTRAINT fk_nao_conformidades_checklist_item FOREIGN KEY (checklist_item_id) REFERENCES checklist_itens (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_nao_conformidades_classificacao FOREIGN KEY (classificacao_nc_id) REFERENCES classificacoes_nc (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_nao_conformidades_projeto FOREIGN KEY (projeto_id) REFERENCES projetos (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE nc_escalonamentos (
  id int unsigned NOT NULL AUTO_INCREMENT,
  nao_conformidade_id int unsigned NOT NULL,
  numero_escalonamento smallint unsigned NOT NULL,
  superior_nome varchar(150) NOT NULL,
  superior_email varchar(255) NOT NULL,
  responsavel_resolucao varchar(150) NOT NULL,
  novo_prazo_resolucao datetime NOT NULL,
  observacoes text,
  escalonado_por int unsigned NOT NULL,
  enviado_em datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_nc_escalonamentos_nc_numero (nao_conformidade_id,numero_escalonamento),
  KEY idx_nc_escalonamentos_escalonado_por (escalonado_por),
  CONSTRAINT fk_nc_escalonamentos_nc FOREIGN KEY (nao_conformidade_id) REFERENCES nao_conformidades (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_nc_escalonamentos_usuario FOREIGN KEY (escalonado_por) REFERENCES usuario (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_nc_escalonamentos_numero_positivo CHECK (numero_escalonamento > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE nc_emails_enviados (
  id int unsigned NOT NULL AUTO_INCREMENT,
  nao_conformidade_id int unsigned NOT NULL,
  escalonamento_id int unsigned DEFAULT NULL,
  enviado_por int unsigned NOT NULL,
  destinatario varchar(255) NOT NULL,
  cc text,
  responder_para varchar(255) NOT NULL,
  assunto varchar(255) NOT NULL,
  corpo_snapshot text NOT NULL,
  anexo_nome varchar(255) DEFAULT NULL,
  anexo_pdf mediumblob,
  status_envio enum('sucesso','falha') NOT NULL,
  erro_envio varchar(500) DEFAULT NULL,
  enviado_em datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_nc_emails_enviados_nc (nao_conformidade_id,enviado_em),
  KEY idx_nc_emails_enviados_enviado_por (enviado_por),
  KEY idx_nc_emails_enviados_escalonamento (escalonamento_id),
  CONSTRAINT fk_nc_emails_enviados_escalonamento FOREIGN KEY (escalonamento_id) REFERENCES nc_escalonamentos (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_nc_emails_enviados_nc FOREIGN KEY (nao_conformidade_id) REFERENCES nao_conformidades (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_nc_emails_enviados_usuario FOREIGN KEY (enviado_por) REFERENCES usuario (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuário da aplicação (o PHP conecta com ele; DB_USERNAME / DB_PASSWORD no .env)
CREATE USER IF NOT EXISTS 'deepcheck_app'@'%' IDENTIFIED BY '<SENHA_DA_APLICACAO>';
GRANT SELECT, INSERT, UPDATE, DELETE ON deepcheck.* TO 'deepcheck_app'@'%';
FLUSH PRIVILEGES;
```

</details>

Tabelas: `usuario`, `senha_redefinicoes`, `projetos`, `projeto_membros`, `classificacoes_nc`,
`classificacoes_nc_historico`, `pgq` (+ `pgq_documentos`, `pgq_itens_avaliados`,
`pgq_plano_avaliacoes`), `checklists`, `checklist_itens`, `nao_conformidades`,
`nc_escalonamentos` e `nc_emails_enviados`. As regras de negócio, decisões e o histórico do projeto
estão documentados no `CLAUDE.md`.
