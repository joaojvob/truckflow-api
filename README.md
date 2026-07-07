# 🚛 TruckFlow API

**API REST para gestão de frotas e logística de transporte rodoviário.**

Backend SaaS multi-tenant construído com Laravel 12, PostgreSQL + PostGIS, projetado para ser consumido por uma aplicação **React (web)** e **Flutter (app mobile)**.

![PHP](https://img.shields.io/badge/PHP-8.4+-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-17-336791?logo=postgresql&logoColor=white)
![PostGIS](https://img.shields.io/badge/PostGIS-3.5-4E9A06)
![Tests](https://img.shields.io/badge/Tests-73%20passed-brightgreen)
![Assertions](https://img.shields.io/badge/Assertions-216-blue)

---

## 📑 Índice

- [Visão Geral](#-visão-geral)
- [Arquitetura](#-arquitetura)
- [Regras de Negócio](#-regras-de-negócio)
- [Fluxo do Workflow](#-fluxo-do-workflow-gestor--motorista)
- [Roles e Permissões](#-roles-e-permissões)
- [Endpoints da API](#-endpoints-da-api)
- [Geolocalização e Rotas](#-geolocalização-e-rotas)
- [Notificações](#-notificações)
- [Enums](#-enums)
- [Estrutura do Projeto](#-estrutura-do-projeto)
- [Setup Local](#-setup-local)
- [Testes](#-testes)
- [Roadmap](#-roadmap)

---

## 🏗 Visão Geral

O **TruckFlow** é uma plataforma SaaS para transportadoras gerenciarem:

- **Fretes** com fluxo completo de atribuição, aceite, documentação e viagem
- **Motoristas** com perfil profissional, CNH e exame de doping
- **Frota** (caminhões e reboques) com status e tipos detalhados
- **Rotas geográficas** com coordenadas PostGIS e integração Google Maps
- **Notificações em tempo real** entre gestor e motorista
- **Auditoria** com log de todas as ações

### Conceito de Uso

| Plataforma | Usuário | Função |
|------------|---------|--------|
| **Web (React)** | Administrador / Gestor | Cria empresa, cadastra motoristas, cria fretes, define rotas, acompanha viagens, aprova documentos |
| **App (Flutter)** | Motorista | Completa o cadastro, aceita/recusa fretes, envia doping e checklist, navega pela rota, reporta incidentes |

---

## 🏛 Arquitetura

```
┌─────────────────────────────────────────────────────────┐
│                    TruckFlow API                        │
├─────────────────────────────────────────────────────────┤
│  Controllers (thin)  →  Services (business logic)       │
│  Form Requests       →  Policies (authorization)        │
│  Resources (output)  →  Notifications (events)          │
├─────────────────────────────────────────────────────────┤
│  Models + Enums + Traits (BelongsToTenant, LogsActivity)│
├─────────────────────────────────────────────────────────┤
│  PostgreSQL 17 + PostGIS 3.5  │  Redis  │  Mailpit      │
└─────────────────────────────────────────────────────────┘
```

### Stack Tecnológico

| Camada | Tecnologia |
|--------|-----------|
| Framework | Laravel 12 (PHP 8.4+) |
| Banco de Dados | PostgreSQL 17 + PostGIS 3.5 |
| Autenticação | Laravel Sanctum (tokens) |
| Cache / Filas | Redis |
| E-mail (dev) | Mailpit |
| Containers | Docker + Laravel Sail |
| Testes | Pest + PHPUnit |
| Geolocalização | PostGIS + Google Maps APIs |

### Padrões Aplicados

- **Multi-tenancy** via Global Scope (`BelongsToTenant` trait)
- **Thin Controllers** — lógica de negócio nos Services
- **Form Requests** para validação e autorização
- **API Resources** para transformação de output
- **Policies** para autorização granular por role
- **Enums** para status, tipos e roles (type-safe)
- **Conventional Commits** no histórico Git
- **Audit Trail** com `LogsActivity` trait

---

## 📋 Regras de Negócio

### 1. Multi-tenancy (Empresa)

- Cada empresa (tenant) opera de forma **completamente isolada** — nenhum dado cruza entre tenants
- O trait `BelongsToTenant` aplica automaticamente um Global Scope que filtra por `tenant_id`
- Toda entidade (frete, caminhão, reboque, usuário, motorista, etc.) pertence a um tenant

### 2. Cadastro e Onboarding

```
GESTOR (Web)                           MOTORISTA (App)
─────────                              ──────────────
1. Cria conta e empresa (tenant)       
2. Cadastra motorista (nome, email,    
   senha temporária, role=driver)      
3. Compartilha credenciais             
                                       4. Faz login no app com credenciais
                                       5. Completa perfil (CNH, telefone,
                                          endereço, dados pessoais)
                                       6. Cadastra caminhão e reboque
```

- O **gestor cria o usuário motorista pela web** com dados básicos (nome, email, senha, role `driver`)
- O motorista recebe as credenciais e **faz login no app Flutter**
- No primeiro acesso, o motorista **completa o cadastro** via `PUT /driver-profile` (CNH, tipo de CNH, telefone, endereço)
- O motorista também cadastra seu caminhão (`POST /trucks`) e reboque (`POST /trailers`)

### 3. Vínculo Gestor ↔ Motorista

- O gestor **vincula motoristas a ele** via `POST /manager/drivers`
- Cada gestor **só tem acesso aos motoristas e fretes vinculados a ele** — não vê dados de outros gestores do mesmo tenant
- O admin vê tudo do tenant, mas o gestor tem **visão restrita** ao que ele criou/gerencia
- Um motorista pode estar vinculado a **múltiplos gestores**

### 4. Fluxo do Frete (Workflow Completo)

O frete passa por **8 estados** controlados por uma máquina de estados rígida:

```
Pending → Assigned → Accepted → Ready → InTransit → Completed
                  ↘ Rejected → Assigned (reatribuir)
         qualquer estado (exceto Completed) → Cancelled
```

**Regras por etapa:**

| Etapa | Quem | O que acontece | Notifica |
|-------|------|----------------|----------|
| **Criar frete** | Gestor | Frete criado com status `Pending`, preço calculado automaticamente | — |
| **Atribuir motorista** | Gestor | Status → `Assigned`, motorista é notificado | 🔔 Motorista |
| **Aceitar frete** | Motorista | Status → `Accepted`, gestor é notificado | 🔔 Gestor |
| **Recusar frete** | Motorista | Status → `Rejected`, gestor notificado com motivo | 🔔 Gestor |
| **Enviar doping** | Motorista | Exame de doping enviado (PDF/imagem), gestor notificado | 🔔 Gestor |
| **Aprovar doping** | Gestor | Exame aprovado/reprovado, motorista notificado | 🔔 Motorista |
| **Enviar checklist** | Motorista | Checklist pré-viagem enviado, gestor notificado | 🔔 Gestor |
| **Liberar viagem** | Gestor | Valida doping ✅ + checklist ✅ → Status `Ready` | 🔔 Motorista |
| **Iniciar viagem** | Motorista | Status → `InTransit`, gestor notificado | 🔔 Gestor |
| **Finalizar viagem** | Motorista | Status → `Completed`, avaliação opcional | 🔔 Gestor |
| **Cancelar frete** | Gestor | Status → `Cancelled` (qualquer estado exceto Completed) | — |

### 5. Pré-requisitos para Iniciar Viagem

O motorista **só pode iniciar a viagem** quando TODOS os requisitos forem atendidos:

- ✅ Status do frete = `Ready`
- ✅ Exame de doping **aprovado** pelo gestor
- ✅ Checklist pré-viagem **enviado**
- ✅ Gestor **liberou a viagem** (`manager_approved = true`)

### 6. Cálculo de Preço do Frete

O preço total é calculado automaticamente:

```
total_price = (price_per_km × distance_km) + (price_per_ton × weight) + toll_cost + fuel_cost
```

### 7. Incidentes e SOS

- O motorista pode reportar **incidentes** durante a viagem (`POST /freights/{id}/incidents`)
- Em emergência, pode acionar **SOS** (`POST /freights/{id}/sos`) — que notifica o gestor imediatamente

---

## 🔄 Fluxo do Workflow (Gestor ↔ Motorista)

```
                         ┌──────────────────────────────────────────┐
                         │              WEB (Gestor)                │
                         │                                         │
                         │  1. Cria frete (Pending)                │
                         │  2. Atribui motorista (→ Assigned)      │
                         │          │                              │
                         │          │  🔔 Notifica motorista       │
                         │          ▼                              │
                         │  ┌───────────────┐                      │
                         │  │ Aguarda       │                      │
                         │  │ resposta      │                      │
                         │  └───────┬───────┘                      │
                         │          │                              │
                         │   🔔 Recebe resposta                    │
                         │          │                              │
                    ┌────┤  Aceito? ├────┐                         │
                    │    │          │    │                          │
                    │    │   SIM    │ NÃO│                         │
                    │    │          │    │                          │
                    │    │          │    └─→ Rejected               │
                    │    │          │       (pode reatribuir)       │
                    │    │          │                               │
                    │    │  🔔 Recebe doping + checklist            │
                    │    │          │                               │
                    │    │  6. Aprova doping                        │
                    │    │  7. Libera viagem (→ Ready)              │
                    │    │          │  🔔 Notifica motorista        │
                    │    │          │                               │
                    │    │  🔔 Recebe "viagem iniciada"             │
                    │    │  🔔 Recebe "viagem finalizada"           │
                    │    └──────────────────────────────────────────┘
                    │
                    │    ┌──────────────────────────────────────────┐
                    │    │              APP (Motorista)             │
                    │    │                                         │
                    │    │  🔔 Recebe notificação do frete         │
                    │    │  3. Aceita ou recusa                    │
                    │    │  4. Envia exame de doping (PDF)         │
                    │    │  5. Envia checklist pré-viagem          │
                    │    │                                         │
                    │    │  🔔 Recebe "viagem liberada"            │
                    │    │  8. Inicia viagem (→ InTransit)         │
                    │    │  9. Finaliza viagem (→ Completed)       │
                    │    └──────────────────────────────────────────┘
```

---

## 👥 Roles e Permissões

### Roles

| Role | Valor | Descrição |
|------|-------|-----------|
| **Admin** | `admin` | Dono da empresa. Acesso total ao tenant |
| **Manager** | `manager` | Gestor de frota. Gerencia motoristas e fretes **vinculados a ele** |
| **Driver** | `driver` | Motorista. Interage apenas com fretes **atribuídos a ele** |

### Matriz de Permissões

| Recurso | Admin | Manager | Driver |
|---------|-------|---------|--------|
| Criar empresa | ✅ | ❌ | ❌ |
| Editar empresa | ✅ | ❌ | ❌ |
| Listar usuários | ✅ | ✅ | ❌ |
| Alterar role de usuário | ✅ | ❌ | ❌ |
| Vincular motorista | ✅ | ✅ | ❌ |
| Criar frete | ✅ | ✅ | ❌ |
| Listar fretes | ✅ (todos) | ✅ (só os dele) | ✅ (só os dele) |
| Atribuir motorista ao frete | ✅ | ✅ (só frete dele) | ❌ |
| Aceitar/recusar frete | ❌ | ❌ | ✅ (só se atribuído) |
| Enviar doping/checklist | ❌ | ❌ | ✅ (só frete dele) |
| Aprovar doping | ✅ | ✅ (só frete dele) | ❌ |
| Liberar viagem | ✅ | ✅ (só frete dele) | ❌ |
| Iniciar/finalizar viagem | ❌ | ❌ | ✅ (só frete dele) |
| Cancelar frete | ✅ | ✅ (só frete dele) | ❌ |
| Deletar frete | ✅ | ❌ | ❌ |
| CRUD caminhão | ✅ (todos) | ✅ | ✅ (só o dele) |
| CRUD reboque | ✅ (todos) | ✅ | ✅ (só o dele) |
| Perfil do motorista | ❌ | ❌ | ✅ (só o dele) |

---

## 🌍 Geolocalização e Rotas

### PostGIS — Armazenamento Geoespacial

O sistema usa **PostGIS 3.5** para armazenar coordenadas de origem e destino como geometria `POINT` (SRID 4326):

```sql
-- Exemplo de armazenamento
origin      = ST_GeomFromText('POINT(-46.6333 -23.5505)', 4326)  -- São Paulo
destination = ST_GeomFromText('POINT(-49.2733 -25.4284)', 4326)  -- Curitiba
```

### Google Maps Platform — APIs Recomendadas

Para integração com geolocalização no **app Flutter** e **web React**, recomendamos as seguintes APIs do Google (com free tier generoso):

| API | Uso | Free Tier |
|-----|-----|-----------|
| **[Directions API](https://developers.google.com/maps/documentation/directions)** | Traçar rota entre origem e destino com waypoints intermediários | US$ 200/mês de crédito (~40.000 requests) |
| **[Maps JavaScript API](https://developers.google.com/maps/documentation/javascript)** | Exibir mapa interativo na web (React) com a rota desenhada | US$ 200/mês de crédito (~28.000 loads) |
| **[Maps SDK for Flutter](https://pub.dev/packages/google_maps_flutter)** | Exibir mapa no app mobile com navegação | Incluso no crédito |
| **[Geocoding API](https://developers.google.com/maps/documentation/geocoding)** | Converter endereço ↔ coordenadas | US$ 200/mês de crédito (~40.000 requests) |
| **[Places API](https://developers.google.com/maps/documentation/places)** | Buscar postos de combustível, pontos de descanso, restaurantes | US$ 200/mês de crédito |

> 💡 **Dica:** O Google oferece **US$ 200/mês de crédito grátis** para todas as APIs do Maps Platform. Para a maioria das transportadoras de pequeno/médio porte, isso cobre 100% do uso mensal.

### Rotas com Waypoints (Pontos de Parada)

O gestor pode definir **waypoints** (pontos de parada obrigatórios ou sugeridos) ao criar o frete:

```jsonc
// POST /api/v1/freights
{
  "origin_address": "São Paulo, SP",
  "destination_address": "Curitiba, PR",
  "origin_lat": -23.5505,
  "origin_lng": -46.6333,
  "destination_lat": -25.4284,
  "destination_lng": -49.2733,
  
  // 🆕 Waypoints definidos pelo gestor
  "waypoints": [
    {
      "type": "fuel_stop",
      "name": "Posto Shell BR-116 km 230",
      "lat": -24.1234,
      "lng": -47.5678,
      "mandatory": true
    },
    {
      "type": "rest_stop",
      "name": "Parada de descanso Registro",
      "lat": -24.4872,
      "lng": -47.8432,
      "mandatory": true
    }
  ],
  "enforce_route": true
}
```

**Regras dos waypoints:**

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `waypoints` | `array` | Lista de pontos de parada na rota |
| `waypoints[].type` | `string` | Tipo: `fuel_stop`, `rest_stop`, `toll`, `delivery_point`, `custom` |
| `waypoints[].name` | `string` | Nome/descrição do ponto |
| `waypoints[].lat` | `float` | Latitude do ponto |
| `waypoints[].lng` | `float` | Longitude do ponto |
| `waypoints[].mandatory` | `bool` | Se `true`, motorista é **obrigado** a passar neste ponto |
| `enforce_route` | `bool` | Se `true`, motorista **deve seguir** a rota exata definida pelo gestor |

**Comportamento no App (motorista):**

| `enforce_route` | Comportamento |
|-----------------|--------------|
| `true` | App exibe a rota do gestor como **fixa**. Motorista não pode alterar. Navegação segue waypoints obrigatórios. |
| `false` | Motorista pode **traçar rota alternativa** e adicionar seus próprios waypoints (postos preferidos, paradas de descanso, etc.). |

**Comportamento na Web (gestor):**

- Vê o mapa em tempo real com a posição do motorista
- Visualiza se o motorista seguiu a rota definida
- Mapa mostra os waypoints com ícones diferenciados por tipo (🛢️ posto, 🛏️ descanso, 🔄 pedágio)

> ✅ **Status de implementação:** Waypoints, `enforce_route`, check-in/check-out e reorder já estão implementados na API. A integração com **Google Maps** (Directions/Places) permanece planejada para as próximas iterações (veja [Roadmap](#-roadmap)).

---

## 🔔 Notificações

O sistema usa **Laravel Database Notifications** (tabela `notifications`) para comunicação entre gestor e motorista. Todas as interações do workflow geram notificações automáticas.

| Notificação | Destinatário | Trigger |
|------------|--------------|---------|
| `FreightAssigned` | 🚗 Motorista | Gestor atribui frete ao motorista |
| `FreightDriverResponded` | 📋 Gestor | Motorista aceita ou recusa o frete |
| `DopingTestSubmitted` | 📋 Gestor | Motorista envia exame de doping |
| `DopingTestReviewed` | 🚗 Motorista | Gestor aprova/reprova o doping |
| `ChecklistSubmitted` | 📋 Gestor | Motorista envia checklist pré-viagem |
| `FreightApproved` | 🚗 Motorista | Gestor libera a viagem ("Viagem liberada!") |
| `FreightStatusChanged` | 📋 Gestor | Motorista inicia ou finaliza viagem |

### Endpoints de Notificação

| Método | Rota | Descrição |
|--------|------|-----------|
| `GET` | `/notifications` | Listar todas as notificações |
| `GET` | `/notifications/unread` | Listar não lidas (com contador) |
| `POST` | `/notifications/{id}/read` | Marcar uma como lida |
| `POST` | `/notifications/read-all` | Marcar todas como lidas |

---

## 📡 Endpoints da API

**Base URL:** `http://localhost/api/v1`  
**Autenticação:** Bearer Token (Laravel Sanctum)  
**Total de rotas:** 46

### Autenticação

| Método | Rota | Descrição | Auth |
|--------|------|-----------|------|
| `POST` | `/login` | Login (retorna token Sanctum) | ❌ |
| `POST` | `/register` | Registrar novo usuário | ❌ |
| `POST` | `/logout` | Logout (revoga token) | ✅ |
| `GET` | `/me` | Dados do usuário autenticado | ✅ |

### Empresa (Tenant)

| Método | Rota | Descrição | Quem |
|--------|------|-----------|------|
| `POST` | `/tenant` | Criar empresa | Qualquer autenticado (sem tenant) |
| `GET` | `/tenant` | Ver minha empresa | Admin / Manager |
| `PUT` | `/tenant` | Atualizar empresa | Admin |

### Usuários

| Método | Rota | Descrição | Quem |
|--------|------|-----------|------|
| `GET` | `/users` | Listar usuários do tenant | Admin / Manager |
| `GET` | `/users/{user}` | Ver detalhes do usuário | Admin / Manager |
| `PATCH` | `/users/{user}/role` | Alterar role | Admin |

### Perfil do Motorista

| Método | Rota | Descrição | Quem |
|--------|------|-----------|------|
| `GET` | `/driver-profile` | Ver meu perfil | Driver |
| `PUT` | `/driver-profile` | Criar/atualizar perfil | Driver |

### Fretes — CRUD

| Método | Rota | Descrição | Quem |
|--------|------|-----------|------|
| `GET` | `/freights` | Listar fretes (filtrado por role) | Todos |
| `POST` | `/freights` | Criar frete | Admin / Manager |
| `GET` | `/freights/{freight}` | Ver detalhes do frete | Dono / Atribuído |
| `PUT` | `/freights/{freight}` | Atualizar frete | Admin / Manager (dono) |
| `DELETE` | `/freights/{freight}` | Deletar frete | Admin |
| `POST` | `/freights/{freight}/cancel` | Cancelar frete | Admin / Manager (dono) |

### Fretes — Workflow

| Método | Rota | Descrição | Quem |
|--------|------|-----------|------|
| `POST` | `/freights/{freight}/assign` | Atribuir motorista | Manager (dono do frete) |
| `POST` | `/freights/{freight}/accept` | Aceitar frete | Driver (atribuído) |
| `POST` | `/freights/{freight}/reject` | Recusar frete | Driver (atribuído) |
| `POST` | `/freights/{freight}/doping` | Enviar exame de doping (upload) | Driver (atribuído) |
| `POST` | `/freights/{freight}/doping/{dopingTest}/review` | Aprovar/reprovar doping | Manager (dono do frete) |
| `POST` | `/freights/{freight}/checklist` | Enviar checklist pré-viagem | Driver (atribuído) |
| `POST` | `/freights/{freight}/approve` | Liberar viagem | Manager (dono do frete) |
| `POST` | `/freights/{freight}/start` | Iniciar viagem | Driver (atribuído) |
| `POST` | `/freights/{freight}/complete` | Finalizar viagem | Driver (atribuído) |

### Incidentes / SOS

| Método | Rota | Descrição | Quem |
|--------|------|-----------|------|
| `POST` | `/freights/{freight}/incidents` | Reportar incidente | Driver |
| `POST` | `/freights/{freight}/sos` | Acionar SOS (emergência) | Driver |

### Gestão Gestor ↔ Motorista

| Método | Rota | Descrição | Quem |
|--------|------|-----------|------|
| `GET` | `/manager/drivers` | Listar meus motoristas | Manager |
| `POST` | `/manager/drivers` | Vincular motorista a mim | Manager |
| `DELETE` | `/manager/drivers/{driver}` | Desvincular motorista | Manager |

### Notificações

| Método | Rota | Descrição | Quem |
|--------|------|-----------|------|
| `GET` | `/notifications` | Listar todas as notificações | Todos |
| `GET` | `/notifications/unread` | Não lidas + contador | Todos |
| `POST` | `/notifications/{id}/read` | Marcar como lida | Todos |
| `POST` | `/notifications/read-all` | Marcar todas como lidas | Todos |

### Frota — Caminhões

| Método | Rota | Descrição | Quem |
|--------|------|-----------|------|
| `GET` | `/trucks` | Listar caminhões | Todos |
| `POST` | `/trucks` | Cadastrar caminhão | Driver |
| `GET` | `/trucks/{truck}` | Ver detalhes | Dono |
| `PUT` | `/trucks/{truck}` | Atualizar caminhão | Dono |
| `DELETE` | `/trucks/{truck}` | Deletar caminhão | Admin |

### Frota — Reboques

| Método | Rota | Descrição | Quem |
|--------|------|-----------|------|
| `GET` | `/trailers` | Listar reboques | Todos |
| `POST` | `/trailers` | Cadastrar reboque | Driver |
| `GET` | `/trailers/{trailer}` | Ver detalhes | Dono |
| `PUT` | `/trailers/{trailer}` | Atualizar reboque | Dono |
| `DELETE` | `/trailers/{trailer}` | Deletar reboque | Admin |

---

## 🏷 Enums

### FreightStatus (Status do Frete)

| Valor | Label | Descrição |
|-------|-------|-----------|
| `pending` | Pendente | Frete criado, sem motorista atribuído |
| `assigned` | Atribuído ao motorista | Gestor atribuiu, aguardando resposta |
| `accepted` | Aceito pelo motorista | Motorista aceitou, enviando documentos |
| `ready` | Liberado para viagem | Doping ✅ + Checklist ✅ + Gestor liberou |
| `in_transit` | Em Trânsito | Viagem em andamento |
| `completed` | Concluído | Viagem finalizada |
| `cancelled` | Cancelado | Frete cancelado pelo gestor |
| `rejected` | Recusado pelo motorista | Motorista recusou (pode ser reatribuído) |

### Transições Válidas

```
Pending   → Assigned, Cancelled
Assigned  → Accepted, Rejected, Cancelled
Accepted  → Ready, Cancelled
Ready     → InTransit, Cancelled
InTransit → Completed
Rejected  → Assigned, Cancelled
Completed → (estado final)
Cancelled → (estado final)
```

### UserRole

| Valor | Label |
|-------|-------|
| `admin` | Administrador |
| `manager` | Gerente (Gestor) |
| `driver` | Motorista |

### TruckStatus

| Valor | Label |
|-------|-------|
| `available` | Disponível |
| `in_use` | Em Uso |
| `maintenance` | Em Manutenção |
| `inactive` | Inativo |

### TrailerType

| Valor | Label | Carga Máxima |
|-------|-------|-------------|
| `flatbed` | Prancha | 28t |
| `refrigerated` | Baú Frigorífico | 24t |
| `dry_van` | Baú Seco | 26t |
| `tanker` | Tanque | 30t |
| `sider` | Sider | 26t |
| `hopper` | Graneleiro | 32t |
| `container` | Porta-Contêiner | 28t |
| `logging` | Florestal | 35t |
| `lowboy` | Prancha Rebaixada | 40t |
| `livestock` | Boiadeiro | 20t |

### DopingStatus

| Valor | Label |
|-------|-------|
| `pending` | Pendente |
| `approved` | Aprovado |
| `rejected` | Reprovado |

### DriverResponse

| Valor | Label |
|-------|-------|
| `pending` | Pendente |
| `accepted` | Aceito |
| `rejected` | Recusado |

---

## 📂 Estrutura do Projeto

```
app/
├── Enums/                          # Enums type-safe (PHP 8.1+)
│   ├── DopingStatus.php
│   ├── DriverResponse.php
│   ├── FreightStatus.php           # 8 estados + máquina de transição
│   ├── TrailerType.php             # 10 tipos brasileiros
│   ├── TruckStatus.php
│   ├── UserRole.php
│   └── WaypointType.php
├── Http/
│   ├── Controllers/Api/
│   │   ├── AuthController.php
│   │   ├── DriverProfileController.php
│   │   ├── FreightController.php           # CRUD de fretes
│   │   ├── FreightWorkflowController.php   # Workflow gestor ↔ motorista
│   │   ├── IncidentController.php
│   │   ├── ManagerDriverController.php     # Vínculo gestor ↔ motorista
│   │   ├── NotificationController.php
│   │   ├── TenantController.php
│   │   ├── TrailerController.php
│   │   ├── TruckController.php
│   │   ├── UserController.php
│   │   └── WaypointController.php          # CRUD + check-in/check-out de waypoints
│   ├── Requests/                           # 17 Form Requests
│   └── Resources/                          # API Resources
├── Models/
│   ├── ActivityLog.php                     # Audit trail
│   ├── Checklist.php                       # Checklist pré-viagem
│   ├── DopingTest.php                      # Exame de doping
│   ├── DriverProfile.php                   # Perfil do motorista (CNH)
│   ├── Freight.php                         # Frete (entidade principal)
│   ├── Tenant.php                          # Empresa
│   ├── Trailer.php                         # Reboque
│   ├── Truck.php                           # Caminhão
│   ├── User.php                            # Usuário (admin/manager/driver)
│   └── Waypoint.php                        # Ponto de parada na rota
├── Notifications/                          # 7 notificações do workflow
│   ├── ChecklistSubmitted.php
│   ├── DopingTestReviewed.php
│   ├── DopingTestSubmitted.php
│   ├── FreightApproved.php
│   ├── FreightAssigned.php
│   ├── FreightDriverResponded.php
│   └── FreightStatusChanged.php
├── Policies/                               # Autorização granular por role
│   ├── FreightPolicy.php
│   ├── TrailerPolicy.php
│   ├── TruckPolicy.php
│   └── WaypointPolicy.php
├── Services/                               # Lógica de negócio
│   ├── DriverProfileService.php
│   ├── FreightManagementService.php        # CRUD + cálculo de preço
│   ├── FreightService.php
│   ├── FreightWorkflowService.php          # Orquestração do workflow
│   ├── TenantService.php
│   ├── TrailerService.php
│   ├── TruckService.php
│   └── WaypointService.php                 # CRUD + reorder de waypoints
└── Traits/
    ├── ApiResponser.php
    ├── BelongsToTenant.php                 # Multi-tenancy (Global Scope)
    └── LogsActivity.php                    # Audit trail automático

database/
├── factories/                              # 7 factories com dados BR
├── migrations/                             # 14 migrações
└── seeders/                                # Seeder completo (2 empresas)

tests/Feature/
├── Auth/AuthenticationTest.php             # 6 testes
├── DriverProfile/DriverProfileTest.php     # 4 testes
├── Freight/FreightCrudTest.php             # 12 testes
├── Freight/FreightWorkflowTest.php         # 17 testes (inclui E2E)
├── Tenant/TenantTest.php                   # 5 testes
├── Trailer/TrailerCrudTest.php             # 6 testes
├── Truck/TruckCrudTest.php                 # 7 testes
└── Waypoint/WaypointCrudTest.php           # 14 testes
```

---

## 🚀 Setup Local

### Pré-requisitos

- [Docker](https://www.docker.com/) e Docker Compose
- Git

### Instalação

```bash
# 1. Clonar o repositório
git clone https://github.com/joaojvob/truckflow-api.git
cd truckflow-api

# 2. Copiar variáveis de ambiente
cp .env.example .env

# 3. Instalar dependências via container
docker run --rm \
  -v $(pwd):/var/www/html \
  -w /var/www/html \
  laravelsail/php84-composer:latest \
  composer install --ignore-platform-reqs

# 4. Subir os containers
./vendor/bin/sail up -d

# 5. Gerar chave da aplicação
./vendor/bin/sail artisan key:generate

# 6. Rodar migrações + seed
./vendor/bin/sail artisan migrate:fresh --seed
```

### Serviços Disponíveis

| Serviço | URL | Descrição |
|---------|-----|-----------|
| **API** | http://localhost | Endpoints REST |
| **Mailpit** | http://localhost:8025 | Visualizar e-mails enviados |
| **PostgreSQL** | localhost:5432 | Banco de dados |
| **Redis** | localhost:6379 | Cache e filas |

### Variáveis de Ambiente

```env
# Banco de dados
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=truckflow
DB_USERNAME=sail
DB_PASSWORD=password

# Cache e filas
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Google Maps (para integração futura)
# GOOGLE_MAPS_API_KEY=sua-chave-aqui
```

### Usuários do Seed

Após rodar `migrate:fresh --seed`, os seguintes usuários são criados:

| Email | Senha | Role | Empresa |
|-------|-------|------|---------|
| `admin@alpha.com` | `password` | admin | Transportadora Alpha |
| `gerente@alpha.com` | `password` | manager | Transportadora Alpha |
| `admin@beta.com` | `password` | admin | Logística Beta |
| Motoristas (factory) | `password` | driver | Alpha / Beta |

---

## 🧪 Testes

```bash
# Rodar todos os testes
./vendor/bin/sail artisan test

# Com cobertura de código
./vendor/bin/sail artisan test --coverage

# Filtrar por arquivo
./vendor/bin/sail artisan test --filter=FreightWorkflowTest

# Filtrar por teste específico
./vendor/bin/sail artisan test --filter=test_complete_workflow_e2e
```

### Resultado Atual

```
✓ 73 testes passando (216 assertions)
✓ Duração: ~9s
```

| Suite | Testes | Descrição |
|-------|--------|-----------|
| `AuthenticationTest` | 6 | Login, registro, logout, perfil |
| `DriverProfileTest` | 4 | CRUD do perfil do motorista |
| `FreightCrudTest` | 12 | CRUD de fretes + escopo por role |
| `FreightWorkflowTest` | 17 | Workflow completo + teste E2E ponta-a-ponta |
| `TenantTest` | 5 | CRUD da empresa |
| `TrailerCrudTest` | 6 | CRUD de reboques |
| `TruckCrudTest` | 7 | CRUD de caminhões |
| `WaypointCrudTest` | 14 | CRUD de waypoints + enforce_route + check-in/out |
| `ExampleTest` | 2 | Smoke tests |
| `UnitTest` | 1 | Unit test básico |

> O teste `test_complete_workflow_e2e` executa o fluxo completo: criar frete → atribuir → aceitar → enviar doping → enviar checklist → aprovar doping → liberar viagem → iniciar → finalizar. Validando o estado final de todos os campos.

---

## 🗺 Roadmap

### ✅ Implementado

- [x] Multi-tenancy com Global Scope isolando dados por empresa
- [x] Autenticação via Laravel Sanctum (token bearer)
- [x] Cadastro de motorista pelo gestor (web) + onboarding pelo app
- [x] CRUD completo de fretes com cálculo automático de preço
- [x] CRUD de caminhões e reboques (10 tipos brasileiros)
- [x] Perfil do motorista (CNH, tipo, telefone, endereço)
- [x] Workflow completo gestor ↔ motorista (8 estados com máquina de transição)
- [x] Exame de doping (upload PDF/imagem + aprovação pelo gestor)
- [x] Checklist pré-viagem (envio + validação)
- [x] Pré-requisitos obrigatórios para iniciar viagem (doping ✅ + checklist ✅ + aprovação ✅)
- [x] Sistema de notificações database (7 tipos de notificação)
- [x] Vínculo gestor ↔ motorista (tabela pivot `manager_driver`)
- [x] Políticas de autorização granular por role
- [x] Audit trail completo (log de todas as ações)
- [x] Incidentes e SOS durante a viagem
- [x] Coordenadas geográficas com PostGIS (origem/destino como POINT)
- [x] Waypoints e rotas — CRUD, `enforce_route`, check-in/check-out e reorder
- [x] 73 testes automatizados (216 assertions, incluindo E2E)
- [x] Conventional Commits (52 commits)
- [x] Documentação completa (README)

### 🔜 Próximas Iterações

- [ ] **Integração Google Directions API** — Traçar rota com paradas intermediárias
- [ ] **Integração Google Places API** — Buscar postos de combustível, restaurantes, pontos de descanso
- [ ] **Tracking GPS em tempo real** — Posição do motorista atualizada periodicamente
- [ ] **WebSocket / Pusher** — Notificações push em tempo real (web + app)
- [ ] **Upload de documentos** — CNH, CRLV, apólice de seguro
- [ ] **Relatórios financeiros** — Faturamento por período, motorista, rota
- [ ] **Dashboard com métricas** — Fretes ativos, receita, km rodados, tempo médio
- [ ] **Exportar relatórios** — PDF e Excel
- [ ] **Integração ANTT** — Consulta de habilitação e RNTRC
- [ ] **Rate limiting por tenant** — Controle de uso da API
- [ ] **API v2** — Versionamento e breaking changes

---

## 📄 Licença

Este projeto é software proprietário. Todos os direitos reservados.

---

<p align="center">
  Feito com ❤️ para o transporte rodoviário brasileiro 🇧🇷
</p>
