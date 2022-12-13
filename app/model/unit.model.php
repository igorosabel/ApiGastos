<?php declare(strict_types=1);

namespace OsumiFramework\App\Model;

use OsumiFramework\OFW\DB\OModel;
use OsumiFramework\OFW\DB\OModelGroup;
use OsumiFramework\OFW\DB\OModelField;

class Unit extends OModel{
	function __construct(){
		$model = new OModelGroup(
			new OModelField(
				name: 'id',
				type: OMODEL_PK,
				comment: 'Id único para cada unidad'
			),
			new OModelField(
				name: 'id_user',
				type: OMODEL_NUM,
				nullable: false,
				default: null,
				ref: 'user.id',
				comment: 'Id del usuario origen'
			),
			new OModelField(
				name: 'related_to',
				type: OMODEL_NUM,
				nullable: false,
				default: null,
				ref: 'user.id',
				comment: 'Id del usuario con el que se relaciona'
			),
			new OModelField(
				name: 'status',
				type: OMODEL_NUM,
				nullable: false,
				default: 0,
				comment: 'Estado de la solicitud 0 pendiente 1 aceptada'
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