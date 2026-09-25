# CLAUDE.md — Sistema de Auditoria de Qualidade

Este documento é o contexto persistente do projeto. Leia-o antes de qualquer alteração e mantenha-o atualizado conforme decisões de arquitetura forem tomadas.

## Stack Tecnológica

- **Backend**: PHP (procedural ou orientado a objetos — seguir o padrão já existente no projeto)
- **Frontend**: HTML, CSS, JavaScript (vanilla, salvo indicação em contrário)
- **Banco de dados**: MySQL (ajustar se o projeto já usa outro)
- **Envio de e-mail**: script Python separado, chamado pelo PHP (via `exec()`/`shell_exec()` ou fila de tarefas — decidir na Fase 5)
- **Segurança**: o projeto já possui headers de segurança configurados e uma arquitetura inicial de login/logoff — **não recriar do zero**, e sim auditar e completar (ver seção "Estado Atual e Pendências").

---

## Estado Atual e Pendências (verificar antes de codar)

Antes de implementar qualquer feature nova, o Claude Code deve:
1. Inspecionar a estrutura de pastas existente (`ls`/`view` na raiz do projeto).
2. Ler os arquivos de configuração de segurança (headers) já presentes e confirmar que cobrem: `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options`, `Strict-Transport-Security` (se HTTPS), proteção CSRF em formulários.
3. Ler a lógica de login/logoff existente e verificar:
   - Senhas armazenadas com hash seguro (`password_hash`/`password_verify`, nunca MD5/SHA1 puro).
   - Uso de sessões PHP (`session_start()`, regeneração de ID de sessão no login — `session_regenerate_id(true)`).
   - Proteção contra força bruta (rate limiting básico ou captcha, se ainda não houver).
   - Logout limpando a sessão corretamente (`session_unset()` + `session_destroy()`).
4. Ler o componente de navbar pré-criado e reutilizá-lo em todas as telas novas — não recriar navbar por página.

Qualquer lacuna encontrada nesses pontos deve ser reportada e corrigida antes de avançar nas features de negócio.

### Fase 1 — concluída e validada (2026-09-25)

#### Problemas encontrados na auditoria e como foram corrigidos

| Problema | Correção |
|---|---|
| `.env`, `.git`, `config/`, `CLAUDE.md`, `docker-compose.yml` etc. eram baixáveis pela web (`GET /.env` → 200), pois a raiz do projeto é o DocumentRoot | `docker/apache/zz-deepcheck.conf` bloqueia esses caminhos (→ 403) e desliga a listagem de diretórios |
| `config/conexao.php` usava `parse_ini_file`, que quebrava com os comentários `#` do `.env` → conexão nunca funcionava | Novo `config/env.php` com função `env()`; `conexao.php` reescrito (charset `utf8mb4`, try/catch) |
| Erros de conexão e de SQL (e warnings/stack traces via `display_errors`) vazavam para o cliente | Mensagens genéricas ao cliente + `error_log`; `display_errors=Off` e `expose_php=Off` em `docker/php/deepcheck.ini` |
| Headers só eram enviados pelos controllers (páginas sem nenhum); faltavam CSP, Referrer-Policy, HSTS; `X-XSS-Protection: 1` obsoleto | `config/headers.php` completo, incluído por todas as páginas via `config/auth.php`; HSTS só sob HTTPS; `X-XSS-Protection: 0` |
| Nenhuma proteção CSRF | Token por sessão (`csrf_token()` / `csrf_valido()` / `exigir_post_com_csrf()`), enviado por meta tag + header `X-CSRF-Token` ou campo hidden |
| Cookie de sessão sem `HttpOnly`/`SameSite`; `use_strict_mode` desligado | `iniciar_sessao()` em `config/auth.php` (cookie `DEEPCHECKSESSID`) + ini do PHP |
| Sem `session_regenerate_id(true)` no login (session fixation) | `autenticar_usuario()` regenera o ID e descarta a sessão anônima |
| Sem proteção contra força bruta | `config/rate_limit.php` (5 falhas IP+e-mail / 20 falhas IP em 15 min → HTTP 429) |
| Login revelava por tempo de resposta se o e-mail existia | `password_verify` contra `HASH_FICTICIO` quando o e-mail não existe |
| `logoff.php` com erro fatal (`session_status` sem `()`), redirect para `/DeepCheck/index.html`, logoff via GET | Reescrito: só POST + CSRF, `encerrar_sessao()` (`session_unset` + apaga cookie + `session_destroy`) |
| 3 lógicas de sessão inconsistentes (`valida_sessao.php`, `verifica_login_realizado.php`, `check_session.php`), timeout não aplicado nas páginas, leitura de `$_SESSION['usuario']['tipo']` inexistente | Unificadas em `config/auth.php` (`estado_sessao()`, `exigir_login()`, `redirecionar_se_logado()`); os 3 arquivos foram removidos |
| Redirects para `home.php` e `perfil.php`, que não existem | Tudo aponta para `/src/Views/menu.php`; botão "Perfil" removido (recriar quando a página existir) |
| Navbar não era usada, usava caminhos `/DeepCheck/` (XAMPP), `navbar.css` inexistente, lia `nome_usuario` em vez de `nome`, sobras de outro projeto (`estoquesButtonLink`) | Navbar reescrita com links + form POST de logoff, `public/css/navbar.css` criado, incluída no `menu.php`; `public/js/navbar.js` e `public/js/menu.js` (vazio) removidos |
| Validação de senha forte só no JS; e-mail sem normalização | Backend valida a mesma regra (8–72 chars, maiúscula, minúscula, número, `@$!%*?&`); e-mail salvo/buscado em minúsculas |
| JS seguia adiante com senha vazia (faltava `return`); `login.js` consultava `check_session.php` | Corrigido; o redirecionamento de quem já está logado é feito no servidor |
| Estilos inline (`style=""`) nas views, incompatíveis com a CSP | Movidos para `public/css/login.css` e `cadastro.css` (classe `.obrigatorio`, `#significadoAspas`) |
| README citava `db/init.sql` inexistente | README corrigido (schema manual via Workbench, rebuild após mudar `docker/`) |
| `Server:` expunha a versão do Apache (o `security.conf` do Debian sobrescrevia `ServerTokens`) | Arquivo com prefixo `zz-` para carregar por último → `Server: Apache` |

Também foi feito: `password_needs_rehash` no login (atualiza o hash se o padrão do PHP mudar) e códigos HTTP corretos nas respostas JSON (400/401/403/405/409/429/500).

#### Arquivos

- **Criados**: `config/env.php`, `config/auth.php`, `config/rate_limit.php`, `docker/apache/zz-deepcheck.conf`, `docker/php/deepcheck.ini`, `public/css/navbar.css`.
- **Reescritos/alterados**: `Dockerfile`, `README.md`, `config/conexao.php`, `config/headers.php`, `src/Controllers/{login_backend,cadastrar_backend,logoff}.php`, `src/Views/{login,cadastro,menu,navbar}.php`, `public/js/{login,cadastrar}.js`, `public/css/{login,cadastro}.css`.
- **Removidos**: `config/check_session.php`, `config/valida_sessao.php`, `config/verifica_login_realizado.php`, `public/js/navbar.js`, `public/js/menu.js`.

#### Ambiente (Docker + MySQL)

- App em `http://localhost:8080`; MySQL exposto no host em `127.0.0.1:3307` (Workbench: usar `127.0.0.1`, não `localhost`). Dentro do Docker o PHP usa `DB_HOST=db`, porta 3306.
- `DB_ROOT_PASSWORD` só vale na **primeira** inicialização do volume `deepcheck_deepcheck_db_data`. Para trocar senha depois: `ALTER USER` no Workbench + atualizar o `.env` (ou `docker compose down -v`, que apaga todos os dados).
- Banco criado no Workbench: **`deepcheck`** (minúsculo — no Linux o nome do banco é case-sensitive; `DB_NAME` no `.env` deve ser exatamente `deepcheck`). Usuário da aplicação: `deepcheck_app`@`%` com `SELECT, INSERT, UPDATE, DELETE ON deepcheck.*`.
- Rate limit fica em `/tmp/deepcheck_rate_limit` do container (zera ao recriar o container). Para liberar manualmente: `docker exec -u root deepcheck_app rm -rf /tmp/deepcheck_rate_limit`.

#### Testes realizados (HTTP direto contra o container, com o banco real)

Todos passaram, sem erros/warnings no log do PHP: cadastro (sem CSRF → 403, senha fraca → 400, válido → 201, duplicado → 409, hash bcrypt e e-mail minúsculo gravados); login (senha errada e e-mail inexistente → mesma mensagem 401 e mesmo tempo de resposta, válido → 200, ID de sessão regenerado, ID antigo barrado); área logada (menu 200, nome escapado na navbar, `login.php` logado → menu); logoff (GET não desloga, POST com token desloga e apaga cookie, ID antigo barrado); timeout de 30 min (→ `login.php?motivo=expirado`, sessão destruída); conta desativada (→ 403 só com senha certa); rate limit (6ª tentativa → 429, senha certa bloqueada durante o bloqueio, outro e-mail do mesmo IP não afetado); bloqueio de arquivos sensíveis (→ 403). O usuário de teste foi apagado ao final.

**Não testado ainda**: fluxo pelo navegador real (JS + CSP) — fazer um cadastro/login manual em `http://localhost:8080/src/Views/cadastro.php`.

#### Pendências conhecidas (fora do escopo da Fase 1)

- Página de perfil (`perfil.php`) e "Esqueceu a senha? Redefinir" (link `#` no login) não existem.
- `index.html` é estático e não recebe os headers de segurança do PHP (não tem formulários nem dados).
- Healthcheck do MySQL usa `mysqladmin ping`, que dá "healthy" mesmo com senha root errada.
- Tabela `usuario` diverge do rascunho `usuarios` do Modelo de Dados — confirmar com o desenvolvedor se `projetos.criado_por` e `projeto_membros.usuario_id` devem referenciar `usuario.id`.

#### Convenções estabelecidas (seguir nas próximas fases)

- **Bootstrap obrigatório**: toda página e todo controller começa com `require_once __DIR__ . '/../../config/auth.php';`. Ele envia os headers de segurança (`config/headers.php`) e inicia a sessão com cookie `HttpOnly`/`SameSite=Lax`/`Secure` (quando HTTPS).
  - Páginas protegidas: `exigir_login();` — páginas de login/cadastro: `redirecionar_se_logado();`.
  - Endpoints JSON que alteram dados: `exigir_post_com_csrf();` (+ `exigir_login_api();` se exigir usuário logado) e responder com `responder_json($dados, $statusHttp)`.
- **CSRF**: páginas expõem `<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">`; o JS envia o header `X-CSRF-Token` em todo `fetch` POST. Forms HTML usam `<input type="hidden" name="csrf_token">`.
- **Sessão**: `$_SESSION['usuario'] = ['id', 'nome']`, `$_SESSION['logado']`, timeout de 30 min por inatividade (`SESSION_TIMEOUT`). Login usa `autenticar_usuario()` (regenera o ID); logoff é só via POST + CSRF e usa `encerrar_sessao()`.
- **CSP** é `'self'` estrita: **proibido** `<script>` inline, `onclick=""` e `style=""` nas views — usar arquivos em `public/js` e `public/css`.
- **Rate limit** de login em `config/rate_limit.php` (arquivos em `sys_get_temp_dir()`, sem tabela): 5 falhas por IP+e-mail e 20 por IP em 15 min.
- **Configuração**: variáveis lidas com `env('NOME')` (`config/env.php`), nunca `parse_ini_file`. Erros de banco vão para `error_log` (logs do container), nunca para a resposta.
- **Apache/PHP** (`docker/apache/zz-deepcheck.conf`, `docker/php/deepcheck.ini`): bloqueia `.env`, dotfiles, `config/`, `scripts/`, `docker/`, `storage/`, `*.md`, `*.yml`, `*.py`, `*.sql`; sem listagem de diretórios; `display_errors=Off`. Alterou esses arquivos → `docker compose up -d --build`.
- **Caminhos**: sempre absolutos a partir da raiz (`/src/...`, `/public/...`), sem o prefixo `/DeepCheck/` do XAMPP.
- **Navbar**: `src/Views/navbar.php`, incluída com `<?php include __DIR__ . '/navbar.php'; ?>` logo após `<body>`.
- **Tabela de usuários real** (criada no Workbench): `usuario (id, nome_usuario, email_usuario, senha, cep, conta_ativa, criado_em, atualizado_em)` — diverge do rascunho `usuarios` abaixo; o código segue a tabela `usuario`.

---

## Visão Geral do Produto

Ferramenta web para gestão de auditorias de qualidade em projetos, com controle de checklist, cálculo automático de aderência, geração de não-conformidades (NCs) e fluxo de escalonamento com envio de e-mail.

### Conceito central de acesso
Cada **projeto de auditoria** tem seu próprio **ID + senha** (credencial do projeto, distinta da conta do usuário). Qualquer usuário autenticado no sistema que possua essas credenciais pode entrar naquele projeto especificamente. Ou seja, é um controle de acesso em dois níveis:
- Nível 1: login do usuário no sistema (conta pessoal).
- Nível 2: acesso a um projeto específico via credencial do projeto.

---

## Gestão do Banco de Dados

O schema do banco é criado e mantido manualmente pelo desenvolvedor via **MySQL Workbench**. O Claude Code NÃO deve:
- Gerar migrations automáticas.
- Executar `CREATE TABLE` / `ALTER TABLE` via script ou ferramenta.
- Assumir que pode alterar a estrutura do banco sozinho.

O Claude Code DEVE:
- Trabalhar com o schema já existente no banco (a seção "Modelo de Dados" abaixo é a referência do que deve existir, mas o schema real criado no Workbench é a fonte da verdade).
- Se identificar que falta uma coluna, tabela ou relação necessária para alguma feature, **avisar e sugerir o SQL correspondente**, mas deixar o desenvolvedor aplicar manualmente no Workbench antes de prosseguir com o código PHP que depende dela.
- Perguntar antes de assumir nomes de colunas/tabelas caso o schema real divirja do proposto neste documento.
- Ao gerar código de acesso a dados (queries PHP), sempre confirmar com o desenvolvedor se os nomes de tabelas/colunas batem com o que já foi criado, em vez de presumir.

## Modelo de Dados (rascunho de schema)

```sql
-- Usuários do sistema — JÁ CRIADA no Workbench com este nome/colunas (fonte da verdade):
usuario (
  id INT UNSIGNED AUTO_INCREMENT PK,
  nome_usuario VARCHAR(100), email_usuario VARCHAR(255) UNIQUE,
  senha VARCHAR(255),              -- hash (password_hash)
  cep CHAR(8), conta_ativa TINYINT(1) DEFAULT 1,
  criado_em, atualizado_em
)

-- Projetos de auditoria
projetos (
  id, nome, projeto_codigo_acesso, projeto_senha_hash,
  criado_por (FK usuarios.id), criado_em, atualizado_em
)

-- Relação de quem já acessou/está vinculado a um projeto (opcional, para exibir "meus projetos")
projeto_membros (
  id, projeto_id (FK), usuario_id (FK), acesso_em
)

-- Plano de Garantia da Qualidade (1 por projeto)
pgq (
  id, projeto_id (FK),
  responsavel_projeto, rq_nome, data_comprometimento,
  objetivo, visao_geral,
  registros_qualidade_local,
  definicao_nc_texto,               -- descrição livre das regras
  processo_escalonamento_texto,     -- descrição livre
  atualizado_em
)

pgq_documentos (          -- tabela "Documentação, Padrões e Diretrizes"
  id, pgq_id (FK), documento, versao
)

pgq_itens_avaliados (     -- tabela "Itens a Serem Avaliados"
  id, pgq_id (FK), documento, local_armazenamento, versao
)

pgq_plano_avaliacoes (    -- tabela "Plano de Avaliações"
  id, pgq_id (FK), artefato_avaliado, data_avaliacao, auditor
)

-- Classificações de prioridade/prazo configuráveis por projeto
classificacoes_nc (
  id, projeto_id (FK), nome (ex: "Simples", "Média", "Alta"),
  prazo_valor (int), prazo_unidade (enum: 'horas','dias')
)

-- Checklist de Qualidade
checklists (
  id, projeto_id (FK), nome, criado_em
)

checklist_itens (
  id, checklist_id (FK),
  numero_item, descricao,
  resultado (enum: 'sim','nao','nao_aplica','nao_avaliado'),
  responsavel_resolucao,
  classificacao_nc_id (FK classificacoes_nc.id, nullable),
  acao_corretiva_indicada,
  data_identificacao_nc (datetime, nullable),
  data_prevista_resolucao (datetime, nullable),
  data_conclusao_nc (datetime, nullable),
  status_nc (enum: 'aberta','em_resolucao','concluida','escalonada', nullable)
)

-- Não Conformidades (gerada a partir de um checklist_item marcado como "não")
nao_conformidades (
  id, checklist_item_id (FK), projeto_id (FK),
  descricao, classificacao_nc_id (FK),
  responsavel_resolucao, responsavel_qa,
  data_primeira_solicitacao, prazo_resolucao,
  numero_escalonamento (int, default 0),
  status (enum: 'aberta','em_resolucao','concluida','escalonada'),
  observacoes,
  criado_em, atualizado_em
)

-- Histórico de escalonamento (1:N com nao_conformidades)
nc_escalonamentos (
  id, nao_conformidade_id (FK),
  numero_escalonamento, superior_responsavel,
  novo_prazo_resolucao, enviado_em,
  destinatario_email
)

-- Log de e-mails enviados (auditoria/rastreabilidade)
nc_emails_enviados (
  id, nao_conformidade_id (FK), escalonamento_id (FK, nullable),
  destinatario, cc (texto, lista de emails), assunto, corpo_snapshot,
  enviado_em, status_envio (enum: 'sucesso','falha')
)
```

Ajustar tipos/nomes ao padrão já usado no restante do banco existente.

---

## Regras de Negócio

### 1. Cálculo de Aderência (Checklist)
Recalcular a cada alteração de item, em tempo real (via JS + confirmação no backend):

```
NT   = total de itens do checklist
NA   = itens marcados como "não avaliado"
NTA  = NT - NA
NNC  = itens marcados como "não" (não conformidades)
NC   = NTA - NNC
% Aderência = (NC / NTA) * 100
```
Itens "não se aplica" contam como avaliados, mas não entram no denominador de conformidade (seguir o mesmo tratamento do checklist de referência: NT - NA = NTA, e NNC é contado sobre os "não").

### 2. Classificação e Prazo de NC
- Definidas por projeto em `classificacoes_nc` (configurável pelo usuário no PGQ, seção 6).
- Ao marcar um item do checklist como "Não":
  1. Sistema grava `data_identificacao_nc` automaticamente (timestamp do servidor).
  2. Usuário escolhe a classificação (ex: Simples).
  3. Sistema calcula `data_prevista_resolucao` = `data_identificacao_nc` + prazo da classificação.

### 3. Fluxo de Envio de NC por E-mail (1º envio, escalonamento 0)
1. Usuário informa e-mail do destinatário (responsável pela resolução).
2. Sistema apresenta o template "Solicitação de Resolução de Não Conformidade" pré-preenchido com:
   - Projeto, Responsável pela Resolução, Responsável por QA, Data da 1ª Solicitação, Prazo de Resolução, Descrição da NC, Classificação, Ação Corretiva Indicada.
   - Campo livre de Observações.
3. Ao confirmar, o sistema:
   - Cria o registro em `nao_conformidades` com `numero_escalonamento = 0`.
   - Dispara o e-mail via script Python.
   - Registra o envio em `nc_emails_enviados`.

### 4. Fluxo de Escalonamento
Quando o prazo de uma NC vence sem resolução (ou o usuário decide escalonar manualmente):
1. Usuário abre a NC na aba "Não Conformidades" e aciona "Escalonar".
2. Mesmo template de comunicação é reaberto, agora com a seção "Histórico de Escalonamento" habilitada:
   - Superior Responsável (novo destinatário/aprovador).
   - Novo Prazo para Resolução.
3. `numero_escalonamento` incrementa (+1) a cada novo ciclo.
4. Novo e-mail é enviado, preferencialmente **em CC para os envolvidos dos ciclos anteriores** (replicar o padrão observado no e-mail de exemplo do Outlook fornecido).
5. Novo registro criado em `nc_escalonamentos`, vinculado à mesma `nao_conformidade`.
6. Status da NC atualizado para `escalonada`.

### 5. Templates
Os dois documentos anexados (Plano de Garantia da Qualidade e Solicitação de Resolução de Não Conformidade) são a referência estrutural exata dos formulários — os campos do banco de dados acima foram extraídos diretamente deles. Não reinventar campos: usar exatamente as seções e colunas desses templates.

---

## Estrutura de Abas por Projeto

Ao entrar em um projeto (via card no dashboard), exibir 3 abas fixas + navbar padrão:

1. **Plano de Garantia da Qualidade** — formulário editável baseado no template.
2. **Checklist de Qualidade** — tabela editável com cálculo de aderência ao vivo e ação de "marcar como não conformidade".
3. **Não Conformidades** — lista de todas as NCs do projeto, com status, histórico de escalonamento e botão de ação para reenviar/escalonar.

---

## Sugestão de Estrutura de Arquivos

```
/projeto-auditoria
  /includes
    navbar.php              (componente já existente — reutilizar)
    auth.php                (login/logoff — auditar e completar)
    security-headers.php    (já existente — auditar)
    db.php                  (conexão)
  /pages
    login.php
    dashboard.php           (cards de projetos)
    projeto.php?id=X&aba=pgq|checklist|nc
  /api                      (endpoints AJAX para checklist dinâmico, cálculo de aderência, etc.)
    checklist_atualizar_item.php
    nc_criar.php
    nc_escalonar.php
    projeto_crud.php
  /scripts
    enviar_email.py         (recebe dados via argumento/stdin ou lê de uma fila/tabela)
  /assets
    css/
    js/
      checklist.js           (cálculo de aderência em tempo real)
      nc.js
  CLAUDE.md                 (este arquivo)
```

Ajustar aos nomes/convenções já usados no projeto existente.

---

## Integração PHP → Python (envio de e-mail)

Opções a avaliar na Fase 5 (escolher uma e documentar aqui a decisão):
- **Síncrona simples**: PHP grava os dados necessários em JSON temporário e chama `shell_exec("python3 scripts/enviar_email.py caminho.json")`.
- **Fila assíncrona**: PHP insere o e-mail pendente numa tabela `nc_emails_enviados` com status `pendente`, e um cron job Python processa a fila periodicamente (mais robusto, evita travar a requisição HTTP).

Recomenda-se a fila assíncrona se o volume de e-mails crescer, mas a síncrona é suficiente para o MVP.

---

## Plano de Implementação por Fases

1. **Fase 1 — Auditoria da base existente** ✅ *(concluída em 2026-09-25 — ver "Fase 1 — concluída e validada")*: revisar segurança, login/logoff, navbar. Corrigir pendências antes de seguir.
2. **Fase 2 — Modelo de dados**: desenvolvedor cria/ajusta as tabelas manualmente no MySQL Workbench, seguindo o schema acima como base; Claude Code apenas valida se os nomes usados no código batem com o que foi criado.
3. **Fase 3 — Dashboard de projetos**: CRUD de projetos (criar, editar nome, apagar) + tela de acesso via ID/senha do projeto.
4. **Fase 4 — Aba PGQ**: formulário completo baseado no template, com sub-tabelas dinâmicas (documentos, itens avaliados, plano de avaliações).
5. **Fase 5 — Aba Checklist**: CRUD de itens, cálculo de aderência em tempo real, marcação de NC com timestamp automático.
6. **Fase 6 — Fluxo de e-mail de NC**: formulário de envio, integração com script Python, template de comunicação.
7. **Fase 7 — Aba Não Conformidades**: listagem, status, histórico, ação de escalonamento (reaproveitando o fluxo de e-mail da Fase 6 com campos extras).
8. **Fase 8 — Configuração de classificações/prazos por projeto**: tela para o usuário definir "Simples/Média/Alta" e seus prazos, usada pelas Fases 5 e 7.

Recomenda-se pedir ao Claude Code para executar **uma fase por vez**, revisando o resultado antes de avançar.
