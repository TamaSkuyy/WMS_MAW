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
| Worker stuck / queue not processing | `docker compose ... exec app php artisan queue:restart` |
| Reverb WebSocket not connecting | Cek `REVERB_*` vars di `.env.prod`; pastikan port nginx proxy `/app` ke reverb |
| Maintenance mode stuck | `docker compose ... exec app php artisan up` |

---

## 10. Quick Reference Card

```bash
# Git update + deploy backend only
git pull && ./deploy-production.sh --update

# Git update + deploy full (backend + frontend)
git pull && ./deploy-production.sh --update --with-assets

# Check status
docker compose -p wms-wma-prod -f docker-compose.prod.yml --env-file .env.prod ps

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
