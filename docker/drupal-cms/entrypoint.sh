#!/bin/bash
set -euo pipefail

log() {
  echo "[drupal-entrypoint] $*"
}

DB_HOST=${DRUPAL_DB_HOST:-drupal-db}
DB_NAME=${DRUPAL_DB_NAME:-drupal}
DB_USER=${DRUPAL_DB_USER:-drupal}
DB_PASSWORD=${DRUPAL_DB_PASSWORD:-drupal}
DB_PORT=${DRUPAL_DB_PORT:-3306}
DB_QUERY=${DRUPAL_DB_QUERY:-"ssl-mode=DISABLED"}
DB_SSL_MODE=${DRUPAL_DB_SSL_MODE:-DISABLED}
AUTO_RESET_DB=${DRUPAL_AUTO_RESET_DB:-true}
SITE_NAME=${DRUPAL_SITE_NAME:-"Temporal CMS Demo"}
ADMIN_USER=${DRUPAL_ADMIN_USER:-admin}
ADMIN_PASS=${DRUPAL_ADMIN_PASSWORD:-admin}
ADMIN_EMAIL=${DRUPAL_ADMIN_EMAIL:-admin@example.com}
DRUSH_BIN="/opt/drupal/vendor/bin/drush"
DRUPAL_ROOT="/opt/drupal/web"

mysql_cmd() {
  mysql \
    --host="${DB_HOST}" \
    --port="${DB_PORT}" \
    --user="${DB_USER}" \
    --password="${DB_PASSWORD}" \
    --ssl=0 \
    "$@"
}

wait_for_db() {
  until mysql_cmd -e 'SELECT 1' >/dev/null 2>&1; do
    log "Waiting for MySQL at ${DB_HOST}:${DB_PORT}..."
    sleep 2
  done
  log "MySQL reachable, continuing."
}

drush_status() {
  su -s /bin/bash www-data -c "${DRUSH_BIN} --root=${DRUPAL_ROOT} status --field=bootstrap" >/tmp/drush-status 2>&1
}

clean_leftovers() {
  local settings="${DRUPAL_ROOT}/sites/default/settings.php"
  if [ -f "${settings}" ]; then
    log "Removing leftover settings.php to allow clean install."
    rm -f "${settings}"
  fi
  local services="${DRUPAL_ROOT}/sites/default/services.yml"
  if [ -f "${services}" ]; then
    rm -f "${services}"
  fi
}

reset_database() {
  if [ "${AUTO_RESET_DB}" != "true" ]; then
    log "Auto-reset disabled; skipping DROP/CREATE."
    return
  fi
  log "Resetting database ${DB_NAME}..."
  if mysql_cmd -e "DROP DATABASE IF EXISTS \`${DB_NAME}\`;"; then
    log "Database dropped (if it existed)."
  else
    log "Unable to drop database (continuing)."
  fi
  if mysql_cmd -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"; then
    log "Database ready."
  else
    log "Unable to create database (continuing; it may already exist)."
  fi
}

install_drupal() {
  local db_url="mysql://${DB_USER}:${DB_PASSWORD}@${DB_HOST}:${DB_PORT}/${DB_NAME}"
  if [ -n "${DB_QUERY}" ]; then
    db_url="${db_url}?${DB_QUERY}"
  fi
  log "Running automated Drupal CMS install..."
  clean_leftovers
  reset_database
  su -s /bin/bash www-data -c "${DRUSH_BIN} --root=${DRUPAL_ROOT} site:install drupal_cms_installer \
    --db-url='${db_url}' \
    --site-name='${SITE_NAME}' \
    --account-name='${ADMIN_USER}' \
    --account-pass='${ADMIN_PASS}' \
    --account-mail='${ADMIN_EMAIL}' \
    -y" >/tmp/drush-install.log 2>&1 || {
      cat /tmp/drush-install.log
      exit 1
    }
}

wait_for_db

if drush_status && grep -qi "Successful" /tmp/drush-status; then
  log "Drupal CMS already installed; skipping."
else
  install_drupal
  chown -R www-data:www-data /opt/drupal
  log "Drupal CMS install complete."
fi

exec apache2-foreground
