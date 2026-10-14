<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OUpdatedAt;
use Osumi\OsumiFramework\ORM\ODeletedAt;

class SpaceMember extends OModel {
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
		nullable: false,
		type: OField::NUMBER,
		ref: 'user.id'
	)]
	public ?int $id_user;

	#[OField(
		comment: '',
		nullable: false,
		type: OField::DATETIME,
		default: OField::CURRENT_TIMESTAMP
	)]
	public ?string $joined_at;

	#[OCreatedAt(
		comment: ''
	)]
	public ?string $created_at;

	#[OUpdatedAt(
		comment: ''
	)]
	public ?string $updated_at;
}
