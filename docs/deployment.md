# Deployment: GitHub Actions → GHCR → Dokploy

Praktika di-deploy sebagai satu image Docker (php-fpm+nginx via `serversideup/php:8.3-fpm-nginx`). CI build image, push ke GHCR, Dokploy pull + jalankan.

## 1. File terkait

- [Dockerfile](../Dockerfile) — multi-stage build: build asset node, composer install, image runtime final. Cuma dipakai CI, VPS tidak build ini.
- [.dockerignore](../.dockerignore) — pangkas build context.
- [.github/workflows/deploy.yml](../.github/workflows/deploy.yml) — build+push saat push ke `main`/`staging`, lalu panggil webhook Dokploy.
- [docker-compose.yml](../docker-compose.yml) — dipakai Dokploy (app type **Compose**), tarik image jadi dari GHCR, tanpa build di VPS.

## 2. Setup GitHub

Tidak perlu setup tambahan untuk push ke GHCR — workflow pakai `GITHUB_TOKEN` bawaan (permission `packages: write` sudah ada di workflow).

Tambah secret per environment (lihat §4.1), bukan secret repo biasa.

Push pertama otomatis buat package `ghcr.io/<owner>/<repo>`. Default visibility package GHCR ikut visibility repo, tapi bisa beda — cek Package settings di github.com kalau Dokploy gagal pull.

## 3. Tag image GHCR

`docker/metadata-action` hasilkan:

- `latest` — hanya dari `main` → **production**
- `staging` — nama branch, dari push `staging` → **staging**
- semver — saat push tag `v1.2.3` (cuma build+push, tidak auto-deploy — lihat catatan di bawah)
- `sha-<short>` — tiap build, buat pin/rollback

Catatan: push tag `v*` cuma build image versioned, **tidak** memicu webhook deploy. App production track `:latest`, yang cuma ter-update dari push `main` — push tag saja tidak refresh itu, jadi kalau auto-deploy ikut jalan, hasilnya redeploy `latest` yang basi. Tag `v*` dipakai buat snapshot rilis untuk pin/rollback manual (§6), bukan trigger deploy.

## 4. Dua environment, dua app Dokploy

Satu image, dua target deploy. Buat dua app terpisah di Dokploy, masing-masing tracking tag berbeda:

| | Production | Staging |
|---|---|---|
| Branch | `main` | `staging` |
| Tag image | `ghcr.io/<owner>/<repo>:latest` | `ghcr.io/<owner>/<repo>:staging` |
| App Dokploy | `praktika-production` | `praktika-staging` |
| DB | instance Postgres terpisah | instance Postgres terpisah |
| GitHub Environment | `production` | `staging` |

Job `deploy` di workflow pilih GitHub Environment berdasar branch (branch `staging` → env `staging`, selain itu termasuk tag `v*` → `production`) lalu baca `secrets.DOKPLOY_DEPLOY_WEBHOOK` dari environment itu — masing-masing environment simpan URL webhook sendiri dengan nama secret sama, tidak perlu if-else branch di secretnya.

### 4.1 Setup GitHub Environments

Repo → Settings → Environments → buat `production` dan `staging`, masing-masing isi secret `DOKPLOY_DEPLOY_WEBHOOK` (URL webhook app Dokploy yang beda). Opsional: tambah required reviewers di `production` buat gate manual sebelum deploy.

### 4.2 Auth registry (kalau package private)

Dokploy → Settings → Registry → Add Registry (sekali saja, dipakai kedua app):

- Registry URL: `ghcr.io`
- Username: username GitHub kamu
- Password: GitHub PAT scope `read:packages` (classic atau fine-grained)

Skip kalau package dibuat public.

### 4.3 Buat aplikasi (type Compose)

Ulangi buat keduanya — Dokploy → Project → Create → **Application** → source type **Compose**:

- Source: Git repo ini, branch `main` untuk app production / `staging` untuk app staging
- Compose path: `docker-compose.yml` (root repo)
- **Build Type: none / pull only** — jangan pilih build dari Dockerfile. Dokploy Compose app defaultnya cuma jalankan `docker compose pull && docker compose up -d` kalau tidak diarahkan build, jadi image ditarik langsung dari GHCR, bukan dibangun ulang di VPS.
- Registry: pilih yang dari §4.2 (kalau private)
- Domain: arahkan ke service `app`, port container **8080** (default nginx serversideup) — subdomain terpisah buat staging (misal `staging.your-domain`)

### 4.4 Environment variables

`docker-compose.yml` baca semua nilai lewat `${VAR}` — isi di tab Environment app Dokploy (nilai dari `.env.example`, disesuaikan per environment):

```
GHCR_OWNER=<owner>
GHCR_REPO=<repo>
IMAGE_TAG=latest            # "staging" di app staging

APP_NAME=Praktika
APP_ENV=production          # atau "staging" di app staging
APP_KEY=                    # php artisan key:generate --show, paste hasilnya — beda key tiap env
APP_DEBUG=false
APP_URL=https://your-domain          # https://staging.your-domain di staging

DB_CONNECTION=pgsql
DB_HOST=<host service postgres dokploy>
DB_PORT=5432
DB_DATABASE=praktika               # praktika_staging di staging
DB_USERNAME=praktika
DB_PASSWORD=<secret>

FILESYSTEM_DISK=local   # atau s3, isi AWS_* kalau pakai s3
```

`IMAGE_TAG` ini kunci "gak build di VPS" — app production isi `latest`, app staging isi `staging`, keduanya nunjuk tag yang sama yang di-push CI (§3), compose cuma pull.

Var `AUTORUN_*` di compose itu entrypoint hook image `serversideup/php` — jalankan migration + cache warmup saat container start, tidak perlu entrypoint script custom. `AUTORUN_LARAVEL_MIGRATION` jalankan `php artisan migrate --force`.

Jangan pernah share `APP_KEY`, DB, atau bucket S3 antara production dan staging — secret terpisah, data terpisah.

### 4.5 Postgres

Provision terpisah per environment via Dokploy → Project → Create → **Database** → PostgreSQL 17 (`praktika-production-db`, `praktika-staging-db`). Pakai hostname service internal sebagai `DB_HOST`.

### 4.6 Storage persisten

Mount volume buat `storage/app` (upload user: foto absensi dll) kalau `FILESYSTEM_DISK=local` — atau pakai disk S3-compatible (var `AWS_*`) biar container tetap stateless, lebih disarankan buat scaling/redeploy. Pakai bucket/prefix terpisah per environment.

### 4.7 Webhook deploy

Tiap app Dokploy → Deployments → Webhook → copy URL → simpan ke secret `DOKPLOY_DEPLOY_WEBHOOK` di GitHub Environment yang sesuai (§4.1). Trigger webhook bikin Dokploy jalankan `docker compose pull && docker compose up -d` — cuma pull tag (`IMAGE_TAG`) yang dikonfigurasi, tidak build ulang.

## 5. Ringkasan alur

```
push staging ──▶ build ──▶ push tag :staging ──▶ curl webhook staging    ──▶ praktika-staging redeploy
push main    ──▶ build ──▶ push tag :latest  ──▶ curl webhook production ──▶ praktika-production redeploy
push tag v*  ──▶ build ──▶ push tag :vX.Y.Z   (tanpa webhook — cuma buat pin/rollback manual, §6)

entrypoint saat container start jalankan migrate/cache (AUTORUN_*)
```

## 6. Rollback

Dokploy app → Deployments → pilih deployment sebelumnya → Redeploy. Atau set manual tag image ke `sha-<short>` yang diketahui baik lalu redeploy — tiap build CI ditag dengan commit sha-nya, tidak ada yang hilang antar push `latest`.

## 7. Test image lokal sebelum andalkan CI

```bash
docker build -t praktika:local .
docker run --rm -p 8080:8080 --env-file .env praktika:local
```

Buka `http://localhost:8080`.
