#!/usr/bin/env bash
#
# Cambia WordPress entre entorno local y URL personalizada (ngrok u otra).
# Uso:
#   sudo ./wp-switch.sh --local
#   sudo ./wp-switch.sh --custom https://ejemplo.com
#

### CONFIGURACIÓN ###
DB_NAME="wordpress"          # nombre de la base de datos
DB_USER="root"               # usuario de MySQL
DB_PASS=""                   # contraseña de MySQL (vacío si no tiene)
WP_CONFIG="/var/www/html/wp-config.php"
VHOST_AVAILABLE="/etc/apache2/sites-available/wordpress.conf"
VHOST_ENABLED="/etc/apache2/sites-enabled/wordpress.conf"

#####################

set -e

if [[ $EUID -ne 0 ]]; then
  echo "[!] Debes ejecutar este script como root (sudo)."
  exit 1
fi

insert_block () {
    local URL="$1"

    # 1. Eliminar bloque previo si existe
    sed -i '/### BEGIN CUSTOM ###/,/### END CUSTOM ###/d' "$WP_CONFIG"

    # 2. Insertar bloque justo ANTES de la línea que contiene “Happy publishing”
    sed -i "/Happy publishing/i \
### BEGIN CUSTOM ###\n\
define('WP_HOME',    '$URL');\n\
define('WP_SITEURL', '$URL');\n\
define('FORCE_SSL_ADMIN', true);\n\
if (isset(\$_SERVER['HTTP_X_FORWARDED_PROTO']) && \$_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {\n\
    \$_SERVER['HTTPS'] = 'on';\n\
}\n\
define('COOKIE_SECURE', true);\n\
### END CUSTOM ###\n" "$WP_CONFIG"
}

if [[ "$1" == "--local" ]]; then
    echo "-> Cambiando a entorno LOCAL"

    # 1. Eliminar bloque CUSTOM y cualquier línea que fuerce SSL
    sed -i '/### BEGIN CUSTOM ###/,/### END CUSTOM ###/d' "$WP_CONFIG"
    sed -i "/FORCE_SSL_ADMIN/d" "$WP_CONFIG"
    sed -i "/COOKIE_SECURE/d"   "$WP_CONFIG"

    # 2. Actualizar URLs en base de datos
    mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" \
      -e "UPDATE wp_options SET option_value='http://localhost'
          WHERE option_name IN ('siteurl','home');"

    # 3. Ajustar VirtualHost a localhost
    DOMAIN="localhost"
    for FILE in "$VHOST_AVAILABLE" "$VHOST_ENABLED"; do
        sed -i "s/ServerName .*/ServerName $DOMAIN/" "$FILE"
        sed -i "/ServerAlias/d" "$FILE"
    done

    systemctl reload apache2
    echo "[OK] WordPress restaurado a LOCAL"

elif [[ "$1" == "--custom" && -n "$2" ]]; then
    URL="$2"
    echo "-> Configurando WordPress para $URL"

    insert_block "$URL"

    # 2. Actualizar URLs en base de datos
    mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" \
      -e "UPDATE wp_options SET option_value='$URL'
          WHERE option_name IN ('siteurl','home');"

    # 3. Ajustar VirtualHost para el nuevo dominio
    DOMAIN=$(echo "$URL" | sed 's#https\?://##')
    for FILE in "$VHOST_AVAILABLE" "$VHOST_ENABLED"; do
        sed -i "s/ServerName .*/ServerName $DOMAIN/" "$FILE"
        sed -i "/ServerAlias/d" "$FILE"
    done

    systemctl reload apache2
    echo "[OK] WordPress configurado para $URL"

else
    echo "Uso:"
    echo "  sudo $0 --local"
    echo "  sudo $0 --custom https://tu-dominio"
    exit 1
fi
