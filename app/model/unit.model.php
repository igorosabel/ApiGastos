<?php declare(strict_types=1);

namespace OsumiFramework\App\Model;

use OsumiFramework\OFW\DB\OModel;

class Unit extends OModel{
	function __construct(){
		$model = [
			'id' => [
				'type'    => OModel::PK,
				'comment' => 'Id único para cada unidad'
			],
			'id_user' => [
				'type'    => OModel::NUM,
				'nullable' => false,
				'default' => null,
				'ref' => 'user.id',
				'comment' => 'Id del usuario origen'
			],
			'related_to' => [
				'type'    => OModel::NUM,
				'nullable' => false,
				'default' => null,
				'ref' => 'user.id',
				'comment' => 'Id del usuario con el que se relaciona'
			],
			'status' => [
				'type'    => OModel::NUM,
				'nullable' => false,
				'default' => '0',
				'comment' => 'Estado de la solicitud 0 pendiente 1 aceptada'
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