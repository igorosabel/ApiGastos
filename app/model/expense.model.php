<?php declare(strict_types=1);

namespace OsumiFramework\App\Model;

use OsumiFramework\OFW\DB\OModel;
use OsumiFramework\OFW\DB\OModelGroup;
use OsumiFramework\OFW\DB\OModelField;

class Expense extends OModel{
	function __construct(){
		$model = new OModelGroup(
			new OModelField(
				name: 'id',
				type: OMODEL_PK,
				incr: false,
				comment: 'Id único para cada gasto'
			),
			new OModelField(
				name: 'id_user',
				type: OMODEL_NUM,
				nullable: false,
				default: null,
				ref: 'user.id',
				comment: 'Id del usuario que crea el gasto'
			),
			new OModelField(
				name: 'id_type',
				type: OMODEL_NUM,
				nullable: true,
				default: null,
				comment: 'Id del tipo de gasto (nulo para varios)'
			),
			new OModelField(
				name: 'concept',
				type: OMODEL_TEXT,
				nullable: false,
				default: 'null',
				size: 100,
				comment: 'Concepto del gasto'
			),
			new OModelField(
				name: 'amount',
				type: OMODEL_FLOAT,
				nullable: false,
				default: 0,
				comment: 'Importe del gasto'
			),
			new OModelField(
				name: 'created_at',
				type: OMODEL_CREATED,
				comment: 'Fecha de creación del registro'
			),
			new OModelField(
				name: 'updated_at',
				type: OMODEL_UPDATED,
				nullable: true,
				default: null,
				comment: 'Fecha de última modificación del registro'
			)
		);

		parent::load($model);
	}
}