# TruckFlow — Brief para Frontend Web (Gestor/Admin)
---

## 1. Visão do produto

**TruckFlow** é um SaaS de logística rodoviária inspirado na experiência do **Euro Truck Simulator (ETS)**:

- Gestão de **transportadora** (empresa/frota)
- **Fretes** com origem/destino, rotas, paradas, preço e workflow realista
- **Motoristas** com CNH, documentos, checklist, doping (simulação operacional)
- **Mapa ao vivo** com rota, waypoints, postos (Places) e posição GPS
- Tom **profissional + imersivo** (logística brasileira, BR-116, postos, descanso)

> **Não é** integração regulatória real com ANTT no MVP. RNTRC/ANTT no roadmap é *flavor* futuro (validação fictícia ou bureau), não bloqueio do frontend.

**Escopo deste brief:** painel **web** para **Admin** e **Gestor (Manager)**.  
App **mobile Flutter** para motorista vem depois (repo separado).

---

## 2. Repositórios

| Repo | Conteúdo |
|------|----------|
| `truckflow-api` | Backend Laravel 12 — **este repo** |
| `truckflow-web` (a criar) | Frontend React — painel gestor |

A API é **REST pura**. Sem Vite/npm neste repo.

---

## 3. Stack recomendada (web)

| Camada | Sugestão |
|--------|----------|
| Framework | **React 18+** + **TypeScript** |
| Build | **Vite** |
| Roteamento | **React Router** v6+ |
| HTTP | **Axios** ou `fetch` + wrapper |
| Estado servidor | **TanStack Query** (React Query) |
| Estado UI local | Zustand ou Context (auth) |
| UI | **shadcn/ui** + Tailwind CSS |
| Formulários | **React Hook Form** + **Zod** |
| Mapas | **Google Maps JavaScript API** (polyline da API + markers) |
| WebSocket | **Laravel Echo** + **pusher-js** (protocolo Reverb) |
| i18n | pt-BR nativo |

---

## 4. Ambiente local

```bash
# API (Sail)
cd truckflow-api
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate:fresh --seed
```

| Serviço | URL |
|---------|-----|
| API REST | `http://localhost/api/v1` |
| OpenAPI (Scramble) | `http://localhost/docs/api` |
| OpenAPI JSON | `http://localhost/docs/api.json` |
| Health | `http://localhost/up` |
| Mailpit | `http://localhost:8025` |
| Reverb WebSocket | `ws://localhost:8080` |

### Contas de teste (seed)

| Papel | E-mail | Senha |
|-------|--------|-------|
| Admin | `admin@alpha.com` | `password` |
| Gestor | `gerente@alpha.com` | `password` |
| Motorista | `motorista@alpha.com` | `password` |

Empresa: **Transportadora Alpha** (`slug: transportadora-alpha`).

---

## 5. Autenticação (Laravel Sanctum)

### Login

```http
POST /api/v1/login
Content-Type: application/json

{ "email": "gerente@alpha.com", "password": "password" }
```

**Resposta:**

```json
{
  "user": { "id": 2, "name": "...", "email": "...", "role": "manager", "tenant_id": 1 },
  "token": "1|xxxxxxxx"
}
```

### Requisições autenticadas

```http
Authorization: Bearer {token}
Accept: application/json
```

### Outros

| Método | Rota | Descrição |
|--------|------|-----------|
| POST | `/register` | Cadastro (usuário sem tenant) |
| POST | `/logout` | Revoga token atual |
| GET | `/me` | Usuário + tenant |

### Fluxo onboarding web

1. `POST /register` → recebe token
2. `POST /tenant` → cria empresa (só se `tenant_id` null)
3. Redireciona para dashboard

---

## 6. Papéis e permissões (web)

| Recurso | Admin | Manager | Driver |
|---------|-------|---------|--------|
| Dashboard / relatórios | ✅ tenant inteiro | ✅ só fretes que criou | ❌ |
| CRUD fretes | ✅ | ✅ (próprios) | ❌ criar |
| Workflow (atribuir, aprovar) | ✅ | ✅ (próprios) | ❌ no web* |
| Ver tracking GPS | ✅ | ✅ | — |
| Usuários / motoristas | ✅ | ✅ vinculados | ❌ |
| Frota (trucks/trailers) | ✅ | ✅ ver | cadastro motorista |
| CNH de motorista (download) | ✅ | ✅ | próprio no app |

\* Motorista usa **app mobile** depois; no web o gestor apenas **monitora**.

---

## 7. Workflow do frete (máquina de estados)

```
pending → assigned → accepted → ready → in_transit → completed
              ↓           ↑
           rejected ──────┘
              ↓
          cancelled (vários pontos)
```

### Ações do gestor (web)

| Status atual | Ação | Endpoint |
|--------------|------|----------|
| pending | Atribuir motorista | `POST /freights/{id}/assign` `{ "driver_id": N }` |
| accepted | Aprovar doping | `POST /freights/{id}/doping/{test}/review` |
| accepted | Liberar viagem | `POST /freights/{id}/approve` |
| — | Calcular rota | `POST /freights/{id}/route` |
| — | CRUD waypoints | `/freights/{id}/waypoints` |

### Ações do motorista (referência — app futuro)

`accept`, `reject`, `doping`, `checklist`, `start`, `complete`, `tracking`, `sos`

O **painel web** deve exibir status e permitir ações de **gestor**; opcionalmente simular motorista em dev.

---

## 8. Endpoints cruciais (gestor)

### Fretes

| Método | Rota | Notas |
|--------|------|-------|
| GET | `/freights` | Lista paginada (filtrada por role) |
| POST | `/freights` | Criar com origem/destino lat/lng |
| GET | `/freights/{id}` | Detalhe + rota resumida |
| PUT | `/freights/{id}` | Atualizar |
| POST | `/freights/{id}/cancel` | Cancelar |

**Criar frete (exemplo):**

```json
{
  "cargo_name": "Soja em grãos",
  "weight": 25.5,
  "origin_lat": -23.5505,
  "origin_lng": -46.6333,
  "origin_address": "São Paulo, SP",
  "destination_lat": -25.4284,
  "destination_lng": -49.2733,
  "destination_address": "Curitiba, PR",
  "distance_km": 400,
  "price_per_km": 5.50,
  "enforce_route": false,
  "waypoints": [
    { "type": "fuel_stop", "name": "Posto Shell", "lat": -24.1, "lng": -47.2, "mandatory": true }
  ]
}
```

### Rota e mapa

| Método | Rota | Notas |
|--------|------|-------|
| GET | `/freights/{id}/route` | `polyline`, distância, duração |
| POST | `/freights/{id}/route` | Chama Google Directions (requer `GOOGLE_MAPS_API_KEY` no backend) |

Renderizar `route_polyline` no Google Maps JS.

### Places (postos, restaurantes)

```http
POST /freights/{id}/places/search
{ "lat": -23.55, "lng": -46.63, "type": "gas_station", "radius_meters": 5000 }
```

Tipos: `gas_station`, `restaurant`, `rest_stop`, `lodging`, `car_repair`.

### Tracking GPS (tempo real)

| Método | Rota | Quem envia |
|--------|------|------------|
| GET | `/freights/{id}/tracking` | Última posição |
| GET | `/freights/{id}/tracking/history` | Histórico |
| POST | `/freights/{id}/tracking` | Motorista (app) |

**Web:** mapa com marcador atual + trail do histórico; subscribe WebSocket para updates.

### Relatórios

| Método | Rota | Resposta |
|--------|------|----------|
| GET | `/reports/dashboard` | Métricas agregadas |
| GET | `/reports/financial?from=&to=` | JSON detalhado |
| GET | `/reports/financial/export?format=pdf\|xlsx&from=&to=` | Download arquivo |

### Documentos

| Método | Rota | Body |
|--------|------|------|
| GET | `/users/{user}/cnh` | Download CNH (gestor) |
| GET | `/trucks/{id}/crlv` | Download CRLV |
| GET | `/trailers/{id}/crlv` | Download CRLV |

Upload de CNH/CRLV é feito pelo **motorista** (app); web só **visualiza/baixa**.

### Notificações

| Método | Rota |
|--------|------|
| GET | `/notifications/unread` |
| POST | `/notifications/read-all` |

Tipos: frete atribuído, motorista respondeu, doping, checklist, viagem liberada, status mudou.

### Gestor ↔ Motoristas

| Método | Rota |
|--------|------|
| GET | `/manager/drivers` |
| POST | `/manager/drivers` `{ "driver_id": N }` |
| DELETE | `/manager/drivers/{driver}` |

---

## 9. WebSocket (Laravel Reverb)

Canal privado (autenticar via Sanctum + `/broadcasting/auth`):

```
private-tenant.{tenantId}.freight.{freightId}
```

| Evento | Payload principal |
|--------|-------------------|
| `driver.location.updated` | `lat`, `lng`, `speed_kmh`, `freight_id` |
| `freight.sos.triggered` | `incident_id`, `type`, `message` |

**Config Echo (frontend):**

```env
VITE_REVERB_APP_KEY=...
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

Usar `authEndpoint: 'http://localhost/broadcasting/auth'` com header Bearer.

---

## 10. Enums principais (TypeScript)

```typescript
type UserRole = 'admin' | 'manager' | 'driver';

type FreightStatus =
  | 'pending' | 'assigned' | 'accepted' | 'ready'
  | 'in_transit' | 'completed' | 'cancelled' | 'rejected';

type WaypointType = 'fuel_stop' | 'rest_stop' | 'loading' | 'unloading' | 'custom';
type PlaceType = 'gas_station' | 'restaurant' | 'rest_stop' | 'lodging' | 'car_repair';
type TrailerType = 'flatbed' | 'refrigerated' | 'tank' | 'container' | /* ver API */;
```

Gerar tipos a partir de `/docs/api.json` quando possível.

---

## 11. Telas sugeridas (MVP web)

### Prioridade 1

1. **Login / Registro / Criar empresa**
2. **Dashboard** — cards de `/reports/dashboard` + gráfico receita
3. **Lista de fretes** — filtros por status, busca
4. **Detalhe do frete** — mapa, workflow actions, timeline de status
5. **Criar/editar frete** — formulário + mapa clicável origem/destino

### Prioridade 2

6. **Mapa ao vivo** — frete `in_transit` + Echo + histórico GPS
7. **Waypoints** — lista, reorder drag-and-drop → `POST .../waypoints/reorder`
8. **Motoristas** — lista, vincular, ver perfil/CNH
9. **Frota** — trucks/trailers, status, CRLV download
10. **Relatórios** — tabela financeira + botões export PDF/XLSX
11. **Notificações** — sino no header

### Prioridade 3

12. **Aprovação doping** — viewer PDF/imagem inline
13. **Places** — buscar postos perto da rota no mapa
14. **Configurações** — dados da empresa (`/tenant`)

---

## 12. UX / identidade (ETS-inspired)

- Paleta escura opcional (cockpit/caminhão), acentos âmbar/verde estrada
- Ícones: caminhão, rota, posto, CNH, combustível
- Mapa é **hero** nas telas de frete
- Feedback sonoro opcional (notificação SOS) — desligável
- Linguagem pt-BR; distâncias em km; moeda R$

---

## 13. CORS e proxy (dev)

Se o frontend rodar em `http://localhost:5173`:

- Configurar proxy Vite → `http://localhost` **ou**
- Habilitar CORS no Laravel (`config/cors.php`) para origem do Vite

Sanctum stateful domains se usar cookies (recomendado: **Bearer token** em localStorage/memory).

---

## 14. Rate limiting

120 req/min por tenant (`API_RATE_LIMIT_PER_MINUTE`). Tratar HTTP 429 com retry/backoff no cliente.

---

## 15. Erros da API (padrão)

```json
{ "message": "Dados inválidos.", "errors": { "campo": ["..."] } }
```

Status comuns: `401` não autenticado, `403` sem permissão, `404` não encontrado, `422` validação.

---

## 16. O que NÃO fazer no web (deixar para app)

- Envio GPS contínuo (`POST /tracking`)
- Check-in em waypoint pelo motorista
- Checklist / doping upload (motorista)
- SOS trigger

O web **monitora** essas ações via API + WebSocket.

---

## 17. Checklist de entrega MVP web

- [ ] Auth + persistência token
- [ ] Guard de rotas por role (só admin/manager)
- [ ] CRUD fretes + workflow gestor
- [ ] Mapa com rota calculada
- [ ] Dashboard + export relatório
- [ ] Lista motoristas + vínculo
- [ ] Notificações
- [ ] Tracking mapa (read + websocket)
- [ ] OpenAPI como referência viva (`/docs/api`)

---

## 18. Referências no backend

- README completo: `/README.md`
- Testes de contrato: `/tests/Feature/`
- Seeds: `/database/seeders/DemoDataSeeder.php`

**Contato API:** este documento + Scramble em `http://localhost/docs/api`.
