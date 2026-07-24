# Buku Manual Administrator — Warehouse Management System

## Mitra Adhi Wasana

> **Untuk:** IT Support / System Administrator  
> **Versi:** 1.0 — Juli 2026

---

## Daftar Isi

1. [Arsitektur Sistem](#1-arsitektur-sistem)
2. [Instalasi Awal](#2-instalasi-awal)
3. [Deployment & Update](#3-deployment--update)
4. [Backup & Restore](#4-backup--restore)
5. [Manajemen User](#5-manajemen-user)
6. [Perintah Artisan Penting](#6-perintah-artisan-penting)
7. [Troubleshooting](#7-troubleshooting)
8. [Monitoring & Maintenance](#8-monitoring--maintenance)
9. [Keamanan](#9-keamanan)

---

## 1. Arsitektur Sistem

### Stack
```
┌──────────────────────────────────────────────┐
│                NGINX (:8081)                  │
│          Reverse proxy + static files         │
└──────────────┬───────────────────────────────┘
               │
┌──────────────▼───────────────────────────────┐
│            APP — Laravel Octane (:8080)       │
│       FrankenPHP + Laravel 11 + Inertia       │
└──────┬───────────────┬───────────────┬───────┘
       │               │               │
┌──────▼──────┐ ┌──────▼──────┐ ┌──────▼──────┐
│   MySQL 8   │ │   Redis     │ │  Reverb     │
│  (:3306)    │ │  (:6379)    │ │  (:8081)    │
└─────────────┘ └─────────────┘ └─────────────┘
```

### Layanan Docker (Production)
| Service | Port | Fungsi |
|---------|------|--------|
| `app` | `8080` (internal) | Aplikasi Laravel Octane/FrankenPHP |
| `nginx` | `8081` → `80` | Reverse proxy & serve static files |
| `mysql` | `3306` (internal) | Database |
| `redis` | `6379` (internal) | Cache, session, queue |
| `queue` | — | Queue worker background jobs |
| `scheduler` | — | Cron/scheduled tasks |
| `reverb` | `8081` (internal) | WebSocket real-time events |

### File Penting
```
/var/www/wms-app/
├── .env.prod              ← Environment variables production
├── docker-compose.prod.yml ← Docker services
├── deploy-production.sh   ← Deployment script
├── storage/               ← Uploads, logs, framework (Docker volume)
├── backups/               ← Backup otomatis
└── public/build/          ← Frontend assets (hasil Vite build)
```

---

## 2. Instalasi Awal

### Prasyarat
- Server Linux (Ubuntu 22.04+ recommended)
- Docker 24+ & Docker Compose v2
- 2+ GB RAM, 20+ GB disk
- Domain/port dibuka: `8081` (app via nginx)

### Langkah Instalasi Pertama Kali

```bash
# 1. Clone project
cd /var/www
git clone <repo-url> wms-app
cd wms-app

# 2. Setup environment file
cp env.prod.docker.example .env.prod
nano .env.prod
#   → Ganti DB_PASSWORD, DB_ROOT_PASSWORD
#   → Generate APP_KEY (lihat bawah)

# 3. Generate APP_KEY
docker compose -f docker-compose.prod.yml run --rm app php artisan key:generate
#   → Copy output APP_KEY ke .env.prod

# 4. Deploy pertama kali
./deploy-production.sh

# 5. Seed storage (upload folder, logs, dll)
./deploy-production.sh --seed-storage
```

### Setup Awal Data
```bash
# Setelah deploy, masuk ke container untuk setup data
docker compose -f docker-compose.prod.yml exec app bash

# Import data dari Excel (supplier, produk, model)
php artisan db:clean --force                 # Bersihkan data dummy
php artisan product:import-from-excel storage/app/imports/data.xlsx
```

---

## 3. Deployment & Update

### Pilihan Deploy

| Command | Waktu | Kapan |
|---------|-------|-------|
| `./deploy-production.sh --update` | ~30-60 detik | Ubah kode PHP/Blade/routes/config |
| `./deploy-production.sh --update --with-assets` | ~60-90 detik | Ubah JS/CSS/Vite |
| `./deploy-production.sh --rebuild` | ~2-3 menit | Tambah package composer/npm |
| `./deploy-production.sh --build` | ~10 menit | Ubah Dockerfile / infrastruktur |

### Alur Update Harian
```bash
# 1. Pull kode terbaru
git pull origin main

# 2. Deploy (pilih sesuai perubahan)
./deploy-production.sh --update --with-assets

# 3. Verifikasi
./deploy-production.sh --check-storage
docker compose -f docker-compose.prod.yml ps
```

### Rollback Cepat
```bash
# Kembali ke commit sebelumnya
git log --oneline -5          # Lihat history
git revert <commit-hash>      # Revert commit spesifik
./deploy-production.sh --update --with-assets
```

### Maintenance Mode
```bash
# Aktifkan (user lihat halaman "sedang maintenance")
docker compose -f docker-compose.prod.yml exec app php artisan down --retry=5

# Kembalikan
docker compose -f docker-compose.prod.yml exec app php artisan up
```

---

## 4. Backup & Restore

### Backup Database
```bash
# Backup via container
docker compose -f docker-compose.prod.yml exec mysql \
  mysqldump -u root -p"${DB_PASSWORD}" wms_wma \
  > backups/db_$(date +%Y%m%d_%H%M%S).sql

# Atau dari host (kalau port mysql dibuka)
mysqldump -h 127.0.0.1 -P 3306 -u root -p wms_wma > backup.sql
```

### Backup Storage (Upload, Log, Framework)
```bash
./deploy-production.sh --backup-storage
# → Tersimpan di ./backups/storage_<timestamp>/
```

### Backup Full (DB + Storage)
```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p backups/$DATE

# DB
docker compose -f docker-compose.prod.yml exec -T mysql \
  mysqldump -u root -p"${DB_PASSWORD}" wms_wma > backups/$DATE/database.sql

# Storage
docker compose -f docker-compose.prod.yml exec -T app \
  tar -czf - /var/www/html/storage > backups/$DATE/storage.tar.gz

echo "Backup selesai: backups/$DATE"
```

### Restore Database
```bash
# 1. Pastikan tidak ada koneksi aktif
docker compose -f docker-compose.prod.yml exec app php artisan down

# 2. Restore
docker compose -f docker-compose.prod.yml exec -T mysql \
  mysql -u root -p"${DB_PASSWORD}" wms_wma < backups/database.sql

# 3. Clear cache & up
docker compose -f docker-compose.prod.yml exec app php artisan optimize:clear
docker compose -f docker-compose.prod.yml exec app php artisan up
```

### Jadwal Backup (via Cron)
```bash
# Tambahkan ke crontab server
0 2 * * * cd /var/www/wms-app && ./deploy-production.sh --backup-storage
0 3 * * * cd /var/www/wms-app && docker compose -f docker-compose.prod.yml exec -T mysql mysqldump -u root -p"DB_PASSWORD_HERE" wms_wma > backups/db_daily.sql
0 4 * * 0 find /var/www/wms-app/backups -name "*.sql" -mtime +30 -delete  # hapus >30 hari
```

---

## 5. Manajemen User

### Membuat User Baru
```bash
docker compose -f docker-compose.prod.yml exec app php artisan tinker

# Di dalam tinker:
$user = App\Models\User::create([
    'name' => 'Nama User',
    'email' => 'user@example.com',
    'password' => bcrypt('password123'),
]);
$user->assignRole('user');  // 'super-admin', 'admin', atau 'user'
```

### Reset Password
```bash
docker compose -f docker-compose.prod.yml exec app php artisan tinker
>>> App\Models\User::where('email', 'user@example.com')->first()->update(['password' => bcrypt('newpassword')]);
```

### Manajemen Role & Permission (via Web UI)
```
1. Login sebagai Super Admin
2. Buka Setup > Roles → tambah/edit role
3. Buka Setup > Permissions → atur akses per menu
4. Buka Setup > Users → assign role ke user
```

### Role Bawaan
| Role | Akses |
|------|-------|
| **Super Admin** | Semua menu termasuk Setup, User, Role, Permission |
| **Admin** | Master data + Transaksi + Laporan (tanpa Setup) |
| **User** | Transaksi + Laporan saja |

---

## 6. Perintah Artisan Penting

### Cache Management
```bash
# Clear semua cache (development/debugging)
php artisan optimize:clear

# Rebuild cache (production — setelah deploy)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan optimize
```

### Database
```bash
php artisan migrate              # Jalankan migrasi
php artisan migrate:rollback     # Undo migrasi terakhir
php artisan migrate:status       # Cek status migrasi
```

### Data
```bash
php artisan db:clean --force              # Hapus semua data dummy
php artisan db:clean --keep-users          # Hapus dummy tapi keep users
php artisan product:import-from-excel <file>  # Import produk dari Excel
```

### Queue & Worker
```bash
php artisan queue:retry all     # Retry semua failed jobs
php artisan queue:failed        # Lihat failed jobs
php artisan queue:restart       # Restart queue workers
```

### Maintenance
```bash
php artisan down --retry=5      # Maintenance mode ON
php artisan up                  # Maintenance mode OFF
php artisan storage:link        # Buat symlink storage → public
```

---

## 7. Troubleshooting

### Masalah Umum & Solusi

| Gejala | Kemungkinan Penyebab | Solusi |
|--------|---------------------|--------|
| **503 Service Unavailable** | Maintenance mode aktif | `php artisan up` |
| **500 Server Error** | Cache corrupt / config error | `php artisan optimize:clear && php artisan config:cache` |
| **Halaman putih / CSS rusak** | Asset Vite tidak ter-update | `./deploy-production.sh --update --with-assets` |
| **Login gagal / session hilang** | Redis down / session expired | Cek Redis: `docker compose exec app php -r "var_dump(Redis::ping());"` |
| **Database connection refused** | MySQL container down | `docker compose ps mysql` — restart kalau perlu |
| **WebSocket tidak update** | Reverb down / port blocked | `docker compose logs reverb` — cek koneksi |
| **Queue jobs menumpuk** | Queue worker down | `docker compose restart queue` |
| **Disk usage tinggi** | Log / backup menumpuk | `du -sh storage/logs/ backups/` — hapus yang lama |
| **Docker cp gagal ("missed writing bytes")** | Disk I/O sibuk | Retry deploy script (sudah ada auto-retry di v1.0+) |
| **Lambat setelah sekian lama** | Cache Redis penuh / fragmentasi | `docker compose restart redis` |

### Cek Status Sistem
```bash
# Semua container
docker compose -f docker-compose.prod.yml ps

# Log real-time
docker compose -f docker-compose.prod.yml logs -f --tail=50

# Log spesifik service
docker compose -f docker-compose.prod.yml logs app --tail=30
docker compose -f docker-compose.prod.yml logs mysql --tail=20

# Cek disk
df -h
docker compose -f docker-compose.prod.yml exec app du -sh /var/www/html/storage/
```

### Recovery Mode (Sistem Rusak Total)
```bash
# 1. Stop semua
docker compose -f docker-compose.prod.yml down

# 2. Restore DB dari backup
# (lihat bagian Backup & Restore)

# 3. Rebuild dari awal
./deploy-production.sh --build

# 4. Seed storage
./deploy-production.sh --seed-storage
```

---

## 8. Monitoring & Maintenance

### Cek Harian
```bash
# 1. Status container
docker compose -f docker-compose.prod.yml ps

# 2. Health check
curl http://localhost:8080/health/ping
curl http://localhost:8080/health/ready

# 3. Cek disk
df -h | grep -E "/$|/var"

# 4. Cek log error terbaru
docker compose -f docker-compose.prod.yml exec app tail -50 storage/logs/laravel.log
```

### Cek Mingguan
```bash
# 1. Cleanup log lama (>7 hari)
docker compose -f docker-compose.prod.yml exec app \
  find /var/www/html/storage/logs -name "*.log" -mtime +7 -delete

# 2. Optimasi database
docker compose -f docker-compose.prod.yml exec mysql \
  mysqlcheck -u root -p"${DB_PASSWORD}" --optimize --databases wms_wma

# 3. Cek backup
ls -lah backups/ | tail -10
```

### Maintenance Bulanan
```bash
# 1. Docker cleanup (hati-hati di production!)
docker system prune -a --volumes --force

# 2. Update OS
sudo apt update && sudo apt upgrade -y

# 3. Rotate backup (>30 hari dihapus)
find backups/ -mtime +30 -delete
```

---

## 9. Keamanan

### Checklist Keamanan
- [ ] `.env.prod` **tidak** boleh di-commit ke git
- [ ] `APP_DEBUG=false` di production
- [ ] `DB_PASSWORD` & `APP_KEY` harus kuat (min 32 karakter)
- [ ] Port database (`3306`) **tidak** boleh expose ke public
- [ ] Ganti password default admin setelah instalasi
- [ ] Backup rutin (minimal harian)
- [ ] Update OS security patches

### Ganti Semua Password Default
```bash
# 1. Database root password
docker compose -f docker-compose.prod.yml exec mysql \
  mysql -u root -p"OLD_PASSWORD" -e "ALTER USER 'root'@'%' IDENTIFIED BY 'NEW_STRONG_PASSWORD';"

# 2. Update .env.prod dengan password baru
nano .env.prod

# 3. Rebuild container
./deploy-production.sh --rebuild
```

### Firewall (UFW)
```bash
# Hanya buka port yang diperlukan
sudo ufw allow 22/tcp      # SSH
sudo ufw allow 8081/tcp    # Aplikasi (nginx)
sudo ufw enable
sudo ufw status
```

### Audit Trail
```bash
# Cek login attempts
docker compose -f docker-compose.prod.yml exec app \
  php artisan tinker --execute="App\Models\User::all(['name','email','last_login_at'])"

# Cek perubahan data penting (dari log)
docker compose -f docker-compose.prod.yml exec app \
  grep -E "created|updated|deleted" storage/logs/laravel.log | tail -50
```

---

> 📧 Kontak dukungan teknis: [isi kontak IT]  
> 📅 Manual ini diperbarui: Juli 2026
