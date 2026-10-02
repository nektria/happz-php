# Revisión técnica de `happz-php`

Fecha: 2026-08-28

## Alcance

Revisión estática y pruebas locales no destructivas sobre las 97 clases de `src/`, configuración de Composer, herramientas de validación y plantillas de proyecto incluidas en `assets/`.

No se modificó código. `composer.lock` ya contenía cambios del usuario al iniciar la redacción de este informe y se ha preservado sin cambios adicionales.

## Resumen

Se encontraron **2 problemas críticos, 13 de prioridad alta, 12 de prioridad media y 4 de prioridad baja**. Los riesgos principales son la desactivación global de TLS, una interpolación SQL vulnerable, la mezcla de conexiones Redis y varios falsos positivos en validaciones y respuestas HTTP.

Tras la actualización de dependencias, `squizlabs/php_codesniffer` está bloqueado en 4.0.4 y `composer audit --locked` no encuentra vulnerabilidades conocidas.

## Problemas críticos

### RV_0001 — Todas las peticiones HTTP desactivan la validación TLS

**Evidencia:** `src/Client/RequestClient.php:199-205`, `292-296` y `379-385` fijan `verify_peer=false` y `verify_host=false` para peticiones normales y subidas de archivos.

**Impacto:** HTTPS no autentica el servidor. Un atacante capaz de interceptar la red puede leer o alterar credenciales, tokens, cuerpos y respuestas sin que el cliente detecte el ataque.

**Recomendación:** conservar la verificación TLS de Symfony por defecto. Si se necesita desactivarla en desarrollo, usar una opción explícita restringida a ese entorno y nunca como comportamiento general de la librería.

### RV_0002 — Los parámetros de tipo array permiten inyección SQL

**Evidencia:** `src/DB/ReadModel.php:120-129` convierte cada array en una cadena de valores entre comillas y la inserta mediante `str_replace`, sin parámetros DBAL ni escape SQL. `buildSQL()` también concatena directamente nombres de ordenación en `src/DB/ReadModel.php:186-193`.

**Impacto:** un valor que contenga comillas o sintaxis SQL puede cambiar la consulta, eludir filtros, leer datos no autorizados o ejecutar operaciones admitidas por el driver. El riesgo se materializa cuando los arrays o campos de ordenación proceden directa o indirectamente de una petición.

**Recomendación:** usar parámetros array de DBAL con su tipo correspondiente, listas de placeholders generadas de forma segura y una lista cerrada de columnas válidas para `ORDER BY`.

## Prioridad alta

### RV_0003 — Redis ignora el servidor y la base de datos de cada instancia

**Evidencia:** `src/Cache/RedisCache.php:19` mantiene una sola conexión estática para todas las subclases; `init()` la reutiliza antes de consultar el DSN (`96-98`) y siempre ejecuta `select(0)` (`115`). `InternalVariableCache` y `SharedVariableCache` intentan seleccionar `/2` y `/1`, pero esas rutas nunca se aplican.

**Impacto:** la primera caché utilizada determina la conexión de todas las demás, incluso si reciben otro host o credenciales. Los datos previstos para bases separadas terminan en DB 0; `fullRedisEmpty()` puede vaciar datos de otros componentes.

**Recomendación:** mantener conexiones por DSN/base de datos, validar esquema/host/puerto/usuario/contraseña y seleccionar el índice extraído de la ruta del DSN.

### RV_0004 — `PUT` no envía cuerpo y los parámetros no se codifican

**Evidencia:** `src/Client/RequestClient.php:387-414` solo genera cuerpo para `POST` y `PATCH`; un `PUT` cae en la rama de query string. Las tres rutas construyen parámetros con `"{$key}={$value}"`, sin `http_build_query`, y no respetan una query ya presente en la URL.

**Impacto:** las APIs que esperan JSON en `PUT` reciben una petición sin cuerpo. Valores con `&`, `=`, `#`, espacios o caracteres Unicode alteran o rompen la URL y pueden inyectar parámetros adicionales.

**Recomendación:** enviar cuerpo también en `PUT`, usar la opción `query` del cliente Symfony y definir de forma explícita el formato de cuerpo de cada operación.

### RV_0005 — `WebResponse` ignora el estado HTTP solicitado

**Evidencia:** `src/Symfony/Controller/WebResponse.php:14-20` guarda `$status`, pero llama a `parent::__construct($content)` sin pasarlo. Una prueba con `new WebResponse('x', 302)` devolvió `getStatusCode() === 200`.

**Impacto:** `Controller::redirect()` crea respuestas declaradas como 301/302 que realmente salen como 200; cualquier otro error o estado especial construido con esta clase también queda falseado.

**Recomendación:** pasar `$status` y, cuando proceda, cabeceras al constructor padre; añadir pruebas para 200, 301, 302 y estados de error.

### RV_0006 — Toda descarga elimina el archivo original

**Evidencia:** `src/Symfony/Listener/RequestListener.php:157-173` convierte cualquier `FileDocument` en `BinaryFileResponse` y llama siempre a `deleteFileAfterSend()`.

**Impacto:** descargar un archivo permanente, reutilizable o compartido lo borra del servidor después de la respuesta. La clase `FileDocument` no informa de este comportamiento ni permite desactivarlo.

**Recomendación:** añadir una opción explícita `deleteAfterSend`, con valor predeterminado seguro, y limitar el borrado a archivos temporales verificados.

### RV_0007 — El registro HTTP puede almacenar credenciales y datos personales

**Evidencia:** `src/Symfony/Listener/RequestListener.php:268-296` solo oculta `x-authorization`, `x-api-id` y cinco campos de primer nivel. No elimina las cabeceras estándar `Authorization`, `Cookie`, `Set-Cookie` ni secretos anidados; además registra petición y respuesta completas en `324-354`.

**Impacto:** tokens bearer, sesiones, claves API y datos personales pueden terminar en stderr, Cloud Logging o Redis y permanecer fuera de los controles del sistema principal.

**Recomendación:** aplicar una política central de allowlist/redacción recursiva a cabeceras, query, cuerpos y respuestas; no registrar cuerpos completos por defecto.

### RV_0008 — Los validadores de color y expresión regular aceptan valores inválidos

**Evidencia:** `Validate::color()` y `Validate::regexp()` comprueban `preg_match(...) === false` (`src/Utils/Validate.php:23-27,322-326`). El retorno `0`, que significa “no coincide”, se acepta. Las pruebas confirmaron que `not-a-color` y `abc` contra `/^z$/` no generan error.

**Impacto:** cualquier texto pasa ambas validaciones siempre que el patrón sea sintácticamente válido.

**Recomendación:** exigir `preg_match(...) === 1`; anclar completamente el patrón de color y probar coincidencia, no coincidencia y regex inválida.

### RV_0009 — La detección de dispositivo devuelve siempre `tablet`

**Evidencia:** `src/Symfony/Controller/Controller.php:90-103` usa `preg_match(...) !== false`. Tanto una coincidencia (`1`) como una no coincidencia (`0`) satisfacen la primera condición. Una prueba con User-Agent de Firefox en Linux devolvió `tablet`.

**Impacto:** nunca se devuelve `mobile` ni `desktop`, lo que puede seleccionar vistas o comportamiento incorrectos.

**Recomendación:** comparar con `=== 1` y cubrir al menos tablet, móvil, escritorio y User-Agent vacío.

### RV_0010 — Los validadores pueden fallar y aun así devolver código 0

**Evidencia:** `bin/code/validate` imprime `FAIL`, pero no acumula el resultado ni ejecuta `exit` distinto de cero (`35-69,179-185`). `ValidateMessagesHandler::play()` captura cualquier error y solo lo imprime (`src/Symfony/Console/Debug/ValidateMessagesHandler.php:20-27`). Ambos se usan como controles de calidad. Además, el script Composer `check` ejecuta `--fix` (`composer.json:64-66`).

**Impacto:** CI puede marcar como válida una revisión con errores de PHPStan, formato o parejas Message/Handler ausentes. El supuesto check también modifica fuentes.

**Recomendación:** propagar el primer código no cero o acumular fallos, relanzar la excepción de mensajes y separar `check` de `fix`.

### RV_0011 — Las plantillas generan entornos PHP 8.4 incompatibles con la librería

**Evidencia:** `composer.json:19` exige PHP `>=8.5`, pero los Dockerfiles y Cloud Build de `assets/setup/server/` usan `eu.gcr.io/nektria/php:8.4`, `php84` y configuración de PHP 8.4.

**Impacto:** un proyecto generado con los assets actuales puede fallar durante `composer install` por no satisfacer la plataforma requerida.

**Recomendación:** unificar las plantillas en PHP 8.5 y añadir una comprobación automatizada que compare la plataforma de Composer con todas las imágenes generadas.

### RV_0012 — La plantilla de producción incorpora `.env` y posibles secretos a la imagen

**Evidencia:** `assets/setup/server/pipeline/Dockerfile:5` ejecuta `COPY . /app/` y después lee `/app/.env`; los assets no generan `.dockerignore`. `assets/setup/.git-ignore` tampoco ignora `.env`, solo una ruta concreta de credenciales de Google.

**Impacto:** secretos de producción, credenciales y archivos locales pueden quedar dentro de una capa de la imagen o terminar versionados en los proyectos generados.

**Recomendación:** generar `.dockerignore`, no copiar `.env` ni credenciales, usar secretos de BuildKit/Cloud Build y añadir `.env` a `.gitignore` conservando solo ejemplos sin secretos.

### RV_0013 — `Console::copy()` permite inyección de comandos

**Evidencia:** `src/Symfony/Console/Console.php:60-65` interpola `$text` dentro de `exec("echo '{$text}' | pbcopy ...")`. Una comilla simple en el texto puede cerrar el argumento y añadir sintaxis de shell.

**Impacto:** si una consola pasa contenido externo o no confiable, se pueden ejecutar comandos con los permisos del proceso PHP.

**Recomendación:** no invocar un shell; abrir `pbcopy` mediante `proc_open` con argumentos separados y escribir el texto por stdin, o aplicar una API de portapapeles segura y portable.

### RV_0014 — Los contadores de Messenger usan claves distintas y operaciones no atómicas

**Evidencia:** `increaseCounter()` sustituye `\` por `_` en la clave (`src/Symfony/Listener/MessageListener.php:292-309`), mientras `decreaseCounter()` usa el nombre de clase sin normalizar (`248-268`). Todos los contadores se implementan como lectura seguida de escritura.

**Impacto:** el contador incrementado no se decrementa; aparecen claves distintas y los valores activos crecen incorrectamente. Con varios workers, operaciones simultáneas pierden incrementos o decrementos.

**Recomendación:** centralizar la construcción de clave y usar `INCR`/`DECR` o un script Lua atómico con TTL.

### RV_0015 — Los fallos de Discord y Mercure se ocultan o se propagan de forma incoherente

**Evidencia:** `DiscordAlert` llama al cliente con `errorIfFails=false` (`src/Alert/DiscordAlert.php:180-188`), por lo que un HTTP 4xx/5xx no lanza `RequestException` y las rutas de fallback de `151-154` y `235-240` no se ejecutan. Un error de transporte se convierte en `BaseException`, que `publishMessage()` no captura. `WSService::publish()` descarta cualquier `Throwable` (`src/Symfony/Service/WSService.php:28-36`).

**Impacto:** alertas rechazadas pueden considerarse publicadas; otros fallos inesperadamente interrumpen el flujo; Mercure nunca informa del error. La clave de deduplicación de Discord puede suprimir reintentos durante cinco minutos aunque el envío haya fallado.

**Recomendación:** comprobar `isSuccessful()`, normalizar errores de transporte/HTTP, aplicar reintentos controlados y devolver o registrar un resultado observable.

## Prioridad media

### RV_0016 — `FileService` permite salir del directorio del proyecto

**Evidencia:** `src/Symfony/Service/FileService.php:26-33` acepta rutas absolutas y concatena rutas relativas sin normalizar `..` ni comprobar el destino real.

**Impacto:** si el nombre de archivo procede de una entrada externa, el servicio permite leer o sobrescribir cualquier archivo accesible por el proceso.

**Recomendación:** resolver `realpath`, rechazar rutas absolutas y traversal, y comprobar que el destino permanece dentro de una raíz autorizada. Para escrituras nuevas debe validarse también el directorio padre.

### RV_0017 — La caché deserializa objetos PHP sin restricción

**Evidencia:** las cachés Redis usan `unserialize(..., ['allowed_classes' => true])` en `InternalRedisCache`, `SharedRedisCache` e `InternalReferenceRedisCache`.

**Impacto:** si Redis puede ser escrito por otro servicio o atacante, una carga manipulada puede instanciar objetos y activar cadenas de object injection disponibles en la aplicación.

**Recomendación:** usar JSON o un serializador con esquema; como mínimo limitar `allowed_classes` a una lista cerrada y proteger Redis con red y autenticación.

### RV_0018 — Varias operaciones de caché prometen garantías que no cumplen

**Evidencia:** `executeIfNotExists()` hace `hasKey()` y `saveKey()` por separado, por lo que no actúa como lock. `InternalVariableCache::refreshKey()` solo escribe si la clave es nueva (`91-99`) y no refresca una existente. `InternalReferenceRedisCache::setListItems()` elimina una lista vacía, pero continúa y vuelve a crear la clave (`308-323`). La mayoría de errores Redis se capturan y descartan.

**Impacto:** callbacks duplicados bajo concurrencia, TTL no renovados, claves vacías inesperadas y fallos de caché indistinguibles de misses normales.

**Recomendación:** usar `SET NX EX`, operaciones atómicas y resultados/errores observables; corregir los contratos de refresh y lista vacía.

### RV_0019 — La plantilla de despliegue continúa después de fallos

**Evidencia:** `assets/setup/bin/deploy:3-8` ejecuta migrador, commit, pull, push y despliegue sin modo estricto ni comprobaciones intermedias.

**Impacto:** puede desplegarse aunque el push haya fallado, continuar tras un conflicto de Git o publicar una versión distinta de la que existe en el remoto.

**Recomendación:** usar modo estricto, validar un worktree limpio y detenerse antes del despliegue si cualquier operación Git falla.

### RV_0020 — `RandomUtil::float()` devuelve valores fuera del intervalo

**Evidencia:** `src/Utils/RandomUtil.php:38-41` multiplica por `random_int()/PHP_INT_MAX`, cuyo factor está entre -1 y 1. En 2.000 llamadas a `float(10, 20)` se obtuvieron valores desde aproximadamente `0.004` hasta `19.97`.

**Impacto:** los valores pueden ser menores que `$min`; validaciones, simulaciones o importes aleatorios construidos sobre esta función son incorrectos.

**Recomendación:** generar un factor uniforme entre 0 y 1 y calcular `$min + factor * ($max - $min)` con control de intervalos y overflow.

### RV_0021 — `ArrayUtil::mapify()` invierte el significado de `keepFirst`

**Evidencia:** `src/Utils/ArrayUtil.php:95-105` omite duplicados cuando `keepFirst` es `false`. La prueba devolvió el primer elemento con el valor predeterminado y el último con `keepFirst=true`.

**Impacto:** el parámetro público hace lo contrario de lo que indica su nombre y puede seleccionar registros incorrectos al resolver duplicados.

**Recomendación:** corregir la condición o renombrar el parámetro preservando compatibilidad; añadir pruebas con claves repetidas.

### RV_0022 — Las APIs de fecha tienen contratos inconsistentes

**Evidencia:** `Clock::add(-1, 'days')` genera un modificador inválido y lanzó `BaseException`, mientras `LocalClock::add()` trata negativos. El tipo documentado admite `months` y `years`, pero `timestamp()` solo admite hasta semanas (`Clock.php:294-305`, `LocalClock.php:448-459`). `LocalClock::sinceString()` calcula fecha menos ahora y produce `-2 minutes ago` para el pasado y `2 minutes ago` para el futuro.

**Impacto:** operaciones públicamente permitidas fallan o generan texto temporal con sentido invertido.

**Recomendación:** unificar ambos relojes, validar unidades al entrar y separar correctamente expresiones de pasado (`ago`) y futuro (`in`).

### RV_0023 — Las excepciones de autenticación usan códigos HTTP intercambiados

**Evidencia:** `AccessDeniedExcepcion` responde 401 y `InsufficientCredentialsExcepcion` responde 403 (`src/Exception/...:9-12`). Semánticamente, credenciales ausentes/insuficientes corresponden normalmente a 401 y acceso autenticado denegado a 403.

**Impacto:** clientes pueden solicitar login cuando no corresponde o no renovar credenciales cuando sí corresponde; métricas y políticas HTTP quedan clasificadas incorrectamente.

**Recomendación:** invertir los estados, corregir el nombre `Excepcion` mediante una transición compatible y documentar el contrato.

### RV_0024 — `ContainerBox::getParameter()` destruye el tipo original

**Evidencia:** `src/Utils/ContainerBox.php:43-67` declara retorno de arrays, booleanos, números y enums, pero convierte arrays a JSON, enums a `null` y el resto a string. Las pruebas devolvieron `false` como `""`, `7` como `"7"` y un array como cadena JSON.

**Impacto:** consumidores que confían en la firma reciben valores de tipo y semántica distintos; `false` se vuelve indistinguible de una cadena vacía.

**Recomendación:** devolver el valor del contenedor sin coerción o declarar y aplicar explícitamente una conversión a string.

### RV_0025 — La paginación SQL es frágil y puede informar totales falsos

**Evidencia:** `ReadModel::buildSQL()` reconstruye la consulta mediante `explode('FROM', $source)` y solo usa los índices 0 y 1 (`207-209`), lo que rompe subconsultas u otros `FROM`. El offset se calcula con el límite original, aunque el límite SQL se recorta (`203-215`). Si una página no contiene filas, `getPaginatedResults()` informa `totalItems=0` (`93-98`) aunque existan filas en páginas anteriores.

**Impacto:** consultas válidas pueden truncarse, las páginas saltan posiciones incorrectas y los metadatos de total cambian al navegar fuera de rango.

**Recomendación:** construir el count/ventana con un query builder, usar el límite normalizado para offset y obtener el total mediante consulta separada o una fila de metadatos estable.

### RV_0026 — Hay dependencias de uso directo no declaradas directamente

**Evidencia:** `src/` importa `symfony/process`, `symfony/string`, componentes de EventDispatcher y paquetes Doctrine. Varios solo llegan transitivamente. En particular, `symfony/process` aparece por `friendsofphp/php-cs-fixer`, que es dependencia de desarrollo, aunque `StaticAnalysisConsole` lo usa en código distribuido.

**Impacto:** `composer install --no-dev` o un cambio de dependencias transitivas puede dejar clases públicas sin sus clases requeridas.

**Recomendación:** declarar cada dependencia runtime usada directamente en `require` y comprobar instalación mínima con `composer install --no-dev` en un proyecto consumidor limpio.

### RV_0027 — No existen pruebas automatizadas de la librería

**Evidencia:** PHPUnit está en `require-dev`, pero no hay directorio ni archivos `*Test.php`; solo existen configuraciones dentro de las plantillas de `assets/`.

**Impacto:** los fallos reproducidos de validación, HTTP, Redis, fechas y utilidades no están protegidos frente a regresiones. PHPStan y formato no verifican comportamiento.

**Recomendación:** añadir tests unitarios y de integración para cada API pública crítica, con Redis/DB/HTTP simulados o servicios efímeros.

## Prioridad baja

### RV_0028 — Métodos supuestamente seguros producen warnings o divisiones por cero

**Evidencia:** `DocumentCollection::opt()` retorna directamente `$items[$key]` pese a declarar nullable (`src/Dto/DocumentCollection.php:180-186`); una clave ausente produjo `Undefined array key`. `PaginatedDocumentCollection::json()` divide por `$pageSize` sin validar que sea mayor que cero (`23-30`).

**Impacto:** entradas fuera de rango generan warnings convertibles en excepciones o `DivisionByZeroError`.

**Recomendación:** usar `?? null` y validar invariantes en constructores.

### RV_0029 — El fallback de logs de consola pierde mensajes silenciosamente

**Evidencia:** `OutputService` escribe en `{$projectDir}/tmp/...` sin crear `tmp` (`src/Symfony/Service/OutputService.php:24-30,91-103`) y no comprueba el retorno de `file_put_contents`.

**Impacto:** cuando no se asigna `OutputInterface`, el log puede no crearse y no queda señal de error.

**Recomendación:** crear y validar el directorio, comprobar bytes escritos y propagar un error utilizable.

### RV_0030 — Una regla PHPStan busca clases de un namespace antiguo

**Evidencia:** `AllowComparingOnlyComparableTypesRule.php:56-57` crea dos veces `ObjectType('Nektria\\Dto\\Clock')`, mientras la clase real es `Xgc\\Dto\\Clock` y falta `LocalClock`.

**Impacto:** las comparaciones de igualdad entre relojes no reciben la regla específica esperada; la configuración da una cobertura aparente que no existe.

**Recomendación:** usar `Clock::class` y `LocalClock::class` y probar la regla con fixtures positivos y negativos.

### RV_0031 — La documentación pública no describe el contrato de la librería

**Evidencia:** `README.md` solo contiene un título y la frase “A comprehensive PHP utility library”. No documenta instalación, PHP 8.5, extensiones, configuración Redis, seguridad HTTP, clases públicas ni ejecución de checks.

**Impacto:** los consumidores dependen de leer la implementación y pueden usar APIs destructivas o con requisitos implícitos sin conocerlos.

**Recomendación:** documentar requisitos, instalación, módulos, ejemplos, contratos de error, efectos secundarios, comandos de validación y política de compatibilidad.

## Comprobaciones realizadas

- `composer validate --strict --no-check-publish`: correcto.
- `composer audit --locked`: sin avisos de seguridad tras la actualización del usuario.
- `composer install --dry-run --no-interaction`: no requiere cambios y la plataforma actual es compatible.
- PHPStan nivel 10: correcto.
- PHP-CS-Fixer en modo `--dry-run`: correcto.
- `php -l`: 139 archivos PHP y scripts compatibles, sin errores de sintaxis.
- Pruebas puntuales sin modificar archivos confirmaron RV_0005, RV_0008, RV_0009, RV_0020, RV_0021, RV_0022, RV_0024 y RV_0028.

No se probaron servicios Redis/PostgreSQL reales, peticiones externas, Cloud Build ni despliegues. Por tanto, los fallos de integración descritos a partir del flujo de código requieren además pruebas end-to-end antes de aplicar una corrección.
