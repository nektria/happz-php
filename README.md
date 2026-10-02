# XGC PHP Tools

A comprehensive PHP utility library.

## Twig en aplicaciones Symfony

El core declara `symfony/twig-bundle` y `twig/twig`, y proporciona la integración
común en `config/twig.php`. Las aplicaciones consumidoras no necesitan declarar
esas dependencias de nuevo.

Activa el bundle estándar de Twig en el `config/bundles.php` de la aplicación:

```php
Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
```

Importa la configuración del core desde `config/services.yaml`:

```yaml
imports:
  - { resource: '../vendor/nektria/php-tools/config/twig.php' }
```

La configuración usa el directorio `templates/` de la aplicación, activa variables
estrictas y publica `Twig\Environment` como alias de `twig`. Está disponible tanto
por inyección de constructor como desde el controlador base del core. Las
plantillas y los datos de cada producto pertenecen a la aplicación consumidora.
Una aplicación puede añadir rutas o ajustar opciones mediante su configuración
`twig` habitual, cargada después del import del core.
