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
		$this->categorys = Category::where(['id_space' => $this->id]);
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
		$this->financial_transactions = FinancialTransaction::where(['id_space' => $this->id]);
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
		$this->recurring_transactions = RecurringTransaction::where(['id_space' => $this->id]);
	}
	private ?array $space_invitations = null;

	/**
	 * Save SpaceInvitation list
	 *
	 * @param array $space_invitations SpaceInvitation list
	 *
	 * @return void
	 */
	public function setSpaceInvitations(array $space_invitations): void {
		$this->space_invitations = $space_invitations;
	}

	/**
	 * Get SpaceInvitation list
	 *
	 * @return array space_invitation list
	 */
	public function getSpaceInvitations(): array {
		if (is_null($this->space_invitations)) {
			$this->loadSpaceInvitations();
		}
		return $this->space_invitations;
	}

	/**
	 * Load SpaceInvitation list
	 *
	 * @return void
	 */
	private function loadSpaceInvitations(): void {
		$this->space_invitations = SpaceInvitation::where(['id_space' => $this->id]);
	}
	private ?array $space_members = null;

	/**
	 * Save SpaceMember list
	 *
	 * @param array $space_members SpaceMember list
	 *
	 * @return void
	 */
	public function setSpaceMembers(array $space_members): void {
		$this->space_members = $space_members;
	}

	/**
	 * Get SpaceMember list
	 *
	 * @return array space_member list
	 */
	public function getSpaceMembers(): array {
		if (is_null($this->space_members)) {
			$this->loadSpaceMembers();
		}
		return $this->space_members;
	}

	/**
	 * Load SpaceMember list
	 *
	 * @return void
	 */
	private function loadSpaceMembers(): void {
		$this->space_members = SpaceMember::where(['id_space' => $this->id]);
	}

}
