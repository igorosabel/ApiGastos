<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OUpdatedAt;
use Osumi\OsumiFramework\ORM\ODeletedAt;

class Space extends OModel {
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
		type: OField::TEXT,
		max: 100
	)]
	public ?string $name;

	#[OField(
		comment: '',
		nullable: false,
		type: OField::TEXT,
		max: 10
	)]
	public ?string $type;

	#[OField(
		comment: '',
		nullable: false,
		type: OField::NUMBER,
		ref: 'user.id'
	)]
	public ?int $owner_id;

	#[OField(
		comment: '',
		nullable: true,
		type: OField::NUMBER,
		ref: 'user.id'
	)]
	public ?int $personal_owner_id;

	#[OCreatedAt(
		comment: ''
	)]
	public ?string $created_at;

	#[OUpdatedAt(
		comment: ''
	)]
	public ?string $updated_at;
}
