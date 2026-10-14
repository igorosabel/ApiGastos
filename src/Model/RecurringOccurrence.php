<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OUpdatedAt;
use Osumi\OsumiFramework\ORM\ODeletedAt;

class RecurringOccurrence extends OModel {
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
		ref: 'financial_transaction.id_space'
	)]
	public ?int $id_space;

	#[OField(
		comment: '',
		nullable: false,
		type: OField::NUMBER,
		ref: 'recurring_transaction.id'
	)]
	public ?int $id_recurring_transaction;

	#[OField(
		comment: '',
		nullable: false,
		type: OField::DATE
	)]
	public ?string $period;

	#[OField(
		comment: '',
		nullable: false,
		type: OField::DATE
	)]
	public ?string $scheduled_date;

	#[OField(
		comment: '',
		nullable: false,
		type: OField::TEXT,
		max: 9,
		default: 'pending'
	)]
	public ?string $status;

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
		type: OField::TEXT,
		max: 255
	)]
	public ?string $concept;

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
		type: OField::NUMBER,
		ref: 'financial_transaction.id'
	)]
	public ?int $id_transaction;

	#[OField(
		comment: '',
		nullable: true,
		type: OField::DATETIME
	)]
	public ?string $resolved_at;

	#[OCreatedAt(
		comment: ''
	)]
	public ?string $created_at;

	#[OUpdatedAt(
		comment: ''
	)]
	public ?string $updated_at;


}
