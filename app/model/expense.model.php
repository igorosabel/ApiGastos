<?php declare(strict_types=1);

namespace OsumiFramework\App\Model;

use OsumiFramework\OFW\DB\OModel;

class Expense extends OModel{
	function __construct(){
		$model = [
			'id' => [
				'type'    => OModel::PK,
				'incr' => false,
				'comment' => 'Id único para cada gasto'
			],
			'id_user' => [
				'type'    => OModel::NUM,
				'nullable' => false,
				'default' => null,
				'ref' => 'user.id',
				'comment' => 'Id del usuario que crea el gasto'
			],
			'id_type' => [
				'type'    => OModel::NUM,
				'nullable' => true,
				'default' => null,
				'comment' => 'Id del tipo de gasto (nulo para varios)'
			],
			'concept' => [
				'type'    => OModel::TEXT,
				'nullable' => false,
				'default' => null,
				'size' => 100,
				'comment' => 'Concepto del gasto'
			],
			'amount' => [
				'type'    => OModel::FLOAT,
				'nullable' => false,
				'default' => '0',
				'comment' => 'Importe del gasto'
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