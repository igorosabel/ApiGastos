<?php declare(strict_types=1);

namespace OsumiFramework\App\Model;

use OsumiFramework\OFW\DB\OModel;
use OsumiFramework\OFW\DB\OModelGroup;
use OsumiFramework\OFW\DB\OModelField;

class User extends OModel{
  function __construct(){
		/**
		 * Configures current model object based on data-base table structure
		 */

    	 $model = new OModelGroup(
			new OModelField(
				name: 'id',
				type: OMODEL_PK,
				comment: 'Id único para cada usuario'
			),
			new OModelField(
				name: 'name',
				type: OMODEL_TEXT,
				nullable: false,
				default: 'null',
				size: 50,
				comment: 'Nombre del usuario'
			),
			new OModelField(
				name: 'email',
				type: OMODEL_TEXT,
				nullable: false,
				default: 'null',
				size: 100,
				comment: 'Email del usuario'
			),
			new OModelField(
				name: 'pass',
				type: OMODEL_TEXT,
				nullable: false,
				default: 'null',
				size: 100,
				comment: 'Contraseña cifrada del usuario'
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