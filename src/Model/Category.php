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

	private ?array $categorys = null;

	/**
	 * Save Category list
	 *
	 * @param array $categorys Category list
	 *
	 * @return void
	 */
	public function setCategorys(array $categorys): void {
		$this->categorys = $categorys;
	}

	/**
	 * Get Category list
	 *
	 * @return array category list
	 */
	public function getCategorys(): array {
		if (is_null($this->categorys)) {
			$this->loadCategorys();
		}
		return $this->categorys;
	}

	/**
	 * Load Category list
	 *
	 * @return void
	 */
	private function loadCategorys(): void {
		$this->categorys = Category::where(['id_space' => $this->id_space]);
	}
	private ?array $categorys = null;

	/**
	 * Save Category list
	 *
	 * @param array $categorys Category list
	 *
	 * @return void
	 */
	public function setCategorys(array $categorys): void {
		$this->categorys = $categorys;
	}

	/**
	 * Get Category list
	 *
	 * @return array category list
	 */
	public function getCategorys(): array {
		if (is_null($this->categorys)) {
			$this->loadCategorys();
		}
		return $this->categorys;
	}

	/**
	 * Load Category list
	 *
	 * @return void
	 */
	private function loadCategorys(): void {
		$this->categorys = Category::where(['id_parent' => $this->id]);
	}
	private ?array $financial_transactions = null;

	/**
	 * Save FinancialTransaction list
	 *
	 * @param array $financial_transactions FinancialTransaction list
	 *
	 * @return void
	 */
	public function setFinancialTransactions(array $financial_transactions): void {
		$this->financial_transactions = $financial_transactions;
	}

	/**
	 * Get FinancialTransaction list
	 *
	 * @return array financial_transaction list
	 */
	public function getFinancialTransactions(): array {
		if (is_null($this->financial_transactions)) {
			$this->loadFinancialTransactions();
		}
		return $this->financial_transactions;
	}

	/**
	 * Load FinancialTransaction list
	 *
	 * @return void
	 */
	private function loadFinancialTransactions(): void {
		$this->financial_transactions = FinancialTransaction::where(['id_space' => $this->id_space]);
	}
	private ?array $financial_transactions = null;

	/**
	 * Save FinancialTransaction list
	 *
	 * @param array $financial_transactions FinancialTransaction list
	 *
	 * @return void
	 */
	public function setFinancialTransactions(array $financial_transactions): void {
		$this->financial_transactions = $financial_transactions;
	}

	/**
	 * Get FinancialTransaction list
	 *
	 * @return array financial_transaction list
	 */
	public function getFinancialTransactions(): array {
		if (is_null($this->financial_transactions)) {
			$this->loadFinancialTransactions();
		}
		return $this->financial_transactions;
	}

	/**
	 * Load FinancialTransaction list
	 *
	 * @return void
	 */
	private function loadFinancialTransactions(): void {
		$this->financial_transactions = FinancialTransaction::where(['id_category' => $this->id]);
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
		$this->recurring_occurrences = RecurringOccurrence::where(['id_category' => $this->id]);
	}
	private ?array $recurring_transactions = null;

	/**
	 * Save RecurringTransaction list
	 *
	 * @param array $recurring_transactions RecurringTransaction list
	 *
	 * @return void
	 */
	public function setRecurringTransactions(array $recurring_transactions): void {
		$this->recurring_transactions = $recurring_transactions;
	}

	/**
	 * Get RecurringTransaction list
	 *
	 * @return array recurring_transaction list
	 */
	public function getRecurringTransactions(): array {
		if (is_null($this->recurring_transactions)) {
			$this->loadRecurringTransactions();
		}
		return $this->recurring_transactions;
	}

	/**
	 * Load RecurringTransaction list
	 *
	 * @return void
	 */
	private function loadRecurringTransactions(): void {
		$this->recurring_transactions = RecurringTransaction::where(['id_space' => $this->id_space]);
	}
	private ?array $recurring_transactions = null;

	/**
	 * Save RecurringTransaction list
	 *
	 * @param array $recurring_transactions RecurringTransaction list
	 *
	 * @return void
	 */
	public function setRecurringTransactions(array $recurring_transactions): void {
		$this->recurring_transactions = $recurring_transactions;
	}

	/**
	 * Get RecurringTransaction list
	 *
	 * @return array recurring_transaction list
	 */
	public function getRecurringTransactions(): array {
		if (is_null($this->recurring_transactions)) {
			$this->loadRecurringTransactions();
		}
		return $this->recurring_transactions;
	}

	/**
	 * Load RecurringTransaction list
	 *
	 * @return void
	 */
	private function loadRecurringTransactions(): void {
		$this->recurring_transactions = RecurringTransaction::where(['id_category' => $this->id]);
	}

}
