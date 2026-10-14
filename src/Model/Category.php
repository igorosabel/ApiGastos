<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OUpdatedAt;
use Osumi\OsumiFramework\ORM\ODeletedAt;

class Category extends OModel {
	#[OPK(
		comment: '',
		nullable: false,
		type: OField::NUMBER,
		incr: true
	)]
	public ?int $id;

	#[OField(
		comment: '',
		nullable: false,
		type: OField::NUMBER,
		ref: 'space.id'
	)]
	public ?int $id_space;

	#[OField(
		comment: '',
		nullable: true,
		type: OField::NUMBER,
		ref: 'category.id'
	)]
	public ?int $id_parent;

	#[OField(
		comment: '',
		nullable: false,
		type: OField::TEXT,
		max: 100
	)]
	public ?string $name;

	#[OField(
		comment: '',
		nullable: false,
		type: OField::TEXT,
		max: 7
	)]
	public ?string $default_type;

	#[OField(
		comment: '',
		nullable: false,
		type: OField::NUMBER,
		default: 0
	)]
	public ?int $sort_order;

	#[OCreatedAt(
		comment: ''
	)]
	public ?string $created_at;

	#[OUpdatedAt(
		comment: ''
	)]
	public ?string $updated_at;
}
