# ADR 001 — Cache de métricas admin com TTL

**Status:** Aceito  
**Data:** 2026-09-17  
**Contexto:** Sprint 19 / #108

---

## Contexto

O endpoint `GET /api/v1/admin/metrics` (`MetricsController`) retorna sete agregações sobre o banco:

- `total_artists`, `active_artists`, `inactive_artists`
- `total_reviews`, `reviews_this_month`
- `total_favorites`, `favorites_this_month`

Cada request executa múltiplos `COUNT` em tabelas que crescem com o uso (reviews, favoritos, perfis de artista). Vários admins abrindo o dashboard ao mesmo tempo repetiriam essas queries sem proteção.

Esse endpoint é **interno** (admin). Clientes finais não consomem esses números.

---

## Decisão

Usar `Cache::remember` com:

- **Chave:** `metrics_{Y_m}` (ex.: `metrics_2026_09`) — inclui ano e mês correntes
- **TTL:** 60 segundos

```php
$cacheKey = 'metrics_'.now()->format('Y_m');
$cachedResults = Cache::remember($cacheKey, 60, function () { /* counts */ });
```

Não há `Cache::forget` nos fluxos que alteram reviews, favoritos ou artistas.

---

## Comparação: catálogo styles/tags

Styles e tags seguem estratégia **diferente**, e isso é intencional:

| Aspecto | Métricas (`MetricsController`) | Catálogo (`StyleController`, `TagController`) |
|--------|--------------------------------|-----------------------------------------------|
| Quem lê | Admin (dashboard) | Clientes / app (listagem pública) |
| Cache na leitura | TTL 60s | TTL 3600s (1h) |
| Invalidação na escrita | Não | Sim — `Cache::forget('styles')` / `Cache::forget('tags')` nos controllers admin |
| Dado stale | Aceitável (até ~60s) | Inaceitável após CRUD admin |

O catálogo invalida na hora porque um admin que cria ou edita um style/tag espera que a API pública reflita a mudança imediatamente. Métricas são indicadores operacionais; pequeno atraso não quebra fluxo de usuário.

---

## Por que TTL e não invalidação em todo write?

1. **Muitos pontos de escrita** — reviews, favoritos, cadastros e ativações de artista alterariam métricas; espalhar `Cache::forget` aumenta risco de esquecer um caminho e complexidade de manutenção.
2. **Stale tolerável** — dashboard admin não exige precisão em tempo real; 60s de atraso é aceitável para counts agregados.
3. **Simplicidade** — uma única chave, um único TTL, lógica concentrada no controller de métricas.
4. **Proteção ao banco** — evita rajadas de `COUNT` quando vários admins atualizam o painel.

---

## Trade-offs

### Prós

- Implementação simples e localizada
- Reduz carga no banco em acessos repetidos ao dashboard
- Chave mensal (`Y_m`) isola naturalmente o contexto “deste mês” sem invalidação manual

### Contras

- Números podem ficar desatualizados por até 60 segundos
- Após mudança relevante (ex.: pico de reviews), admin pode ver total “antigo” brevemente
- Não há garantia de consistência forte entre métricas e estado real do banco

---

## Alternativas consideradas

| Alternativa | Por que não (por enquanto) |
|-------------|----------------------------|
| Invalidar cache em cada `Review`, `Favorite`, `ArtistProfile` | Alto acoplamento; muitos arquivos; ganho marginal para uso admin |
| TTL maior (ex.: 5–15 min) | Stale mais visível; 60s equilibra frescor e performance |
| TTL zero / sem cache | Funciona em dev; em produção com tráfego admin, queries repetidas |
| Tempo real (websockets, polling) | Overkill para MVP; sem requisito de produto |

---

## Quando revisar esta decisão

Reavaliar se:

- Métricas passarem a ser **produto** (ex.: números públicos, billing por uso)
- SLA exigir dados **frescos** (< 5s)
- Volume de writes tornar TTL de 60s insuficiente para “sentir” atualizações

Nesses casos, considerar invalidação seletiva, TTL menor, ou fila que recalcula métricas de forma assíncrona.

---

## Resposta de entrevista (2 min)

> Usamos **TTL de 60s** nas métricas porque são agregações pesadas, consumidas só por admin, e pequeno atraso é aceitável — invalidar em cada review/favorito espalharia lógica demais. Em **styles/tags**, o cliente vê o catálogo: usamos cache longo na leitura e **`Cache::forget` no CRUD admin** para garantir que mudanças apareçam na hora. A escolha depende de **quem consome o dado** e **quanto stale é tolerável**.
