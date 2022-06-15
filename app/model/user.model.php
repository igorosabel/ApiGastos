<?php declare(strict_types=1);

namespace OsumiFramework\App\Model;

use OsumiFramework\OFW\DB\OModel;

class User extends OModel{
  function __construct(){
		/**
		 * Configures current model object based on data-base table structure
		 */

    $model = [
      'id' => [
        'type'    => OModel::PK,
        'comment' => 'Id único para cada usuario'
      ],
      'name' => [
        'type'    => OModel::TEXT,
        'nullable' => false,
        'default' => null,
        'size' => 50,
        'comment' => 'Nombre del usuario'
      ],
      'email' => [
        'type'    => OModel::TEXT,
        'nullable' => false,
        'default' => null,
        'size' => 100,
        'comment' => 'Email del usuario'
      ],
      'pass' => [
        'type'    => OModel::TEXT,
        'nullable' => false,
        'default' => null,
        'size' => 100,
        'comment' => 'Contraseña cifrada del usuario'
      ],
      'created_at' => [
        'type'    => OModel::CREATED,
        'comment' => 'Fecha de creación del registro'
      ],
      'updated_at' => [
        'type'    => OModel::UPDATED,
        'nullable' => true,
        'default' => null,
        'comment' => 'Fecha de última modificación del registro'
      ]
    ];

    parent::load($model);
  }
}