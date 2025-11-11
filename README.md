# Sistema de Pagamentos Multi-Gateway

## 📋 Descrição
Sistema completo de pagamentos com múltiplos gateways e fallback automático desenvolvido em Laravel 11. Atende todos os requisitos técnicos do teste com implementação completa de TDD, Docker, autenticação e sistema de roles.

## ✅ Requisitos Técnicos Atendidos

### 🗄️ Banco de Dados
- ✅ **MySQL** como banco de dados principal
- ✅ **ORM Eloquent** para gestão completa do banco
- ✅ Migrations para estruturação das tabelas
- ✅ Seeders para dados iniciais de teste

### 🔌 API & Respostas
- ✅ **API RESTful** com endpoints organizados
- ✅ **Respostas em JSON** para todas as requisições
- ✅ **Validação de dados** com Validator do Laravel
- ✅ Status codes apropriados para cada cenário

### 🐳 Containerização
- ✅ **Docker Compose** com serviços integrados:
  - `app` - Aplicação Laravel (PHP 8.2)
  - `mysql` - Banco de dados MySQL 8.0
  - `nginx` - Servidor web
- ✅ **Gateways mockados** via Docker separado
- ✅ Ambiente de desenvolvimento isolado

### 🧪 Testes & Qualidade
- ✅ **TDD Implementado** com PHPUnit
- ✅ **Testes unitários** para serviços e lógica de negócio
- ✅ **Testes de feature** para endpoints da API
- ✅ **Cobertura abrangente** de funcionalidades críticas
- ✅ **Mocking** de serviços externos para testes confiáveis

### 🔐 Segurança & Autenticação
- ✅ **Sistema de roles** (ADMIN, MANAGER, FINANCE, USER)
- ✅ **Autorização granular** por endpoints
- ✅ **Laravel Sanctum** para autenticação JWT
- ✅ **Validação de dados** em todas as requisições

## 🚀 Funcionalidades Principais

### 💳 Sistema de Pagamentos
- Processamento de pagamentos com múltiplos gateways
- Fallback automático entre gateways por prioridade
- Cálculo automático de valores baseado em produtos
- Suporte a múltiplos produtos por transação

### 👥 Gestão de Usuários e Permissões
- CRUD completo de usuários com validação de roles
- Sistema de autorização com middlewares personalizados
- Proteção contra auto-deleção de usuários

### 📊 Gestão de Dados
- Gestão completa de produtos, clientes e transações
- Histórico detalhado de todas as operações
- Configuração dinâmica de gateways

## 🛠 Tecnologias

- **Laravel 11** - Framework PHP
- **MySQL 8.0** - Banco de dados
- **Docker & Docker Compose** - Containerização
- **Laravel Sanctum** - Autenticação API
- **PHP 8.2** - Linguagem de programação
- **PHPUnit** - Framework de testes
- **Nginx** - Servidor web

## 📦 Instalação Rápida

```bash
# 1. Clone o repositório
git clone https://github.com/rsantsil/pagamentos_multi_gateway_api.git
cd pagamentos_multi_gateway_api

# 2. Suba os containers
docker-compose up -d --build

# 3. Execute as migrations e seeders
docker-compose exec app php artisan migrate --seed

# 4. Execute os mocks dos gateways (terminal separado)
docker run -p 3001:3001 -p 3002:3002 matheusprotzen/gateways-mock

# 5. Acesse a aplicação
http://localhost:8000
```

## 🧪 Cobertura de Testes
- ✅ **Autenticação:** Login, logout e validações
- ✅ **Transações:** Processamento de pagamentos, fallback, validações
- ✅ **Serviços:** Lógica de negócio do sistema de pagamentos
- ✅ **Gateways:** Comunicação com provedores de pagamento
- ✅ **Roles:** Sistema de autorização e permissões

## 🔌 API Endpoints

### 🔓 Rotas Públicas
| Método | Endpoint              | Descrição               |
|--------|-----------------------|-------------------------|
| POST   | /api/login            | Login de usuário        |
| POST   | /api/purchase         | Processar pagamento     |
| GET    | /api/products         | Listar produtos         |
| GET    | /api/products/{id}    | Detalhes do produto     |

### 🔐 Rotas Protegidas por Role

#### 👨‍💼 ADMIN - Acesso Total
| Método | Endpoint                          | Descrição                     |
|--------|-----------------------------------|-------------------------------|
| GET    | /api/users                        | Listar usuários               |
| POST   | /api/users                        | Criar usuário                 |
| PUT    | /api/users/{id}                  | Atualizar usuário             |
| DELETE | /api/users/{id}                  | Deletar usuário               |
| PATCH  | /api/gateways/{id}/toggle        | Ativar/desativar gateway      |
| PATCH  | /api/gateways/{id}/priority      | Alterar prioridade            |

#### 👔 MANAGER - Gestão Operacional
| Método | Endpoint                          | Descrição                     |
|--------|-----------------------------------|-------------------------------|
| POST   | /api/products                     | Criar produto                 |
| PUT    | /api/products/{id}                | Atualizar produto             |
| DELETE | /api/products/{id}                | Deletar produto               |

#### 💰 FINANCE - Área Financeira
| Método | Endpoint                          | Descrição                     |
|--------|-----------------------------------|-------------------------------|
| POST   | /api/transactions/{id}/refund     | Reembolsar transação         |

#### 👤 USER - Usuário Básico
| Método | Endpoint                          | Descrição                     |
|--------|-----------------------------------|-------------------------------|
| GET    | /api/transactions                 | Listar transações            |
| GET    | /api/transactions/{id}           | Detalhes da transação        |
| GET    | /api/clients                      | Listar clientes              |
| GET    | /api/clients/{id}                | Detalhes do cliente          |
| POST   | /api/logout                       | Logout                       |
| GET    | /api/user                         | Dados do usuário logado      |

## 🧪 Testando a API

### Collection do Postman/Insomnia

Para facilitar os testes, use a collection completa:

1. **Baixe o arquivo**: [`postman_collection.json`](postman_collection.json)
2. **Importe** no Postman, Insomnia ou sua ferramenta preferida
3. **Configure as variáveis**:
   - `base_url`: `http://localhost:8000/api`
   - `auth_token`: (será preenchido automaticamente após login)

### 🐳 Gateways Mock para Testes

Para desenvolvimento e testes, utilize os gateways mock:

**Com autenticação:**
```bash
docker run -p 3001:3001 -p 3002:3002 matheusprotzen/gateways-mock
```

**Sem autenticação:**
```bash
docker run -p 3001:3001 -p 3002:3002 -e REMOVE_AUTH='true' matheusprotzen/gateways-mock
```


## 💳 Exemplos de Uso

### Processar Pagamento
```bash
curl -X POST http://localhost:8000/api/purchase \
    -H "Content-Type: application/json" \
    -d '{
        "products": [
            {
                "id": 1,
                "quantity": 2
            }
        ],
        "card_number": "4111111111111111",
        "cvv": "123",
        "client_name": "João Silva",
        "client_email": "joao@email.com"
    }'
```
**Response de Sucesso:**
```json
{
    "success": true,
    "transaction_id": 1,
    "gateway_used": "Gateway 1",
    "status": "approved"
}
```

### Login de Usuário
```bash
curl -X POST http://localhost:8000/api/login \
    -H "Content-Type: application/json" \
    -d '{
        "email": "admin@example.com",
        "password": "password"
    }'
```
**Response:**
```json
{
    "success": true,
    "token": "1|abc123...",
    "user": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@example.com",
        "role": "ADMIN"
    }
}
```

### Acesso a Rota Protegida
```bash
curl -X GET http://localhost:8000/api/users \
    -H "Authorization: Bearer 1|abc123..." \
    -H "Content-Type: application/json"
```

## 👥 Sistema de Roles

### Permissões por Role
| Permissão                | ADMIN | MANAGER | FINANCE | USER |
|-------------------------|-------|---------|---------|------|
| Gestão de usuários       | ✅    | ❌      | ❌      | ❌   |
| Gestão de produtos       | ✅    | ✅      | ❌      | ❌   |
| Reembolsos              | ✅    | ❌      | ✅      | ❌   |
| Listar transações       | ✅    | ✅      | ✅      | ✅   |
| Listar clientes         | ✅    | ✅      | ✅      | ✅   |
| Processar pagamentos     | ✅    | ✅      | ✅      | ✅   |

### Usuários de Teste
| Email                  | Password | Role    | Acesso                |
|-----------------------|----------|---------|-----------------------|
| admin@example.com     | password | ADMIN   | Total                 |
| manager@example.com   | password | MANAGER | Gestão operacional     |
| finance@example.com   | password | FINANCE | Área financeira        |
| user@example.com      | password | USER    | Acesso básico         |

## 🗄 Estrutura do Banco de Dados

### Diagrama Simplificado
```
users
    ├── id
    ├── name
    ├── email
    ├── password
    └── role (ADMIN, MANAGER, FINANCE, USER)

gateways
    ├── id
    ├── name
    ├── is_active
    └── priority

clients
    ├── id
    ├── name
    └── email

products
    ├── id
    ├── name
    ├── amount (centavos)
    └── is_active

transactions
    ├── id
    ├── client_id
    ├── gateway_id
    ├── external_id
    ├── status
    ├── amount
    └── card_last_numbers

transaction_products
    ├── transaction_id
    ├── product_id
    ├── quantity
    └── unit_amount
```

## 🔄 Fluxo de Pagamento
- Validação dos dados de entrada e produtos
- Cálculo do valor total baseado nos produtos e quantidades
- Processamento sequencial através dos gateways por ordem de prioridade
- Fallback automático em caso de falha em qualquer gateway
- Registro da transação e associação dos produtos
- Retorno do resultado para o cliente

## 🐳 Docker

### Serviços
- app - Aplicação Laravel (PHP 8.2 + Composer)
- mysql - Banco de dados MySQL 8.0
- nginx - Servidor web Nginx

### Comandos Úteis
```bash
# Entrar no container da aplicação
docker-compose exec app bash

# Ver logs da aplicação
docker-compose logs app

# Executar comandos Artisan
docker-compose exec app php artisan migrate
docker-compose exec app php artisan test

# Parar os containers
docker-compose down

# Rebuildar os containers
docker-compose up -d --build
```

## 🧪 Executando os Testes
```bash
# Executar teste com relatório detalhado
docker-compose exec app ./vendor/bin/phpunit --testdox

```


## 👨‍💻 Autor
Desenvolvido por Robson dos Santos Silva como teste técnico para a BeTalent.
