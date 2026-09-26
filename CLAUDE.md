# CLAUDE.md — Sistema de Auditoria de Qualidade

Este documento é o contexto persistente do projeto. Leia-o antes de qualquer alteração e mantenha-o atualizado conforme decisões de arquitetura forem tomadas.

## Stack Tecnológica

- **Backend**: PHP (procedural ou orientado a objetos — seguir o padrão já existente no projeto)
- **Frontend**: HTML, CSS, JavaScript (vanilla, salvo indicação em contrário)
- **Banco de dados**: MySQL (ajustar se o projeto já usa outro)
- **Envio de e-mail**: script Python separado, chamado pelo PHP (via `exec()`/`shell_exec()` ou fila de tarefas — decidir na Fase 5)
- **Segurança**: o projeto já possui headers de segurança configurados e uma arquitetura inicial de login/logoff — **não recriar do zero**, e sim auditar e completar (ver seção "Estado Atual e Pendências").

---

## ▶️ Ponto de retomada (atualizado em 2026-09-25)

**Onde paramos** (atualizado em 2026-09-26): Fases 1, 3, 4, 5 e **6** concluídas e validadas no navegador (Fase 6 com envio real pelo Gmail). Tudo commitado, com merge na `main` e push para o GitHub (commit "Fase 6: envio da NC por e-mail"). **Próximo passo: Fase 7** (aba Não Conformidades + escalonamento): propor o SQL de `nc_escalonamentos` (+ coluna/FK `nc_emails_enviados.escalonamento_id`) e as regras (quem é o superior, CC para os envolvidos dos ciclos anteriores, novo prazo, `numero_escalonamento` +1, status `escalonada`, preencher `checklist_itens.data_escalonamento`, histórico no PDF, tirar `escalonada` da seleção manual no checklist), validar num MySQL descartável e esperar o desenvolvedor criar no Workbench. Fase 2 (modelo de dados) avança junto com cada fase.

**Ao retomar, fazer nesta ordem:**
1. ~~Validar a Fase 5 no navegador~~ ✅ validada em 2026-09-26. ~~`DROP INDEX uk_checklists_projeto_nome`~~ ✅ aplicado.
2. ~~(Opcional) DROP INDEX~~ ✅.
3. Continuar a **Fase 6 — Fluxo de e-mail de NC**, seguindo o mesmo processo das fases anteriores:
   - Ler os templates `~/Downloads/Trabalho Qualidade de Software/Não Conformidades/Solicitacao_Resolucao_Nao_Conformidade - 1..6.pdf` e o e-mail de exemplo em `~/Downloads/Trabalho Qualidade de Software/E-mails/Documentos Auditoria/` e comparar com o rascunho de `nao_conformidades` e `nc_emails_enviados` no "Modelo de Dados".
   - **Decidir com o desenvolvedor** a integração PHP → Python (síncrona via `shell_exec` × fila assíncrona; ver "Integração PHP → Python") e o provedor/credenciais SMTP (nunca commitar credenciais: usar `.env` + `env()`). O container PHP (`php:8.2-apache`) **não tem Python instalado** — vai exigir mudança no `Dockerfile` ou um serviço separado no `docker-compose.yml`.
   - Propor o SQL de `nao_conformidades` e `nc_emails_enviados` (status com `fechada_por_excecao`; FK `classificacao_nc_id` RESTRICT; FK para `projetos` em CASCADE; FK `checklist_item_id` — decidir RESTRICT, que bloqueia excluir item com NC enviada) e validar num MySQL descartável antes de entregar. **Não criar tabelas no banco do projeto**: o desenvolvedor aplica no Workbench e o Claude Code confere com `SHOW CREATE TABLE`.
   - Implementar o bloqueio pendente da Fase 5: item com NC já enviada não pode sair de "Não conformidade" nem ser excluído.
4. Fases seguintes: 7 (aba Não Conformidades + escalonamento, preenche `checklist_itens.data_escalonamento`) e 8 (edição de classificações/prazos, hoje só leitura na seção 6 do PGQ).

**Forma de trabalho combinada com o desenvolvedor** (manter):
- Uma fase por vez. Antes de codar, ler o template da fase, apontar divergências com este documento e pedir decisão; propor SQL e esperar o desenvolvedor criar as tabelas; conferir o schema real.
- Testar tudo via HTTP (curl) com usuários de teste descartáveis (`teste.claude.*@example.com`), apagando-os ao final — **nunca mexer nos dados reais do desenvolvedor** (hoje: 2 usuários e 1 projeto dele no banco).
- Registrar cada fase aqui (implementado, arquivos, regras, testes, pendências, convenções) e atualizar "Pendências em aberto" e "Estrutura real de arquivos".
- Commits: só quando o desenvolvedor pedir; criar branch a partir da `main` (ele decide quando fazer merge/push).

**Como retomar a conversa**: `claude --continue` (ou `claude -c`) reabre a conversa mais recente deste diretório; `claude --resume` (ou `claude -r`) mostra a lista de conversas para escolher. Mesmo numa conversa nova, este arquivo é lido automaticamente e contém todo o contexto necessário.

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

**Validado pelo desenvolvedor no navegador real (2026-09-25)**: cadastro e login com JS/CSP reais.

#### Pendências conhecidas (fora do escopo da Fase 1)

- Página de perfil (`perfil.php`) e "Esqueceu a senha? Redefinir" (link `#` no login) não existem.
- `index.html` é estático e não recebe os headers de segurança do PHP (não tem formulários nem dados).
- Healthcheck do MySQL usa `mysqladmin ping`, que dá "healthy" mesmo com senha root errada.

**Resolvido (2026-09-25)**: nome da tabela confirmado como `usuario` (singular). Toda referência no Modelo de Dados abaixo (`usuarios`, `projetos.criado_por`, `projeto_membros.usuario_id`) deve apontar para `usuario.id`. **Fluxo completo testado manualmente pelo navegador (cadastro + login com JS/CSP reais) e validado.**

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
- **Tabela de usuários real** (criada no Workbench): `usuario (id, nome_usuario, email_usuario, senha, cep, conta_ativa, criado_em, atualizado_em)` — nome já corrigido no Modelo de Dados abaixo; toda FK para usuário deve referenciar `usuario.id`.

### Fase 2 — parcial: tabelas necessárias para a Fase 3 criadas (2026-09-25)

Criadas pelo desenvolvedor no Workbench e **verificadas no banco** (`SHOW CREATE TABLE` bate 100% com o SQL proposto): `projetos`, `projeto_membros`, `classificacoes_nc`. O `deepcheck_app` tem `SELECT, INSERT, UPDATE, DELETE` nelas. As definições exatas estão no "Modelo de Dados" abaixo.

**Padrão de schema a seguir nas próximas tabelas** (mesmo de `usuario`):
- `id INT UNSIGNED NOT NULL AUTO_INCREMENT`; toda FK com o **mesmo tipo** da coluna referenciada (`INT UNSIGNED`).
- `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`.
- `criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `atualizado_em ... ON UPDATE CURRENT_TIMESTAMP`.
- Nomes de constraints explícitos: `uk_<tabela>_<cols>`, `idx_<tabela>_<cols>`, `fk_<tabela>_<ref>`, `chk_<tabela>_<regra>`.

**Regras de `ON DELETE` definidas:**
- `projetos.criado_por → usuario`: **RESTRICT** (usuário que criou projetos não pode ser apagado; "remover" usuário = `conta_ativa = 0`).
- `projeto_membros → projetos` e `→ usuario`: **CASCADE**.
- `classificacoes_nc → projetos`: **CASCADE**.
- `pgq → projetos` e `pgq_documentos` / `pgq_itens_avaliados` / `pgq_plano_avaliacoes → pgq`: **CASCADE** (Fase 4).
- **Pendente para a Fase 5/7**: FKs de `checklist_itens.classificacao_nc_id` e `nao_conformidades.classificacao_nc_id` devem ser **RESTRICT** (não apagar classificação em uso). As demais tabelas filhas de `projetos` (checklists, NCs...) devem cascatear, senão a exclusão de projeto passa a retornar 409.

**Ainda faltam criar** (fases seguintes): ~~`pgq`, `pgq_documentos`, `pgq_itens_avaliados`, `pgq_plano_avaliacoes` (Fase 4)~~ criadas em 2026-09-25; `checklists`, `checklist_itens` (Fase 5); `nao_conformidades`, `nc_escalonamentos`, `nc_emails_enviados` (Fases 6/7). O Claude Code propõe o SQL de cada uma quando a fase chegar; o desenvolvedor aplica no Workbench.

### Decisões para a Fase 3 — Dashboard de Projetos (confirmadas pelo desenvolvedor em 2026-09-25)

1. **Código de acesso gerado pelo sistema** (`projeto_codigo_acesso`), não escolhido pelo usuário. Proposta: 8 caracteres aleatórios (`random_int`) de um alfabeto sem caracteres ambíguos (sem `0/O`, `1/I/L`), maiúsculos; em caso de colisão no UNIQUE, gerar de novo. A busca é case-insensitive (collation `_ci`). A **senha do projeto** é definida pelo criador e gravada só como `password_hash` em `projeto_senha_hash`.
2. **O criador entra automaticamente em `projeto_membros`** ao criar o projeto. "Meus projetos" no dashboard = `projeto_membros` do usuário logado, ordenado por `acesso_em DESC`.
   - `projeto_membros.acesso_em` = **último acesso**: ao entrar num projeto (inclusive por código + senha), fazer `INSERT ... ON DUPLICATE KEY UPDATE acesso_em = NOW()`.
3. **Classificações padrão inseridas automaticamente** em `classificacoes_nc` na criação do projeto (editáveis depois, na Fase 8). Prazos confirmados pelo desenvolvedor: **Alta = 24 horas**, **Média = 3 dias**, **Simples = 5 dias**.
4. Criação do projeto + vínculo do criador + classificações padrão devem ocorrer **numa única transação** (`begin_transaction`/`commit`/`rollback`).
5. A tela de acesso por código + senha do projeto deve usar o rate limit de `config/rate_limit.php` (mesmo padrão do login), para impedir força bruta na senha do projeto.

### Fase 3 — Dashboard de Projetos: concluída, testada via HTTP e validada no navegador (2026-09-25)

#### O que foi implementado

- **Dashboard** (`src/Views/menu.php`): formulários "Novo projeto" (nome, senha, confirmação) e "Entrar em um projeto" (código + senha); lista "Meus projetos" em cards (nome, código, criador — "Você" se for o próprio —, último acesso), ordenada por `acesso_em DESC`. Botões Renomear/Excluir aparecem só para o criador.
- **Página do projeto** (`src/Views/projeto.php?id=X&aba=pgq|checklist|nc`): cabeçalho com nome e código, 3 abas fixas (Plano de Garantia da Qualidade / Checklist de Qualidade / Não Conformidades) com conteúdo provisório "implementada na Fase N". `aba` inválida cai em `pgq`. Cada abertura atualiza `projeto_membros.acesso_em`.
- **Endpoints JSON** (`src/Controllers/`, todos `exigir_login_api()` + `exigir_post_com_csrf()`):
  - `projeto_criar.php` (`nome`, `senha`, `confirmar_senha`) → 201 `{projeto: {id, codigo}}`.
  - `projeto_editar.php` (`id`, `nome`) — renomear; só o criador.
  - `projeto_excluir.php` (`id`) — só o criador; membros e classificações somem via CASCADE; erro 1451 (FK RESTRICT de tabelas futuras) → 409.
  - `projeto_acessar.php` (`codigo`, `senha`) → vincula o usuário em `projeto_membros` e devolve `redirect`. Rate limit: 5 falhas por usuário+código e 20 por usuário em 15 min (→ 429). Usa `HASH_FICTICIO` para não revelar se o código existe.
- **Model** `src/Models/projetos.php`: geração/normalização de código, validações, `projetos_do_usuario()`, `projeto_do_membro()`, `projeto_por_codigo()`, `projeto_registrar_acesso()` (upsert), `projeto_criar()` (transação: projeto + criador em membros + 3 classificações padrão), `projeto_renomear()`, `projeto_excluir()`. Constantes: `PROJETO_CLASSIFICACOES_PADRAO`, `PROJETO_NOME_MAX = 150`, `PROJETO_SENHA_MIN = 8`, `PROJETO_SENHA_MAX = 72`.
- **Front**: `public/js/api.js` (novo helper compartilhado), `public/js/menu.js`, `public/css/menu.css`, `public/css/projeto.css`. Renomear usa `prompt()` e excluir usa `confirm()` (compatíveis com a CSP).

#### Arquivos da Fase 3

- **Criados**: `src/Models/projetos.php`, `src/Controllers/{projeto_criar,projeto_editar,projeto_excluir,projeto_acessar}.php`, `src/Views/projeto.php`, `public/js/api.js`, `public/js/menu.js`, `public/css/menu.css`, `public/css/projeto.css`.
- **Alterados**: `src/Views/menu.php` (virou o dashboard), `config/auth.php` (`HASH_FICTICIO`, `e()`), `config/conexao.php` (`SET time_zone`), `src/Controllers/login_backend.php` (`HASH_FICTICIO` removido daqui), `docker/php/deepcheck.ini` (`date.timezone`).

#### Regras de negócio definidas nesta fase

- **Acesso nível 2 = linha em `projeto_membros`.** Toda tela/endpoint de projeto deve chamar `projeto_do_membro($conexao, $projetoId, $usuarioId)`; `null` → página redireciona para `menu.php` / API responde 404 (não revelar se o projeto existe). Não há flag de sessão para o projeto: depois de entrar uma vez com código + senha, o usuário continua com acesso.
- **Só o criador (`projetos.criado_por`) renomeia e exclui.** Membros apenas acessam. Não membro recebe 404.
- **Código de acesso**: 8 caracteres de `ABCDEFGHJKMNPQRSTUVWXYZ23456789`; a digitação aceita minúsculas, espaços e hífens (`projeto_normalizar_codigo()`). Visível para todos os membros no card e no cabeçalho do projeto.
- **Senha do projeto**: 8–72 caracteres, sem regra de complexidade (é compartilhada pela equipe); não pode ser recuperada nem alterada (ainda não há tela para isso).

#### Mudanças transversais

- `HASH_FICTICIO` foi movido de `login_backend.php` para `config/auth.php` (reutilizado no acesso a projetos).
- Novo helper `e()` em `config/auth.php` para escapar saída HTML — **usar em toda saída dinâmica nas views**.
- **Fuso horário**: `date.timezone = America/Sao_Paulo` em `docker/php/deepcheck.ini` e `config/conexao.php` faz `SET time_zone` com o mesmo offset → `CURRENT_TIMESTAMP`/`NOW()` gravam horário de Brasília (importante para prazos de NC nas Fases 5–7).

#### Testes realizados (HTTP direto, 3 usuários de teste: criador, membro e não membro — todos apagados ao final)

Todos passaram, sem erros no log do PHP: criação (sem CSRF → 403, sem login → 401, nome vazio / >150 / senha curta / confirmação diferente → 400, válida → 201 com código de 8 chars no alfabeto certo; banco com criador em `projeto_membros` e Alta 24h / Média 3d / Simples 5d; horário gravado em Brasília); nome com `<script>` escapado no card, no `data-nome` e no título; acesso (senha errada e código inexistente → mesma mensagem 401, código digitado em minúsculas com hífen → aceito, membro criado, dashboard do membro mostra o criador e não mostra Renomear/Excluir); não membro abrindo `projeto.php` → redirect; abas e `aba` inválida; permissões (membro renomear/excluir → 403, não membro → 404, id inexistente → 404, id inválido → 400, criador renomeia → 200); rate limit (6ª tentativa → 429, senha certa bloqueada, outro membro não afetado); exclusão (CASCADE limpa membros e classificações; ex-membro é redirecionado); GET em endpoint → 405; `/src/Models/projetos.php` pela web → 403; `RESTRICT` impede apagar usuário que criou projeto.

**Validado pelo desenvolvedor no navegador real (2026-09-25)**: formulários, `prompt`/`confirm` e recarregamento após criar/renomear/excluir.

#### Pendências conhecidas da Fase 3

- Não há como alterar a senha do projeto nem transferir a posse (criador) do projeto.
- Membro não consegue "sair" de um projeto (remover o próprio vínculo); o criador não consegue remover membros.
- Os dados das fases seguintes (pgq, checklists, NCs) precisam de FK para `projetos` com **ON DELETE CASCADE** para que a exclusão do projeto continue funcionando; se alguma for RESTRICT, a exclusão retorna 409.

#### Convenções adicionadas (seguir nas próximas fases)

- **Acesso a dados em `src/Models/<entidade>.php`** (funções procedurais que recebem `mysqli $conexao`); controllers só validam entrada, checam permissão e respondem. `src/Models/` é bloqueado pelo Apache.
- **Todo CSS/JS nas views usa `asset('/public/...')`** (`<link href="<?= asset('/public/css/x.css') ?>">`, `<script src="<?= asset('/public/js/x.js') ?>">`) — nunca o caminho fixo, senão o navegador pode continuar com a versão antiga do cache.
- **Não usar `<input type="month">` / `type="week"`** (não existem no Firefox/Safari). `type="date"` pode.
- **Todo `fetch` POST nas telas novas usa `enviarPost(url, dados)` de `public/js/api.js`** (envia CSRF, trata sessão expirada redirecionando ao login, trata erro de rede/JSON). Incluir `<script src="/public/js/api.js">` antes do script da página.
- Códigos HTTP nas APIs: 400 validação, 401 não autenticado/credencial errada, 403 sem permissão/CSRF, 404 não encontrado ou sem acesso, 405 método, 409 conflito, 429 rate limit, 500 erro interno.

### Decisões para a Fase 4 — Aba PGQ (confirmadas pelo desenvolvedor em 2026-09-25)

Template de referência: `~/Imagens/Template_Plano_de_Garantia_da_Qualidade.pdf` (cópia `.doc` em `~/Downloads/Trabalho Qualidade de Software/Template Plano - Plano de Garantia da Qualidade/`). O rascunho original de `pgq` não cobria tudo o que há no template; divergências resolvidas assim:

1. **Capa incluída**: `autor_gqa`, `versao_documento`, `cidade`, `data_documento` (DATE, exibido/editado como mm/aaaa, gravado como dia 1 do mês). **Logo do projeto fica para depois** (exige upload).
2. **Comprometimento com uma data por linha** (`data_comprometimento_responsavel`, `data_comprometimento_rq`) no lugar da única `data_comprometimento` do rascunho. **Assinatura não é armazenada** (é feita no documento impresso).
3. **Qualquer membro do projeto edita o PGQ** (não só o criador). Concorrência: vale o último salvamento.
4. **Seção 6**: as classificações de `classificacoes_nc` aparecem só para leitura + texto livre `definicao_nc_texto`; edição das classificações fica para a Fase 8.
5. **Salvamento**: um único botão "Salvar" grava o PGQ inteiro numa transação (upsert em `pgq` por `projeto_id` + substituição das linhas das 3 sub-tabelas). O registro `pgq` é criado no primeiro salvamento (inclusive para projetos antigos). Ordem das linhas = `id`.

Tabelas criadas pelo desenvolvedor no Workbench e **verificadas no banco** (2026-09-25): `pgq`, `pgq_documentos`, `pgq_itens_avaliados`, `pgq_plano_avaliacoes` — definições exatas no "Modelo de Dados"; cadeia `projetos → pgq → sub-tabelas` toda em CASCADE.

### Fase 4 — Aba PGQ: concluída, testada via HTTP e validada no navegador (2026-09-25)

#### O que foi implementado

- **Aba PGQ** (`src/Views/projeto.php?id=X&aba=pgq` → partial `src/Views/abas/pgq.php`): formulário na ordem do template — Capa (autor GQA, versão, cidade, mês/ano), Comprometimento (tabela com Responsável pelo Projeto e RQ: nome + data), 1.1 Objetivo, 1.2 Visão Geral, seções 2/3/4 como tabelas dinâmicas (adicionar/remover linha), 5. Registros de Qualidade, 6. Definição das NCs (tabela só leitura das classificações do projeto, ordenada por prazo, + texto livre), 7. Processo de escalonamento. Rodapé fixo com "Salvar", mensagem e "Última atualização" (ou "Ainda não salvo").
- **Endpoint** `src/Controllers/pgq_salvar.php` (POST; `exigir_login_api()` + CSRF + `projeto_do_membro()` → qualquer membro salva). Resposta: `{status, mensagem, atualizado_em: "dd/mm/aaaa hh:mm"}`.
- **Model** `src/Models/pgq.php`:
  - Constantes que descrevem o formulário: `PGQ_CAMPOS_TEXTO` (campo → rótulo + máx.), `PGQ_CAMPOS_DATA`, `PGQ_SUBTABELAS` (chave do form → tabela, título da seção, colunas com rótulo/máx./obrigatório/data), `PGQ_TEXTO_LONGO_MAX = 10000`, `PGQ_LINHAS_MAX = 100` por sub-tabela.
  - `pgq_ler_entrada($_POST)` valida/normaliza tudo (vazio → NULL; linhas totalmente vazias descartadas; primeira coluna da linha obrigatória; datas estritas `aaaa-mm-dd`, mês/ano `aaaa-mm` → dia 1; valores que chegam como array viram vazio) e devolve `['erro' => ...]` ou `['campos', 'linhas']`.
  - `pgq_do_projeto()` e `pgq_salvar()` (transação: `INSERT ... AS novo ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(pgq.id), ..., atualizado_em = CURRENT_TIMESTAMP` + `DELETE`/`INSERT` das sub-tabelas). `atualizado_em` é forçado no UPDATE para mudar mesmo quando só as sub-tabelas mudam.
- **Model** `src/Models/classificacoes_nc.php` (novo, será estendido na Fase 8): `classificacoes_do_projeto()` (ordem por prazo em horas) e `classificacao_prazo_texto()` ("24 horas", "1 dia"...).
- **Front**: `public/js/pgq.js` (linhas novas a partir de `<template id="modelo-<chave>">`, remover linha, aviso `beforeunload` com alterações não salvas, salvar via `enviarPost`), `public/css/pgq.css`.
- `projeto.php` agora carrega os dados/CSS/JS **só da aba atual** (`$arquivosAba`); abas ainda não implementadas continuam com o aviso "Fase N".
- `docker/php/deepcheck.ini`: `max_input_vars = 5000` (o padrão 1000 truncaria em silêncio o POST de tabelas grandes; 100 linhas × 3 tabelas ≈ 800 campos).

#### Arquivos da Fase 4

- **Criados**: `src/Models/pgq.php`, `src/Models/classificacoes_nc.php`, `src/Controllers/pgq_salvar.php`, `src/Views/abas/pgq.php`, `public/js/pgq.js`, `public/css/pgq.css`.
- **Alterados**: `src/Views/projeto.php` (carrega dados/CSS/JS da aba atual e inclui o partial), `docker/php/deepcheck.ini` (`max_input_vars = 5000`).

#### Testes realizados (HTTP direto; usuários criador, membro e não membro — dados de teste apagados ao final)

Todos passaram, sem erros/warnings no log do PHP: aba renderiza para projeto sem PGQ ("Ainda não salvo", 1 linha vazia por tabela, 3 templates, classificações Alta 24 horas / Média 3 dias / Simples 5 dias); outras abas não carregam `pgq.js`; partial acessado direto → 404; não membro → redirect (página) e 404 (API); sem CSRF → 403; sem login → 401; `projeto_id` inválido → 400; texto acima do limite, data inválida (`2026-02-30`), mês inválido (`2026-13`), linha sem a coluna obrigatória, data de linha em formato errado e 101 linhas → 400 com mensagem indicando seção/linha, e nada gravado; salvamento válido grava tudo (vazios como NULL, mês/ano como dia 1, quebras de linha preservadas, linha vazia no meio descartada); reexibição com HTML/aspas/`</textarea><script>` escapados; membro (não criador) salva, o `pgq.id` se mantém (upsert) e as linhas das sub-tabelas são substituídas; `atualizado_em` muda mesmo alterando só sub-tabela; **rollback** comprovado (falha no meio da transação não altera nada do que já estava salvo); 100 linhas × 3 tabelas salvas e renderizadas; excluir o projeto apaga `pgq` e as 3 sub-tabelas (CASCADE).

**Validado pelo desenvolvedor no navegador real (2026-09-25)**: preenchimento, adicionar/remover linhas, salvar, recarregar e aviso de alterações não salvas.

#### Pendências conhecidas da Fase 4

- Logo do projeto na capa (exige upload de arquivo).
- Exportar/imprimir o PGQ no formato do documento (PDF/DOC).
- Edição simultânea: vale o último salvamento, sem aviso de conflito.
- Edição das classificações da seção 6 → Fase 8.

#### Convenções adicionadas

- **Abas do projeto como partials em `src/Views/abas/<aba>.php`**, incluídas por `projeto.php`, que carrega os dados antes de fechar a conexão e define `$arquivosAba` (CSS/JS da aba). O partial começa com um guarda (`if (!isset(...)) { http_response_code(404); exit; }`) porque `src/Views/` é acessível pela web.
- **Tabelas dinâmicas**: inputs com nome `<chave>[<coluna>][]` (o PHP recebe arrays paralelos por coluna), linha-modelo em `<template>`, e o formulário inteiro salvo numa transação.

### Decisões para a Fase 5 — Aba Checklist (confirmadas pelo desenvolvedor em 2026-09-25)

Template de referência: `~/Downloads/Trabalho Qualidade de Software/Template Checklist - Processo de Qualidade/Modelo Checklist - Processo de Qualidade 1.2.xlsx` (exemplo preenchido: `~/Downloads/Checklist - Processo de Qualidade - 1.2.pdf`). Colunas: Nº, Descrição, Resultado, Data e hora da identificação da NC, Responsável pela resolução, Classificação da NC, Ação corretiva indicada, Data prevista de resolução, Data e hora do escalonamento, Data e hora da conclusão da NC, Status da NC.

1. **Aderência** segue a planilha ("não se aplica" fora do NTA) + regra de status de NC (resolvida = conformidade; fechada por exceção = não aplicável) — ver "Regras de Negócio › 1. Cálculo de Aderência".
2. **Novo status de NC `fechada_por_excecao`** (ver "Status Padronizados"). Os status do template ("Concluido / Pendente / Fechamento por Exceção") **não** são usados; vale a lista padronizada.
3. **Apenas 1 checklist por projeto** (`UNIQUE (projeto_id)` em `checklists`). Criado sob demanda (como o PGQ).
4. **Coluna `data_escalonamento`** adicionada a `checklist_itens` (existe no template, faltava no rascunho); preenchida na Fase 7.
5. **Salvamento automático por item**: cada alteração é salva na hora e o servidor devolve a aderência recalculada; o JS também recalcula na hora.
6. **Ao marcar `nao_conformidade`**: servidor grava `data_identificacao_nc` e `status_nc = 'pendente'`; ao escolher a classificação, `data_prevista_resolucao = data_identificacao_nc + prazo`.
7. **Ao sair de `nao_conformidade`**: os dados de NC do item são limpos; se voltar a ser NC, ganha nova data de identificação. (A partir da Fase 6: bloquear se a NC já tiver sido enviada por e-mail.)
8. **`numero_item` estável**: novo item = maior número + 1; excluir não renumera (NCs enviadas citam o número).
9. Checklist **nasce vazio**; o usuário adiciona os itens. Qualquer membro do projeto edita.
10. *Confirmado pelo desenvolvedor*: `data_conclusao_nc` é gravada automaticamente quando o status vira `resolvida` **ou** `fechada_por_excecao` (ambos encerram a NC) e é limpa se o status voltar para aberto.
11. Integração PHP → Python do e-mail: decisão adiada para a Fase 6 (quando o envio é implementado).

**Schema**: `checklists` foi criada com `UNIQUE (projeto_id, nome)` e `checklist_itens` não chegou a ser criada; o SQL corrigido (ALTER em `checklists` + CREATE de `checklist_itens` com o novo status) foi validado pelo Claude Code num MySQL descartável (container temporário, não o banco do projeto) e entregue ao desenvolvedor para aplicar no Workbench.

**Verificado no banco (2026-09-25)**: `checklist_itens` criada exatamente como proposto. Em `checklists`, o `UNIQUE KEY uk_checklists_projeto (projeto_id)` **já foi aplicado** (a regra "1 checklist por projeto" está garantida pelo banco). O índice antigo redundante (`uk_checklists_projeto_nome`) foi removido pelo desenvolvedor em 2026-09-26 — `checklists` agora tem só `PRIMARY` e `uk_checklists_projeto (projeto_id)`.

### Fase 5 — Aba Checklist: concluída, testada via HTTP e validada no navegador (2026-09-25/26)

#### O que foi implementado

- **Aba Checklist** (`projeto.php?id=X&aba=checklist` → partial `src/Views/abas/checklist.php`):
  - Painel de indicadores no topo: **Aderência**, NT, NA, NTA, NC, NNC e não aplicáveis, com a fórmula explicada logo abaixo.
  - Tabela com as colunas do template: Nº, Descrição, Resultado (Não avaliado / Conforme / Não conformidade / Não se aplica), Data e hora da identificação da NC, Responsável pela resolução, Classificação da NC (exibida como "Alta | 24 horas"), Ação corretiva indicada, Data prevista de resolução, Data e hora do escalonamento, Data e hora da conclusão da NC, Status da NC, Excluir.
  - Campos de NC ficam desabilitados enquanto o item não for "Não conformidade"; linha de NC com fundo destacado; data prevista em vermelho quando a NC aberta está com prazo vencido.
  - Formulário "Novo item" no fim da tabela. O checklist (`checklists`) é criado no primeiro item, com o nome `Checklist de Qualidade`.
- **Salvamento automático por campo**: cada `change` (select na hora; texto ao sair do campo) envia só aquele campo. O JS recalcula a aderência na hora e a resposta do servidor confirma. Salvamentos da mesma linha vão em fila (ordem garantida); em erro, o campo volta ao último valor salvo e a mensagem aparece acima da tabela. Aviso `beforeunload` se houver salvamento em andamento. Confirmação antes de tirar um item de "Não conformidade" quando ele já tem dados de NC.
- **Endpoints** (`src/Controllers/`, todos com login + CSRF + verificação de membro):
  - `checklist_item_adicionar.php` (`projeto_id`, `descricao`) → 201 com `item_html` (linha pronta, renderizada pela mesma função da página) + `indicadores`; 409 ao atingir o limite.
  - `checklist_item_atualizar.php` (`item_id`, `campo`, `valor`) → `item` (datas já formatadas, flag `atrasado`) + `indicadores`. Campos aceitos: `CHECKLIST_CAMPOS_EDITAVEIS`; datas nunca vêm do cliente.
  - `checklist_item_excluir.php` (`item_id`) → `indicadores`; 409 se o item estiver referenciado (preparado para a Fase 6).
- **Model** `src/Models/checklist.php`:
  - Constantes com os valores padronizados e rótulos (`CHECKLIST_RESULTADOS`, `CHECKLIST_STATUS_NC`, `CHECKLIST_STATUS_NC_ENCERRADOS`), limites (`CHECKLIST_ITENS_MAX = 500`, descrição/ação 1000, responsável 150).
  - **Regras em funções puras**: `checklist_categoria_item()` e `checklist_indicadores()` (fórmula de aderência — espelhadas em `categoriaItem()` no JS) e `checklist_aplicar_alteracao()` (todas as transições de NC). `checklist_item_atrasado()`.
  - `checklist_adicionar_item()` em transação com `SELECT ... FOR UPDATE` no checklist para que adições simultâneas não repitam número.
  - `checklist_item_do_membro()` (autorização por item), `checklist_salvar_item()`, `checklist_excluir_item()`, `checklist_item_para_json()`.
- **View helper** `src/Views/abas/checklist_linha.php` (`checklist_linha_html()`), usado pela aba e pelo endpoint de adicionar.
- **Front**: `public/js/checklist.js`, `public/css/checklist.css`.

#### Arquivos da Fase 5

- **Criados**: `src/Models/checklist.php`, `src/Controllers/{checklist_item_adicionar,checklist_item_atualizar,checklist_item_excluir}.php`, `src/Views/abas/checklist.php`, `src/Views/abas/checklist_linha.php`, `public/js/checklist.js`, `public/css/checklist.css`.
- **Alterados**: `src/Views/projeto.php` (carrega dados/CSS/JS da aba Checklist).

#### Testes realizados (dados de teste apagados ao final)

Todos passaram, sem erros/warnings no log do PHP.
- **Regras (PHP CLI, 20 verificações)**: exemplo da planilha (48 Sim, 6 Não, 1 N/A) → NT 55, NTA 54, NC 48, NNC 6, **88,89%**; resolvida = conformidade, fechada por exceção = não aplicável, pendente/escalonada/não resolvida = NNC; NTA 0 → "—"; marcar NC grava identificação + `pendente` (remarcar não muda a data); Alta 24 h / Média 3 dias calculadas a partir da identificação (trocar a classificação recalcula); limpar classificação limpa a prevista; classificação de outro projeto ou valor não numérico → erro; `resolvida`/`fechada_por_excecao` gravam conclusão (passar de uma para a outra mantém a data original) e voltar a status aberto limpa; sair de NC limpa os 8 campos de NC; campos de NC em item não-NC, status/resultado fora da lista, descrição vazia/longa e campo não editável → erro; atraso só para NC aberta com prazo vencido.
- **HTTP (criador, membro e não membro)**: aba vazia (aderência "—", checklist só é criado no 1º item); adicionar (sem CSRF 403, não membro 404, descrição vazia 400, HTML escapado no `item_html`, membro também adiciona); fluxo de NC completo com horário de Brasília; indicadores corretos em cada resposta (cenário misto → 75%; trocar para resolvida → 100%); página renderiza indicadores, linhas de NC e campos desabilitados; item atrasado destacado; excluir (não membro 404, membro 200 com indicadores); **60 adições simultâneas sem número repetido**; limite de 500 itens (501º → 409) e aba com 500 itens carregando em ~16 ms; **excluir projeto com item usando classificação funciona** (CASCADE apaga checklist, itens e classificações, sem órfãos).
- **Schema (MySQL descartável)**: CHECK impede status/identificação de NC em item não-NC; ENUM rejeita status fora da lista; UNIQUE impede número repetido e 2º checklist; RESTRICT impede apagar classificação em uso.

**Validado pelo desenvolvedor no navegador real (2026-09-26)**: tudo funcionando. Observação dele: ao escolher o status `escalonada`, a "Data e hora do escalonamento" não é preenchida — **comportamento esperado**: essa data será gravada pelo fluxo de escalonamento da **Fase 7** (botão "Escalonar", que envia o e-mail ao superior). Na Fase 7, avaliar tirar `escalonada` da seleção manual para que só o fluxo defina esse status.

#### Pendências conhecidas da Fase 5

- ~~Remover o índice redundante `uk_checklists_projeto_nome`~~ ✅ aplicado pelo desenvolvedor em 2026-09-26.
- Ao excluir o **último** item, o próximo item novo reutiliza aquele número ("maior + 1"). Números de itens existentes nunca mudam. Se for preciso nunca reutilizar, será necessário um contador em `checklists` (mudança de schema).
- O status `escalonada` já pode ser escolhido manualmente, mas `data_escalonamento` e o histórico de escalonamento só serão preenchidos pelo fluxo da Fase 7.
- A partir da Fase 6: bloquear a saída de "Não conformidade" (e a exclusão do item) quando a NC já tiver sido enviada por e-mail.
- Nome do checklist fixo (`Checklist de Qualidade`), sem tela para editar.
- Importar/copiar itens de um modelo ou de outro projeto.
- Edição simultânea do mesmo campo: vale o último salvamento.


### Análise da Fase 6 — Envio da NC por e-mail (em andamento, 2026-09-26)

**Material lido**: `~/Downloads/Trabalho Qualidade de Software/Não Conformidades/Solicitacao_Resolucao_Nao_Conformidade - 1..6.pdf` e os e-mails em `.../E-mails/Documentos Auditoria/` (sequência completa do "Murilo": 1ª solicitação → escalonamento → resolução).

**Estrutura do documento "Solicitação de Resolução de Não Conformidade" (Versão 1.0)**:
- Cabeçalho: Projeto, Responsável pela Resolução, Responsável por QA (texto livre com vários nomes, ex.: "Emilly B., Giovanna P., Lucas L., Rodrigo Y."), Data da 1ª Solicitação, Prazo de Resolução, Nº de Escalonamento.
- "Não Conformidade Identificada": Descrição | Classificação ("Simples | 1 hora") | Ação Corretiva Indicada.
- "Histórico de Escalonamento": colunas **Superior | Responsável | Prazo para Resolução** ("Nenhum" no envio inicial).
- "Observações" (texto livre; "Nenhuma observação adicional.").

**Padrão dos e-mails (Outlook institucional @pucpr.edu.br, Microsoft 365)**:
- 1ª solicitação: De = auditor (conta pessoal), Para = responsável pela resolução; assunto `Solicitação de Resolução de Não Conformidade - <Projeto>`; corpo curto ("Olá, <nome>, segue em anexo a Solicitação...") assinado "Equipe de QA - <nomes>"; **o documento vai como PDF anexo**.
- Escalonamento (Fase 7): encaminhado ao superior (professora) com o 1º envio junto, e a resposta dela saiu **em CC para todos os envolvidos** (equipe de QA + responsáveis).
- Resolução: o responsável responde "Não conformidade corrigida" para a equipe de QA (fora do sistema).

**Decisões tomadas (desenvolvedor, 2026-09-26)**:
- Integração PHP → Python: **síncrona** (ver "Integração PHP → Python").
- **Conta remetente única do sistema** ("DeepCheck"): `From: "<Nome do usuário> via DeepCheck" <EMAIL_REMETENTE>`, `Reply-To` e `CC` = e-mail do usuário que enviou. O desenvolvedor vai criar a conta (Gmail com senha de app) e preencher o `.env`.
- **Documento enviado como PDF anexo** (fiel ao template), gerado em Python com reportlab.
- **Mailpit** como "pega-e-mails" de desenvolvimento.

**Infraestrutura já feita (2026-09-26)**:
- `Dockerfile`: `python3` + `python3-reportlab` (apt, Debian 13 trixie → Python 3.13, reportlab 4.3). `smtplib` é da biblioteca padrão.
- `docker-compose.yml`: serviço `mailpit` (`axllent/mailpit`), SMTP em `mailpit:1025` só na rede interna; interface web em `http://localhost:8025` (`MAILPIT_PORT`), publicada só em `127.0.0.1`.
- `.env.example`: variáveis `SMTP_HOST`, `SMTP_PORT`, `SMTP_SEGURANCA` (`starttls`/`ssl`/`nenhuma`), `SMTP_USUARIO`, `SMTP_SENHA`, `EMAIL_REMETENTE`, `EMAIL_REMETENTE_NOME`, `MAILPIT_PORT`, com os valores do Mailpit como padrão e o passo a passo do Gmail (senha de app) comentado.
- Testado: container `app` enviou e-mail via `smtplib` e o Mailpit recebeu.
- **O envio em uso é REAL** (decisão do desenvolvedor): o `.env` está configurado com o Gmail do sistema (`smtp.gmail.com:587`, `starttls`, senha de app). **Login SMTP testado em 2026-09-26: OK** (conexão, STARTTLS e autenticação aceitos; nenhum e-mail enviado no teste). O Mailpit serve apenas para os testes automáticos do Claude Code.
- ⚠️ **Regra para testes do Claude Code**: nunca disparar e-mail real para endereços de teste. Para testes automáticos de envio, subir uma **cópia temporária do container `app`** (mesma imagem e mesmo código, outra porta, rede `deepcheck`) com `-e SMTP_HOST=mailpit -e SMTP_PORT=1025 -e SMTP_SEGURANCA=nenhuma -e SMTP_USUARIO= -e SMTP_SENHA=` — `env()` dá prioridade a variáveis de ambiente, então a cópia usa o Mailpit sem alterar o `.env`. Envio real só para o e-mail do próprio desenvolvedor e com autorização explícita dele.

**SQL proposto (2026-09-26) e validado num MySQL descartável** — aguardando o desenvolvedor criar no Workbench: `nao_conformidades` (1 por item — `UNIQUE (checklist_item_id)`; campos do documento: descrição, classificação, ação corretiva, responsável pela resolução + e-mail, responsável por QA, data da 1ª solicitação, prazo, nº de escalonamento, status padronizado, observações; FK `projeto_id` CASCADE, `checklist_item_id` **RESTRICT**, `classificacao_nc_id` RESTRICT) e `nc_emails_enviados` (destinatário, CC, responder_para, assunto, corpo, nome do anexo + **PDF enviado em MEDIUMBLOB**, status_envio, erro_envio, enviado_por → usuario RESTRICT, NC CASCADE). A coluna `escalonamento_id` fica para a Fase 7 (junto com `nc_escalonamentos`).
- **Achado no teste**: com `checklist_item_id` em RESTRICT, `DELETE FROM projetos` **falha** (o InnoDB tenta apagar os itens antes das NCs). Solução validada: `projeto_excluir()` passa a apagar as NCs do projeto **antes** do projeto, na mesma transação. Assim o RESTRICT continua impedindo excluir item com NC enviada.

**Tabelas criadas pelo desenvolvedor e verificadas no banco (2026-09-26)**: `nao_conformidades` e `nc_emails_enviados` batem 100% com o SQL proposto.

**Regras da Fase 6 (confirmadas pelo desenvolvedor em 2026-09-26)**:
1. Depois de enviada, a NC fica "travada" no checklist: o item não sai de "Não conformidade" e responsável / classificação / ação corretiva ficam só leitura (é o que foi comunicado); o status continua editável. `nao_conformidades.status` e `checklist_itens.status_nc` são atualizados juntos.
2. Prazo de resolução (no documento) = data prevista do checklist; Data da 1ª solicitação = momento do envio. **A data prevista passa a ser calculada em dias úteis** — ver "Regras de Negócio › 2. Classificação e Prazo de NC".
3. "Responsável por QA" vem preenchido com o RQ do PGQ (ou o nome do usuário, se o PGQ não tiver RQ) e é editável no formulário de envio; o e-mail é assinado "Equipe de QA - <responsável por QA>".
4. Envio exige classificação e responsável preenchidos no item.

**Conta remetente — análise feita** (pergunta do desenvolvedor: "dá para enviar pela conta de cada usuário?"): a senha do DeepCheck não é a senha do e-mail (e só guardamos o hash dela); enviar pela conta Microsoft 365 institucional exigiria SMTP AUTH (normalmente desabilitado nesses tenants e sendo descontinuado pela Microsoft) ou OAuth com app registrado e aprovado pelo admin da PUCPR; guardar senha de e-mail de cada usuário exigiria armazená-la de forma reversível (risco alto). **Escolhido**: conta remetente única do sistema (SMTP no `.env`), com `From: "<Nome do usuário> via DeepCheck" <conta do sistema>`, `Reply-To: <e-mail do usuário>` e o usuário em CC (fica com cópia e as respostas vão para ele). Enviar com `From` = e-mail do usuário por um servidor que não é o dele **não** funciona (SPF/DKIM/DMARC → spam ou rejeição).

### Fase 6 — Envio da NC por e-mail: concluída, testada via HTTP e validada no navegador com envio real (2026-09-26)

#### O que foi implementado

- **Prazo em dias úteis** (`checklist_calcular_prevista()` em `src/Models/checklist.php`), conforme "Regras de Negócio › 2". Prazo em dias é gravado como `23:59:59` e exibido só com a data (`checklist_formatar_prazo()`), no checklist e no PDF.
- **Aba Checklist**:
  - Nova coluna **"Solicitação de resolução"**: botão **"Enviar NC"** (habilitado só quando `checklist_item_pode_enviar()`: item NC, com classificação/data prevista e responsável, ainda não enviado) ou "Enviada em dd/mm/aaaa hh:mm".
  - Botão abre um `<dialog>` com o resumo do documento (projeto, item, responsável, prazo, descrição, classificação, ação corretiva) e os campos: **e-mail do responsável** (obrigatório), **Responsável por QA** (pré-preenchido com o RQ do PGQ ou o nome do usuário), **Com cópia para** (opcional, até 10 e-mails) e **Observações**. Antes de enviar, espera terminar qualquer salvamento pendente da linha.
  - **Depois do envio**, descrição, resultado, responsável, classificação e ação corretiva ficam travados (no servidor e na tela); o status continua editável e é **replicado em `nao_conformidades.status`** (`checklist_salvar_item()` em transação). Excluir o item fica bloqueado (409 na API + FK RESTRICT).
- **Endpoint** `src/Controllers/nc_enviar.php` (login + CSRF + membro; rate limit de **20 tentativas de envio por usuário a cada 15 min**, contando falhas): valida (`nc_ler_envio()`), cria a NC e chama o script **na mesma transação** — se o envio falhar, faz rollback e nada fica gravado (resposta 502 com mensagem amigável; detalhe técnico só no log). Em caso de sucesso grava o e-mail em `nc_emails_enviados` com o **PDF exatamente como foi enviado**. Dois envios simultâneos do mesmo item → só 1 e-mail (UNIQUE em `checklist_item_id`; o segundo recebe 409).
- **E-mail** (padrão dos exemplos do Outlook): `From: "<Nome do usuário> via DeepCheck" <EMAIL_REMETENTE>`, `To: <Responsável> <e-mail informado>`, `Cc:` usuário + cópias extras, `Reply-To:` usuário; assunto `Solicitação de Resolução de Não Conformidade - <Projeto>`; corpo igual ao dos exemplos, assinado "Equipe de QA - <Responsável por QA>"; anexo `Solicitacao_Resolucao_Nao_Conformidade - Item <nº>.pdf`.
- **PDF** (reportlab, A4) no layout do template: título, subtítulo "Template ... — Versão 1.0", tabela de cabeçalho (Projeto, Responsável pela Resolução, Responsável por QA, Data da 1ª Solicitação, Prazo de Resolução, Nº de Escalonamento), faixas azuis "Não Conformidade Identificada" (Descrição | Classificação | Ação Corretiva Indicada), "Histórico de Escalonamento" (Superior | Responsável | Prazo para Resolução — "Nenhum" no 1º envio; já aceita linhas para a Fase 7) e "Observações" ("Nenhuma observação adicional." se vazio). Texto sempre escapado.
- **Arquitetura do envio**: `config/email.php` (`email_config_smtp()` lê o `.env`; `email_executar_script()` roda `scripts/enviar_email.py` via `proc_open` sem shell, JSON no stdin/stdout — a senha SMTP nunca vai na linha de comando). O script gera o PDF, envia via `smtplib` (`starttls`/`ssl`/`nenhuma`, timeout 20 s) e devolve o PDF em base64; modo `apenas_pdf` gera sem enviar. `set_time_limit(60)` no endpoint.
- `projeto_excluir()` agora apaga as NCs antes do projeto, na mesma transação (ver "Análise da Fase 6").

#### Arquivos da Fase 6

- **Criados**: `scripts/enviar_email.py`, `config/email.php`, `src/Models/nao_conformidades.php`, `src/Controllers/nc_enviar.php`.
- **Alterados**: `Dockerfile` (python3 + python3-reportlab), `docker-compose.yml` (serviço `mailpit`), `.env.example` (variáveis de e-mail), `src/Models/checklist.php` (prazo em dias úteis, JOIN com `nao_conformidades`, travas, sincronização do status, `pode_enviar`), `src/Models/projetos.php` (`projeto_excluir()` em transação), `src/Models/pgq.php` (`pgq_rq_nome()`), `src/Controllers/checklist_item_excluir.php` (bloqueia item com NC enviada), `src/Views/abas/checklist.php` (coluna + dialog), `src/Views/abas/checklist_linha.php` (célula de solicitação e travas), `src/Views/projeto.php` (`$responsavelQaPadrao`), `public/js/checklist.js`, `public/css/checklist.css`.

#### Testes realizados (dados de teste apagados e Mailpit esvaziado ao final)

Todos passaram, sem erros/warnings no log do PHP. Envios feitos por uma **cópia temporária do app** apontando para o Mailpit (o app principal, configurado com o Gmail, não enviou nada).
- **Prazo (PHP CLI, 13 casos)**: seg + 3 dias → qui (exemplo do desenvolvedor); sex + 1 dia → seg; sáb/dom + N dias → conta a partir de segunda; 10 dias atravessando 2 fins de semana; horas pulando o fim de semana (sex 17:00 + 24 h → seg 17:00; sáb 15:00 + 2 h → seg 02:00); formatação só com data para prazo em dias.
- **PDF**: layout conferido visualmente contra o template; "Nº de Escalonamento" 0 aparece (bug corrigido: 0 saía em branco); HTML no texto sai literal; emoji/caracteres CJK não quebram a geração; entrada inválida → erro tratado.
- **HTTP**: `pode_enviar` só com NC + classificação + responsável; botão habilitado só nesses itens; validações (sem CSRF 403, não membro 404, e-mail inválido, e-mail com quebra de linha/injeção de cabeçalho, QA vazio, CC inválido, item sem classificação, item conforme → 400, nada enviado); envio real para o Mailpit em ~0,2 s com From/To/Cc/Reply-To/assunto/corpo/anexo corretos; NC gravada com prazo = data prevista (dia útil, 23:59:59) e 1ª solicitação = momento do envio; **PDF no banco idêntico ao recebido (mesmo MD5)**; travas (5 campos → 400), status editável por outro membro e sincronizado com `nao_conformidades`, resolvida grava conclusão e atualiza aderência; excluir item enviado → 409; página mostra "Enviada em" e campos travados; reenviar → 409; **5 envios simultâneos → 1 NC e 1 e-mail**; **SMTP fora do ar → 502, rollback (nada gravado, item continua enviável)**; rate limit (21ª tentativa → 429); **excluir projeto com 3 NCs e 3 e-mails → tudo apagado, sem órfãos**.

**Teste no navegador (desenvolvedor, Firefox 156, 2026-09-26) — 2 problemas encontrados e corrigidos:**
1. **"Enviar NC" não fazia nada**: o Firefox usava o `checklist.js` antigo do cache (o log do Apache não mostrava nenhum download do JS novo), então o botão novo não tinha tratador de clique. **Correção**: helper `asset()` em `config/auth.php`, que acrescenta `?v=<data de modificação do arquivo>` a todo CSS/JS; todas as views passaram a usá-lo. Quando um arquivo muda, a URL muda e o navegador baixa a versão nova.
2. **Mês/ano da capa do PGQ não funcionava**: `<input type="month">` não existe no Firefox (vira caixa de texto; o log mostrava 18 salvamentos recusados com 400). **Correção**: duas listas — `data_documento_mes` (Janeiro…Dezembro, constante `PGQ_MESES`) e `data_documento_ano` (ano atual ±5, incluindo o ano salvo) — validadas em `pgq_ler_entrada()` (os dois ou nenhum; gravado como dia 1 do mês). Testado via HTTP: salvar, reexibir selecionado, só um dos dois → 400, mês 13 → 400, limpar → NULL.

**Validado pelo desenvolvedor no navegador (2026-09-26)**, após as correções: mês/ano da capa funcionando e **envio real pelo Gmail** para o próprio e-mail dele — "o email chegou certinho" (remetente, CC, PDF anexo e responder-para). Registro conferido no banco (NC + e-mail com `status_envio = sucesso` e PDF gravado).

#### Pendências conhecidas da Fase 6

- Se o e-mail sair mas a gravação final no banco falhar (muito improvável), o e-mail foi enviado sem registro — fica só no log (`DeepCheck: falha ao registrar envio...`).
- Sem pré-visualização do PDF antes de enviar (o script já tem o modo `apenas_pdf`, dá para expor numa rota).
- Sem tela para baixar o PDF/ver o e-mail enviado → **Fase 7** (aba Não Conformidades).
- Rate limit de envio fica no `/tmp` do container (mesma limitação do login).
- Feriados não entram no cálculo de prazo (só fins de semana).
- Itens com data prevista calculada pela regra antiga (antes dos dias úteis) só são recalculados se a classificação for trocada.

### Estrutura real de arquivos (após a Fase 6)

```
config/                 bootstrap incluído por tudo (bloqueado na web)
  auth.php              sessão, login, CSRF, e(), responder_json(), HASH_FICTICIO
  email.php             ponte PHP → Python (proc_open) e configuração SMTP do .env
  headers.php           headers de segurança
  conexao.php           mysqli ($conexao), utf8mb4, time_zone
  env.php               env('NOME')
  rate_limit.php        rate limit em arquivos no /tmp do container
src/Models/             acesso a dados (bloqueado na web)
  projetos.php          projetos + membros + classificações padrão
  pgq.php               PGQ e sub-tabelas (seções 2, 3, 4)
  checklist.php         checklist, itens, regras de NC, prazo em dias úteis e fórmula de aderência
  nao_conformidades.php NCs enviadas, texto do e-mail e registro dos envios
  classificacoes_nc.php leitura das classificações (Fase 8 vai estender)
src/Controllers/        endpoints (JSON, exceto logoff)
  login_backend.php, cadastrar_backend.php, logoff.php
  projeto_criar.php, projeto_editar.php, projeto_excluir.php, projeto_acessar.php
  pgq_salvar.php
  checklist_item_adicionar.php, checklist_item_atualizar.php, checklist_item_excluir.php
  nc_enviar.php         gera o PDF e envia a Solicitação de Resolução de NC
src/Views/              páginas
  login.php, cadastro.php
  menu.php              dashboard "Meus projetos"
  projeto.php           página do projeto com as 3 abas
  navbar.php            componente compartilhado
  abas/pgq.php          partial da aba PGQ
  abas/checklist.php    partial da aba Checklist
  abas/checklist_linha.php  HTML de uma linha do checklist (aba + endpoint de adicionar)
public/js/              api.js (enviarPost), login.js, cadastrar.js, menu.js, pgq.js, checklist.js
public/css/             login, cadastro, navbar, menu, projeto, pgq, checklist
scripts/                enviar_email.py — gera o PDF (reportlab) e envia (smtplib) (bloqueado na web)
docker/                 apache/zz-deepcheck.conf, php/deepcheck.ini (bloqueado na web)
index.html, style.css   landing page estática
```

A seção "Sugestão de Estrutura de Arquivos" mais abaixo é a proposta original; **a estrutura acima é a real e deve ser seguida.**

### Pendências em aberto (consolidado de todas as fases — atualizado em 2026-09-25)

**Conta de usuário (Fase 1)**
- Página de perfil (`perfil.php`) não existe (botão "Perfil" removido da navbar).
- "Esqueceu a senha? Redefinir" no login é um link `#` sem funcionalidade.

**Infraestrutura (Fase 1)**
- `index.html` é estático e não recebe os headers de segurança do PHP (não tem formulários nem dados).
- Healthcheck do MySQL usa `mysqladmin ping`, que dá "healthy" mesmo com a senha root errada.
- Rate limit fica no `/tmp` do container e zera quando o container é recriado.

**Projetos (Fase 3)**
- Não há como alterar a senha do projeto nem transferir a posse (criador) para outro usuário.
- Membro não consegue "sair" de um projeto; o criador não consegue remover membros.

**PGQ (Fase 4)**
- Logo do projeto na capa (exige upload de arquivo).
- Exportar/imprimir o PGQ no formato do documento (PDF/DOC).
- Edição simultânea: vale o último salvamento, sem aviso de conflito.
- Edição das classificações/prazos da seção 6 → **Fase 8**.

**Checklist (Fase 5)**
- Excluir o último item faz o próximo reutilizar o número.
- `data_escalonamento` (não é preenchida ao escolher `escalonada` manualmente — observado pelo desenvolvedor) e histórico de escalonamento → **Fase 7**; bloqueio de alterações em NC já enviada → **Fase 6**.
- Nome do checklist fixo; importar/copiar itens de modelo; edição simultânea sem aviso de conflito.

**E-mail de NC (Fase 6)**
- Pré-visualização do PDF; tela para baixar o PDF/ver e-mails enviados (→ Fase 7); feriados fora do cálculo de prazo; itens com prazo antigo só recalculam ao trocar a classificação; e-mail enviado sem registro se a gravação final falhar (só log).

**Banco (próximas fases)**
- Criar `nc_escalonamentos` e a coluna/FK `nc_emails_enviados.escalonamento_id` (Fase 7) — SQL proposto pelo Claude Code, aplicado pelo desenvolvedor no Workbench.
- FKs para `classificacoes_nc` devem ser RESTRICT; as demais filhas de `projetos` devem ser CASCADE (senão a exclusão de projeto retorna 409).

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

-- Projetos de auditoria — JÁ CRIADA no Workbench (fonte da verdade):
projetos (
  id INT UNSIGNED AUTO_INCREMENT PK,
  nome VARCHAR(150) NOT NULL,
  projeto_codigo_acesso VARCHAR(32) NOT NULL,   -- UNIQUE uk_projetos_codigo_acesso; gerado pelo sistema
  projeto_senha_hash VARCHAR(255) NOT NULL,     -- password_hash
  criado_por INT UNSIGNED NOT NULL,             -- fk_projetos_criado_por → usuario.id ON DELETE RESTRICT
  criado_em, atualizado_em
)

-- Quem está vinculado a cada projeto ("meus projetos") — JÁ CRIADA no Workbench:
projeto_membros (
  id INT UNSIGNED AUTO_INCREMENT PK,
  projeto_id INT UNSIGNED NOT NULL,   -- fk_projeto_membros_projeto → projetos.id ON DELETE CASCADE
  usuario_id INT UNSIGNED NOT NULL,   -- fk_projeto_membros_usuario → usuario.id ON DELETE CASCADE
  acesso_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP   -- último acesso (upsert)
  -- UNIQUE uk_projeto_membros_projeto_usuario (projeto_id, usuario_id)
  -- INDEX idx_projeto_membros_usuario_acesso (usuario_id, acesso_em)
)

-- Plano de Garantia da Qualidade (1 por projeto) — JÁ CRIADA no Workbench:
pgq (
  id INT UNSIGNED AUTO_INCREMENT PK,
  projeto_id INT UNSIGNED NOT NULL,          -- UNIQUE uk_pgq_projeto; fk_pgq_projeto → projetos.id ON DELETE CASCADE
  -- Capa
  autor_gqa VARCHAR(150), versao_documento VARCHAR(30), cidade VARCHAR(100),
  data_documento DATE,                       -- exibido como mm/aaaa
  -- Comprometimento
  responsavel_projeto VARCHAR(150), data_comprometimento_responsavel DATE,
  rq_nome VARCHAR(150), data_comprometimento_rq DATE,
  -- Seções 1, 5, 6 e 7
  objetivo TEXT, visao_geral TEXT,
  registros_qualidade_local VARCHAR(500),
  definicao_nc_texto TEXT,                   -- descrição livre das regras
  processo_escalonamento_texto TEXT,         -- descrição livre
  criado_em, atualizado_em
  -- todas as colunas de conteúdo aceitam NULL
)

pgq_documentos (          -- seção 2 "Documentação, Padrões e Diretrizes" — JÁ CRIADA
  id, pgq_id INT UNSIGNED NOT NULL (fk_pgq_documentos_pgq → pgq.id CASCADE),
  documento VARCHAR(255) NOT NULL, versao VARCHAR(30)
)

pgq_itens_avaliados (     -- seção 3 "Itens a Serem Avaliados" — JÁ CRIADA
  id, pgq_id INT UNSIGNED NOT NULL (fk_pgq_itens_avaliados_pgq → pgq.id CASCADE),
  documento VARCHAR(255) NOT NULL, local_armazenamento VARCHAR(500), versao VARCHAR(30)
)

pgq_plano_avaliacoes (    -- seção 4 "Plano de Avaliações" — JÁ CRIADA
  id, pgq_id INT UNSIGNED NOT NULL (fk_pgq_plano_avaliacoes_pgq → pgq.id CASCADE),
  artefato_avaliado VARCHAR(255) NOT NULL, data_avaliacao DATE, auditor VARCHAR(150)
)

-- Classificações de prioridade/prazo configuráveis por projeto — JÁ CRIADA no Workbench:
classificacoes_nc (
  id INT UNSIGNED AUTO_INCREMENT PK,
  projeto_id INT UNSIGNED NOT NULL,       -- fk_classificacoes_nc_projeto → projetos.id ON DELETE CASCADE
  nome VARCHAR(50) NOT NULL,              -- ex: "Simples", "Média", "Alta"; UNIQUE (projeto_id, nome)
  prazo_valor SMALLINT UNSIGNED NOT NULL, -- CHECK chk_classificacoes_nc_prazo_positivo (> 0)
  prazo_unidade ENUM('horas','dias') NOT NULL
)

-- Checklist de Qualidade (1 por projeto)
checklists (
  id INT UNSIGNED AUTO_INCREMENT PK,
  projeto_id INT UNSIGNED NOT NULL,   -- UNIQUE uk_checklists_projeto; fk_checklists_projeto → projetos.id CASCADE
  nome VARCHAR(150) NOT NULL,
  criado_em, atualizado_em
)

checklist_itens (
  id INT UNSIGNED AUTO_INCREMENT PK,
  checklist_id INT UNSIGNED NOT NULL,         -- fk_checklist_itens_checklist → checklists.id CASCADE
  numero_item SMALLINT UNSIGNED NOT NULL,     -- UNIQUE (checklist_id, numero_item); estável, não renumera
  descricao VARCHAR(1000) NOT NULL,
  resultado ENUM('conforme','nao_conformidade','nao_se_aplica') NULL,  -- NULL = não avaliado
  data_identificacao_nc DATETIME NULL,
  responsavel_resolucao VARCHAR(150) NULL,
  classificacao_nc_id INT UNSIGNED NULL,      -- fk_checklist_itens_classificacao → classificacoes_nc.id RESTRICT
  acao_corretiva_indicada VARCHAR(1000) NULL,
  data_prevista_resolucao DATETIME NULL,
  data_escalonamento DATETIME NULL,           -- coluna do template; preenchida na Fase 7
  data_conclusao_nc DATETIME NULL,
  status_nc ENUM('pendente','resolvida','nao_resolvida','escalonada','fechada_por_excecao') NULL,
  criado_em, atualizado_em
  -- CHECK chk_checklist_itens_nc_consistente: data_identificacao_nc e status_nc só com resultado = 'nao_conformidade'
)

-- Não Conformidades (gerada a partir de um checklist_item marcado como "nao_conformidade")
nao_conformidades (
  id, checklist_item_id (FK), projeto_id (FK),
  descricao, classificacao_nc_id (FK),
  responsavel_resolucao, responsavel_qa,
  data_primeira_solicitacao, prazo_resolucao,
  numero_escalonamento (int, default 0),
  status (enum: 'pendente','resolvida','nao_resolvida','escalonada','fechada_por_excecao'),
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

### 0. Status Padronizados (usar exatamente estes valores em todo o sistema)

**Itens do Checklist** (`checklist_itens.resultado`):
- `conforme`
- `nao_conformidade`
- `nao_se_aplica`
- (ausência de valor / `NULL` = item ainda não avaliado, não é um status selecionável pelo usuário)

**Não Conformidades** (`nao_conformidades.status` e `checklist_itens.status_nc`):
- `pendente`
- `resolvida`
- `nao_resolvida`
- `escalonada`
- `fechada_por_excecao` *(adicionado pelo desenvolvedor em 2026-09-25 — equivale ao "Fechamento por Exceção" do template do checklist)*

Não usar sinônimos ou valores alternativos (ex: `sim`/`nao`, `aberta`, `concluida`, `em_resolucao`) em nenhuma parte do código, banco ou interface — manter os nomes acima em todo lugar (colunas do banco, valores de `<select>`, respostas de API, labels visuais podem traduzir para exibição, mas o valor armazenado é sempre um destes).

### 1. Cálculo de Aderência (Checklist)
Recalcular a cada alteração de item, em tempo real (via JS + confirmação no backend):

**Regra corrigida em 2026-09-25** (confirmada pelo desenvolvedor), seguindo as fórmulas reais da planilha `Modelo Checklist - Processo de Qualidade 1.2.xlsx` (`NTA = NT − (NA + não aplicáveis)`, `Aderência = Sim / NTA`) + regra de status de NC definida pelo desenvolvedor. A versão anterior deste texto dizia que "não se aplica" entrava no NTA, o que divergia da planilha (exemplo da planilha: 48 Sim, 6 Não, 1 N/A → **88,89%**, e não 89,09%).

Cada item é classificado assim:

| `resultado` | `status_nc` | Conta como |
|---|---|---|
| `NULL` | — | **NA** (não avaliado) |
| `nao_se_aplica` | — | **NNA** (não aplicável) |
| `conforme` | — | conformidade |
| `nao_conformidade` | `resolvida` | **conformidade** (NC corrigida) |
| `nao_conformidade` | `fechada_por_excecao` | **NNA** (funciona como "não se aplica") |
| `nao_conformidade` | `pendente`, `escalonada`, `nao_resolvida` | **NNC** (não conformidade) |

```
NT   = total de itens do checklist
NA   = itens não avaliados
NNA  = itens não aplicáveis (nao_se_aplica + NCs fechadas por exceção)
NTA  = NT - NA - NNA
NNC  = não conformidades em aberto (pendente, escalonada, nao_resolvida)
NC   = NTA - NNC
% Aderência = (NC / NTA) * 100     (NTA = 0 → aderência não se aplica, exibir "—")
```
Exibir também o total de não aplicáveis (NNA), como a planilha faz.

*Confirmado pelo desenvolvedor (2026-09-25)*: `nao_resolvida` conta como não conformidade, como `pendente`/`escalonada`.

### 2. Classificação e Prazo de NC
- Definidas por projeto em `classificacoes_nc` (configurável pelo usuário no PGQ, seção 6).
- Ao marcar um item do checklist com resultado = "nao_conformidade":
  1. Sistema grava `data_identificacao_nc` automaticamente (timestamp do servidor).
  2. Usuário escolhe a classificação (ex: Simples).
  3. Sistema calcula `data_prevista_resolucao` a partir de `data_identificacao_nc` **pulando fins de semana** (regra definida pelo desenvolvedor em 2026-09-26):
     - **Prazo em dias**: a contagem começa **no dia seguinte** à identificação e só conta dias úteis (segunda a sexta). A data prevista é esse dia, **até o fim do dia** (gravado como `23:59:59`, exibido só como data). Ex.: NC marcada na segunda (dia 1) com 3 dias → quinta (dia 4); marcada na sexta com 1 dia → segunda; marcada no sábado com 1 dia → segunda.
     - **Prazo em horas** *(interpretação do Claude Code, a confirmar)*: conta a partir do momento da identificação e as horas de sábado e domingo não contam. Ex.: sexta 17:00 + 24 horas → segunda 17:00.
     - Feriados **não** são considerados (só sábado e domingo).
     - Itens que já tinham data prevista calculada pela regra antiga só são recalculados se a classificação for trocada.

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

**Decisão (desenvolvedor, 2026-09-26): síncrona** — o PHP chama o script Python na própria requisição (`shell_exec`; na implementação, preferir `proc_open` com os dados via stdin: é o mesmo modelo síncrono, mas sem passar pelo shell e com código de saída). Exige instalar `python3` no container `app` (Dockerfile).

Opções que foram avaliadas:
- **Síncrona simples**: PHP grava os dados necessários em JSON temporário e chama `shell_exec("python3 scripts/enviar_email.py caminho.json")`.
- **Fila assíncrona**: PHP insere o e-mail pendente numa tabela `nc_emails_enviados` com status `pendente`, e um cron job Python processa a fila periodicamente (mais robusto, evita travar a requisição HTTP).

Recomenda-se a fila assíncrona se o volume de e-mails crescer, mas a síncrona é suficiente para o MVP.

---

## Plano de Implementação por Fases

1. **Fase 1 — Auditoria da base existente** ✅✅ *(concluída, testada via HTTP direto E validada manualmente pelo navegador em 2026-09-25 — ver "Fase 1 — concluída e validada")*: revisar segurança, login/logoff, navbar. Nome real da tabela de usuário (`usuario`) confirmado em todo o Modelo de Dados.
2. **Fase 2 — Modelo de dados** 🟡 *(parcial em 2026-09-25: `projetos`, `projeto_membros`, `classificacoes_nc` criadas — ver "Fase 2 — parcial"; demais tabelas são criadas junto com a fase que as usa)*: desenvolvedor cria/ajusta as tabelas manualmente no MySQL Workbench, seguindo o schema acima como base; Claude Code apenas valida se os nomes usados no código batem com o que foi criado.
3. **Fase 3 — Dashboard de projetos** ✅✅ *(concluída, testada via HTTP e validada pelo desenvolvedor no navegador em 2026-09-25 — ver "Fase 3 — Dashboard de Projetos")*: CRUD de projetos (criar, editar nome, apagar) + tela de acesso via ID/senha do projeto.
4. **Fase 4 — Aba PGQ** ✅✅ *(concluída, testada via HTTP e validada pelo desenvolvedor no navegador em 2026-09-25 — ver "Fase 4 — Aba PGQ")*: formulário completo baseado no template, com sub-tabelas dinâmicas (documentos, itens avaliados, plano de avaliações).
5. **Fase 5 — Aba Checklist** ✅✅ *(concluída, testada via HTTP e validada pelo desenvolvedor no navegador em 2026-09-26 — ver "Fase 5 — Aba Checklist")*: CRUD de itens, cálculo de aderência em tempo real, marcação de NC com timestamp automático.
6. **Fase 6 — Fluxo de e-mail de NC** ✅✅ *(concluída, testada via HTTP e validada pelo desenvolvedor no navegador com envio real pelo Gmail em 2026-09-26 — ver "Fase 6 — Envio da NC por e-mail")*: formulário de envio, integração com script Python, template de comunicação.
7. **Fase 7 — Aba Não Conformidades**: listagem, status, histórico, ação de escalonamento (reaproveitando o fluxo de e-mail da Fase 6 com campos extras).
8. **Fase 8 — Configuração de classificações/prazos por projeto**: tela para o usuário definir "Simples/Média/Alta" e seus prazos, usada pelas Fases 5 e 7.

Recomenda-se pedir ao Claude Code para executar **uma fase por vez**, revisando o resultado antes de avançar.
