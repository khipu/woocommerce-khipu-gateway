=== WooCommerce khipu ===
Contributors: khipu
Donate link:
Tags: payment gateway, khipu, woocommerce, chile
Requires at least: 6.0
Tested up to: 6.6.1
Stable tag: 4.0.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Permite el uso de khipu en WooCommerce, khipu es un medio de pago que permite pagar usando Cuentas Bancarias.

== Description ==

Permite el uso de khipu en WooCommerce, khipu es un medio de pago que permite pagar usando Cuentas Bancarias.

== Installation ==

1. Descargar el archivo woocommerce-khipu-gateway.zip, el cual contiene el plugin WooCommerce Khipu para WordPress.
2. Abrir en el navegador el administrador de tu tienda WooCommerce e ingresar con tu usuario y clave de administrador.
3. En el menú ir a “Plugins”, luego clic en “Añadir nuevo” y seleccionar “Añadir plugins”.
4. Examina y sube el archivo que descargaste en el paso 1, luego clic en “Volver a la página de plugins” y activarlo.
5. Buscamos el plugin instalado llamado ”khipu transferencia simplificada” en Woocommerce->Ajustes->Pagos y actívalo presionando el botón “Configuración”.
6. Ahora accede a tu cuenta de Khipu aquí, luego haz clic en “Opciones de la cuenta”, dirígete a la última sección “Para integrar Khipu a tu sitio web” y busca ahí tu Id de cobrador y la llave.
7. Copia el Id de cobrador, vuelve a la página de administración de WooCommerce y pégalo en el campo “Id cobrador”.
8. Repite el paso 7, pero esta vez copiando y pegando la “Llave secreta” y luego presiona “Guardar cambios”.
9. En la misma sección, crea un nuevo API KEY y repite el paso anterior, copiando y pegando en el formulario de configuración del plugin.
10. Repetir  el proceso desde el paso 5 pero en la sección “khipu transferencia normal”.

== Frequently asked questions ==

No hay preguntas aún.

== Screenshots ==

1. https://s3.amazonaws.com/static.khipu.com/woocommerce/activate-plugin.png
2. https://s3.amazonaws.com/static.khipu.com/woocommerce/khipu-settings.png
3. https://s3.amazonaws.com/static.khipu.com/id-cobrador.png

== Changelog ==
4.0.2 Log de Versión
4.0 Actualización a API 3.0 Khipu
3.6 Compatible con PHP8
3.5 Incluye detalle de compra en el comprobante de pago
3.4 Habilitado para operar en Chile, Argentina y España
3.2 Compatible con Wordpress 6.0
3.1 Permite elegir estado luego del apgo recibido.
3.0 Uso del timeout de woocommerce para recuperación de stock al crear un pago, detección automática de medios de pago disponibles.
2.9 Cambio de nombre en configuración de woocommerce
2.8.1 Corrección de URL de cancelación para permitir al usuario volver al carro
2.8 Agrega URL de cancelación para permitir al usuario volver al carro
2.7 Mejoras de UX
2.6 Se agrega soporte para Webpay
2.5 Se agrega soporte para PayMe
1.6 Mejoras para compatibilidad con php 5.2
1.5 Uso de API de notificación 1.3
1.4 Chequeo de existencia del plugin WooCommerce
1.3 Uso de API khipu 1.3
1.0 Versión inicial


== Upgrade notice ==

Versión inicial
