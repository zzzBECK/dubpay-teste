# DubPay - API de Pagamentos

Sistema de pagamentos Laravel com autenticação e 2 provedores mock (Stripe e PayPal).

📖 **Documentação:** [Arquitetura](ARCHITECTURE.md) | [Diagramas](ARCHITECTURE_DIAGRAMS.md)

## 🚀 Como Rodar

```bash
# 1. Instalar dependências
composer install

# 2. Configurar ambiente
cp .env.example .env
php artisan key:generate

# 3. Banco de dados (PostgreSQL com Docker)
docker-compose up -d
# Configurar .env com PostgreSQL e executar php artisan migrate
php artisan migrate

# 4. Iniciar servidor
php artisan serve
```

## 📡 Endpoints

### Autenticação

| Método | Endpoint        | Descrição          |
| ------ | --------------- | ------------------ |
| `POST` | `/api/register` | Registrar usuário  |
| `POST` | `/api/login`    | Login              |
| `GET`  | `/api/user`     | Perfil (protegido) |
| `POST` | `/api/logout`   | Logout (protegido) |

### Pagamentos

| Método | Endpoint             | Descrição                       |
| ------ | -------------------- | ------------------------------- |
| `POST` | `/api/payments`      | Criar pagamento (protegido)     |
| `GET`  | `/api/payments`      | Listar pagamentos (protegido)   |
| `GET`  | `/api/payments/{id}` | Consultar pagamento (protegido) |

### Webhooks

| Método | Endpoint               | Descrição      |
| ------ | ---------------------- | -------------- |
| `POST` | `/api/webhooks/stripe` | Webhook Stripe |
| `POST` | `/api/webhooks/paypal` | Webhook PayPal |

## 🧪 Exemplos de Uso

**1. Registrar usuário:**

```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{"name":"João","email":"joao@test.com","password":"123456","password_confirmation":"123456"}'
```

**2. Login:**

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"joao@test.com","password":"123456"}'
```

**3. Criar pagamento:**

```bash
curl -X POST http://localhost:8000/api/payments \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"amount":"100.00","currency":"USD","payment_method":"card","customer_data":{"name":"João","email":"joao@test.com"}}'
```

**4. Webhook:**

```bash
curl -X POST http://localhost:8000/api/webhooks/stripe \
  -H "Content-Type: application/json" \
  -d '{"id":"evt_123","type":"payment_intent.succeeded","data":{"object":{"id":"pi_123","status":"succeeded"}}}'
```

## ✅ Testes

```bash
php artisan test
```
