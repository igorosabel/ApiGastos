<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service\Category;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use PDO;
use Throwable;
use Osumi\OsumiFramework\App\Model\Category;
use Osumi\OsumiFramework\App\Model\SpaceMember;
use Osumi\OsumiFramework\Core\OService;
use Osumi\OsumiFramework\ORM\ODB;

class CategoryService extends OService {
    /**
     * Obtiene las categorías accesibles de un espacio.
     *
     * @param int $space_id Identificador del espacio.
     * @param int $user_id Usuario autenticado.
     * @return list<Category> Categorías.
     */
    public function getBySpace(int $space_id, int $user_id): array {
        $this->requireActiveMember($space_id, $user_id);

        return Category::where(
            ['id_space' => $space_id],
            ['order_by' => 'sort_order#ASC']
        );
    }

    /**
     * Crea una categoría raíz o hija.
     *
     * @param int $space_id Espacio contable.
     * @param int $user_id Usuario autenticado.
     * @param string $name Nombre.
     * @param string $default_type Tipo de movimiento.
     * @param int|null $parent_id Categoría padre.
     * @param int $sort_order Orden.
     * @return Category Categoría creada.
     */
    public function create(
        int $space_id,
        int $user_id,
        string $name,
        string $default_type,
        ?int $parent_id = null,
        int $sort_order = 0
    ): Category {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 100) {
            throw new InvalidArgumentException(
                'El nombre debe tener entre 1 y 100 caracteres.'
            );
        }

        if (!in_array($default_type, ['expense', 'income'], true)) {
            throw new InvalidArgumentException(
                'El tipo de categoría no es válido.'
            );
        }

        if ($sort_order < 0 || ($parent_id !== null && $parent_id <= 0)) {
            throw new InvalidArgumentException(
                'El orden o la categoría padre no son válidos.'
            );
        }

        $this->requireActiveMember($space_id, $user_id);

        if ($parent_id !== null) {
            $parent = Category::findOne([
                'id' => $parent_id,
                'id_space' => $space_id
            ]);

            if (
                $parent === null ||
                $parent->default_type !== $default_type
            ) {
                throw new InvalidArgumentException(
                    'La categoría padre no pertenece al espacio o tiene otro tipo.'
                );
            }
        }

        $category = new Category();
        $category->id_space = $space_id;
        $category->id_parent = $parent_id;
        $category->name = $name;
        $category->default_type = $default_type;
        $category->sort_order = $sort_order;

        if (!$category->save() || $category->id === null) {
            throw new RuntimeException(
                'No se ha podido crear la categoría.'
            );
        }

        return $category;
    }

    /**
     * Comprueba la pertenencia activa al espacio.
     *
     * @param int $space_id Espacio solicitado.
     * @param int $user_id Usuario autenticado.
     * @return void
     */
    private function requireActiveMember(
        int $space_id,
        int $user_id
    ): void {
        if ($space_id <= 0 || $user_id <= 0) {
            throw new InvalidArgumentException(
                'Los identificadores no son válidos.'
            );
        }

        $membership = SpaceMember::findOne([
            'id_space' => $space_id,
            'id_user' => $user_id,
            'left_at' => null
        ]);

        if ($membership === null) {
            throw new DomainException(
                'No tienes acceso a este espacio.'
            );
        }
    }

    /**
     * Actualiza el nombre, el orden y la posición de una categoría.
     *
     * No permite cambiar de espacio, de tipo ni generar ciclos.
     *
     * @param int $space_id Identificador del espacio.
     * @param int $user_id Usuario autenticado.
     * @param int $category_id Categoría que se modifica.
     * @param string $name Nuevo nombre.
     * @param int|null $parent_id Nuevo padre o null para raíz.
     * @param int $sort_order Orden de presentación.
     * @return Category Categoría actualizada.
     */
    public function update(
        int $space_id,
        int $user_id,
        int $category_id,
        string $name,
        ?int $parent_id,
        int $sort_order
    ): Category {
        $name = trim($name);

        if (
            $space_id <= 0 ||
            $user_id <= 0 ||
            $category_id <= 0 ||
            ($parent_id !== null && $parent_id <= 0) ||
            $sort_order < 0 ||
            $name === '' ||
            mb_strlen($name) > 100
        ) {
            throw new InvalidArgumentException(
                'Los datos de la categoría no son válidos.'
            );
        }

        $db = ODB::getInstance();

        if (!$db->beginTransaction()) {
            throw new RuntimeException(
                'No se ha podido iniciar la transacción.'
            );
        }

        try {
            // Serializamos los cambios del árbol por espacio.
            $stmt = $db->prepare(
                'SELECT id FROM `space`
                 WHERE id = :id
                 FOR UPDATE'
            );
            $stmt->execute(['id' => $space_id]);

            if ($stmt->fetch(PDO::FETCH_ASSOC) === false) {
                throw new DomainException(
                    'No tienes acceso a este espacio.'
                );
            }

            // Autorización actual, sin consultar caché ORM.
            $stmt = $db->prepare(
                'SELECT id FROM `space_member`
                 WHERE id_space = :space_id
                   AND id_user = :user_id
                   AND left_at IS NULL
                 LIMIT 1'
            );
            $stmt->execute([
                'space_id' => $space_id,
                'user_id' => $user_id
            ]);

            if ($stmt->fetch(PDO::FETCH_ASSOC) === false) {
                throw new DomainException(
                    'No tienes acceso a este espacio.'
                );
            }

            $stmt = $db->prepare(
                'SELECT id, id_parent, default_type
                 FROM `category`
                 WHERE id = :id AND id_space = :space_id'
            );
            $stmt->execute([
                'id' => $category_id,
                'space_id' => $space_id
            ]);

            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($current)) {
                throw new InvalidArgumentException(
                    'La categoría no pertenece al espacio.'
                );
            }

            if ($parent_id === $category_id) {
                throw new InvalidArgumentException(
                    'Una categoría no puede ser su propio padre.'
                );
            }

            // Recorremos los ascendientes del nuevo padre.
            // Si llegamos a la categoría editada, habría un ciclo.
            $ancestor_id = $parent_id;
            $visited = [];

            while ($ancestor_id !== null) {
                if (
                    $ancestor_id === $category_id ||
                    isset($visited[$ancestor_id])
                ) {
                    throw new InvalidArgumentException(
                        'La reorganización produciría un ciclo.'
                    );
                }

                $visited[$ancestor_id] = true;

                $stmt = $db->prepare(
                    'SELECT id_parent, default_type
                     FROM `category`
                     WHERE id = :id AND id_space = :space_id'
                );
                $stmt->execute([
                    'id' => $ancestor_id,
                    'space_id' => $space_id
                ]);

                $ancestor = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!is_array($ancestor)) {
                    throw new InvalidArgumentException(
                        'El padre no pertenece al espacio.'
                    );
                }

                if (
                    $ancestor['default_type'] !==
                    $current['default_type']
                ) {
                    throw new InvalidArgumentException(
                        'El padre debe tener el mismo tipo.'
                    );
                }

                $ancestor_id = $ancestor['id_parent'] === null
                    ? null
                    : (int) $ancestor['id_parent'];
            }

            $stmt = $db->prepare(
                'UPDATE `category`
                 SET name = :name,
                     id_parent = :parent_id,
                     sort_order = :sort_order
                 WHERE id = :id AND id_space = :space_id'
            );

            $stmt->bindValue('name', $name, PDO::PARAM_STR);
            $stmt->bindValue(
                'parent_id',
                $parent_id,
                $parent_id === null
                    ? PDO::PARAM_NULL
                    : PDO::PARAM_INT
            );
            $stmt->bindValue(
                'sort_order',
                $sort_order,
                PDO::PARAM_INT
            );
            $stmt->bindValue('id', $category_id, PDO::PARAM_INT);
            $stmt->bindValue('space_id', $space_id, PDO::PARAM_INT);

            if (!$stmt->execute()) {
                throw new RuntimeException(
                    'No se ha podido actualizar la categoría.'
                );
            }

            if (!$db->commit()) {
                throw new RuntimeException(
                    'No se ha podido confirmar la actualización.'
                );
            }

            // Construimos la respuesta a partir de datos actuales.
            $stmt = $db->prepare(
                'SELECT id, id_space, id_parent, name,
                        default_type, sort_order
                 FROM `category`
                 WHERE id = :id'
            );
            $stmt->execute(['id' => $category_id]);
            $stored = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($stored)) {
                throw new RuntimeException(
                    'No se ha podido recuperar la categoría.'
                );
            }

            $category = new Category();
            $category->id = (int) $stored['id'];
            $category->id_space = (int) $stored['id_space'];
            $category->id_parent = $stored['id_parent'] === null
                ? null
                : (int) $stored['id_parent'];
            $category->name = $stored['name'];
            $category->default_type = $stored['default_type'];
            $category->sort_order = (int) $stored['sort_order'];

            return $category;
        }
        catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }
}
