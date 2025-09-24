# DubPay - Arquitetura

## Componentes

**Controllers:**

-   AuthController: Registro, login, logout
-   PaymentController: Criação, consulta e listagem de pagamentos

**Services:**

-   PaymentService: Orquestração principal
-   PaymentProviderRouter: Seleção de providers
-   Mock Providers: StripeProvider e PayPalProvider

**Models:**

-   Payment: Model principal
-   User: Usuário com relacionamento para pagamentos

## Fluxo de Pagamento

1. Cliente envia POST /payments
2. PaymentService valida e roteia para provider
3. Provider processa e retorna status
4. Sistema armazena resultado no banco

**Status:** pending → processing → success/failed

## Providers

**Stripe Mock:** Taxa 2.9%, falha para valores > $10000  
**PayPal Mock:** Taxa 3.4%, suporta USD/EUR/BRL apenas

## Segurança

-   Autenticação: Laravel Sanctum
-   Validação de entrada nos controllers
-   Webhooks com validação de assinatura
