<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service\Category;

use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\App\Model\Category;
use Osumi\OsumiFramework\App\Model\SpaceMember;
use Osumi\OsumiFramework\Core\OService;
use RuntimeException;

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
}
