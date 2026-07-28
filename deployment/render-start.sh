#!/usr/bin/env bash
set -Eeuo pipefail

cd /var/www/html

render_port="${PORT:-10000}"
if [[ ! "$render_port" =~ ^[0-9]+$ ]] || (( render_port < 1 || render_port > 65535 )); then
    echo >&2 "PORT must be a number between 1 and 65535."
    exit 1
fi

sed -ri "s/^Listen [0-9]+$/Listen ${render_port}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \\*:[0-9]+>/<VirtualHost *:${render_port}>/" /etc/apache2/sites-available/000-default.conf

if [[ -s /etc/secrets/aiven-ca.pem ]]; then
    install -m 0644 /etc/secrets/aiven-ca.pem /usr/local/share/ca-certificates/aiven-ca.crt
    update-ca-certificates >/dev/null
fi

# Run the official initialization without starting the long-running Apache process.
/usr/local/bin/docker-entrypoint.sh apache2 -v >/dev/null

mkdir -p wp-content/uploads
chown -R www-data:www-data wp-content/uploads
touch .htaccess
chown www-data:www-data .htaccess

site_url="${TRADEFLOW_SITE_URL:-}"
if [[ -z "$site_url" && -n "${RENDER_EXTERNAL_HOSTNAME:-}" ]]; then
    site_url="https://${RENDER_EXTERNAL_HOSTNAME}"
fi
if [[ -z "$site_url" ]]; then
    echo >&2 "Set TRADEFLOW_SITE_URL to the public HTTPS URL for this service."
    exit 1
fi
site_url="${site_url%/}"

wp_cli=(wp --allow-root --path=/var/www/html)

required_variables=(
    TRADEFLOW_ADMIN_USER
    TRADEFLOW_ADMIN_PASSWORD
    TRADEFLOW_ADMIN_EMAIL
)
for variable_name in "${required_variables[@]}"; do
    if [[ -z "${!variable_name:-}" ]]; then
        echo >&2 "Set ${variable_name} before the first deploy."
        exit 1
    fi
done

wordpress_ready=false
for attempt in {1..30}; do
    if "${wp_cli[@]}" core is-installed >/dev/null 2>&1; then
        wordpress_ready=true
        break
    fi

    if "${wp_cli[@]}" core install \
        --url="$site_url" \
        --title="${TRADEFLOW_SITE_TITLE:-TradeFlow}" \
        --admin_user="$TRADEFLOW_ADMIN_USER" \
        --admin_password="$TRADEFLOW_ADMIN_PASSWORD" \
        --admin_email="$TRADEFLOW_ADMIN_EMAIL" \
        --skip-email >/dev/null 2>&1; then
        wordpress_ready=true
        break
    fi

    echo "Waiting for MySQL (${attempt}/30)..."
    sleep 2
done
if [[ "$wordpress_ready" != true ]]; then
    echo >&2 "WordPress could not connect to MySQL within 60 seconds."
    exit 1
fi

if ! "${wp_cli[@]}" plugin is-active tradeflow-core >/dev/null 2>&1; then
    "${wp_cli[@]}" plugin activate tradeflow-core >/dev/null
fi
if ! "${wp_cli[@]}" theme is-active tradeflow >/dev/null 2>&1; then
    "${wp_cli[@]}" theme activate tradeflow >/dev/null
fi
"${wp_cli[@]}" option update timezone_string "${TRADEFLOW_TIMEZONE:-America/Toronto}" >/dev/null
"${wp_cli[@]}" option update default_comment_status closed >/dev/null
"${wp_cli[@]}" option update default_ping_status closed >/dev/null
"${wp_cli[@]}" rewrite structure '/%postname%/' --hard >/dev/null

exec /usr/local/bin/docker-entrypoint.sh "$@"
