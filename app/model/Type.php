<?php declare(strict_types=1);

namespace OsumiFramework\App\Model;

use OsumiFramework\OFW\DB\OModel;

class Type extends OModel{
	/**
	 * Configures current model object based on data-base table structure
	 */
  function __construct(){
    $table_name  = 'type';
    $model = [
      'id' => [
        'type'    => OModel::PK,
        'comment' => 'Id único para cada tipo de gasto'
      ],
      'concept' => [
        'type'    => OModel::TEXT,
        'nullable' => false,
        'default' => null,
        'size' => 100,
        'comment' => 'Nombre para el tipo de gasto'
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

    parent::load($table_name, $model);
  }
}