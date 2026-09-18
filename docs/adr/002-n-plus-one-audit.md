# ADR 002 — Auditoria N+1 e preventLazyLoading

**Status:** Aceito  
**Data:** 2026-09-18
**Contexto:** Sprint 19 / #109

---

## Contexto

Esta API expõe várias listagens paginadas — artistas, favoritos, reviews e painel admin — que serializam relações Eloquent (`user`, `styles`, `tags`, imagens, etc.). Sem **eager loading** (`with()`), cada registro da página pode disparar queries extras ao montar o JSON: o clássico problema **N+1** (1 query da listagem + N queries por relação).

O projeto já adota `with()` nos controllers, `whenLoaded` nos Resources e agregações com `withCount`/`withAvg`. Em **local** e **testing**, `preventLazyLoading` transforma lazy load acidental em exceção. Este ADR documenta a auditoria dos endpoints de listagem e o que foi encontrado.

---

## Decisão / estado atual

### preventLazyLoading

Arquivo: `app/Providers/AppServiceProvider.php`

| Ambiente   | Ativo? | Comportamento se lazy load |
| ---------- | ------ | -------------------------- |
| local      | sim | exceção se lazy load |
| testing    | sim | exceção se lazy load |
| production | não | query silenciosa (lazy load permitido) |

### Padrão adotado no projeto

<!-- Como este codebase evita N+1 hoje? -->

- [x] Controllers usam `with([...])` nas listagens
- [x] Resources usam `whenLoaded(...)` onde aplicável
- [x] Agregações via `withCount` / `withAvg` / `withExists` (não loop manual)
- [x] Testes feature cobrem endpoints de listagem

---

## Endpoints auditados

Para cada linha: abra controller + resource, confira `with()` e anote **OK** ou **atenção**.

| Endpoint | Controller | `with()` usado | Resource | Status |
| -------- | ---------- | -------------- | -------- | ------ |
| `GET /api/v1/artists` | `ArtistController@index` | `user`, `styles`, `tags`, `mainImage` | `ArtistResource` | OK |
| `GET /api/v1/artists/{id}` | `ArtistController@show` | `user`, `styles`, `tags`, `images` | `ArtistResource` | OK |
| `GET /api/v1/favorites` | `FavoriteController@index` | `user`, `styles`, `tags`, `images` | `ArtistResource` | OK |
| `GET /api/v1/artists/{id}/reviews` | `ReviewController@index` | `user` | `ReviewResource` | Atenção |
| `GET /api/v1/admin/artists` | `ArtistAdminController@index` | `user`, `styles`, `tags` | `ArtistResource` | OK |
| `GET /api/v1/admin/reports` | `ReportAdminController@index` | `reporter`, `reportable` | `ReportResource` | OK |

---

## Observações

- `GET /artists` (index) usa `mainImage`; `show` usa `images` — mesmo `ArtistResource`, eager load diferente.
- `GET /favorites` carrega `images` (não `mainImage`).
- **Reviews (`ReviewResource`):** controller do `index` faz `with('user')`, mas o Resource acessa `$this->user` direto (sem `whenLoaded`). Funciona no fluxo atual; risco se reutilizar o Resource sem eager load. Refactor futuro: `whenLoaded('user')` + `load('user')` em `store`/`update`/`reply`.
- Agregações (`withAvg`, `withCount`, `withExists`) em artistas/favoritos evitam N+1 de contagem.
- Testes: `php artisan test --compact` → passou (preventLazyLoading ativo em testing).

---

## Consequências

### Positivas

- Listagens paginadas fazem poucas queries por request (eager load + agregações), em vez de 1 + N por relação.
- `whenLoaded` nos Resources evita query extra quando a relação não foi carregada de propósito.
- `preventLazyLoading` em local/testing transforma lazy load acidental em erro visível nos testes.
- Endpoints auditados têm padrão consistente: controller carrega relações, Resource serializa.
- Testes feature passam com essa configuração — regressão de N+1 tende a quebrar CI cedo.

### Negativas / limites

- `ReviewResource` depende do controller carregar `user`; padrão menos defensivo que `ArtistResource` com `whenLoaded`.

---

## Resposta de entrevista (2 min)

> **N+1** é quando listo N registros e, ao serializar, cada um dispara queries de relação — vira 1 query da listagem + N extras. Na listagem de artistas com 20 itens e relações como user, styles e tags, isso escala rápido e sobrecarrega o banco.
>
> **Neste projeto** evitamos com três camadas: `with()` no controller para eager load; `whenLoaded` no Resource para não acessar relação não carregada; e agregações (`withCount`, `withAvg`) em vez de contar em loop. Auditei endpoints como `/artists`, `/favorites`, reviews e admin — a maioria está OK.
>
> **`preventLazyLoading`** está ativo em local e testing: se alguém esquecer o `with()`, estoura exceção nos testes em vez de ir silencioso para produção. Em produção fica desligado para não quebrar request real.
>
> **Ponto de atenção:** `ReviewResource` ainda acessa `user` direto; o controller compensa com `with('user')`. Refactor futuro: migrar para `whenLoaded`, como no `ArtistResource`.
