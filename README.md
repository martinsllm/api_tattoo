# Tattoo Finder API

API REST desenvolvida em **Laravel** para conectar usuários a tatuadores com base em geolocalização, permitindo busca por proximidade, filtros por estilos/tags, avaliações, favoritos e portfólio.

---

## Tecnologias

* Laravel 13
* PHP 8.4
* Sanctum (autenticação via tokens)
* spatie/laravel-permission (roles & permissões)
* Scramble (documentação OpenAPI)
* MySQL

---

## Funcionalidades

* Autenticação (registro, login, logout, perfil)
* Verificação de e-mail e troca de e-mail com confirmação
* CRUD de artistas (ativar / desativar perfil)
* Busca por localização (lat/lng + raio + ordenação por distância)
* Filtros por estilos, tags, cidade, estado e texto livre
* Portfólio: upload, remoção e definição de imagem principal
* Avaliações (reviews) com soft delete
* Favoritos
* Área administrativa (moderar artistas e reviews)

---

## Tipos de usuário

* **Cliente**: usuário comum (favorita e avalia artistas)
* **Artista**: usuário com `artist_profile` e portfólio
* **Admin**: modera artistas e avaliações

---

## Arquitetura

* Controllers enxutos
* Services (regras de negócio)
* Form Requests (validação)
* API Resources (respostas)
* Policies (autorização)
* API versionada (`/api/v1`)

---

## Principais tabelas

* users
* artist_profiles
* artist_images
* styles / tags (+ pivots)
* reviews
* favorites
* permission tables (roles/permissions)

---

## Endpoints

Base: `/api/v1`

**Auth**

* POST   `/register`
* POST   `/login`
* POST   `/logout`
* GET    `/me`
* PATCH  `/me`

**E-mail**

* GET    `/email/verify/{id}/{hash}/{token}`
* GET    `/email/verify-change/{id}/{hash}/{token}`
* POST   `/email/resend-verification`
* DELETE `/email/cancel-pending-email`

**Artists**

* GET    `/artists` (busca + filtros)
* GET    `/artists/{id}`
* POST   `/artists`
* PATCH  `/artists/{id}`
* PATCH  `/artists/{id}/activate`
* PATCH  `/artists/{id}/deactivate`

**Images**

* POST   `/artists/{id}/images`
* PATCH  `/images/{id}/main`
* DELETE `/images/{id}`

**Reviews**

* GET    `/artists/{id}/reviews`
* POST   `/reviews`
* DELETE `/reviews/{id}`

**Favorites**

* GET    `/favorites`
* POST   `/artists/{id}/favorite`

**Outros**

* GET    `/styles`
* GET    `/tags`
* GET    `/health`

**Admin**

* PATCH  `/admin/artists/{id}/activate`
* PATCH  `/admin/artists/{id}/deactivate`
* DELETE `/admin/reviews/{id}`

---

## Documentação

Documentação OpenAPI gerada via **Scramble**, disponível em `/docs`.
Controlada pela variável `DOCS_ENABLED` (habilite em ambientes não-produtivos).

---

## Setup

```bash
git clone <repo>
cd api_tattoo
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Ambiente de desenvolvimento completo (server + queue + logs + vite):

```bash
composer run dev
```

---

## Testes

```bash
php artisan test
```

Suíte de testes de feature cobrindo auth, verificação de e-mail, artistas,
imagens, reviews, favoritos e área administrativa.

---

## Autor

Lucas Martins

Projeto desenvolvido com foco em aprendizado e aprofundamento no ecossistema Laravel, explorando conceitos como construção de APIs REST, autenticação com Sanctum, organização de código com Services, Resources, Policies e boas práticas de arquitetura.
