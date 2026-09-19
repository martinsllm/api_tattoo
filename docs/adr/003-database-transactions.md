# ADR 003 — Uso de transações de banco (DB::transaction)

**Status:** Aceito  
**Data:** 2026-09-19
**Contexto:** Sprint 19 / #110

---

## Contexto

Esta API executa fluxos em que **várias escritas no banco** precisam acontecer juntas — criar perfil de artista com styles/tags e role, upload de várias imagens com registro em `artist_images (model ArtistImage)`, ou garantir uma única imagem principal por artista. Se uma etapa falhar no meio, o sistema não pode ficar **pela metade** (dados inconsistentes para o cliente ou para o admin).

Usamos `DB::transaction()` do Laravel para garantir **atomicidade**: ou todas as operações do bloco commitam, ou nenhuma (rollback automático). Este ADR mapeia **onde** o projeto usa transações, **por quê** em cada caso, e **o que quebraria** sem elas — não cobre fila, e-mail ou disco em todos os fluxos (limites documentados depois).

---

## Decisão / padrão adotado

Usamos `DB::transaction()` do Laravel quando **múltiplas operações no banco** precisam ser **atômicas** — ou todas commitam, ou nenhuma (rollback automático em exceção).

**Onde procurar no código:**

| Arquivo | Métodos com transação |
| ------- | --------------------- |
| `ArtistImageService` | `multipleUpload`, `setMain`, `reorder` |
| `ArtistService` | `create`, `update` |
| `AccountService` | `delete` |
| `AuthController` | `register`, `updateProfile`, `cancelPendingEmail` |
| `EmailVerificationController` | `verifyChange` |


- [X] `ArtistImageService`
- [X] `ArtistService`
- [ ] `AccountService`
- [ ] `AuthController` / outros

---

## Casos mapeados

Para cada caso: abra o arquivo, leia o bloco `DB::transaction` e preencha as 4 colunas.

### Caso 1 — Upload múltiplo de imagens

**Onde:** `ArtistImageService::multipleUpload`

| Campo | Preencher |
| ----- | --------- |
| **Operações dentro da transação** | grava imagem no disco, insert de registro no banco, dispatch de job de thumbnail |
| **Por que precisa ser atômico** | Upload é em batch: ou todas as imagens entram com posições corretas, ou nenhuma. Cliente não pode ver galeria incompleta por falha no meio. |
| **O que quebra sem transação** | Registros parciais em artist_images (ex.: 2 de 5); posições (position) inconsistentes; resposta da API lista imagens que o usuário acha que enviou todas, mas o DB não reflete. |
| **Observação extra** | Catch externo remove arquivos do disco se a transação falhar (compensa store antes do rollback). Thumbnail vai para fila dentro da TX — tema de limites (job vs DB). |

---

### Caso 2 — Criar perfil de artista

**Onde:** `ArtistService::create`

| Campo | Preencher |
| ----- | --------- |
| **Operações dentro da transação** | create do perfil; sync de styles e tags (se enviados); syncRoles(['artist']) no user. |
| **Por que precisa ser atômico** | Cadastro de artista é um conceito só: perfil + estilos/tags + permissão de artista. Ou completa tudo, ou nada — senão o usuário fica “meio artista”. |
| **O que quebra sem transação** | Perfil criado mas sync de styles falhou → artista sem estilos no catálogo.
Perfil + styles ok, mas syncRoles falhou → tem perfil, API ainda trata como client.
Tags gravadas, perfil rollback parcial impossível manualmente → inconsistência difícil de debugar. |
| **Observação extra** | Só entra na transação o que é banco (profile, pivots, roles). Nada de disco/fila aqui — fluxo 100% DB, transação cobre bem. |

---

### Caso 3 — Definir imagem principal

**Onde:** `ArtistImageService::setMain`

| Campo | Preencher |
| ----- | --------- |
| **Operações dentro da transação** | is_main false em todas; is_main true na escolhida |
| **Por que precisa ser atômico** | impedir mais de uma main por artista, ou artista sem imagem principal |
| **O que quebra sem transação** | zero ou duas imagens main visíveis no catálogo |
| **Observação extra** | Dois updates sequenciais; sem transação, outro request no meio ou falha entre os dois deixa estado inválido. Só DB — transação cobre 100%. |

---

## O que **não** vai dentro da transação (limites)

Transação **não** rollbacka fila, disco, e-mail nem serviços externos. Neste projeto:

- **Storage (disco/S3):** arquivos não participam do rollback do banco. No upload (`multipleUpload`), `store()` roda dentro da transação, mas o **catch** apaga paths se a TX falhar. No delete de conta (`AccountService::delete`), arquivos são removidos **depois** do commit — primeiro limpa DB (tokens, profile, user), depois `disk->delete`.
- **Fila (jobs):** `GenerateArtistImageThumbnail::dispatch` roda dentro da TX do upload; se houver rollback, o job **pode** já estar na fila com um `image_id` que não existe mais. Worker trata falha ou no-op — atomicidade “total” não inclui fila.
- **E-mail:** em troca de e-mail (`AuthController::updateProfile`), a transação grava `pending_email`, desativa catálogo se necessário e revoga tokens; o **envio** (`sendPendingEmailChangeNotification`) ocorre **após** o commit — evita e-mail enviado para mudança que rollbackou.
- **APIs externas:** não há nestes fluxos, mas mesma regra — chamar HTTP dentro de TX é arriscado (lento + não desfaz se rollback).

---

## Consequências

### Positivas

- Permite atomicidade nas operações, garantindo que uma transação seja executada por completo ou totalmente revertida, sem deixar atualizações parciais;
- Upload em lote e setMain ficam consistentes no DB (sem galeria/main quebrada).
- Criar artista evita usuário “meio artista” (perfil + pivots + role juntos).

### Negativas / limites

- Não cobre fila, storage, e-mail nem APIs externas (ver seção Limites acima).

---

## Quando usar transação (regra prática)

- **Usar** quando 2+ writes no banco formam uma regra “tudo ou nada” (ex.: batch upload, cadastro artista, trocar main).
- **Não usar** para um único `create`/`update` isolado — overhead sem benefício.
- **Nunca assumir** que TX cobre disco, fila ou e-mail — tratar à parte (ver Limites).

---

## Resposta de entrevista (2 min)

> Transação garante atomicidade **no banco**: commit ou rollback. Uso em upload múltiplo (registros + posições), criar artista (perfil + pivots + role) e setMain (só uma imagem principal). Sei que fila, storage e e-mail ficam fora — no upload compenso disco no catch; e-mail só depois do commit.
