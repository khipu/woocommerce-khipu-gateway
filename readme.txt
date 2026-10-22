=== Khipu for WooCommerce ===
Contributors: Khipu
Tags: payment gateway, Khipu, woocommerce, chile, peru
Requires at least: 6.0
Tested up to: 6.8
Stable tag: 4.3.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Permite el uso de khipu en WooCommerce, khipu es un medio de pago que permite pagar usando Cuentas Bancarias en Chile y Perú.

== Description ==

Permite el uso de khipu en WooCommerce, khipu es un medio de pago que permite pagar usando Cuentas Bancarias en Chile y Perú.

**Aviso legal:** Este plugin no está afiliado ni respaldado por Automattic Inc. o por la marca WooCommerce. “WooCommerce” es una marca registrada de Automattic Inc.


== Installation ==

1. Descargar el archivo woocommerce-khipu-gateway.zip, el cual contiene el plugin WooCommerce Khipu para WordPress.
2. Abrir en el navegador el administrador de tu tienda WooCommerce e ingresar con tu usuario y clave de administrador.
3. En el menú ir a “Plugins”, luego clic en “Añadir nuevo” y seleccionar “Añadir plugins”.
4. Examina y sube el archivo que descargaste en el paso 1, luego clic en “Volver a la página de plugins” y activarlo.
5. Buscamos el plugin instalado llamado “Khipu” en Woocommerce->Ajustes->Pagos y presionamos el botón “Configuración”.
6. Ahora accede a tu cuenta de Khipu aquí, luego haz clic en “Opciones de la cuenta”, dirígete a la última sección “Para integrar Khipu a tu sitio web” y busca ahí tu Id de cobrador y la llave.
7. Copia el Id de cobrador, vuelve a la página de administración de WooCommerce y pégalo en el campo “Id cobrador”.
8. Repite el paso 7, pero esta vez copiando y pegando la “Llave secreta” y luego presiona “Guardar cambios”.
9. En la misma sección, crea un nuevo API KEY y repite el paso anterior, copiando y pegando en el formulario de configuración del plugin.
10. Una vez guardadas las credenciales, en “Medios de pago a mostrar” marca los medios que quieres ofrecer en tu checkout. Cada medio aparece como una opción. Con el interruptor “Modo de pago” eliges entre Embebido (izquierda, por defecto) y Redirección (derecha). El pago embebido solo se habilitará para la transferencia simplificada, y solo si tu cuenta tiene ese medio; los demás medios (transferencia normal, LigoPay) siempre van por redirección. Sin transferencia simplificada activa, el interruptor queda deshabilitado en Redirección y todo va por redirección.

== Frequently asked questions ==

No hay preguntas aún.

== Screenshots ==

1. https://s3.amazonaws.com/static.khipu.com/woocommerce/activate-plugin.png
2. https://s3.amazonaws.com/static.khipu.com/woocommerce/khipu-settings.png
3. https://s3.amazonaws.com/static.khipu.com/id-cobrador.png

== Changelog ==
4.3.0 Un solo panel de configuración de Khipu con título y descripción editables, pago embebido (modo Embebido o Redirección) y selección de los medios de pago de la cuenta. Soporte para Perú (PEN): transferencia normal y LigoPay, con pago por redirección (el modo embebido es solo para transferencia simplificada). Importante: al actualizar hay que volver a ingresar el Id de cobrador, la llave y la API Key. Ya no se pueden usar dos cuentas de cobro distintas en la misma tienda, el modo por defecto pasa a Embebido (solo para transferencia simplificada, si está activa) y aparecen todos los medios de pago que tenga la cuenta.
4.2.0 Devolución de dinero (reembolso total o parcial) de pedidos pagados con Khipu, desde la lista de pedidos
4.1.5 Cumplimiento normas Wordpress
4.1 Compatibilidad con Woocomerce Blocks
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


== Upgrade Notice ==

= 4.3.0 =
Un solo panel de Khipu: tras actualizar, vuelve a ingresar Id de cobrador, llave y API Key en WooCommerce → Ajustes → Pagos → Khipu. Una sola cuenta de cobro por tienda. Embebido es el modo por defecto (solo transferencia simplificada) y aparecen todos los medios de tu cuenta.

== External services ==

Este plugin se conecta al servicio externo “Khipu Payment API” para crear y consultar pagos.

- Servicios: API de Khipu (https://payment-api.khipu.com) y, en modo Embebido, el script del navegador https://js.khipu.com/v1/kws.js. Proveedor: https://khipu.com

- ¿Qué datos se envían y cuándo?
  - En la creación de un pago, el plugin envía a la API de Khipu (https://payment-api.khipu.com): monto, moneda, asunto, correo del comprador, orden/ID interno y las URLs de retorno y de notificación necesarias para procesar el pago.
  - En modo Embebido, el navegador del comprador carga https://js.khipu.com/v1/kws.js para mostrar el pago dentro de la tienda.
  - En la consulta de un pago, se solicita el estado del pago usando el identificador de pago devuelto por la API.

- ¿Por qué?
  - Es necesario para iniciar y confirmar transacciones de pago dentro del flujo de WooCommerce.

- ¿Dónde puedo revisar las políticas?
  - Términos del servicio de Khipu: https://khipu.com
  - Política de privacidad de Khipu: https://khipu.com

 - ¿A quién contacto en caso de dudas o problemas?
  - En caso de cualquier problema con el plugin y su configuración, puedes escribirnos a soporte@khipu.com