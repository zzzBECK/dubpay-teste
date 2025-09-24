-- Script de inicialização do PostgreSQL para DubPay
-- Este script é executado automaticamente quando o container é criado

-- Criar extensões necessárias
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- Configurações adicionais
ALTER DATABASE dubpay SET timezone TO 'UTC';