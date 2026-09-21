# DeepCheck

Sistema de auditoria de projetos.

## Rodando com Docker

Pré-requisitos: Docker e Docker Compose.

1. Copie o arquivo de variáveis de ambiente:

   ```bash
   cp .env.example .env
   ```

2. Suba os containers:

   ```bash
   docker compose up -d --build
   ```

3. Acesse a aplicação em [http://localhost:8080](http://localhost:8080)
   (a porta pode ser alterada via `APP_PORT` no `.env`).

4. O banco MySQL fica exposto em `localhost:3307` (porta 3306 interna do
   container, mapeada para 3307 no host para não conflitar com um MySQL
   já instalado na máquina). Para conectar pelo MySQL Workbench, use:
   - Host: `127.0.0.1`
   - Porta: `3307` (ou o valor de `DB_EXPOSED_PORT` no `.env`)
   - Usuário/senha: os valores de `DB_USERNAME`/`DB_PASSWORD` do `.env`

A tabela `usuario` é criada automaticamente na primeira subida do banco
a partir de `db/init.sql`.

Para parar os containers:

```bash
docker compose down
```

Para parar e apagar também os dados do banco:

```bash
docker compose down -v
```
