# DubPay - Multi-Provider Payment Gateway

Sistema de integração escalável para múltiplos provedores de pagamento, desenvolvido em Laravel com arquitetura robusta e resiliente.

## 🚀 Características

- **Múltiplos Provedores**: Suporte para Stripe, PayPal, PagSeguro, MercadoPago
- **Roteamento Inteligente**: Seleção automática do melhor provedor
- **Retry & Failover**: Recuperação automática de falhas
- **Idempotência**: Prevenção de processamento duplicado
- **Webhook Processing**: Processamento seguro de eventos
- **Observabilidade**: Logs estruturados e métricas
- **Segurança**: Minimização do escopo PCI

## 📋 Pré-requisitos

### Opção 1: Instalação Local

- PHP 8.1 ou superior
- Composer
- PostgreSQL 13+
- Redis (opcional, para cache)

### Opção 2: Docker (Recomendado)

- Docker
- Docker Compose

## 🛠️ Instalação

### Opção 1: Docker (Recomendado)

#### 1. Clone o repositório

```bash
git clone <repository-url>
cd dubpay
```

#### 2. Inicie os containers

```bash
docker-compose up -d
```

#### 3. Aguarde os serviços iniciarem

```bash
docker-compose logs -f app
```

A API estará disponível em `http://localhost:8000`

Os serviços incluem:

- **PostgreSQL**: localhost:5432
- **Redis**: localhost:6379
- **API Laravel**: localhost:8000

#### Comandos úteis Docker:

**Usando Makefile (Recomendado):**

```bash
# Instalação completa (build + up + migrate)
make install

# Ver todos os comandos disponíveis
make help

# Iniciar containers
make up

# Ver logs
make logs

# Executar migrações
make migrate

# Acessar shell da aplicação
make shell

# Parar containers
make down
```

**Usando Docker Compose diretamente:**

```bash
# Ver logs
docker-compose logs -f

# Executar comandos Artisan
docker-compose exec app php artisan migrate

# Parar containers
docker-compose down

# Rebuild containers
docker-compose up --build -d
```

### Opção 2: Instalação Local

#### 1. Clone o repositório

```bash
git clone <repository-url>
cd dubpay
```

#### 2. Instale as dependências

```bash
composer install
```

#### 3. Configure o ambiente

```bash
cp .env.example .env
```

Edite o arquivo `.env` com suas configurações:

```env
# Database PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=dubpay
DB_USERNAME=dubpay_user
DB_PASSWORD=dubpay_password

# Payment Providers (Para testes, use valores mock)
STRIPE_SECRET_KEY=sk_test_dummy_key
PAYPAL_CLIENT_ID=dummy_paypal_client_id
```

#### 4. Gere a chave da aplicação

```bash
php artisan key:generate
```

#### 5. Execute as migrações

```bash
php artisan migrate
```

#### 6. Inicie o servidor

```bash
php artisan serve
```

A API estará disponível em `http://localhost:8000`

## 📚 Endpoints da API

### Autenticação

#### Registro

```http
POST /api/register
Content-Type: application/json

{
  "name": "João Silva",
  "email": "joao@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

#### Login

```http
POST /api/login
Content-Type: application/json

{
  "email": "joao@example.com",
  "password": "password123"
}
```

#### Logout

```http
POST /api/logout
Authorization: Bearer {token}
```

#### Usuário Autenticado

```http
GET /api/user
Authorization: Bearer {token}
```

### Pagamentos

#### Processar Pagamento

```http
POST /api/payments
Authorization: Bearer {token}
Content-Type: application/json

{
  "amount": "100.00",
  "currency": "USD",
  "payment_method": "card",
  "customer_data": {
    "name": "João Silva",
    "email": "joao@example.com"
  },
  "description": "Compra de produto X",
  "provider": "stripe",
  "idempotency_key": "unique-key-123"
}
```

#### Listar Pagamentos

```http
GET /api/payments
Authorization: Bearer {token}
```

#### Status do Pagamento

```http
GET /api/payments/{payment_id}
Authorization: Bearer {token}
```

#### Webhook (Público)

```http
POST /api/webhooks/{provider}
Content-Type: application/json
X-Signature: {signature}

{
  "id": "evt_123",
  "type": "payment.succeeded",
  "data": { ... }
}
```

### Health Check

```http
GET /api/health
```

## 🧪 Como Testar

**Pré-requisito**: Certifique-se que os containers estão rodando:

```bash
# Com Makefile
make up

# Ou com docker-compose
docker-compose up -d
```

### 1. Registrar um usuário

```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

### 2. Fazer login e obter token

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "password123"
  }'
```

### 3. Processar um pagamento

```bash
curl -X POST http://localhost:8000/api/payments \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "amount": "50.00",
    "currency": "USD",
    "payment_method": "card",
    "customer_data": {
      "name": "Test Customer",
      "email": "customer@example.com"
    },
    "description": "Test payment"
  }'
```

### 4. Simular webhook

```bash
curl -X POST http://localhost:8000/api/webhooks/stripe \
  -H "Content-Type: application/json" \
  -H "X-Signature: test_signature" \
  -d '{
    "id": "evt_test_123",
    "type": "payment_intent.succeeded",
    "data": {
      "object": {
        "id": "pi_test_123",
        "status": "succeeded"
      }
    }
  }'
```

## 🎯 Cenários de Teste Mock

Os provedores mock simulam diferentes cenários baseados no valor:

### Stripe Provider

- **< R$ 50**: ✅ Sucesso
- **R$ 50 - R$ 100**: ⏳ Pendente (3D Secure)
- **> R$ 100**: ❌ Falha (cartão recusado)

### PayPal Provider

- **< R$ 1**: ❌ Valor muito baixo
- **R$ 1 - R$ 80**: ✅ Sucesso
- **> R$ 80**: ⏳ Pendente (revisão)
- **Moeda != USD/EUR/BRL**: ❌ Moeda não suportada

## � Troubleshooting Docker

### Problemas Comuns

#### Container não inicia

```bash
# Verificar logs
docker-compose logs postgres
docker-compose logs app

# Rebuild sem cache
docker-compose build --no-cache
docker-compose up -d
```

#### Erro de permissão

```bash
# No Windows, certifique-se que o Docker Desktop está rodando
# No Linux, adicione seu usuário ao grupo docker
sudo usermod -aG docker $USER
```

#### Banco não conecta

```bash
# Verificar se o PostgreSQL está rodando
docker-compose exec postgres pg_isready -U dubpay_user

# Conectar diretamente ao banco
docker-compose exec postgres psql -U dubpay_user -d dubpay
```

#### Reset completo

```bash
# Parar e remover tudo
docker-compose down -v --remove-orphans

# Remover imagens
docker-compose build --no-cache

# Reiniciar
docker-compose up -d
```

## �🔧 Configuração de Produção

### 1. Variáveis de Ambiente

```env
APP_ENV=production
APP_DEBUG=false

# Use credenciais reais dos provedores
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
PAYPAL_CLIENT_ID=real_client_id
PAYPAL_CLIENT_SECRET=real_client_secret

# Database de produção PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=your-prod-postgres-host
DB_PORT=5432
DB_DATABASE=dubpay_prod
DB_USERNAME=your-prod-user
DB_PASSWORD=your-secure-password

# Cache Redis
CACHE_DRIVER=redis
REDIS_HOST=your-redis-host
REDIS_PASSWORD=your-redis-password
```

### 2. Comandos de Deploy

```bash
# Instalar dependências de produção
composer install --no-dev --optimize-autoloader

# Otimizar configuração
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Executar migrações
php artisan migrate --force
```

## 📊 Monitoramento

### Logs

Os logs são estruturados em JSON e incluem:

- `trace_id`: Para rastreamento de requests
- `payment_id`: Identificador do pagamento
- `provider`: Provedor utilizado
- `duration_ms`: Tempo de processamento

### Métricas Importantes

- Taxa de sucesso por provedor
- Tempo médio de processamento
- Rate de retry e failover
- Disponibilidade dos provedores

## 🔒 Segurança

### PCI Compliance

- Nenhum dado de cartão é armazenado
- Comunicação HTTPS obrigatória
- Tokenização via provedores
- Logs sanitizados (sem PII)

### Autenticação

- Bearer tokens via Laravel Sanctum
- Rate limiting por usuário/IP
- Verificação de assinatura de webhooks

## 📖 Documentação Adicional

- [Arquitetura do Sistema](ARCHITECTURE.md)
- [Guia de Contribuição](CONTRIBUTING.md)
- [Changelog](CHANGELOG.md)

## 🤝 Contribuição

1. Fork o projeto
2. Crie uma branch para sua feature (`git checkout -b feature/nova-feature`)
3. Commit suas mudanças (`git commit -am 'Add: nova feature'`)
4. Push para a branch (`git push origin feature/nova-feature`)
5. Abra um Pull Request

## 📝 Licença

Este projeto está sob a licença MIT. Veja o arquivo [LICENSE](LICENSE) para mais detalhes.

## 📞 Suporte

Para dúvidas ou problemas:

- Abra uma issue no GitHub
- Entre em contato: suporte@dubpay.com
- Documentação: https://docs.dubpay.com

---

**Versão**: 1.0.0  
**Última atualização**: 23/09/2025
