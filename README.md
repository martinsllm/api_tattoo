# 🖋️ Tattoo Finder API

API desenvolvida em Laravel para conectar usuários a tatuadores com base em geolocalização, permitindo busca por proximidade, estilos, avaliações e portfólio.

---

## 🚀 Tecnologias

* Laravel
* PHP 8+
* Sanctum (auth)
* MySQL

---

## 📦 Funcionalidades

* 🔐 Autenticação (login, registro, logout)
* 🎨 CRUD de artistas
* 📍 Busca por localização (raio + distância)
* 🎯 Filtros por estilos e tags
* 🖼️ Upload e remoção de imagens

---

## 👤 Tipos de usuário

* Cliente: usuário comum
* Artista: usuário com `artist_profile`

---

## 🧠 Arquitetura

* Controllers enxutos
* Services (regras de negócio)
* Form Requests (validação)
* Resources (respostas)

---

## 🗄️ Principais tabelas

* users
* artist_profiles
* artist_images
* styles / tags (+ pivots)

---

## 📡 Endpoints

**Auth**

* POST /api/register
* POST /api/login
* POST /api/logout

**Artists**

* GET /api/artists
* GET /api/artists/{id}
* POST /api/artists
* PUT /api/artists/{id}

**Images**

* POST /api/artists/{id}/images
* DELETE /api/images/{id}

---

## ⚙️ Setup

```bash
git clone <repo>
cd projeto
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve
```

---

## 🚧 Roadmap

* Reviews
* Favoritos
* Imagem principal
* Policies / permissões

---

## 👨‍💻 Autor

Lucas Martins

Projeto desenvolvido com foco em aprendizado e aprofundamento no ecossistema Laravel, explorando conceitos como construção de APIs REST, autenticação com Sanctum, organização de código com Services, Resources e boas práticas de arquitetura.
