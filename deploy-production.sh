#!/bin/bash
# =============================================================================
# WMS WMA – Production Deployment Script
#
# Menjalankan Docker production stack TANPA mengganggu Laravel Sail.
#
# Usage:
#   ./deploy-production.sh          # Build & deploy
#   ./deploy-production.sh --build  # Force rebuild images
#   ./deploy-production.sh --down   # Stop production stack
# =============================================================================
set -euo pipefail

# Warna untuk output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[0;33m'
CYAN='\033[0;36m'
NC='\033[0m'

PROJECT_NAME="wms-wma-prod"
COMPOSE_FILE="docker-compose.prod.yml"
ENV_FILE=".env.prod"

log()     { echo -e "${GREEN}[DEPLOY]${NC} $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC} $*"; }
err()     { echo -e "${RED}[ERROR]${NC} $*"; }
success() { echo -e "${GREEN}[✓]${NC} $*"; }

# ── Parse arguments ────────────────────────────────────────────────────────────
FORCE_BUILD=false
BUILD_ASSETS=false
ACTION="deploy"

for arg in "$@"; do
    case "$arg" in
        --build)        FORCE_BUILD=true ;;
        --rebuild)      ACTION="rebuild" ;;
        --update)       ACTION="update" ;;
        --with-assets)  BUILD_ASSETS=true ;;
        --down)         ACTION="down" ;;
        --seed-storage)   ACTION="seed-storage" ;;
        --check-storage)  ACTION="check-storage" ;;
        --backup-storage) ACTION="backup-storage" ;;
        --help|-h)
            echo ""
            echo -e "${CYAN}Usage: $0 [OPTIONS]${NC}"
            echo ""
            echo -e "  ${GREEN}--update${NC}          ⚡ Update cepat tanpa rebuild image (~30-60 detik)"
            echo -e "                    Salin code PHP/Blade terbaru, jalankan migrate+cache+reload"
            echo -e "  ${GREEN}--update --with-assets${NC}  Sama + rebuild frontend Vite (~60-90 detik)"
            echo ""
            echo -e "  ${YELLOW}--rebuild${NC}         🔄 Rebuild image pakai cache + rolling restart (~2-3 menit)"
            echo -e "                    Gunakan jika composer.json atau package.json berubah"
            echo ""
            echo -e "  ${RED}--build${NC}           💥 Force rebuild TOTAL tanpa cache (~10 menit)"
            echo -e "                    Reserve untuk perubahan Dockerfile atau infra"
            echo ""
            echo -e "  --down            Stop production stack"
            echo -e "  --seed-storage    Copy ./storage dari host ke Docker volume (one-time)"
            echo -e "  --check-storage   Cek isi & status storage volume"
            echo -e "  --backup-storage  Backup storage volume ke ./backups/"
            echo ""
            echo -e "${CYAN}Kapan pakai apa:${NC}"
            echo "  Hanya ubah PHP/Blade/routes/config  →  ./deploy-production.sh --update"
            echo "  Ubah JS/CSS/Vite                    →  ./deploy-production.sh --update --with-assets"
            echo "  Tambah package composer/npm         →  ./deploy-production.sh --rebuild"
            echo "  Ubah Dockerfile / infra             →  ./deploy-production.sh --build"
            echo ""
            exit 0
            ;;
    esac
done

# ── Container runtime detection (Podman or Docker) ──────────────────────────
if command -v podman &>/dev/null; then
    RUNTIME="podman"
    COMPOSE_CMD="podman compose"
elif command -v docker &>/dev/null; then
    RUNTIME="docker"
    COMPOSE_CMD="docker compose"
else
    err "Tidak ditemukan podman maupun docker!"
    exit 1
fi
log "Container runtime: ${RUNTIME}"

dc() {
    ${COMPOSE_CMD} -p "${PROJECT_NAME}" -f "${COMPOSE_FILE}" --env-file "${ENV_FILE}" "$@"
}

clear_laravel_caches() {
    log "Clear cache aplikasi (optimize + Redis cache store)..."
    dc exec -T app php artisan optimize:clear
    dc exec -T app php artisan cache:clear
}

# ── Seed storage: copy host ./storage into named Docker volume ────────────────
seed_storage() {
    local CONTAINER
    CONTAINER=$(dc ps -q app 2>/dev/null | head -1)
    if [ -z "$CONTAINER" ]; then
        err "App container belum jalan. Jalankan deploy dulu, baru --seed-storage."
        exit 1
    fi
    if [ ! -d "./storage" ]; then
        err "Folder ./storage tidak ditemukan di host!"
        exit 1
    fi
    log "Copying ./storage ke container app..."
    ${RUNTIME} cp ./storage/. "${CONTAINER}:/var/www/html/storage/"
    log "Fixing permissions..."
    dc exec -T app chown -R sail:sail /var/www/html/storage
    dc exec -T app chmod -R 775 /var/www/html/storage
    log "Storage seeded ✓"
}

# ── Backup storage volume ke host ─────────────────────────────────────────────
backup_storage() {
    local CONTAINER
    CONTAINER=$(dc ps -q app 2>/dev/null | head -1)
    if [ -z "$CONTAINER" ]; then
        warn "App container belum jalan, skip backup."
        return
    fi
    BACKUP_DATE=$(date +%Y%m%d_%H%M%S)
    BACKUP_DIR="./backups/storage_${BACKUP_DATE}"
    log "Backup storage ke: ${BACKUP_DIR}"
    mkdir -p "${BACKUP_DIR}"
    ${RUNTIME} cp "${CONTAINER}:/var/www/html/storage/." "${BACKUP_DIR}/" 2>/dev/null || true
    log "Backup selesai ✓"
}

# ── Quick Update: copy code ke container tanpa rebuild image ─────────────────
quick_update() {
    local START_TIME
    START_TIME=$(date +%s)

    echo -e "${CYAN}"
    echo "========================================"
    echo "  WMS WMA – Quick Update"
    echo "========================================"
    echo -e "${NC}"

    # Validasi env
    if [ ! -f "${ENV_FILE}" ]; then
        err "File ${ENV_FILE} tidak ditemukan!"
        exit 1
    fi

    # Cek container berjalan
    APP_CONTAINER=$(dc ps -q app 2>/dev/null | head -1)
    if [ -z "$APP_CONTAINER" ]; then
        err "App container tidak berjalan. Jalankan full deploy dulu:"
        echo "  ./deploy-production.sh"
        exit 1
    fi

    local QUEUE_CONTAINER SCHED_CONTAINER
    QUEUE_CONTAINER=$(dc ps -q queue 2>/dev/null | head -1 || true)
    SCHED_CONTAINER=$(dc ps -q scheduler 2>/dev/null | head -1 || true)

    # SAFETY TRAP: pastikan maintenance mode SELALU dimatikan meski ada error di tengah jalan
    trap 'warn "Cleanup trap: memastikan maintenance mode OFF..."; dc exec -T app php artisan up 2>/dev/null || true' EXIT

    # 1. Aktifkan maintenance mode
    log "1/7 Maintenance mode ON..."
    dc exec -T app php artisan down --retry=5 --render="errors::503" 2>/dev/null || true

    # 2. Salin file kode (tanpa vendor, node_modules, dll)
    log "2/7 Salin kode PHP terbaru ke container app..."
    ${RUNTIME} cp app/. "${APP_CONTAINER}:/var/www/html/app/"
    ${RUNTIME} cp config/. "${APP_CONTAINER}:/var/www/html/config/"
    ${RUNTIME} cp database/. "${APP_CONTAINER}:/var/www/html/database/"
    ${RUNTIME} cp routes/. "${APP_CONTAINER}:/var/www/html/routes/"
    # Pastikan resources dir exist sebelum copy
    dc exec -T app mkdir -p /var/www/html/resources
    ${RUNTIME} cp resources/. "${APP_CONTAINER}:/var/www/html/resources/" 2>/dev/null || warn "Resources copy partial atau kosong"
    dc exec -T app chown -R sail:sail \
        /var/www/html/app \
        /var/www/html/config \
        /var/www/html/database \
        /var/www/html/routes \
        /var/www/html/resources

    # 3. Build & salin frontend assets (opsional)
    if [ "${BUILD_ASSETS}" = true ]; then
        log "3/7 Build frontend assets (Vite)..."
        # Clear Vite cache agar selalu rebuild dari scratch (bukan pakai cache lama)
        rm -rf node_modules/.vite public/build
        # ASSET_URL harus '/' agar CSS tidak meng-embed URL absolut localhost ke font path
        ASSET_URL="/" VITE_APP_URL="" npm run build

        # Salin ke container app
        ${RUNTIME} cp public/build/. "${APP_CONTAINER}:/var/www/html/public/build/"
        dc exec -T app chown -R sail:sail /var/www/html/public/build

        # PENTING: Salin juga ke container nginx karena nginx yang serve static files
        NGINX_CONTAINER=$(dc ps -q nginx 2>/dev/null | head -1 || true)
        if [ -n "$NGINX_CONTAINER" ]; then
            log "  → Salin assets ke nginx container..."
            ${RUNTIME} cp public/build/. "${NGINX_CONTAINER}:/var/www/html/public/build/"
        else
            warn "  Nginx container tidak ditemukan, assets hanya tersalin ke app."
        fi
    else
        log "3/7 Skip build assets (gunakan --with-assets untuk rebuild Vite)"
    fi

    # 4. Propagasi ke container lain (queue, scheduler, nginx)
    if [ -n "$QUEUE_CONTAINER" ]; then
        log "4/7 Propagasi ke container queue & scheduler..."
        for cnt in $QUEUE_CONTAINER $SCHED_CONTAINER; do
            [ -z "$cnt" ] && continue
            ${RUNTIME} cp app/. "${cnt}:/var/www/html/app/"
            ${RUNTIME} cp config/. "${cnt}:/var/www/html/config/"
            ${RUNTIME} cp routes/. "${cnt}:/var/www/html/routes/"
            ${RUNTIME} cp database/. "${cnt}:/var/www/html/database/"
            # Ensure resources dir exist
            ${RUNTIME} exec "$cnt" mkdir -p /var/www/html/resources 2>/dev/null || true
            ${RUNTIME} cp resources/. "${cnt}:/var/www/html/resources/" 2>/dev/null || true
        done
    else
        log "4/7 Skip (queue/scheduler tidak berjalan)"
    fi

    # Propagasi ke nginx container (untuk static files & view updates)
    NGINX_CONTAINER=$(dc ps -q nginx 2>/dev/null | head -1 || true)
    if [ -n "$NGINX_CONTAINER" ]; then
        log "  → Propagasi update ke nginx container..."
        ${RUNTIME} exec "$NGINX_CONTAINER" mkdir -p /var/www/html/resources 2>/dev/null || true
        ${RUNTIME} cp resources/. "${NGINX_CONTAINER}:/var/www/html/resources/" 2>/dev/null || true
        if [ -d "public/build/" ]; then
            ${RUNTIME} exec "$NGINX_CONTAINER" mkdir -p /var/www/html/public/build 2>/dev/null || true
            ${RUNTIME} cp public/build/. "${NGINX_CONTAINER}:/var/www/html/public/build/" 2>/dev/null || true
        fi
    fi

    # 5. Migrasi database
    log "5/7 Migrasi database..."
    dc exec -T app php artisan migrate --force

    # 6. Clear & rebuild semua cache
    log "6/7 Clear & rebuild cache (config, route, view, event)..."
    clear_laravel_caches
    dc exec -T app php artisan config:cache
    dc exec -T app php artisan route:cache
    dc exec -T app php artisan view:cache
    dc exec -T app php artisan event:cache
    dc exec -T app php artisan optimize

    # Restart queue workers (pick up new code)
    dc exec -T app php artisan queue:restart 2>/dev/null || true

    # 7. Graceful reload Octane workers (FrankenPHP)
    log "7/7 Graceful reload Octane workers..."
    if dc exec -T app php artisan octane:reload 2>/dev/null; then
        log "  Workers di-reload via octane:reload ✓"
    else
        warn "  octane:reload gagal — workers mungkin baru akan direfresh setelah container direstart"
    fi

    # Maintenance mode OFF — juga ditangani oleh trap EXIT di atas
    trap - EXIT
    dc exec -T app php artisan up

    local END_TIME ELAPSED
    END_TIME=$(date +%s)
    ELAPSED=$((END_TIME - START_TIME))

    echo
    echo -e "${CYAN}========================================${NC}"
    success "Quick Update selesai dalam ${ELAPSED} detik! 🚀"
    echo -e "${CYAN}========================================${NC}"
    echo
    echo "  Rebuild assets : ./deploy-production.sh --update --with-assets"
    echo "  Lihat logs     : ${COMPOSE_CMD} -p ${PROJECT_NAME} -f ${COMPOSE_FILE} logs -f"
    echo
}

# ── Smart Rebuild: build dengan cache + rolling restart ──────────────────────
smart_rebuild() {
    local START_TIME
    START_TIME=$(date +%s)

    echo -e "${CYAN}"
    echo "========================================"
    echo "  WMS WMA – Smart Rebuild (cached)"
    echo "========================================"
    echo -e "${NC}"

    if [ ! -f "${ENV_FILE}" ]; then
        err "File ${ENV_FILE} tidak ditemukan!"
        exit 1
    fi
    source "${ENV_FILE}"
    for var in DB_PASSWORD DB_ROOT_PASSWORD APP_KEY; do
        val="$(eval echo \$${var} 2>/dev/null || true)"
        if [ -z "$val" ] || echo "$val" | grep -qi 'CHANGE_ME\|REPLACE_ME'; then
            err "${var} belum diset di ${ENV_FILE}!"
            exit 1
        fi
    done

    # Build image DENGAN cache (jauh lebih cepat dari --no-cache)
    log "Build image (dengan layer cache)..."
    log "  Layer cache dipakai → hanya stage yang berubah yang direbuild"
    dc build

    # Rolling restart: service non-critical dulu, app terakhir
    log "Rolling restart services..."

    # Restart scheduler & queue dulu (tidak ada healthcheck ketat)
    dc up -d --no-deps scheduler queue reverb 2>/dev/null || dc up -d --no-deps queue 2>/dev/null || true

    # Maintenance mode
    APP_CONTAINER=$(dc ps -q app 2>/dev/null | head -1 || true)
    if [ -n "$APP_CONTAINER" ]; then
        dc exec -T app php artisan down --retry=5 --render="errors::503" 2>/dev/null || true
    fi

    # Restart app
    dc up -d --no-deps app

    # Tunggu app healthy
    log "Menunggu app healthy..."
    RETRIES=18
    for i in $(seq 1 $RETRIES); do
        STATUS=$(dc ps app --format '{{.Health}}' 2>/dev/null || echo "starting")
        if [ "$STATUS" = "healthy" ]; then
            success "App healthy! ✓"
            break
        fi
        if [ "$i" = "$RETRIES" ]; then
            warn "App belum healthy setelah ${RETRIES} percobaan."
            dc logs app --tail=20 2>&1 || true
            err "Rebuild gagal. Cek logs di atas."
            exit 1
        fi
        echo -n "."
        sleep 5
    done
    echo

    # Migrate + cache
    dc exec -T app php artisan migrate --force
    dc exec -T app php artisan storage:link --force || true
    clear_laravel_caches
    dc exec -T app php artisan config:cache
    dc exec -T app php artisan route:cache
    dc exec -T app php artisan view:cache
    dc exec -T app php artisan event:cache
    dc exec -T app php artisan optimize
    dc exec -T app php artisan queue:restart 2>/dev/null || true

    # App back online
    dc exec -T app php artisan up

    # Restart nginx juga untuk pick up image baru
    dc up -d --no-deps nginx 2>/dev/null || true

    local END_TIME ELAPSED
    END_TIME=$(date +%s)
    ELAPSED=$((END_TIME - START_TIME))

    echo
    echo -e "${CYAN}========================================${NC}"
    success "Smart Rebuild selesai dalam ${ELAPSED} detik! ✓"
    echo -e "${CYAN}========================================${NC}"
    echo
    dc ps
    echo
}

# ── Stop production stack ─────────────────────────────────────────────────────
if [ "${ACTION}" = "down" ]; then
    log "Menghentikan production stack..."
    dc down
    log "Production stack dihentikan."
    exit 0
fi

# ── Seed storage action ──────────────────────────────────────────────────────
if [ "${ACTION}" = "seed-storage" ]; then
    seed_storage
    exit 0
fi

# ── Check storage action ─────────────────────────────────────────────────────
if [ "${ACTION}" = "check-storage" ]; then
    log "=== Storage Volume Check ==="
    echo
    log "📁 Directory structure:"
    dc exec -T app find /var/www/html/storage -maxdepth 3 -type d | head -30
    echo
    log "📄 Uploads (app/public):"
    dc exec -T app ls -lah /var/www/html/storage/app/public/ 2>/dev/null || warn "Folder kosong"
    echo
    log "📋 Logs:"
    dc exec -T app ls -lah /var/www/html/storage/logs/ 2>/dev/null || warn "Folder kosong"
    echo
    log "🔗 Storage symlink:"
    dc exec -T app ls -la /var/www/html/public/storage 2>/dev/null || warn "Symlink belum ada!"
    echo
    log "🔒 Permissions (storage root):"
    dc exec -T app ls -la /var/www/html/storage/
    echo
    log "🔧 Framework:"
    dc exec -T app ls -la /var/www/html/storage/framework/
    echo
    # Maintenance mode check
    if dc exec -T app test -f /var/www/html/storage/framework/down 2>/dev/null; then
        warn "⚠️  MAINTENANCE MODE AKTIF! (file 'down' ditemukan)"
        warn "   Hapus dengan: $0 exec app php artisan up"
    else
        log "✅ Tidak ada maintenance mode"
    fi
    echo
    # Disk usage
    log "💾 Disk usage:"
    dc exec -T app du -sh /var/www/html/storage/ 2>/dev/null
    dc exec -T app du -sh /var/www/html/storage/app/public/ 2>/dev/null || true
    dc exec -T app du -sh /var/www/html/storage/logs/ 2>/dev/null || true
    exit 0
fi

# ── Backup storage action ────────────────────────────────────────────────────
if [ "${ACTION}" = "backup-storage" ]; then
    backup_storage
    exit 0
fi

# ── Quick Update action ───────────────────────────────────────────────────────
if [ "${ACTION}" = "update" ]; then
    quick_update
    exit 0
fi

# ── Smart Rebuild action ──────────────────────────────────────────────────────
if [ "${ACTION}" = "rebuild" ]; then
    smart_rebuild
    exit 0
fi

# ── Pre-flight checks ────────────────────────────────────────────────────────
echo -e "${CYAN}"
echo "========================================"
echo "  WMS WMA – Production Deployment"
echo "========================================"
echo -e "${NC}"

if [ ! -f "${ENV_FILE}" ]; then
    err "File ${ENV_FILE} tidak ditemukan!"
    warn "Salin template dan edit:"
    echo "  cp env.prod.docker.example .env.prod"
    echo "  nano .env.prod"
    exit 1
fi

# Validasi env vars penting
source "${ENV_FILE}"
for var in DB_PASSWORD DB_ROOT_PASSWORD APP_KEY; do
    val="$(eval echo \$${var} 2>/dev/null || true)"
    if [ -z "$val" ] || echo "$val" | grep -qi 'CHANGE_ME\|REPLACE_ME'; then
        err "${var} belum diset di ${ENV_FILE}!"
        exit 1
    fi
done

log "Environment file: ${ENV_FILE} ✓"
log "Project name: ${PROJECT_NAME} (terpisah dari Sail)"

# ── Build images ─────────────────────────────────────────────────────────────
if [ "${FORCE_BUILD}" = true ]; then
    warn "Force rebuild semua images (--no-cache)..."
    warn "Ini butuh ~10 menit. Gunakan --rebuild untuk rebuild lebih cepat dengan cache."
    dc build --no-cache
else
    log "Building images (dengan layer cache)..."
    dc build
fi

# ── Start services ───────────────────────────────────────────────────────────
log "Menjalankan production stack..."
dc up -d

# ── Wait for app health ──────────────────────────────────────────────────────
log "Menunggu app healthy..."
RETRIES=20
for i in $(seq 1 $RETRIES); do
    STATUS=$(dc ps app --format '{{.Health}}' 2>/dev/null || echo "starting")
    if [ "$STATUS" = "healthy" ]; then
        log "App healthy! ✓"
        break
    fi
    if [ "$i" = "$RETRIES" ]; then
        warn "App belum healthy setelah ${RETRIES} percobaan."
        warn "=== Container Status ==="
        dc ps
        echo
        warn "=== App Logs (last 30 lines) ==="
        dc logs app --tail=30 2>&1 || true
        echo
        warn "=== Manual healthcheck test ==="
        dc exec -T app curl -sv http://localhost:8080/health/ping 2>&1 || echo "(curl gagal — Octane mungkin belum start)"
        echo
        err "Deployment gagal. Fix error di atas lalu jalankan ulang."
        exit 1
    fi
    echo -n "."
    sleep 5
done
echo

# ── Run migrations ───────────────────────────────────────────────────────────
log "Menjalankan migrasi database..."
dc exec -T app php artisan migrate --force
log "Storage link + rebuild artisan caches..."
dc exec -T app php artisan storage:link --force || true
clear_laravel_caches
dc exec -T app php artisan config:cache
dc exec -T app php artisan route:cache
dc exec -T app php artisan view:cache
dc exec -T app php artisan event:cache
dc exec -T app php artisan optimize
dc exec -T app php artisan queue:restart 2>/dev/null || true
# ── Readiness check (verify DB + Redis from inside app) ──────────────────
log "Verifikasi readiness (DB + Redis)..."
READY_RESPONSE=$(dc exec -T app curl -s http://localhost:8080/health/ready 2>/dev/null || true)
if echo "$READY_RESPONSE" | grep -q '"status":"ready"'; then
    log "App ready — DB & Redis connected ✓"
else
    warn "App belum fully ready. Response:"
    echo "  $READY_RESPONSE"
    warn "Cek logs untuk detail:"
    echo "  ${COMPOSE_CMD} -p ${PROJECT_NAME} -f ${COMPOSE_FILE} logs app --tail=30"
    echo "  ${COMPOSE_CMD} -p ${PROJECT_NAME} -f ${COMPOSE_FILE} exec app cat storage/logs/laravel.log | tail -50"
fi
# ── Summary ─────────────────────────────────────────────────────────────────
APP_PORT_VAL=${APP_PORT:-8090}

echo
echo -e "${CYAN}========================================${NC}"
log "Deployment selesai!"
echo -e "${CYAN}========================================${NC}"
echo
echo -e "  App URL    : ${GREEN}http://SERVER_IP:${APP_PORT_VAL}${NC}"
echo -e "  WebSocket  : ${GREEN}ws://SERVER_IP:${APP_PORT_VAL}/app${NC} (via nginx)"
echo
echo "    Backup    : ls -lah ./backups/"
echo -e "  ${YELLOW}Perintah berguna:${NC}"
echo "    Logs      : ${COMPOSE_CMD} -p ${PROJECT_NAME} -f ${COMPOSE_FILE} logs -f"
echo "    Status    : ${COMPOSE_CMD} -p ${PROJECT_NAME} -f ${COMPOSE_FILE} ps"
echo "    Stop      : ./deploy-production.sh --down"
echo "    Rebuild   : ./deploy-production.sh --build"
echo "    Seed data : ./deploy-production.sh --seed-storage    (copy ./storage → volume)"
echo "    Cek data  : ./deploy-production.sh --check-storage   (inspeksi isi volume)"
echo "    Backup    : ./deploy-production.sh --backup-storage  (copy volume → ./backups/)"
echo "    Artisan   : ${COMPOSE_CMD} -p ${PROJECT_NAME} -f ${COMPOSE_FILE} exec app php artisan ..."
echo

# ── Show running containers ───────────────────────────────────────────────────
dc ps
