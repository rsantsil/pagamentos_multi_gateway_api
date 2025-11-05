# Sistema de Pagamentos Multi-Gateway

## 📋 Descrição
Sistema completo de pagamentos com múltiplos gateways e fallback automático desenvolvido em Laravel. O sistema permite processar pagamentos através de diversos gateways de pagamento com fallback automático em caso de falha, com sistema completo de autenticação e autorização baseado em roles.

## 🚀 Funcionalidades

### 💳 Sistema de Pagamentos
- ✅ **Processamento de pagamentos** com múltiplos gateways
- ✅ **Fallback automático** entre gateways em caso de falha
- ✅ **Cálculo automático** de valores baseado em produtos e quantidades
- ✅ **Suporte a múltiplos produtos** por transação
- ✅ **Integração com gateways mockados** para desenvolvimento

### 🔐 Sistema de Segurança
- ✅ **Autenticação JWT** com Laravel Sanctum
- ✅ **Sistema de roles** (ADMIN, MANAGER, FINANCE, USER)
- ✅ **Autorização granular** por endpoints
- ✅ **Validação de dados** completa

### 👥 Gestão de Usuários
- ✅ **CRUD completo** de usuários
- ✅ **Atribuição de roles** com permissões específicas
- ✅ **Proteção contra auto-deleção**

### 📊 Gestão de Dados
- ✅ **Gestão de produtos** com valores em centavos
- ✅ **Histórico completo** de transações
- ✅ **Gestão de clientes** com histórico de compras
- ✅ **Configuração de gateways** com prioridade

## 🛠 Tecnologias

- **Laravel 11** - Framework PHP
- **MySQL 8.0** - Banco de dados
- **Docker & Docker Compose** - Containerização
- **Laravel Sanctum** - Autenticação API
- **PHP 8.2** - Linguagem de programação
- **PHPUnit** - Framework de testes
- **Nginx** - Servidor web

## 📦 Instalação

### Pré-requisitos
- Docker e Docker Compose
- Git

### Passos para instalação

```bash
# 1. Clone o repositório
git clone https://github.com/seu-usuario/pagamentos_multi_gateway_api.git
cd pagamentos_multi_gateway_api

# 2. Suba os containers
docker-compose up -d --build

# 3. Execute as migrations e seeders
docker-compose exec app php artisan migrate --seed

# 4. Execute os mocks dos gateways (em outro terminal)
docker run -p 3001:3001 -p 3002:3002 matheusprotzen/gateways-mock

# 5. Acesse a aplicação
http://localhost:8000
```

## Executar toda a suíte de testes
docker-compose exec app ./vendor/bin/phpunit

# Executar com relatório detalhado
docker-compose exec app ./vendor/bin/phpunit --testdox

# Executar testes específicos
docker-compose exec app ./vendor/bin/phpunit tests/Feature/TransactionTest.php
docker-compose exec app ./vendor/bin/phpunit tests/Unit/Services/PaymentServiceTest.php