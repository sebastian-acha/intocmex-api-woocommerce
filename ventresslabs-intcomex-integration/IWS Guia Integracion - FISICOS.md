Confidencial — Uso interno y de clientes autorizados 
Página 1 de 6 
INTCOMEX WEB SERVICES 
Guía de integración para clientes B2B 
Modalidad: Venta de productos físicos  
 
Versión 1.0 
0. Introducción 
Intcomex Web Services (IWS) es la plataforma de integración B2B de Intcomex que permite a 
sus clientes automatizar la consulta de catálogo, precios e inventario, así como la gestión de 
órdenes de compra, directamente desde sus propios sistemas. 
 
Este documento está dirigido al equipo técnico de desarrollo del cliente y tiene como objetivo 
servir de guía paso a paso durante todo el proceso de integración, desde los requisitos previos 
hasta el despliegue en el ambiente de producción. 
 
La integración cubierta en este documento corresponde a la modalidad de venta de productos 
físicos a través de IWS. 
 
A lo largo del documento se indica claramente qué acciones corresponden al cliente, cuáles al 
equipo comercial de Intcomex y cuáles al equipo de TI de Intcomex, para que en todo momento 
exista claridad sobre quién debe actuar y qué esperar como respuesta. 
 
1. Prerrequisitos para iniciar la integración 
Responsable: Cliente 
 
Para aprovechar la funcionalidad proporcionada por IWS, el cliente debe cumplir con los 
siguientes requisitos antes de realizar cualquier solicitud a la plataforma. 
 
Una vez cumplidos, el cliente podrá realizar solicitudes y recibir respuestas utilizando servicios 
REST a través de mensajes HTTP o HTTPS, desde su propio entorno: ya sea un punto de 
venta, sitio web, aplicación móvil u otro tipo de sistema. 
1.1 Conocimientos técnicos del equipo de desarrollo 
Se recomienda que el equipo de desarrollo del cliente tenga un buen entendimiento de los 
siguientes conceptos antes de iniciar: 
 
• 
Servicios web RESTful 
• 
Solicitudes y respuestas HTTP y HTTPS

---

Confidencial — Uso interno y de clientes autorizados 
Página 2 de 6 
• 
Formatos de datos JSON y/o XML 
 
2. Ambientes disponibles 
Responsable: TI Intcomex 
 
IWS dispone de dos ambientes: TEST y Producción. Toda integración debe iniciarse 
obligatoriamente en TEST antes de pasar a Producción. 
 
Característica 
Ambiente TEST 
Ambiente Producción 
Propósito 
Desarrollo y validación de la 
integración 
Operación real con datos y órdenes 
reales 
Credenciales 
API Key y Access Key de TEST 
(entregadas por TI Intcomex al inicio) 
API Key y Access Key de Producción 
(entregadas tras aprobación) 
IP fija requerida 
No requerida 
Sí. El cliente debe compartir su IP 
pública fija antes de recibir 
credenciales de Producción 
Aprobación para 
acceder 
Automática al recibir credenciales 
Requiere validación de pruebas 
realizadas en TEST de parte del 
cliente y entrega de IP fija 
 
ℹ Canal de comunicación durante la integración 
• 
La comunicación entre el cliente y el equipo de TI de Intcomex se realiza vía correo electrónico. 
• 
Si el cliente no recibe respuesta en ese plazo, debe escalar con su ejecutivo comercial de Intcomex. 
• 
Si el equipo tiene dudas puntuales, se recomienda primero revisar la grabación del proceso completo en: 
https://iws.intcomex.com/materiales/material-descargable.html — cubre el proceso completo y resuelve la mayoría de 
las preguntas frecuentes. Si después de verla persisten las dudas, puede agendar una sesión con el especialista de 
TI (ver sección 8.2). 
 
3. Autenticación 
Responsable: Cliente 
 
Cada solicitud a IWS requiere autenticación mediante dos credenciales entregadas por el 
equipo de TI de Intcomex: 
 
• 
API Key: se envía en el encabezado (header) de cada request HTTP para identificar al 
cliente en la plataforma.

---

Confidencial — Uso interno y de clientes autorizados 
Página 3 de 6 
• 
Access Key: no se envía directamente en el encabezado. Se utiliza para construir una 
firma (signature) que luego se convierte mediante el algoritmo SHA-256 antes de 
incluirse en la solicitud. 
El proceso de firma es obligatorio para todas las peticiones. Es importante tener en cuenta que 
la generación de la firma debe realizarse usando la zona horaria UTC 0. 
 
La especificación completa del mecanismo de autenticación y la generación de firma está 
documentada oficialmente en: 
https://iws.intcomex.com/reference/api.html#section/Documentation/Security. Se recomienda 
leer esta sección antes de implementar cualquier llamado a la API. 
 
ℹ Importante sobre las credenciales 
Las credenciales de TEST y las de Producción son distintas. No utilice credenciales de un ambiente en el otro. 
Las credenciales son confidenciales y de uso exclusivo del cliente. No deben compartirse. 
Si las credenciales son comprometidas, contactar de inmediato al equipo de TI de Intcomex vía correo o al ejecutivo comercial. 
 
4. Servicios disponibles (APIs) 
Responsable: Cliente 
 
Para la modalidad de venta de productos físicos, el cliente debe integrar los siguientes 
servicios. La tabla resume cada API, su propósito y la periodicidad de consumo recomendada: 
 
API 
Descripción 
Periodicidad 
Documentación 
GetCatalog 
Obtiene el catálogo 
completo de productos 
disponibles para el cliente. 
Máximo 1 vez 
por hora 
Ver documentación 
GetInventory 
Consulta el inventario 
disponible en tiempo real. 
Máximo 1 vez 
por hora 
Ver documentación 
GetPriceList 
Obtiene la lista de precios 
vigentes para el cliente. 
Máximo 1 vez 
por hora 
Ver documentación 
DownloadExtendedCatalog 
Obtiene enlaces a las 
imágenes disponibles 
Máximo 1 vez 
por mes 
Ver documentación 
GetProduct/s 
Obtiene información en 
tiempo real del o los 
productos. 
A demanda 
Ver documentación 
PlaceOrder 
Crea una nueva orden de 
compra en IWS. 
A demanda 
Ver documentación 
 
⚠ Importante: use únicamente las API s descritas en este documento

---

Confidencial — Uso interno y de clientes autorizados 
Página 4 de 6 
• 
La documentación oficial de IWS contiene APIs destinadas a otros modelos de integración que no aplican para el 
modelo descrito en este documento. 
• 
El cliente debe consumir exclusivamente las APIs listadas en este documento. Utilizar APIs fuera de esta lista puede 
causar comportamientos inesperados, errores en el procesamiento de órdenes o inconsistencias en los datos. 
• 
La información que se obtiene con el api DownloadExtendedCatalog corresponde a lo que se tiene disponible, si esto 
no cumple con las necesidades del cliente, puede contactar a un proveedor de contenido externo. 
 
5. Flujo de integración recomendado 
Responsable: Cliente 
 
A continuación, se describe la secuencia recomendada de consumo de las APIs para una 
integración correcta y eficiente. Respetar este orden evita errores de dependencia entre 
servicios. 
5.1 Sincronización de datos de catálogo (proceso periódico) 
Estas tres APIs deben ejecutarse de forma periódica, con una frecuencia máxima de una vez 
por hora, para mantener actualizados los datos en el sistema del cliente: 
 
1. GetCatalog — sincronizar el catálogo de productos disponibles. 
2. GetPriceList — actualizar los precios vigentes. 
3. GetInventory — verificar disponibilidad de inventario antes de presentar productos al 
usuario final. 
5.2 Ciclo de vida de una orden (proceso a demanda) 
Cuando un cliente final realiza una compra, el sistema del cliente debe ejecutar las siguientes 
APIs en este orden estricto, se recomienda realizarlo en el proceso de checkout: 
 
4. GetProduct/s – para obtener el stock de los productos en tiempo real 
5. PlaceOrder — crear la orden en IWS con los productos seleccionados. 
 
 
6. Checklist de go-live a producción 
 
Antes de solicitar el paso al ambiente de producción, deben estar completados todos los 
criterios de la siguiente lista. TI de Intcomex validará en los logs del ambiente de TEST que las 
APIs hayan sido consumidas con respuestas exitosas.

---

Confidencial — Uso interno y de clientes autorizados 
Página 5 de 6 
Criterio 
Responsable 
Completado 
GetCatalog ejecutado con respuesta exitosa en ambiente TEST 
Cliente + TI 
Intcomex 
☐ 
GetPriceList ejecutado con respuesta exitosa en ambiente TEST 
Cliente + TI 
Intcomex 
☐ 
GetInventory ejecutado con respuesta exitosa en ambiente TEST 
Cliente + TI 
Intcomex 
☐ 
PlaceOrder ejecutado con respuesta exitosa en ambiente TEST 
Cliente + TI 
Intcomex 
☐ 
TI Intcomex confirma validación de logs en ambiente TEST 
TI Intcomex 
☐ 
Cliente comparte su dirección IP pública fija al equipo de TI de 
Intcomex 
Cliente 
☐ 
TI Intcomex configura el perfil del cliente en IWS y TRAX en 
Producción y la IP y entrega credenciales de Producción 
TI Intcomex 
☐ 
Cliente confirma acceso al portal de soporte de IWS (ver sección 
7) 
Cliente 
☐ 
 
ℹ Proceso para pasar a producción 
1. El cliente solicita el paso a producción por correo, en el hilo donde se encuentran el ejecutivo comercial y el equipo de TI de 
Intcomex. 
2. TI Intcomex valida los logs y confirma que las APIs tuvieron respuestas exitosas. 
3. El cliente comparte su IP pública fija al equipo de TI de Intcomex por correo. 
4. TI Intcomex configura el perfil del cliente en IWS y TRAX en Producción, configura la IP y entrega las credenciales del 
ambiente de Producción. 
5. A partir de este punto, cualquier inconveniente debe gestionarse exclusivamente a través de la mesa de soporte de IWS. Por 
eso es importante que el cliente se asegure de tener acceso al portal de soporte antes del go-live (ver sección 8.3). 
 
7. Soporte y canales de contacto 
 
7.1 Durante la integración en ambiente TEST 
Mientras el cliente se encuentra en el proceso de integración en el ambiente de TEST, la 
comunicación con el equipo de TI de Intcomex se realiza vía correo electrónico 
(iwsintegraciones@intcomex.com), con copia al ejecutivo comercial. 
7.2 Sesión con el especialista de TI 
Si el equipo de desarrollo tiene dudas que no pueden resolverse por correo, se recomienda 
revisar la grabación de una sesión regular con clientes disponible en el siguiente enlace, ya que 
puede resolver la mayoría de las dudas frecuentes: 
 https://iws.intcomex.com/materiales/material-descargable.html

---

Confidencial — Uso interno y de clientes autorizados 
Página 6 de 6 
 
Si las dudas no son resueltas puede agendar una sesión directa con el especialista de TI de 
Intcomex a través de su ejecutivo comercial. Se recomienda utilizar este recurso para consultas 
puntuales, no como canal general de soporte. 
7.3 Después del go-live en producción 
Una vez desplegado en producción, cualquier inconveniente debe gestionarse exclusivamente 
a través de la mesa de soporte de IWS. No se atenderán casos de producción por correo 
directo. 
 
El cliente puede acceder al portal de soporte en el siguiente enlace: 
https://myservices.intcomex.com/es/XGT 
 
El acceso al portal de soporte se realiza con las mismas credenciales que el cliente utiliza para 
Webstore de Intcomex. 
 
ℹ ¿No tiene acceso al portal de soporte? 
Si el cliente no puede ingresar al portal de soporte con sus credenciales de Webstore, debe solicitarlo antes del go-live a 
producción. 
Para gestionar el acceso: el ejecutivo comercial de Intcomex debe crear un ticket interno en JIRA especificando el Customer ID 
y el correo de la persona que ingresará al portal de soporte de IWS. 
Se recomienda verificar y resolver este acceso durante la etapa de TEST, antes del despliegue a producción. 
 
 
Este documento es de uso exclusivo para clientes B2B autorizados por Intcomex.