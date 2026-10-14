<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OUpdatedAt;
use Osumi\OsumiFramework\ORM\ODeletedAt;

class FinancialTransaction extends OModel {
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
		ref: 'space_member.id_user'
	)]
	public ?int $id_user;

	#[OField(
		comment: '',
		nullable: false,
		type: OField::NUMBER,
		ref: 'category.id'
	)]
	public ?int $id_category;

	#[OField(
		comment: '',
		nullable: false,
		type: OField::TEXT,
		max: 7
	)]
	public ?string $type;

	#[OField(
		comment: '',
		nullable: false,
		type: OField::DECIMAL,
		precision: 12,
		scale: 2
	)]
	public ?string $amount;

	#[OField(
		comment: '',
		nullable: true,
		type: OField::TEXT,
		max: 255
	)]
	public ?string $concept;

	#[OField(
		comment: '',
		nullable: false,
		type: OField::DATE
	)]
	public ?string $date;

	#[OCreatedAt(
		comment: ''
	)]
	public ?string $created_at;

	#[OUpdatedAt(
		comment: ''
	)]
	public ?string $updated_at;
}
