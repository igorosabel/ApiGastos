<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OUpdatedAt;
use Osumi\OsumiFramework\ORM\ODeletedAt;

class User extends OModel {
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
		max: 254
	)]
	public ?string $email;

	#[OField(
		comment: '',
		nullable: false,
		type: OField::TEXT,
		max: 255
	)]
	public ?string $password_hash;

	#[OCreatedAt(
		comment: ''
	)]
	public ?string $created_at;

	#[OUpdatedAt(
		comment: ''
	)]
	public ?string $updated_at;

	private ?array $spaces = null;

	/**
	 * Save Space list
	 *
	 * @param array $spaces Space list
	 *
	 * @return void
	 */
	public function setSpaces(array $spaces): void {
		$this->spaces = $spaces;
	}

	/**
	 * Get Space list
	 *
	 * @return array space list
	 */
	public function getSpaces(): array {
		if (is_null($this->spaces)) {
			$this->loadSpaces();
		}
		return $this->spaces;
	}

	/**
	 * Load Space list
	 *
	 * @return void
	 */
	private function loadSpaces(): void {
		$this->spaces = Space::where(['owner_id' => $this->id]);
	}
	private ?array $spaces = null;

	/**
	 * Save Space list
	 *
	 * @param array $spaces Space list
	 *
	 * @return void
	 */
	public function setSpaces(array $spaces): void {
		$this->spaces = $spaces;
	}

	/**
	 * Get Space list
	 *
	 * @return array space list
	 */
	public function getSpaces(): array {
		if (is_null($this->spaces)) {
			$this->loadSpaces();
		}
		return $this->spaces;
	}

	/**
	 * Load Space list
	 *
	 * @return void
	 */
	private function loadSpaces(): void {
		$this->spaces = Space::where(['personal_owner_id' => $this->id]);
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
		$this->space_invitations = SpaceInvitation::where(['invited_by' => $this->id]);
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
		$this->space_members = SpaceMember::where(['id_user' => $this->id]);
	}

}
