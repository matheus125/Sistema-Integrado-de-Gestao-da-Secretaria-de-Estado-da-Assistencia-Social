# SIGES

Sistema Integrado de Gestao da Secretaria de Estado da Assistencia Social.

Preparação do servidor, serviços, HTTPS e backups: [guia de produção](docs/producao.md).
Dados já migrados: [registro da migração MySQL](docs/migracao-mysql.md).

## Stack

- Backend: Laravel 12, PHP 8.4+, Sanctum, Queue, Scheduler, Notifications, Events, Policies e Broadcasting
- Frontend: Vue 3, Quasar Framework e PWA
- Banco: MySQL 8
- Bibliotecas: Spatie Permission, Spatie Activity Log, Laravel Excel e DomPDF

## Estrutura Inicial

- `app/Http/Controllers/Api`: endpoints REST iniciais
- `config/siges.php`: catalogo de modulos e acoes do RBAC
- `database/migrations/*siges_core*`: tabelas dos modulos principais
- `database/seeders/RolesAndPermissionsSeeder.php`: perfis e permissoes iniciais
- `frontend/quasar`: aplicacao Vue 3 + Quasar

## Modulos Mapeados

- Dashboard
- Administracao
- Gerencia de Transporte
- Gerencia de Materiais e Patrimonio
- Gerencia de Informatica
- Relatorios
- Auditoria
- Configuracoes

## Primeiros Comandos (instalação nova de desenvolvimento)

Não executar seeders ou gerar outra APP_KEY sobre os dados já migrados.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Frontend:

```bash
cd frontend/quasar
npm install
npm run dev -- --hostname 127.0.0.1 --port 9000
```

Builds:

```bash
npm --prefix frontend/quasar run build
npm --prefix frontend/quasar run build -- -m pwa
```

Usuario inicial criado pelo seeder:

- Email: `admin@siges.local`
- Senha: `password`
