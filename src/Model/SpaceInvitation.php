<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OUpdatedAt;
use Osumi\OsumiFramework\ORM\ODeletedAt;

class SpaceInvitation extends OModel {
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
		type: OField::TEXT,
		max: 254
	)]
	public ?string $email;

	#[OField(
		comment: '',
		nullable: false,
		type: OField::TEXT,
		max: 64
	)]
	public ?string $token_hash;

	#[OField(
		comment: '',
		nullable: false,
		type: OField::NUMBER,
		ref: 'user.id'
	)]
	public ?int $invited_by;

	#[OField(
		comment: '',
		nullable: false,
		type: OField::DATETIME
	)]
	public ?string $expires_at;

	#[OField(
		comment: '',
		nullable: true,
		type: OField::DATETIME
	)]
	public ?string $accepted_at;

	#[OField(
		comment: '',
		nullable: true,
		type: OField::DATETIME
	)]
	public ?string $revoked_at;

	#[OCreatedAt(
		comment: ''
	)]
	public ?string $created_at;

	#[OUpdatedAt(
		comment: ''
	)]
	public ?string $updated_at;
}
