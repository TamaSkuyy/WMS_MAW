# WMS MAW — Fresh Server Setup & Production Deployment

> **Target**: VPS / dedicated server Ubuntu 24.04 LTS (min. 2 vCPU, 4 GB RAM, 30 GB disk)
>
> **Flow**: Semua perintah di section 1–3 dijalankan sebagai **root**. Mulai section 4, gunakan user `deploy` via `su - deploy`.

---

## 1. System Preparation (as root)

### 1.1 Update & Install Essentials

```bash
apt update && apt upgrade -y
apt install -y curl wget git unzip supervisor ufw
```

### 1.2 Create Deploy User

```bash
# Buat user untuk deployment
adduser deploy
usermod -aG docker deploy
```

> User `deploy` untuk operasional harian. Root bisa langsung `su - deploy` tanpa password.  
> User ini sudah masuk group `docker` — tidak perlu `sudo` tiap docker command.

**Mulai dari sini, setelah install Docker/Portainer, switch ke user deploy:**
```bash
su - deploy
```

### 1.3 Configure Firewall (root)

```bash
ufw allow 22/tcp        # SSH
ufw allow 80/tcp        # HTTP (optional)
ufw allow 443/tcp       # HTTPS (optional)
ufw allow 8090/tcp      # App (via nginx in docker)
ufw enable
```

### 1.4 Swap (root, kalau RAM < 4 GB)

```bash
fallocate -l 4G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
echo '/swapfile none swap sw 0 0' | tee -a /etc/fstab
```

---

## 2. Install Docker + Portainer (root)

### 2.1 Docker Engine

```bash
# Official Docker install script
curl -fsSL https://get.docker.com | sh

# Verify
docker --version
docker run hello-world
```

### 2.2 Docker Compose Plugin

```bash
apt install -y docker-compose-plugin
docker compose version
```

### 2.3 Portainer (Web UI untuk manage Docker)

```bash
# Create volume
docker volume create portainer_data

# Run Portainer (port 9443 = HTTPS, 8000 = agent tunnel)
docker run -d \
  --name portainer \
  --restart unless-stopped \
  -p 9443:9443 \
  -p 8000:8000 \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -v portainer_data:/data \
  portainer/portainer-ce:latest

# Akses: https://SERVER_IP:9443
# Setup admin user first time, pilih "Local" environment
```

---

## 3. Install PHP & Node.js (root — untuk build di host)

### 3.1 PHP 8.4 + Extensions

```bash
# Add Ondrej PPA
add-apt-repository -y ppa:ondrej/php
apt update

# Install PHP + extensions
apt install -y \
  php8.4-cli php8.4-mysql php8.4-mbstring \
  php8.4-exif php8.4-bcmath php8.4-gd \
  php8.4-intl php8.4-zip php8.4-curl \
  php8.4-redis php8.4-xml

php -v
```

### 3.2 Composer

```bash
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
composer --version
```

### 3.3 Node.js 22 + npm

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt install -y nodejs

node -v   # v22.x
npm -v    # 10.x
```

### 3.4 FrankenPHP + Laravel Octane

Tidak perlu install terpisah — project sudah pakai image `dunglas/frankenphp:1.4-php8.4`.

---

## 4. Clone & Setup Project (as deploy user)

```bash
# Dari root, switch ke user deploy
su - deploy
```

### 4.1 Clone Repository

```bash
cd /opt
git clone https://github.com/your-org/wms-wma.git
cd wms-wma
```

### 4.2 Install Dependencies (Host — untuk build Vite)

```bash
composer install --no-dev --optimize-autoloader
npm install
```

### 4.3 Create Environment File

```bash
# Copy dari example
cp .env.example .env.prod

# Generate APP_KEY
php artisan key:generate

# Edit .env.prod
nano .env.prod
```

**`.env.prod` minimal:**

```env
APP_NAME="WMS MAW"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:xxx-generated-key
APP_URL=http://SERVER_IP:8090
APP_PORT=8090

# Database
DB_CONNECTION=mysql

# MySQL (akan dibuat oleh docker-compose, gunakan nama service)
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=wms_wma
DB_USERNAME=wms_user
DB_PASSWORD=CHANGE_ME_DB_PASS
DB_ROOT_PASSWORD=CHANGE_ME_ROOT_PASS

# Redis (service name di docker-compose)
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Broadcast (Reverb WebSocket)
BROADCAST_CONNECTION=reverb

# Reverb
REVERB_APP_ID=767971
REVERB_APP_KEY=wms-app-key
REVERB_APP_SECRET=wms-app-secret
REVERB_HOST="reverb"
REVERB_PORT=8081
REVERB_SCHEME=http

# Vite (untuk build assets)
VITE_APP_NAME="WMS MAW"
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${APP_URL}"
VITE_REVERB_PORT=8090
VITE_REVERB_SCHEME=http

# Mail
MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@example.com
```

### 4.4 Build Frontend Assets

```bash
# Export env vars dulu
set -a
source .env.prod
set +a

# Build Vite untuk production
ASSET_URL="/" npm run build
```

---

## 5. Deploy Production Stack

### 5.1 Struktur Docker

```
├── Dockerfile                 # FrankenPHP + Octane image
├── docker-compose.prod.yml    # 7 services: app, nginx, queue, scheduler, reverb, mysql, redis
├── docker/nginx/default.conf  # Nginx reverse proxy config
├── deploy-production.sh       # Deployment script
└── .env.prod                  # Environment variables
```

### 5.2 First Deploy (Full Build)

```bash
./deploy-production.sh --build
```

> Proses ini: build Docker image → start 7 service → migrate DB → cache optimize.  
> Durasi: ~5-10 menit pertama kali (download image base + install PHP extensions).

### 5.3 Seed Database (first time only)

```bash
docker compose -p wms-wma-prod -f docker-compose.prod.yml --env-file .env.prod exec app php artisan db:seed
```

### 5.4 Create Superadmin User

```bash
docker compose -p wms-wma-prod -f docker-compose.prod.yml --env-file .env.prod exec app php artisan tinker
```

```php
User::create(['name'=>'Super Admin','email'=>'admin@example.com','password'=>bcrypt('PASSWORD_ANDA')]);
// Assign role di UI setelah login
```

### 5.5 Verify

Buka di browser:
```
http://SERVER_IP:8090
```

---

## 6. Deploy Script Usage

| Command | Use Case | Duration |
|---------|----------|----------|
| `./deploy-production.sh --build` | First deploy / Dockerfile berubah | 5-10 min |
| `./deploy-production.sh --rebuild` | composer.json / package.json berubah | 2-3 min |
| `./deploy-production.sh --update` | Hanya PHP/Blade/routes/config berubah | ~30-60 sec |
| `./deploy-production.sh --update --with-assets` | JS/CSS/Vite juga berubah | ~60-90 sec |
| `./deploy-production.sh --down` | Stop semua service | — |
| `./deploy-production.sh --check-storage` | Cek isi storage volume | — |
| `./deploy-production.sh --backup-storage` | Backup storage volume ke ./backups | — |

**Quick update daily work flow:**
```bash
git pull
# Kalau ada perubahan frontend:
./deploy-production.sh --update --with-assets
# Kalau hanya backend:
./deploy-production.sh --update
```

---

## 7. Container Services Architecture

```
                        ┌───────────────────────┐
                        │   NGINX (:80 → :8081)  │
                        │   Reverse Proxy        │
                        └──────┬────────────────┘
                               │
              ┌────────────────┼──────────────────┐
              ▼                ▼                  ▼
    ┌─────────────┐   ┌──────────────┐   ┌──────────────┐
    │ APP (8090)   │   │ REVERB (8081)│   │ Static Files │
    │ Octane       │   │ WebSocket    │   │ /build/      │
    │ FrankenPHP   │   │              │   │ /images/     │
    └──────┬───────┘   └──────┬───────┘   └──────────────┘
           │                  │
    ┌──────┼───────┐         │
    ▼      ▼       ▼         │
┌──────┐ ┌──────┐ ┌──────┐  │
│MySQL │ │Redis │ │Queue │  │
│(3306)│ │(6379)│ │Worker│◄─┘
└──────┘ └──────┘ └──────┘

┌──────────┐
│Scheduler │  (cron jobs)
└──────────┘
```

---

## 8. Portainer Tips

### Lihat semua container
Portainer → `Containers` → lihat status, logs, restart

### Monitor resource
Portainer → `Dashboard` → CPU/RAM/Disk usage per container

### Lihat logs realtime
Portainer → container `wms-wma-prod-app` → `Logs`

### Backup database
```bash
docker exec $(docker compose -p wms-wma-prod -f docker-compose.prod.yml --env-file .env.prod ps -q mysql) \
  mysqldump -u root -p$DB_ROOT_PASSWORD $DB_DATABASE > backup_$(date +%Y%m%d).sql
```

---

## 9. Troubleshooting

| Issue | Solution |
|-------|----------|
| Port 8090 already in use | Ubah `APP_PORT` di `.env.prod`, e.g., `APP_PORT=8091` |
| MySQL connection refused | Tunggu 30 detik setelah start, MySQL 8.0 butuh waktu init |
| Storage symlink error | `docker compose ... exec app php artisan storage:link --force` |
| Assets 404 (CSS/JS) | Run deploy dengan `--with-assets` |
| Permission denied on logs | `docker compose ... exec app chmod -R 775 /var/www/html/storage` |
| Worker stuck / queue not processing | `./deploy-production.sh --check-queue` untuk diagnosa; restart worker: `docker compose ... restart queue` (atau `docker compose ... exec app php artisan queue:restart` untuk restart graceful setelah job selesai) |
| Reverb WebSocket not connecting | Cek `REVERB_*` vars di `.env.prod`; pastikan port nginx proxy `/app` ke reverb |
| Maintenance mode stuck | `docker compose ... exec app php artisan up` |
| `--rebuild` berhenti di "App belum healthy" padahal Octane jalan | Dulu: maintenance mode memblokir `/health/ping` (503). Sekarang `/health/ping` & `/up` dikecualikan dari maintenance (`bootstrap/app.php`) + script mencetak output healthcheck terakhir & otomatis `artisan up` saat gagal. Jalankan `git pull` lalu rebuild. |
| Route baru 404 setelah rebuild | Cache rute lama ikut ter-copy ke image. Sekarang `.dockerignore` + `Dockerfile` membuang `bootstrap/cache/*.php` sebelum build. Pastikan `git pull` dulu. |
| Deploy berhenti di "Cek koneksi Redis" — `Class "Illuminate\Redis\Connectors\PhpRedisConnector" not found` | **Bukan extension yang hilang.** Skrip cek Redis dulu menjalankan `php -r` tanpa autoloader/bootstrap. Sekarang sudah diperbaiki (`require vendor/autoload.php` → bootstrap → `Redis::connection()->ping()`). Jalankan `git pull` lalu deploy ulang. Untuk memastikan extension: `docker compose ... exec app php -m \| grep -i '^redis$'` — kalau kosong baru rebuild (Dockerfile sudah memuat `redis`). |

### 9.1 502 Bad Gateway saat idle (penting)

**Bukan CSRF.** Session/CSRF kedaluwarsa menghasilkan **419 Page Expired**, bukan
502. 502 = nginx tidak bisa menghubungi app (container mati/restart/timeout).
Kalau operator sering kena 419 setelah idle lama, naikkan `SESSION_LIFETIME`
di `.env.prod` (mis. 480 menit) lalu restart app — itu masalah terpisah dari 502.

Penyebab tersering di stack ini (FrankenPHP + Octane + MySQL):

1. **Koneksi DB worker Octane basi** setelah idle lama → `MySQL server has gone away`.
   Sudah diperbaiki: listener `DisconnectFromDatabases` di `config/octane.php`
   aktif (putus koneksi tiap request) + `wait_timeout` MySQL diperbesar.
2. **Container app OOM-kill / restart** (worker menumpuk memori) → nginx 502
   selama restart. Sudah diperbaiki: queue worker didaur ulang
   (`--max-time=3600 --max-jobs=1000`), log container dibatasi (rotasi).
3. **Healthcheck palsu**: sebelumnya `curl -s` tanpa `-f`, jadi 404/500 pun
   dianggap sehat dan nginx `depends_on: service_healthy` bisa keliru.
   Sekarang `curl -fsS` ke `/health/ping` (route publik baru) + `start_period`.

Diagnosa cepat di VPS (ganti `-p wms-wma-prod -f docker-compose.prod.yml --env-file .env.prod` sesuai konvensi):

```bash
# 1) Status & restart count container
docker compose ps
docker inspect --format '{{.Name}} health={{if .State.Health}}{{.State.Health.Status}}{{end}} restarts={{.RestartCount}}' $(docker compose ps -q)

# 2) Ada OOM/kill/restart? (penyebab paling umum)
docker events --since 24h --until 0m | grep -iE "oom|kill|die|restart" | tail -30
dmesg -T | grep -i "killed process" | tail -10
free -h

# 3) Log app & nginx (cari "upstream", "connection refused", "gone away")
docker compose logs app --since 2h | grep -iE "error|exception|gone away|fatal" | tail -40
docker compose logs nginx --since 2h | grep -iE "error|502|upstream" | tail -40

# 4) Log nginx host (SSL) — sumber 502 ke user
sudo tail -100 /var/log/nginx/error.log
```

Kalau ternyata OOM: batasi worker (`--workers=2` sudah dipakai), tambah RAM/swap
VPS, atau pisahkan MySQL keluar dari VPS yang sama.

### 9.2 502 Bad Gateway saat / setelah import (penting)

Gejala: operator klik **Close & Refresh** setelah import (shopping/cycle/master
data) lalu halaman blank dengan **502 Bad Gateway** — kadang impor sendiri
kelihatan berhasil.

Penyebab yang sudah diperbaiki di kode (rilis 2026-09-16):

1. **Payload halaman index terlalu besar.** Halaman Shopping dulu mengirim
   **seluruh** daftar frame draft ke browser (untuk modal Kirim Massal). Setelah
   import ribuan frame, payload puluhan MB → worker Octane kehabisan memori →
   502 tepat saat halaman di-refresh. Sekarang daftar draft dicari lewat endpoint
   `shoppings/draft-frames` (server-side, berlimit 30 + pencarian/scan).
2. **`memory_limit` PHP default image = 128M.** Sekarang batas PHP ada di
   `docker/php/zz-wms.ini` (512M, upload 12M / post 13M, `max_input_vars=5000`,
   `max_execution_time=300`). File itu di-COPY ke image **dan** di-bind-mount ke
   semua service PHP, jadi bisa diubah tanpa rebuild (lihat §9.4).
3. **`client_max_body_size` nginx** sebelumnya default **1 MB** → file import
   >1 MB ditolak sebelum sampai Laravel. Sekarang 12M di
   `docker/nginx/default.conf`. **Host nginx (SSL) juga harus** punya
   `client_max_body_size 20M;` (lihat §4.5).
4. **Notifikasi import bisa menggagalkan job.** `ImportCompletedNotification`
   memakai channel broadcast; kalau Reverb mati, job dianggap gagal lalu di-retry
   3x padahal data sudah masuk. Sekarang kegagalan notifikasi hanya dicatat log.
5. **Daftar error dibatasi 200 baris** per import (`ProcessImport::MAX_STORED_ERRORS`).
   File yang hampir semua barisnya gagal tidak lagi membengkakkan kolom JSON dan
   endpoint status yang di-polling UI.
6. **`QUEUE_CONNECTION=sync` dilarang.** Dengan `sync`, seluruh import berjalan di
   dalam request HTTP → request timeout → 502. `deploy-production.sh` sekarang
   menolak deploy kalau nilainya `sync`/`null` (harus `database` atau `redis`).

Cek cepat kalau masih terjadi:

```bash
# 1) Konfigurasi queue di container (harus database/redis, BUKAN sync)
docker compose -p wms-wma-prod -f docker-compose.prod.yml --env-file .env.prod \
  exec app php artisan tinker --execute="echo config('queue.default');"

# 2) Batas PHP yang aktif di container
docker compose -p wms-wma-prod -f docker-compose.prod.yml --env-file .env.prod \
  exec app php -i | grep -E "memory_limit|upload_max_filesize|post_max_size|max_input_vars"

# 3) Ada OOM-kill saat import?
docker events --since 2h --until 0m | grep -iE "oom|kill|die" | tail -20
dmesg -T | grep -i "killed process" | tail -5

# 4) Error asli di app (bukan hanya 502 nginx)
docker compose logs app --since 1h | grep -iE "Allowed memory|Fatal|Exception" | tail -20
cat storage/logs/laravel.log | tail -50

# 5) Import yang gagal/macet
docker compose -p wms-wma-prod -f docker-compose.prod.yml --env-file .env.prod \
  exec app php artisan queue:failed | tail -20
```

> Catatan: perubahan `Dockerfile` (batas PHP) dan `docker/nginx/default.conf`
> butuh `./deploy-production.sh --rebuild --with-assets` agar berlaku di VPS.

### 9.3 `CheckDidNotComplete ... The file "" does not exist` (health check Backups)

Log yang muncul (biasanya tiap 10 menit, dari container **scheduler**):

```
Spatie\Health\Exceptions\CheckDidNotComplete
The check named `Backups` did not complete. An exception was thrown with this
message: Symfony\...\FileNotFoundException: The file "" does not exist
```

**Ini bukan penyebab 502/500 di browser.** `health:check` menangkap exception
per-check (`report($exception)` + status `crashed` di hasil health), jadi operator
tidak melihat error — efeknya hanya log kotor + check Backups selalu merah.

Penyebabnya: `BackupsCheck::new()` didaftarkan **tanpa** `locatedAt()`/`onDisk()`,
sehingga check memanggil `File::glob('')`; hasilnya `false` di container PHP →
`new SymfonyFile('')` → exception. Sudah diperbaiki di
`app/Providers/AppServiceProvider.php`:

```php
BackupsCheck::new()
    ->onDisk('backup-db')                        // root /backups
    ->locatedAt(config('backup.backup.name'));   // folder "WMS MAW"
```

Sekarang hasilnya normal, mis. `Failed: No backups found` (bukan crash) kalau
belum ada file backup.

**Temuan penting**: `backup:run --only-db` (jadwal 02:00) dijalankan dari
container **scheduler**, yang dulu **tidak** me-mount `./backups/db:/backups`.
Akibatnya file backup ditulis ke filesystem container scheduler dan **hilang**
saat container dibuat ulang, sementara check membaca folder yang berbeda.
Sudah diperbaiki di `docker-compose.prod.yml` (service `scheduler` ikut mount
`./backups/db:/backups:rw`). Verifikasi setelah deploy:

```bash
# 1) Folder backup = bind mount, bukan filesystem container
docker compose -p wms-wma-prod -f docker-compose.prod.yml --env-file .env.prod \
  exec scheduler sh -c "mount | grep /backups; ls -lah /backups"

# 2) Backup manual + cek hasilnya terlihat oleh app
docker compose -p wms-wma-prod -f docker-compose.prod.yml --env-file .env.prod \
  exec scheduler php artisan backup:run --only-db
ls -lah ./backups/db/"WMS MAW"

# 3) Hasil health check (Backups tidak lagi "did not complete")
docker compose -p wms-wma-prod -f docker-compose.prod.yml --env-file .env.prod \
  exec app php artisan health:check
```

> Di dev lokal/CI, disk `backup-db` diarahkan lewat `BACKUP_DB_PATH`
> (default produksi `/backups`) supaya check tetap bisa jalan tanpa mount.
> Notifikasi health otomatis dimatikan selama `HEALTH_TO_ADDRESS` kosong —
> isi alamat email dulu kalau ingin notifikasi check gagal.

### 9.4 `Allowed memory size of 134217728 bytes exhausted` (memory_limit)

Contoh log (path `/var/www/html/vendor/...` = di dalam container):

```
Symfony\Component\ErrorHandler\Error\FatalError
Allowed memory size of 134217728 bytes exhausted (tried to allocate 2621440 bytes)
in .../vendor/laravel/framework/src/Illuminate/Database/Eloquent/Relations/BelongsTo.php:136
```

Arti angka itu: **134217728 = 128 MB** → limit default image PHP, artinya image
belum memakai `docker/php/zz-wms.ini` (belum di-rebuild) atau file itu belum
ter-mount. Kalau muncul di `BelongsTo::getEagerModelKeys()`, penyebabnya hampir
selalu **satu request memuat koleksi model yang tidak dibatasi** lalu meng-eager-load
relasi belongsTo untuk semuanya (mis. daftar ribuan draft + `shoppingLocation`).

**Naikkan limit memang perlu, tapi bukan solusinya.** Batas 512M hanya jaring
pengaman; query yang memuat data tak terbatas tetap harus dibatasi
(paginate / `limit()` / agregasi SQL), kalau tidak masalahnya pindah ke 512M.

Titik yang sudah dibatasi di kode (rilis 2026-09-17) — semuanya dulu memuat
seluruh riwayat ke memori:

| Lokasi | Sebelum | Sekarang |
| --- | --- | --- |
| `ReportController` summary receiving/shopping | `->get()` seluruh `cycle_items`/`shopping_items` + 6-7 relasi eager-load hanya untuk 3 angka | agregasi SQL (`count`/`sum`/`distinct`) |
| `ReportController` + `BaseExporter` export | semua baris → model → array → PhpSpreadsheet | CSV di-stream per 500 baris; xlsx dibatasi 20.000 baris, pdf 3.000 (pesan jelas, bukan 502) |
| `CycleController::show()` `lastUsedRacks` | semua `cycle_items` historis untuk produk di cycle | satu query `ROW_NUMBER()` per produk |
| `ShoppingController::index` | eager-load semua `items` padahal cuma `items_count` | `withCount('items')` saja |
| `StockOpnameController::resolveRows()` | `with('rack')` (tak dipakai) atas semua baris stok | kolom seperlunya, tanpa eager load |
| `NotificationController::markAllAsRead()` | hidrasi semua notifikasi belum dibaca | satu `UPDATE` |
| `ShoppingImporter` frame cache | satu model per frame unik tanpa batas | dibatasi 5.000 entri |

Menaikkan limit **tanpa rebuild** (file di-mount dari repo):

```bash
nano docker/php/zz-wms.ini          # ubah memory_limit, mis. 768M
docker compose -p wms-wma-prod -f docker-compose.prod.yml --env-file .env.prod up -d
# verifikasi limit yang benar-benar aktif (jalankan di app/queue/scheduler):
docker compose -p wms-wma-prod -f docker-compose.prod.yml --env-file .env.prod \
  exec app php -r 'echo ini_get("memory_limit"), PHP_EOL;'
```

Kalau image lama belum punya file itu, sekaligus rebuild:
`./deploy-production.sh --rebuild --with-assets`.

Cari request pelakunya (kalau error berulang) — FatalError OOM tidak menyertakan
request-nya, jadi pakai kombinasi log nginx (jam + URL) dan catat:

```bash
# URL yang diakses tepat sebelum error (jam sama), dari nginx host/docker
docker compose -p wms-wma-prod -f docker-compose.prod.yml logs nginx --since 30m | tail -40
sudo tail -100 /var/log/nginx/access.log

# Ringkasan error terakhir beserta file:line
docker compose -p wms-wma-prod -f docker-compose.prod.yml logs app --since 30m \
  | grep -iE "memory size|Fatal" | tail -20
```

Kalau RAM VPS terbatas, tambah swap (§1.4) dulu sebelum menaikkan limit — limit
besar tanpa RAM/swap cukup hanya memindahkan OOM dari PHP ke kernel.

### 9.5 502 mendadak saat memakai halaman transaksi — diagnosa cepat

502 = nginx tidak dapat respons valid dari container `app` (worker Octane mati,
container restart, atau request menggantung). **Redis jarang jadi penyebab 502
langsung** — kalau Redis/CACHE mati, gejalanya biasanya error 500 di semua
halaman. Tapi kalau `CACHE_STORE`/`SESSION_DRIVER=redis` sementara Redis tidak
sehat, aplikasi bisa error di setiap request dan ikut memicu worker bermasalah —
karena itu pembacaan pengaturan/fitur (`Features`/`Setting`) sekarang tahan cache
mati (fallback DB → default config), dan pilihan combobox Shopping tahan kolom
yang belum ada (fallback ke master).

Jalankan diagnosa sekali jalan:

```bash
./deploy-production.sh --diagnose
```

Yang dilaporkan (berurutan): status/health/restart container → event OOM/kill →
`memory_limit` + extension redis yang aktif di container → ping Redis + config
cache/session/queue → status migrasi → error terakhir di log app + Laravel →
log 502 nginx → daftar job gagal.

Cara membaca hasilnya:

| Temuan | Artinya | Tindakan |
| --- | --- | --- |
| `memory_limit=128M` | Image/container lama (batas 512M belum terpakai) | `./deploy-production.sh --update` (container dibuat ulang dengan mount `docker/php/zz-wms.ini`), atau `--rebuild` |
| Ada `oom`/`killed process` container app | Request terlalu berat (mis. katalog produk besar) | Update ke versi terbaru (payload produk sudah dipersempit) + naikkan `memory_limit` di `docker/php/zz-wms.ini` |
| `migrate:status` masih `Pending` | Kolom/tabel baru belum ada → error 500 di form/laporan | `./deploy-production.sh --update` (otomatis migrate) atau `docker compose ... exec app php artisan migrate --force` |
| `redis-cli ping` gagal | Redis tidak sehat | `docker compose -p wms-wma-prod -f docker-compose.prod.yml restart redis`; kalau tetap gagal pertimbangkan `CACHE_STORE=database` + `SESSION_DRIVER=database` di `.env.prod` |
| restart count container naik terus | Container crash-loop | Lihat bagian 6/7 output (error asli) — jangan hanya restart berulang |
| Tidak ada temuan | 502 hanya transient (deploy/restart) | Ulangi akses; kalau berulang kirim output diagnosa |

### 9.6 502 pada halaman tertentu — `upstream sent too big header`

Gejala di log nginx (docker **atau** host):

```
[error] upstream sent too big header while reading response header from upstream,
client: ..., request: "GET /shoppings/create HTTP/1.1",
upstream: "http://172.18.0.5:8080/shoppings/create"
→ 502
```

**Bukan Redis / OOM / migrasi** (cek §9.5 dulu untuk memastikan). Ini murni
**buffer header nginx kekecilan**.

Penyebab: Laravel (lewat `Vite::prefetch()` + middleware
`AddLinkHeadersForPreloadedAssets`) mengirim header **`Link:` Early Hints** untuk
**semua** aset build. Dengan 124 aset, ukurannya ±**7,5 KB** — ditambah cookie
sesi/XSRF. Default nginx cuma `proxy_buffer_size 4k` (atau 8k) → nginx menolak
respons → 502 pada halaman yang headernya paling besar (mis. `/shoppings/create`).

Cek header mana yang membengkak (2 langkah):

```bash
# 1) Halaman TANPA login (mis. /login) — cukup untuk melihat header dasar app
curl -sS -D - -o /dev/null http://127.0.0.1:8081/login \
  | awk 'BEGIN{RS="\r\n"} NF {print length($0)"\t"substr($0,1,70)}' | sort -rn | head -8

# 2) Halaman yang 502 — WAJIB pakai cookie sesi yang valid.
#    Ambil nilai `laravel_session` dari browser: DevTools → Application/Storage → Cookies.
curl -sS -D - -o /dev/null \
  -H 'Cookie: laravel_session=<PASTE_NILAI_COOKIE>' \
  -H 'Accept: text/html' \
  http://127.0.0.1:8081/shoppings/create \
  | awk 'BEGIN{RS="\r\n"} NF {print length($0)"\t"substr($0,1,70)}' | sort -rn | head -8
```

> Catatan: tanpa login, `/shoppings/create` hanya membalas 302 ke `/login` (header
> kecil ±500 byte) — jadi pengukuran tanpa cookie **tidak** menggambarkan kasus 502.
> Kalau halaman terbesar hanya ±2 KB, artinya buffer nginx di server memang lebih
> kecil dari itu (cek `nginx -T | grep proxy_buffer`) dan cukup dinaikkan seperti
> di bawah.

Perbaikan (sudah dipasang di repo untuk docker nginx — `docker/nginx/default.conf`):

```nginx
proxy_buffer_size 32k;
proxy_buffers 8 32k;
proxy_busy_buffers_size 64k;
large_client_header_buffers 8 32k;
client_header_buffer_size 8k;
```

> PENTING: respons melewati **dua** nginx (docker nginx → nginx host/SSL). Kalau
> hanya salah satu dinaikkan, error yang sama muncul dari lapisan berikutnya.
> Tambahkan blok yang sama ke `location /` di nginx **host** (config contoh di §4.5).

Setelah itu:

```bash
git pull
./deploy-production.sh --update --with-assets   # nginx conf di-mount, container nginx dibuat ulang
sudo nginx -t && sudo systemctl reload nginx    # untuk nginx host
```

**Verifikasi buffer benar-benar aktif** (bukan hanya `nginx -t` yang bilang syntax ok):

```bash
# Harus muncul 3 baris 32k. Kalau kosong → file conf di server masih versi lama
# (belum `git pull`) atau nginx belum di-reload.
docker compose -p wms-wma-prod -f docker-compose.prod.yml --env-file .env.prod \
  exec nginx nginx -T | grep -E "proxy_buffer_size|proxy_buffers|proxy_busy"

# Cek file di server sudah versi baru
grep -n "proxy_buffer" docker/nginx/default.conf

# Nginx host juga (kalau masih 502 setelah docker nginx beres, cek log host)
sudo grep -n "proxy_buffer" /etc/nginx/sites-available/* 2>/dev/null
sudo tail -20 /var/log/nginx/error.log
```

Alternatif kalau tidak mau menaikkan buffer: kurangi jumlah header dengan
menghapus `Vite::prefetch(concurrency: 3)` di `app/Providers/AppServiceProvider.php`
(header `Link:` turun drastis, tapi kehilangan Early Hints).

> **Kalau `nginx -t` menolak dengan `"large_client_header_buffers" directive is
> not allowed here`**: direktif itu berada di dalam blok `location { }`. Pindahkan
> ke level `server { }` (atau ke `http { }` di `/etc/nginx/nginx.conf`). Yang boleh
> di dalam `location` hanya `proxy_buffer_size`, `proxy_buffers`, dan
> `proxy_busy_buffers_size`. Selama `nginx -t` gagal, nginx **tidak** di-reload —
> situs tetap jalan dengan konfigurasi lama, jadi tidak ada downtime.

> ### ⚠️ Jebakan: bind-mount FILE tunggal "mengunci inode"
>
> Gejala: `docker/nginx/default.conf` di host sudah benar (ada `proxy_buffer_size`),
> `nginx -s reload` sukses, tapi `nginx -T` **tetap tidak menampilkan** direktifnya —
> dan file di dalam container isinya versi lama (cek
> `exec nginx grep -c proxy_buffer_size /etc/nginx/conf.d/default.conf` → `0`).
>
> Penyebab: compose dulu me-mount **file tunggal**
> (`./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf`). Bind-mount file
> mengikat **inode** saat container dibuat; `git pull` menulis file baru lalu
> `rename` → inode baru, sehingga container **tetap melihat file lama** sampai
> container di-recreate. `reload` tidak akan pernah menolong.
>
> Perbaikan:
> 1. Sekali sekarang: `docker compose -p wms-wma-prod -f docker-compose.prod.yml
>    --env-file .env.prod up -d --force-recreate nginx`
> 2. Permanen (sudah ada di repo): compose me-mount **direktori**
>    (`./docker/nginx:/etc/nginx/conf.d:ro`) → perubahan isi file langsung terlihat,
>    cukup `nginx -s reload`.
>
> Catatan: hal yang sama berlaku untuk mount file lain (mis.
> `docker/php/zz-wms.ini`). Kalau nilai `memory_limit` di container tidak sesuai
> isi file, jalankan `up -d --force-recreate app queue scheduler reverb`.

---

### 9.7 Stok "dobel" — terutama baris RELAY (penting)

**RELAY = baris stok dengan `rack_id` NULL** (barang sudah diterima tapi belum
ditempatkan di rak). Di **Transactions > Stocks** baris ini diberi badge `⚠ RELAY`.

Penyebab dobel yang sudah ditemukan:

1. **Baris duplikat RELAY** — unique index lama `(product_id, rack_id)` tidak
   menahan duplikat saat `rack_id` NULL (MySQL menganggap tiap NULL berbeda),
   jadi satu produk/RELAY bisa punya beberapa baris stok dan qty-nya terlihat
   dobel. Migrasi `2026_09_18_000001_add_null_safe_unique_index_to_stocks`
   menggabungkan baris duplikat yang sudah ada **dan** membuat index unik
   NULL-safe sehingga tidak bisa terjadi lagi (insert duplikat langsung ditolak).
2. **Baris RELAY palsu dari Stock Opname** — kalau kolom `RAK` di file opname
   kosong/hilang, semua baris dianggap RELAY; aplikasi lalu membuat baris stok
   RELAY **baru** berdampingan dengan baris rak yang sudah ada ⇒ total seolah
   dobel. Sekarang baris "new" seperti itu diberi peringatan tegas di pratinjau
   (`⚠ produk ini SUDAH punya stok di <rak> — kalau barangnya sama, JANGAN
   diterapkan`), dan penerapan opname ikut merapikan baris duplikat bucket itu.

#### Langkah 1 — Audit dulu (read-only, aman, tidak mengubah data)

```bash
DC="docker compose -p wms-wma-prod -f docker-compose.prod.yml --env-file .env.prod"

$DC exec -T app php artisan stocks:audit                          # ringkasan + 4 bagian
$DC exec -T app php artisan stocks:audit --search=VISOR --limit=50 # fokus 1 produk/part
$DC exec -T app php artisan stocks:audit --json > /tmp/audit-stok.json
```

Isi keluaran:

| Bagian | Artinya |
| --- | --- |
| 1) Baris duplikat | produk + rak/RELAY yang sama muncul >1× (qty bisa terlihat dobel) |
| 2) Kandidat dobel RELAY | produk punya qty di rak **dan** di RELAY — "KUAT" kalau qty relay = total rak |
| 3) Baris RELAY dari opname | bukti baris RELAY baru dibuat oleh stock opname (qty sistem 0) |
| 4) Selisih buku besar | stok sistem vs hitungan riwayat (terima − kirim ± opname ± perbaikan CLI) |

#### Langkah 2 — Perbaiki (dry-run dulu, baru `--apply`)

```bash
# a) Gabungkan baris duplikat (kunci produk+rak/RELAY sama)
$DC exec -T app php artisan stocks:merge-duplicates                    # pratinjau
$DC exec -T app php artisan stocks:merge-duplicates --apply --reason="gabung baris relay duplikat"

# b) Hapus baris RELAY palsu (barangnya sebenarnya ada di rak)
$DC exec -T app php artisan stocks:set-quantity --product=P5162-0KA08 --rack=RELAY --qty=0 \
     --reason="barang sudah tercatat di rak A1"
$DC exec -T app php artisan stocks:set-quantity --product=P5162-0KA08 --rack=RELAY --qty=0 \
     --reason="barang sudah tercatat di rak A1" --apply

# c) Pindahkan isi RELAY ke rak aslinya (otomatis digabung kalau rak sudah ada isinya)
$DC exec -T app php artisan stocks:set-quantity --product=123 --rack=RELAY --move-to=A1 \
     --reason="pindah relay ke rak asli" --apply

# d) Samakan satu bucket dengan buku besar transaksi
$DC exec -T app php artisan stocks:set-quantity --product=123 --rack=RELAY --from-ledger \
     --reason="samakan dengan riwayat transaksi" --apply
```

- Tanpa `--apply` = **DRY-RUN** (tidak ada yang berubah).
- `--apply` wajib menyertakan `--reason` (min 5 karakter) dan di production
  meminta konfirmasi ketik `YA`.
- Setiap perbaikan dicatat sebagai baris **stock opname** `FIX-YYYYMMDD-NNN`
  (alasan tersimpan di kolom `notes`), jadi muncul di **Riwayat Opname** aplikasi
  dan tetap konsisten dengan buku besar.
- `--product` menerima **ID** maupun **part number**.

#### Langkah 3 — Verifikasi

```bash
$DC exec -T app php artisan stocks:audit
# Bagian 1–3 harus "tidak ada ✓"; bagian 4 (selisih buku besar) harus kosong.
```

> **Catatan `--from-ledger`**: mengandalkan riwayat transaksi. Kalau pernah
> memakai **Pemutihan Data** mode yang mempertahankan stok (`--keep-stock`),
> buku besar tidak bisa dipakai (riwayat dihapus tapi stok disimpan).

---

## 10. Quick Reference Card

```bash
# Git update + deploy backend only
git pull && ./deploy-production.sh --update

# Git update + deploy full (backend + frontend)
git pull && ./deploy-production.sh --update --with-assets

# Check status
docker compose -p wms-wma-prod -f docker-compose.prod.yml --env-file .env.prod ps

# Diagnosa queue worker (status, restart count, job backlog, log)
./deploy-production.sh --check-queue

# Diagnosa error 502/500 (container, OOM, Redis, migrasi, log)
./deploy-production.sh --diagnose

# Audit stok (read-only) — duplikat baris, RELAY dobel, selisih vs buku besar
docker compose -p wms-wma-prod -f docker-compose.prod.yml --env-file .env.prod exec -T app php artisan stocks:audit

# Perbaikan stok (dry-run dulu; tambahkan --apply --reason="..." untuk eksekusi)
docker compose -p wms-wma-prod -f docker-compose.prod.yml --env-file .env.prod exec -T app php artisan stocks:merge-duplicates

# View logs
docker compose -p wms-wma-prod -f docker-compose.prod.yml --env-file .env.prod logs -f app

# Run artisan
docker compose -p wms-wma-prod -f docker-compose.prod.yml --env-file .env.prod exec app php artisan ...
```

---

## 11. Bonus: Domain + HTTPS Setup

### 11.1 DNS Record

Arahkan domain/subdomain ke IP server:

```
Type:   A
Name:   wms          (atau @ untuk root domain)
Value:  123.45.67.89
TTL:    3600
```

Contoh hasil: `wms.example.com` → `123.45.67.89`

### 11.2 Update `.env.prod`

```env
# Ganti APP_URL ke domain
APP_URL=https://wms.example.com
APP_PORT=8090

# Aktifkan force HTTPS
FORCE_HTTPS=true

# Reverb WebSocket — harus pakai domain yg sama
VITE_REVERB_HOST="${APP_URL}"
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
REVERB_SCHEME=https
```

### 11.3 Host Nginx (Reverse Proxy + SSL Termination)

Install nginx di host (bukan di Docker — ini nginx parent):

```bash
apt install -y nginx certbot python3-certbot-nginx
```

Buat config reverse proxy:

```bash
nano /etc/nginx/sites-available/wms
```

```nginx
server {
    listen 80;
    server_name wms.example.com;

    # ── Header REQUEST (cookie bisa besar karena XSRF + sesi) ──────────
    # ⚠️ Dua direktif ini HANYA boleh di konteks `http` atau `server`.
    #    Kalau ditaruh di dalam `location { }` → nginx gagal start:
    #    "directive is not allowed here".
    large_client_header_buffers 8 32k;
    client_header_buffer_size 8k;

    # ── Proxy to Docker nginx (port 8081) ───────────────────
    location / {
        proxy_pass http://127.0.0.1:8081;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 300s;
        client_max_body_size 20M;

        # ── Buffer HEADER RESPONS — WAJIB (boleh di dalam location) ─────
        # Laravel mengirim header `Link:` Early Hints untuk semua aset build
        # (±7,5 KB dengan 124 aset); default 4-8 KB → nginx balas 502
        # "upstream sent too big header" (lihat §9.6).
        proxy_buffer_size 32k;
        proxy_buffers 8 32k;
        proxy_busy_buffers_size 64k;
    }

    # WebSocket (Reverb)
    location /app {
        proxy_pass http://127.0.0.1:8081;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_read_timeout 86400s;
    }
}
```

> **Alternatif lebih aman** (kalau tidak mau menyentuh file site): taruh dua direktif
> header request di **`http { }`** pada `/etc/nginx/nginx.conf` saja — berlaku untuk
> semua site, dan hanya `proxy_buffer_size`/`proxy_buffers`/`proxy_busy_buffers_size`
> yang ditambahkan di `location /`:
>
> ```nginx
> # /etc/nginx/nginx.conf
> http {
>     ...
>     large_client_header_buffers 8 32k;
>     client_header_buffer_size 8k;
>     ...
> }
> ```
>
> Cek posisi direktif yang sudah ada (kalau `nginx -t` menolak):
> ```bash
> sudo nl -ba /etc/nginx/sites-available/<nama-site> | sed -n '1,45p'
> ```

Enable site:

```bash
ln -s /etc/nginx/sites-available/wms /etc/nginx/sites-enabled/
rm /etc/nginx/sites-enabled/default 2>/dev/null || true
nginx -t && systemctl reload nginx
```

### 11.4 SSL via Let's Encrypt

```bash
# Dapatkan sertifikat
certbot --nginx -d wms.example.com

# Test auto-renewal
certbot renew --dry-run
```

> SSL auto-renew via systemd timer (otomatis setelah certbot install).

### 11.5 Update Firewall

```bash
ufw allow 80/tcp
ufw allow 443/tcp
ufw deny 8090/tcp     # Tutup direct port — hanya lewat nginx SSL
```

### 11.6 Rebuild Frontend (setelah APP_URL berubah)

```bash
su - deploy
cd /opt/wms-wma

# Export env baru
set -a
source .env.prod
set +a

# Rebuild Vite (URL asset harus absolute domain)
ASSET_URL="/" npm run build

# Deploy
./deploy-production.sh --update --with-assets
```

### 11.7 Verify

```bash
# HTTP → harus redirect ke HTTPS
curl -I http://wms.example.com | grep Location

# HTTPS → harus 200
curl -I https://wms.example.com

# Observatory check
# https://observatory.mozilla.org/analyze/wms.example.com
```

### 11.8 Arsitektur Final

```
Internet
   │
   ▼
┌──────────────────────────────────┐
│ Host Nginx (:80/:443)            │  ← SSL termination
│ /etc/nginx/sites-available/wms   │     Certbot auto-renew
└────────────┬─────────────────────┘
             │ proxy_pass http://127.0.0.1:8081
             ▼
┌──────────────────────────────┐
│ Docker Nginx (:8081 internal) │  ← Static files + proxy to app
└────────────┬─────────────────┘
             │ proxy_pass http://app:8080
             ▼
┌──────────────────────────────┐
│ App — FrankenPHP (:8080)     │  ← Laravel + Inertia
│ Security Headers Middleware  │     CSP, HSTS, nonce
└──────────────────────────────┘
```
