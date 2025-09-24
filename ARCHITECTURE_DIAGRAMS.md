# DubPay - Diagramas Simplificados (Teste Técnico)

## 1. Arquitetura Simples - Laravel API

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Client App    │    │   Laravel API   │    │   Database      │
│ (Postman/curl)  │◄──►│   (DubPay)      │◄──►│ (SQLite/Postgres)│
└─────────────────┘    └─────────────────┘    └─────────────────┘
                              │
                              ▼
                    ┌─────────────────┐
                    │  Mock Providers │
                    │                 │
                    │ ┌─────┐ ┌─────┐ │
                    │ │Stripe│ │PayPal│ │
                    │ │Mock │ │Mock │ │
                    │ └─────┘ └─────┘ │
                    └─────────────────┘
```

**Componentes Implementados:**

-   AuthController (registro/login/logout)
-   PaymentController (criar/listar/consultar pagamentos)
-   PaymentService (lógica de negócio)
-   PaymentProviderRouter (seleção de provider)
-   2 Mock Providers (Stripe e PayPal simulados)

## 2. Fluxo Simples de Pagamento

```
Cliente         Laravel API       Mock Provider       Database
  │                  │                   │               │
  │ POST /payments   │                   │               │
  ├─────────────────►│                   │               │
  │                  │ 1. Validate       │               │
  │                  │ 2. Select Provider│               │
  │                  ├──────────────────►│               │
  │                  │                   │ 3. Process    │
  │                  │ 4. Response       │    (mock)     │
  │                  │◄──────────────────┤               │
  │                  │ 5. Save to DB     │               │
  │                  ├───────────────────────────────────►│
  │ 6. Return result │                   │               │
  │◄─────────────────┤                   │               │
  │                  │                   │               │
  │ POST /webhooks   │                   │               │
  ├─────────────────►│ 7. Update status  │               │
  │                  ├───────────────────────────────────►│
  │ 8. OK            │                   │               │
  │◄─────────────────┤                   │               │
```

## 3. Autenticação Simples

```
Cliente                Laravel Sanctum              Database
  │                         │                         │
  │ POST /register          │                         │
  ├────────────────────────►│                         │
  │                         │ Create user             │
  │                         ├────────────────────────►│
  │ User + Token            │                         │
  │◄────────────────────────┤                         │
  │                         │                         │
  │ POST /payments          │                         │
  │ (with Bearer token)     │                         │
  ├────────────────────────►│                         │
  │                         │ Validate token          │
  │                         │ Process payment         │
  │ Payment response        │                         │
  │◄────────────────────────┤                         │
```

**Segurança Implementada:**

-   Laravel Sanctum para autenticação
-   Validação de entrada nos controllers
-   Dados sensíveis NÃO armazenados (apenas metadados)
-   Webhooks validados por assinatura (mock)

## 4. Estrutura de Desenvolvimento

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│  Development    │    │     Testing     │    │   Production    │
│                 │    │                 │    │    (Future)     │
├─────────────────┤    ├─────────────────┤    ├─────────────────┤
│ • SQLite        │    │ • SQLite        │    │ • PostgreSQL    │
│ • PHP 8.2+      │    │ • Pest Tests    │    │ • Docker        │
│ • Laravel Serve │    │ • RefreshDB     │    │ • Load Balancer │
│ • Local files   │    │ • Mock Data     │    │ • Monitoring    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

**Atual (Teste Técnico):**

-   Servidor único Laravel (`php artisan serve`)
-   SQLite para desenvolvimento
-   PostgreSQL via Docker Compose (opcional)
-   Testes automatizados com Pest

## 5. Monitoramento Básico

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│  Laravel Logs   │    │  Health Check   │    │  Test Coverage  │
│                 │    │                 │    │                 │
│ • laravel.log   │    │ GET /api/health │    │ • Pest Tests    │
│ • Payment logs  │    │                 │    │ • Feature tests │
│ • Provider logs │    │ Returns:        │    │ • Auth flow     │
│ • Error logs    │    │ - Status: ok    │    │ • Payment flow  │
│                 │    │ - Timestamp     │    │ • Webhooks      │
│                 │    │ - Version       │    │                 │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

**Observabilidade Atual:**

-   Logs estruturados do Laravel
-   Health check endpoint simples
-   Tracking de tentativas via PaymentAttempt
-   Testes automatizados para validação

## 6. Setup Local (Teste Técnico)

```
┌─────────────────────────────────────────────────────────────────┐
│                    DESENVOLVIMENTO LOCAL                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1. Clone do repositório                                        │
│     git clone [repo]                                            │
│                                                                 │
│  2. Instalar dependências                                       │
│     composer install                                            │
│                                                                 │
│  3. Configurar ambiente                                         │
│     cp .env.example .env                                        │
│     php artisan key:generate                                    │
│                                                                 │
│  4. Database (opções)                                           │
│     • SQLite (padrão): touch database/database.sqlite          │
│     • PostgreSQL: docker compose up -d                         │
│                                                                 │
│  5. Migrations                                                  │
│     php artisan migrate                                         │
│                                                                 │
│  6. Servidor                                                    │
│     php artisan serve (porta 8000)                             │
│                                                                 │
│  7. Testes                                                      │
│     php artisan test                                            │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

## 7. Teste e Validação

```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│    Code     │    │    Test     │    │   Manual    │
│  Commit     │───►│   Suite     │───►│  Testing    │
│             │    │             │    │             │
└─────────────┘    └─────────────┘    └─────────────┘
       │                  │                  │
       ▼                  ▼                  ▼
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│ Git Branch  │    │ Pest Tests  │    │ Postman/    │
│ (new)       │    │ • AuthTest  │    │ curl Tests  │
│             │    │ • PaymentTest│    │             │
└─────────────┘    └─────────────┘    └─────────────┘
```

**Validação Implementada:**

-   Testes automatizados (Pest)
-   README com exemplos de uso
-   Script test_api.sh para validação completa
-   Documentação de endpoints
