<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OUpdatedAt;
use Osumi\OsumiFramework\ORM\ODeletedAt;

class RecurringTransaction extends OModel {
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
		nullable: false,
		type: OField::TEXT,
		max: 255
	)]
	public ?string $concept;

	#[OField(
		comment: '',
		nullable: false,
		type: OField::NUMBER
	)]
	public ?int $day_of_month;

	#[OField(
		comment: '',
		nullable: false,
		type: OField::DATE
	)]
	public ?string $start_date;

	#[OField(
		comment: '',
		nullable: true,
		type: OField::DATE
	)]
	public ?string $end_date;

	#[OField(
		comment: '',
		nullable: false,
		type: OField::BOOL,
		default: true
	)]
	public ?bool $active;

	#[OCreatedAt(
		comment: ''
	)]
	public ?string $created_at;

	#[OUpdatedAt(
		comment: ''
	)]
	public ?string $updated_at;

	private ?array $recurring_occurrences = null;

	/**
	 * Save RecurringOccurrence list
	 *
	 * @param array $recurring_occurrences RecurringOccurrence list
	 *
	 * @return void
	 */
	public function setRecurringOccurrences(array $recurring_occurrences): void {
		$this->recurring_occurrences = $recurring_occurrences;
	}

	/**
	 * Get RecurringOccurrence list
	 *
	 * @return array recurring_occurrence list
	 */
	public function getRecurringOccurrences(): array {
		if (is_null($this->recurring_occurrences)) {
			$this->loadRecurringOccurrences();
		}
		return $this->recurring_occurrences;
	}

	/**
	 * Load RecurringOccurrence list
	 *
	 * @return void
	 */
	private function loadRecurringOccurrences(): void {
		$this->recurring_occurrences = RecurringOccurrence::where(['id_space' => $this->id_space]);
	}
	private ?array $recurring_occurrences = null;

	/**
	 * Save RecurringOccurrence list
	 *
	 * @param array $recurring_occurrences RecurringOccurrence list
	 *
	 * @return void
	 */
	public function setRecurringOccurrences(array $recurring_occurrences): void {
		$this->recurring_occurrences = $recurring_occurrences;
	}

	/**
	 * Get RecurringOccurrence list
	 *
	 * @return array recurring_occurrence list
	 */
	public function getRecurringOccurrences(): array {
		if (is_null($this->recurring_occurrences)) {
			$this->loadRecurringOccurrences();
		}
		return $this->recurring_occurrences;
	}

	/**
	 * Load RecurringOccurrence list
	 *
	 * @return void
	 */
	private function loadRecurringOccurrences(): void {
		$this->recurring_occurrences = RecurringOccurrence::where(['id_recurring_transaction' => $this->id]);
	}

}
