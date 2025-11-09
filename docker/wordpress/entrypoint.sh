#!/bin/bash
set -euo pipefail

WP_PATH="/var/www/html"

log() {
  echo "[wordpress-entrypoint] $*"
}

DB_HOST=${WORDPRESS_DB_HOST:-wordpress-db}
DB_PORT=${WORDPRESS_DB_PORT:-3306}
DB_USER=${WORDPRESS_DB_USER:-wordpress}
DB_PASSWORD=${WORDPRESS_DB_PASSWORD:-wordpress}
DB_NAME=${WORDPRESS_DB_NAME:-wordpress}

SITE_URL=${WORDPRESS_SITE_URL:-http://localhost:8081}
SITE_TITLE=${WORDPRESS_SITE_TITLE:-"Temporal WordPress Demo"}
ADMIN_USER=${WORDPRESS_ADMIN_USER:-admin}
ADMIN_PASS=${WORDPRESS_ADMIN_PASSWORD:-admin}
ADMIN_EMAIL=${WORDPRESS_ADMIN_EMAIL:-admin@example.com}

TEMPORAL_URL=${TEMPORAL_REST_URL:-}

wait_for_db() {
  until mysqladmin --protocol=tcp ping \
      -h "${DB_HOST}" \
      -P "${DB_PORT}" \
      -u "${DB_USER}" \
      -p"${DB_PASSWORD}" >/dev/null 2>&1; do
    log "Waiting for MySQL at ${DB_HOST}:${DB_PORT}..."
    sleep 2
  done
  log "MySQL is reachable."
}

ensure_wp_core() {
  if [ ! -f "${WP_PATH}/wp-settings.php" ]; then
    log "WordPress core missing; downloading files."
    wp --path="${WP_PATH}" core download --force
  fi
}

ensure_wp_config() {
  if [ ! -f "${WP_PATH}/wp-config.php" ]; then
    log "Generating wp-config.php"
    wp --path="${WP_PATH}" config create \
      --dbname="${DB_NAME}" \
      --dbuser="${DB_USER}" \
      --dbpass="${DB_PASSWORD}" \
      --dbhost="${DB_HOST}:${DB_PORT}" \
      --skip-check || {
        log "Failed to create wp-config.php"
        exit 1
      }
  fi
}

install_wordpress() {
  log "Running automated WordPress install..."
  wp --path="${WP_PATH}" core install \
    --url="${SITE_URL}" \
    --title="${SITE_TITLE}" \
    --admin_user="${ADMIN_USER}" \
    --admin_password="${ADMIN_PASS}" \
    --admin_email="${ADMIN_EMAIL}" \
    --skip-email

  if wp --path="${WP_PATH}" plugin is-installed wp-temporal-cms >/dev/null 2>&1; then
    wp --path="${WP_PATH}" plugin activate wp-temporal-cms || true
  fi

  if [ -n "${TEMPORAL_URL}" ]; then
    wp --path="${WP_PATH}" option update temporal_cms_rest_url "${TEMPORAL_URL}" >/dev/null 2>&1 || true
  fi

  log "WordPress install complete."
}

wait_for_db
ensure_wp_core
ensure_wp_config
if wp --path="${WP_PATH}" core is-installed >/dev/null 2>&1; then
  log "WordPress already installed; skipping auto-install."
else
  install_wordpress
fi

log "Starting WordPress services."
if [ "$#" -eq 0 ]; then
  set -- apache2-foreground
fi
exec /usr/local/bin/docker-entrypoint.sh "$@"
