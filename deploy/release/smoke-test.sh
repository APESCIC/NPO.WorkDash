#!/bin/bash
#
# Install a release zip on a bare Apache + PHP host (no Composer, no Node) and
# check the three supported web layouts:
#
#   port 80    web root = npo-workdash/public
#   port 8081  web root = a fixed public_html beside npo-workdash
#   port 8082  web root = a subdomain folder, public_html/sub
#
# Usage (as root, in a php:*-apache container with pdo_mysql and zip):
#   deploy/release/smoke-test.sh <zip> [home]
# Database: DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD.

set -euo pipefail

zip="$(realpath "${1:?Usage: smoke-test.sh <zip> [home]}")"
home="${2:-/srv/home}"
: "${DB_HOST:?}" "${DB_PORT:=3306}" "${DB_DATABASE:?}" "${DB_USERNAME:?}" "${DB_PASSWORD:?}"

fail() {
    echo "::error::$*"
    tail -n 50 "$home"/npo-workdash/shared/storage/logs/*.log /var/log/apache2/error.log 2> /dev/null || true
    exit 1
}

code() {
    curl -s --path-as-is -o /dev/null -w '%{http_code}' "$1"
}

if command -v composer > /dev/null || command -v node > /dev/null || command -v npm > /dev/null; then
    fail "This host must not have Composer or Node"
fi

echo "==> Unzipping into $home with public_html and a subdomain folder"
rm -rf "$home"
mkdir -p "$home/public_html/sub"
(cd "$home" && unzip -q "$zip")
cp -a "$home/npo-workdash/public/." "$home/public_html/"
cp -a "$home/npo-workdash/public/." "$home/public_html/sub/"
chown -R www-data:www-data "$home"

echo "==> Serving the three layouts with Apache"
a2enmod rewrite > /dev/null
cat > /etc/apache2/sites-available/000-default.conf << EOF
Listen 8081
Listen 8082
<Directory $home>
    AllowOverride All
    Require all granted
</Directory>
<VirtualHost *:80>
    DocumentRoot $home/npo-workdash/public
</VirtualHost>
<VirtualHost *:8081>
    DocumentRoot $home/public_html
</VirtualHost>
<VirtualHost *:8082>
    DocumentRoot $home/public_html/sub
</VirtualHost>
EOF
apache2ctl start
sleep 2

echo "==> Server check and installer before install"
curl -sf http://127.0.0.1/check.php | grep -q 'NPO.WorkDash server check' || fail "check.php did not load"
final="$(curl -sL -o /dev/null -w '%{http_code} %{url_effective}' http://127.0.0.1/)"
[[ "$final" == "200 http://127.0.0.1/install" ]] || fail "An uninstalled site should open the install wizard, got: $final"

echo "==> Installing with app:install through the stable artisan proxy"
password="Smoke-$(head -c 18 /dev/urandom | base64 | tr -d '/+=')"
su -s /bin/bash www-data -c "php $home/npo-workdash/artisan app:install --no-interaction \
    --db-host='$DB_HOST' --db-port='$DB_PORT' --db-database='$DB_DATABASE' \
    --db-username='$DB_USERNAME' --db-password='$DB_PASSWORD' \
    --url=http://127.0.0.1 --admin-name='Smoke Admin' --admin-email=admin@example.test \
    --admin-password='$password' --workspace='Smoke Charity'" || fail "app:install failed"

for port in 80 8081 8082; do
    base="http://127.0.0.1:$port"
    echo "==> Checking $base"

    [[ "$(code "$base/login")" == 200 ]] || fail "$base/login did not return 200"
    curl -s "$base/login" | grep -q 'build/assets/' || fail "$base/login has no built assets"
    [[ "$(code "$base/favicon.ico")" == 200 ]] || fail "$base/favicon.ico is not served"
    [[ "$(code "$base/install")" == 404 ]] || fail "$base/install is still open after install"
    [[ "$(code "$base/check.php")" == 404 ]] || fail "$base/check.php is still open after install"

    for path in .env .htaccess workdash-path.php RequirementsChecker.php ../shared/.env ../npo-workdash/shared/.env \
        ../../npo-workdash/shared/.env ../current.php composer.json storage/logs/laravel.log vendor/autoload.php; do
        status="$(code "$base/$path")"
        [[ "$status" =~ ^(400|403|404)$ ]] || fail "$base/$path returned $status"
    done
done

echo "Installed and checked all three layouts."
