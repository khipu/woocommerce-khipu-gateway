# Khipu for WooCommerce

## Instalación

1. Descargar el archivo woocommerce-khipu-gateway.zip, el cual contiene el plugin WooCommerce Khipu para WordPress.
2. Abrir en el navegador el administrador de tu tienda WooCommerce e ingresar con tu usuario y clave de administrador.
3. En el menú ir a “Plugins”, luego clic en “Añadir nuevo” y seleccionar “Añadir plugins”.
4. Examina y sube el archivo que descargaste en el paso 1, luego clic en “Volver a la página de plugins” y activarlo.
5. Buscamos el plugin instalado llamado “Khipu” en Woocommerce->Ajustes->Pagos y presionamos el botón “Configuración”.
6. Ahora accede a tu cuenta de Khipu aquí, luego haz clic en “Opciones de la cuenta”, dirígete a la última sección “Para integrar Khipu a tu sitio web” y busca ahí tu Id de cobrador y la llave.
7. Copia el Id de cobrador, vuelve a la página de administración de WooCommerce y pégalo en el campo “Id cobrador”.
8. Repite el paso 7, pero esta vez copiando y pegando la “Llave secreta” y luego presiona “Guardar cambios”.
9. En la misma sección, crea un nuevo API KEY y repite el paso anterior, copiando y pegando en el formulario de configuración del plugin.
10. Una vez guardadas las credenciales, en “Medios de pago a mostrar” marca los medios que quieres ofrecer en tu checkout. Cada medio aparece como una opción. Con el interruptor “Modo de pago” eliges entre Embebido (izquierda, por defecto) y Redirección (derecha). El pago embebido solo se habilitará para la transferencia simplificada, y solo si tu cuenta tiene ese medio; los demás medios siempre van por redirección.

## Medios de pago

Cada medio que tenga tu cuenta Khipu aparece como una fila propia en el checkout, con el nombre `{Título} - {Medio}`. Las filas se ordenan solas: transferencia simplificada primero y transferencia normal al final.

| País | Moneda | Medios |
|---|---|---|
| Chile | CLP | Transferencia simplificada, Transferencia normal |
| Perú | PEN | Transferencia normal, LigoPay |
| Argentina | ARS | Transferencia simplificada, Pago por QR (NAVE), Transferencia normal |

El plugin no aplica reglas por moneda ni por país: lo que decide es tu cuenta. En PEN y ARS configura 2 decimales en WooCommerce; el panel avisa si están en 0.

## Disclaimer

Este plugin no está afiliado ni respaldado por Automattic Inc. o por la marca WooCommerce. 'woocommerce-khipu-gateway' es una marca registrada de Automattic Inc.

## Soporte

En caso de cualquier problema con el plugin y su configuración, puedes escribirnos a soporte@khipu.com