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
WP_CONFIG="/var/www/html/wordpress/wp-config.php"
VHOST_AVAILABLE="/etc/apache2/sites-available/wordpress.conf"
VHOST_ENABLED="/etc/apache2/sites-enabled/wordpress.conf"

#####################

set -e

if [[ $EUID -ne 0 ]]; then
  echo "[!] Debes ejecutar este script como root (sudo)."
  exit 1
fi

if [[ "$1" == "--local" ]]; then
    echo "-> Cambiando a entorno LOCAL"

    # 1. Eliminar bloques añadidos en wp-config.php
    sed -i '/### BEGIN CUSTOM ###/,/### END CUSTOM ###/d' "$WP_CONFIG"

    # 2. Actualizar URLs en base de datos
    mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" \
      -e "UPDATE wp_options SET option_value='http://localhost/wordpress'
          WHERE option_name IN ('siteurl','home');"

    # 3. Ajustar VirtualHost a localhost en ambos archivos
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

    # 1. Añadir bloque al final de wp-config.php si no existe
    if ! grep -q "### BEGIN CUSTOM ###" "$WP_CONFIG"; then
cat <<EOF >> "$WP_CONFIG"

### BEGIN CUSTOM ###
define('WP_HOME',    '$URL');
define('WP_SITEURL', '$URL');
define('FORCE_SSL_ADMIN', true);
if (isset(\$_SERVER['HTTP_X_FORWARDED_PROTO']) && \$_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
    \$_SERVER['HTTPS']='on';
}
define('COOKIE_SECURE', true);
### END CUSTOM ###
EOF
    else
        # si ya existe, actualiza solo la URL
        sed -i "s#define('WP_HOME'.*#define('WP_HOME',    '$URL');#" "$WP_CONFIG"
        sed -i "s#define('WP_SITEURL'.*#define('WP_SITEURL', '$URL');#" "$WP_CONFIG"
    fi

    # 2. Actualizar URLs en base de datos
    mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" \
      -e "UPDATE wp_options SET option_value='$URL'
          WHERE option_name IN ('siteurl','home');"

    # 3. Ajustar VirtualHost para el nuevo dominio en ambos archivos
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
