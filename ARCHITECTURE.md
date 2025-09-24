# DubPay - Sistema de Integração de Múltiplos Provedores de Pagamento

## 1. Arquitetura de Alto Nível

### 1.1 Visão Geral dos Componentes

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────────┐
│   Client App    │    │   Load Balancer  │    │   Payment Gateway   │
│                 │────│                  │────│      (DubPay)       │
│ Web/Mobile/API  │    │   (Nginx/AWS)    │    │                     │
└─────────────────┘    └──────────────────┘    └─────────────────────┘
                                                          │
                              ┌───────────────────────────┼───────────────────────────┐
                              │                           │                           │
                              ▼                           ▼                           ▼
                    ┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
                    │  Authentication │       │  Payment Router │       │  Webhook Handler│
                    │    Service      │       │    Service      │       │    Service      │
                    └─────────────────┘       └─────────────────┘       └─────────────────┘
                                                          │
                              ┌───────────────────────────┼───────────────────────────┐
                              │                           │                           │
                              ▼                           ▼                           ▼
                    ┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
                    │ Stripe Provider │       │ PayPal Provider │       │Future Providers │
                    │    (Mock)       │       │    (Mock)       │       │  PagSeguro/MP   │
                    └─────────────────┘       └─────────────────┘       └─────────────────┘
                                                          │
                              ┌───────────────────────────┼───────────────────────────┐
                              │                           │                           │
                              ▼                           ▼                           ▼
                    ┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
                    │    Database     │       │      Cache      │       │      Logs       │
                    │  (MySQL/Pgsql)  │       │    (Redis)      │       │   (ELK Stack)   │
                    └─────────────────┘       └─────────────────┘       └─────────────────┘
```

### 1.2 Principais Serviços

- **Authentication Service**: Gerencia registro, login e tokens JWT/Sanctum
- **Payment Router Service**: Decide qual provedor usar baseado em regras de negócio
- **Payment Service**: Processa pagamentos com retry e idempotência
- **Webhook Handler**: Processa eventos dos provedores de forma idempotente
- **Provider Abstraction**: Interface comum para todos os provedores

## 2. Fluxo de Pagamento

### 2.1 Fluxo Principal (Checkout → Confirmação)

```
Cliente                API Gateway           Payment Router        Provider              Webhook
  │                         │                      │                  │                     │
  │ 1. POST /payments       │                      │                  │                     │
  ├────────────────────────►│                      │                  │                     │
  │                         │ 2. Validate & Route  │                  │                     │
  │                         ├─────────────────────►│                  │                     │
  │                         │                      │ 3. Select Best   │                     │
  │                         │                      │    Provider      │                     │
  │                         │                      ├─────────────────►│                     │
  │                         │                      │                  │ 4. Process Payment │
  │                         │                      │                  │                     │
  │                         │                      │ 5. Response      │                     │
  │                         │                      │◄─────────────────┤                     │
  │                         │ 6. Payment Status    │                  │                     │
  │                         │◄─────────────────────┤                  │                     │
  │ 7. Response (Success/   │                      │                  │                     │
  │    Pending/Failed)      │                      │                  │                     │
  │◄────────────────────────┤                      │                  │                     │
  │                         │                      │                  │ 8. Event Webhook   │
  │                         │                      │                  ├────────────────────►│
  │                         │                      │                  │                     │ 9. Process Event
  │                         │                      │                  │                     │    (Idempotent)
  │                         │ 10. Update Status    │◄─────────────────┼─────────────────────┤
  │                         │◄─────────────────────┤                  │                     │
```

### 2.2 Estratégias de Roteamento

#### Por Moeda:

- **BRL**: PagSeguro > MercadoPago > PayPal
- **USD/EUR**: Stripe > PayPal
- **Outras**: PayPal (fallback)

#### Por Valor:

- **< R$ 10**: Provedor com menor taxa
- **R$ 10 - R$ 10.000**: Load balancing
- **> R$ 10.000**: Provedor mais confiável (Stripe)

#### Por Disponibilidade:

- Health checks a cada 30s
- Circuit breaker pattern
- Automatic failover

## 3. Tratamento de Falhas e Resiliência

### 3.1 Estratégias de Retry

```php
// Retry Logic
Max Attempts: 3
Backoff: Exponential (0.2s, 0.4s, 0.8s)
Retry Conditions:
- Network timeout
- Provider temporary error (5xx)
- Rate limiting (429)

// Circuit Breaker
Failure Threshold: 5 failures in 60s
Recovery Timeout: 30s
Health Check: Every 10s
```

### 3.2 Idempotência

- **Client-side**: `idempotency_key` obrigatória em requests
- **Server-side**: Hash do payload para webhooks
- **Database**: Unique constraints + upsert operations
- **Cache**: Redis para evitar processamento duplicado

### 3.3 Conciliação

```
Daily Reconciliation Process:
1. Compare internal transactions with provider reports
2. Identify discrepancies
3. Auto-resolve obvious cases
4. Flag complex cases for manual review
5. Generate reconciliation report
```

## 4. Segurança

### 4.1 Minimização do Escopo PCI

- **Tokenização**: Cartões nunca armazenados
- **Proxy Pattern**: Dados sensíveis passam direto ao provider
- **No-Log Policy**: PII/PCI dados não logados
- **Encryption**: Credenciais de API em vault (HashiCorp Vault)

### 4.2 Armazenamento de Credenciais

```
Production Environment:
├── HashiCorp Vault
│   ├── Stripe Keys (encrypted)
│   ├── PayPal Credentials (encrypted)
│   └── Webhook Secrets (encrypted)
├── Environment Variables (non-sensitive)
└── Database (no credentials stored)
```

### 4.3 Autenticação e Autorização

- **API Authentication**: Laravel Sanctum (Bearer tokens)
- **Webhook Verification**: HMAC-SHA256 signature validation
- **Rate Limiting**: 100 req/min por usuário, 1000 req/min por IP
- **CORS**: Configurado para domínios específicos

## 5. Observabilidade

### 5.1 Logs Estruturados

```json
{
  "timestamp": "2024-01-01T12:00:00Z",
  "level": "INFO",
  "service": "payment-service",
  "trace_id": "abc123",
  "payment_id": "uuid-123",
  "provider": "stripe",
  "event": "payment_processed",
  "duration_ms": 1250,
  "status": "success"
}
```

### 5.2 Métricas Principais

- **Business Metrics**:

  - Success rate por provider
  - Average processing time
  - Revenue per provider
  - Failed payment reasons

- **Technical Metrics**:
  - Request latency (P50, P95, P99)
  - Error rate by endpoint
  - Provider availability
  - Queue depth

### 5.3 Distributed Tracing

- Request ID propagation
- Provider call tracing
- Database query tracking
- External API monitoring

## 6. Testes

### 6.1 Estratégia de Testes

```
Unit Tests (80%):
├── Payment Provider Mocks
├── Business Logic
├── Validation Rules
└── Helper Functions

Integration Tests (15%):
├── API Endpoints
├── Database Operations
├── External API Mocks
└── Webhook Processing

E2E Tests (5%):
├── Complete Payment Flow
├── Webhook Delivery
└── Error Scenarios
```

### 6.2 Test Coverage Goals

- **Unit Tests**: > 90% code coverage
- **Integration Tests**: All critical paths
- **Load Tests**: 1000 RPS sustained
- **Chaos Engineering**: Provider failures, network partitions

## 7. Deployment e Escalabilidade

### 7.1 Arquitetura de Deploy

```
Production Setup:
├── Load Balancer (AWS ALB)
├── API Servers (3x instances)
├── Background Workers (2x instances)
├── Database (RDS with read replicas)
├── Cache (Redis Cluster)
└── Monitoring (CloudWatch + Grafana)
```

### 7.2 Estratégias de Escala

- **Horizontal Scaling**: Auto-scaling groups
- **Database**: Read replicas + connection pooling
- **Cache**: Redis cluster with sharding
- **Queue**: SQS/Redis queues for async processing
- **CDN**: CloudFlare for static assets

---

**Versão**: 1.0  
**Data**: 23/09/2025  
**Autor**: Sistema DubPay
