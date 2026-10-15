# Patrones de Osumi Framework en Gastos

Este documento complementa `AGENTS.md`. Los ejemplos deben contrastarse con la versión de OFW utilizada.

## DTO

Utilizar `ODTO` para entradas estructuradas y datos confiables de middleware.

```php
class CreateSpaceDTO extends ODTO {
    #[ODTOField(required: true)]
    public ?string $name = null;

    #[ODTOField(
        required: true,
        middleware: 'Login',
        middlewareProperty: 'id'
    )]
    public ?int $idUser = null;
}
```

Un componente de ruta puede recibir el DTO directamente:

```php
public function run(CreateSpaceDTO $dto): void {
    if (!$dto->isValid()) {
        // Preparar respuesta de validación.
        return;
    }

    // Delegar la operación al servicio.
}
```

Las validaciones de obligatoriedad corresponden al DTO. Las reglas específicas del negocio permanecen en el servicio.

## Servicios e inyección

Los servicios extienden `OService`.

En los componentes se obtienen mediante:

```php
public function __construct() {
    parent::__construct();
    $this->space_service = inject(SpaceService::class);
}
```

No sustituir `inject()` por `new SpaceService()` en código de producción.

## Acceso a datos

Priorizar los modelos generados y el ORM:

```php
$memberships = SpaceMember::where([
    'id_user' => $user_id
]);

$space = Space::findOne([
    'id' => $space_id
]);

$space = new Space();
$space->name = 'Familia';
$space->type = 'shared';
$space->owner_id = $user_id;
$space->personal_owner_id = null;
$space->save();
```

Utilizar `ODB::getInstance()` para obtener la conexión necesaria para transacciones y comprobar que las operaciones del ORM participan en ella.

El SQL directo debe estar justificado por necesidades concretas y no ser la opción predeterminada.

## Respuestas JSON con componentes

Preferir componentes reutilizables de representación de modelos.

Estructura de referencia:

- `Component/Model/Space/SpaceComponent.php`
- `Component/Model/Space/SpaceTemplate.json`
- `Component/Model/SpaceList/SpaceListComponent.php`
- `Component/Model/SpaceList/SpaceListTemplate.php`

El componente de un elemento expone el modelo y los datos necesarios para su representación.

El componente de listado recorre los elementos y renderiza un componente por cada uno.

Ejemplo de plantilla de respuesta:

```json
{
    "success": {{ success | bool }},
    "spaces": [{{ list }}]
}
```

Utilizar los pipes adecuados para números, booleanos y texto JSON.

No recurrir a serialización manual de arrays cuando la composición de componentes cubra esa necesidad.

## Autenticación

- El login genera JWT mediante `OToken`.
- Las rutas protegidas utilizan `LoginMiddleware`.
- La identidad autenticada se publica en el contexto del middleware.
- Los DTO pueden obtener identificadores desde ese contexto.
- Los servicios comprueban las autorizaciones correspondientes.
- No confiar en un identificador enviado en el cuerpo de la petición.

## Referencias reales

Repositorio OFW:
https://github.com/osumionline/framework

Repositorio TPV Backup API:
https://github.com/osumionline/TPV-Backup-API

Consultar especialmente sus DTO y los componentes de `src/Component/Model/`.

Los ejemplos de otros proyectos sirven como referencia, no sustituyen la comprobación de compatibilidad con OFW 9.12.1.
