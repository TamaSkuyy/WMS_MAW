#!/usr/bin/env bash

set -euo pipefail

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
CYAN='\033[0;36m'
NC='\033[0m'

log() { echo -e "${GREEN}[dev-local]${NC} $*"; }
warn() { echo -e "${YELLOW}[warn]${NC} $*"; }
err() { echo -e "${RED}[error]${NC} $*"; }

usage() {
  cat <<'EOF'
Usage:
  ./scripts/dev-local.sh [--init] [--no-infra] [--with-reverb] [--with-queue]
  ./scripts/dev-local.sh --stop
  ./scripts/dev-local.sh artisan [args...]
  ./scripts/dev-local.sh php [args...]
  ./scripts/dev-local.sh mysql [args...]
  ./scripts/dev-local.sh composer [args...]
  ./scripts/dev-local.sh npm [args...]

Options:
  --init      Install deps & run initial Laravel setup (composer, npm, key, migrate, storage:link)
  --no-infra  Do not start mysql/redis/mailpit containers
  --with-reverb  Start Laravel Reverb server (artisan reverb:start)
  --with-queue   Start queue worker (artisan queue:work)
  --stop      Stop infra containers started by compose
  -h, --help  Show this help

Default behavior:
  1) Start mysql, redis, mailpit via podman/docker compose
  2) Run php artisan serve
  3) Run vite dev server (npm run dev)
  4) Optional: run Reverb/queue worker
  5) Stop app processes automatically on Ctrl+C
EOF
}

INIT=false
NO_INFRA=false
STOP_ONLY=false
WITH_REVERB=false
WITH_QUEUE=false
INFRA_STARTED=false

if command -v podman >/dev/null 2>&1; then
  RUNTIME="podman"
  COMPOSE_CMD="podman compose"
elif command -v docker >/dev/null 2>&1; then
  RUNTIME="docker"
  COMPOSE_CMD="docker compose"
else
  err "Podman/Docker tidak ditemukan."
  exit 1
fi

COMPOSE_FILE="docker-compose.yml"
PROJECT_NAME="wms-wma-dev"
COMPOSE_WWWUSER="${WWWUSER:-$(id -u)}"
COMPOSE_WWWGROUP="${WWWGROUP:-$(id -g)}"

dc() {
  WWWUSER="${COMPOSE_WWWUSER}" WWWGROUP="${COMPOSE_WWWGROUP}" \
    ${COMPOSE_CMD} -p "${PROJECT_NAME}" -f "${COMPOSE_FILE}" "$@"
}

if [ ! -f ".env" ]; then
  warn ".env belum ada, menyalin dari .env.example"
  cp .env.example .env
fi

get_env() {
  local key="$1"
  local default="$2"
  local value
  value=$(grep -E "^${key}=" .env | tail -1 | cut -d '=' -f2- || true)
  value="${value%\"}"
  value="${value#\"}"
  if [ -z "$value" ]; then
    echo "$default"
  else
    echo "$value"
  fi
}

resolve_php_bin() {
  if [ -n "${PHP_BIN:-}" ] && command -v "${PHP_BIN}" >/dev/null 2>&1; then
    echo "${PHP_BIN}"
    return 0
  fi

  if command -v php >/dev/null 2>&1; then
    echo "php"
    return 0
  fi

  if command -v php84 >/dev/null 2>&1; then
    echo "php84"
    return 0
  fi

  return 1
}

if ! PHP_CMD="$(resolve_php_bin)"; then
  err "PHP binary tidak ditemukan di shell non-interaktif."
  err "Set PATH global atau jalankan: PHP_BIN=php84 ./scripts/dev-local.sh"
  exit 1
fi

# --- Command Proxying ---
if [ "$#" -gt 0 ]; then
  case "$1" in
    artisan|php|composer|npm|node|mysql)
      CMD="$1"
      shift

      case "$CMD" in
        artisan)
          exec "$PHP_CMD" artisan "$@"
          ;;
        php)
          exec "$PHP_CMD" "$@"
          ;;
        composer)
          exec composer "$@"
          ;;
        npm)
          exec npm "$@"
          ;;
        node)
          exec node "$@"
          ;;
        mysql)
          DB_USERNAME=$(get_env DB_USERNAME "sail")
          DB_PASSWORD=$(get_env DB_PASSWORD "password")
          DB_DATABASE=$(get_env DB_DATABASE "wms_maw")

          # Coba jalankan mysql ke dalam container docker/podman yang berjalan
          if dc ps -q mysql >/dev/null 2>&1; then
            exec dc exec -it mysql mysql -u"${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" "$@"
          else
            err "Container mysql tidak berjalan."
            exit 1
          fi
          ;;
      esac
      ;;
  esac
fi

for arg in "$@"; do
  case "$arg" in
    --init) INIT=true ;;
    --no-infra) NO_INFRA=true ;;
    --with-reverb) WITH_REVERB=true ;;
    --with-queue) WITH_QUEUE=true ;;
    --stop) STOP_ONLY=true ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      err "Unknown option: $arg"
      usage
      exit 1
      ;;
  esac
done


APP_PORT="$(get_env APP_PORT 8000)"
VITE_PORT="$(get_env VITE_PORT 5175)"
REVERB_PORT="$(get_env REVERB_PORT 8081)"

if [[ "$APP_PORT" =~ ^[0-9]+$ ]] && [ "$APP_PORT" -lt 1024 ]; then
  warn "APP_PORT=${APP_PORT} adalah privileged port, fallback ke 8000 untuk user non-root."
  APP_PORT="8000"
fi

supports_reverb_signals() {
  "${PHP_CMD}" -r 'exit(extension_loaded("pcntl") && defined("SIGINT") ? 0 : 1);'
}

supports_php_redis() {
  "${PHP_CMD}" -r 'exit(class_exists("Redis") ? 0 : 1);'
}

if [ "$STOP_ONLY" = true ]; then
  log "Stopping infra containers..."
  dc stop mysql redis mailpit || true
  dc down --remove-orphans || true
  if pgrep -f "artisan serve --host=0.0.0.0" >/dev/null 2>&1; then
    warn "Stopping running php artisan serve process(es)..."
    pkill -f "artisan serve --host=0.0.0.0" || true
  fi
  if pgrep -f "npm run dev -- --host 0.0.0.0" >/dev/null 2>&1; then
    warn "Stopping running vite process(es)..."
    pkill -f "npm run dev -- --host 0.0.0.0" || true
  fi
  if pgrep -f "artisan reverb:start" >/dev/null 2>&1; then
    warn "Stopping running reverb process(es)..."
    pkill -f "artisan reverb:start" || true
  fi
  if pgrep -f "artisan queue:work" >/dev/null 2>&1; then
    warn "Stopping running queue worker process(es)..."
    pkill -f "artisan queue:work" || true
  fi
  log "Infra stopped."
  exit 0
fi

if [ "$NO_INFRA" = false ]; then
  log "Starting infra containers (mysql, redis, mailpit) with ${RUNTIME}..."
  dc up -d mysql redis mailpit
  INFRA_STARTED=true
else
  warn "Skipping infra startup (--no-infra)."
fi

if [ "$INIT" = true ]; then
  log "Running initial setup..."

  if [ ! -d "vendor" ]; then
    log "composer install"
    composer install
  else
    log "vendor already exists, skip composer install"
  fi

  if [ ! -d "node_modules" ]; then
    log "npm install"
    npm install --legacy-peer-deps


  else
    log "node_modules already exists, skip npm install"
  fi

  if ! grep -q '^APP_KEY=base64:' .env; then
    log "php artisan key:generate"
    "${PHP_CMD}" artisan key:generate
  fi

  log "Menunggu MySQL siap sebelum migrate..."
  DB_PWD=$(get_env DB_PASSWORD "password")
  for i in {1..30}; do
    if dc exec -T mysql mysqladmin ping -p"${DB_PWD}" >/dev/null 2>&1; then
      break
    fi
    sleep 2
  done

  log "php artisan migrate"
  "${PHP_CMD}" artisan migrate

  "${PHP_CMD}" artisan storage:link >/dev/null 2>&1 || true
fi

if [ ! -d "vendor" ]; then
  err "Folder vendor belum ada. Jalankan: composer install (atau pakai --init)"
  exit 1
fi

if [ ! -d "node_modules" ]; then
  err "Folder node_modules belum ada. Jalankan: npm install (atau pakai --init)"
  exit 1
fi

APP_URL_VAL="$(get_env APP_URL "")"
if [ -n "$APP_URL_VAL" ] && [[ "$APP_URL_VAL" == *":800" ]]; then
  warn "APP_URL saat ini: ${APP_URL_VAL}. Server local berjalan di :${APP_PORT}."
  warn "Disarankan ubah APP_URL di .env ke http://localhost:${APP_PORT} agar konsisten dengan Vite/Laravel plugin."
fi

cleanup() {
  warn "Stopping app processes..."
  if [ -n "${ARTISAN_PID:-}" ] && kill -0 "$ARTISAN_PID" 2>/dev/null; then
    kill "$ARTISAN_PID" 2>/dev/null || true
  fi
  if [ -n "${VITE_PID:-}" ] && kill -0 "$VITE_PID" 2>/dev/null; then
    kill "$VITE_PID" 2>/dev/null || true
  fi
  if [ -n "${REVERB_PID:-}" ] && kill -0 "$REVERB_PID" 2>/dev/null; then
    kill "$REVERB_PID" 2>/dev/null || true
  fi
  if [ -n "${QUEUE_PID:-}" ] && kill -0 "$QUEUE_PID" 2>/dev/null; then
    kill "$QUEUE_PID" 2>/dev/null || true
  fi
  wait 2>/dev/null || true

  if [ "$INFRA_STARTED" = true ]; then
    log "Stopping infra containers..."
    dc stop mysql redis mailpit || true
    dc down --remove-orphans || true
  fi

  log "Stopped."
}

trap cleanup INT TERM EXIT

log "Starting Laravel on http://localhost:${APP_PORT} ..."
"${PHP_CMD}" artisan serve --host=0.0.0.0 --port="${APP_PORT}" &
ARTISAN_PID=$!

log "Starting Vite on http://localhost:${VITE_PORT} ..."
npm run dev -- --host 0.0.0.0 --port "${VITE_PORT}" &
VITE_PID=$!

if [ "$WITH_REVERB" = true ]; then
  if ! supports_reverb_signals; then
    warn "Reverb dilewati: extension pcntl / konstanta SIGINT tidak tersedia di PHP CLI ini."
    warn "Gunakan PHP dengan pcntl aktif atau jalankan tanpa --with-reverb."
    WITH_REVERB=false
  elif ! supports_php_redis; then
    warn "Reverb dilewati: PHP extension Redis (phpredis) tidak tersedia di host CLI ini."
    warn "Opsi cepat: jalankan tanpa --with-reverb, atau pakai artisan via container."
    WITH_REVERB=false
  else
    log "Starting Reverb on http://localhost:${REVERB_PORT} ..."
    "${PHP_CMD}" artisan reverb:start --host=0.0.0.0 --port="${REVERB_PORT}" &
    REVERB_PID=$!
  fi
fi

if [ "$WITH_QUEUE" = true ]; then
  log "Starting queue worker..."
  "${PHP_CMD}" artisan queue:work --tries=1 &
  QUEUE_PID=$!
fi

echo -e "${CYAN}----------------------------------------------${NC}"
echo -e "${CYAN}Laravel : http://localhost:${APP_PORT}${NC}"
echo -e "${CYAN}Vite    : http://localhost:${VITE_PORT}${NC}"
if [ "$WITH_REVERB" = true ]; then
  echo -e "${CYAN}Reverb  : http://localhost:${REVERB_PORT}${NC}"
fi
echo -e "${CYAN}Mailpit : http://localhost:8025${NC}"
echo -e "${CYAN}Press Ctrl+C to stop app processes${NC}"
echo -e "${CYAN}----------------------------------------------${NC}"

wait -n "$ARTISAN_PID" "$VITE_PID"
