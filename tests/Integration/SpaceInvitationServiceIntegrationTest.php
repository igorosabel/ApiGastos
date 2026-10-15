<?php declare(strict_types=1);

require_once __DIR__ . '/OrmIntegrationTestCase.php';

use Osumi\OsumiFramework\App\Model\Space;
use Osumi\OsumiFramework\App\Model\SpaceMember;
use Osumi\OsumiFramework\App\Service\Space\SpaceInvitationService;
use Osumi\OsumiFramework\App\Service\Space\SpaceService;

final class SpaceInvitationServiceIntegrationTest extends OrmIntegrationTestCase {
    /** @var list<int> */
    private array $user_ids = [];

    /** @var list<int> */
    private array $space_ids = [];

    /**
     * Elimina todos los registros creados por cada prueba.
     */
    protected function tearDown(): void {
        try {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            foreach (array_reverse($this->space_ids) as $space_id) {
                $this->deleteBySpace('space_invitation', $space_id);
                $this->deleteBySpace('space_member', $space_id);

                $stmt = $this->db->prepare(
                    'DELETE FROM `space` WHERE id = ?'
                );
                $stmt->execute([$space_id]);
            }

            foreach (array_reverse($this->user_ids) as $user_id) {
                $stmt = $this->db->prepare(
                    'DELETE FROM `user` WHERE id = ?'
                );
                $stmt->execute([$user_id]);
            }
        }
        finally {
            parent::tearDown();
        }
    }

    /**
     * Comprueba el almacenamiento del hash, la normalización
     * del correo y la caducidad de siete días.
     */
    public function testCreateInvitation(): void {
        $owner_id = $this->createUser();
        $space = $this->createSharedSpace($owner_id);
        $service = new SpaceInvitationService();

        $before = time();

        $result = $service->create(
            $space->id,
            $owner_id,
            '  INVITADO@EXAMPLE.COM  '
        );

        $after = time();

        $invitation = $result['invitation'];
        $token = $result['token'];

        $this->assertNotNull($invitation->id);
        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/D',
            $token
        );

        $stmt = $this->db->prepare(
            'SELECT id_space, email, token_hash, invited_by,
                    expires_at, accepted_at, revoked_at
             FROM `space_invitation`
             WHERE id = ?'
        );
        $stmt->execute([$invitation->id]);
        $stored = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($stored);
        $this->assertSame($space->id, (int) $stored['id_space']);
        $this->assertSame($owner_id, (int) $stored['invited_by']);
        $this->assertSame('invitado@example.com', $stored['email']);
        $this->assertSame(hash('sha256', $token), $stored['token_hash']);
        $this->assertNotSame($token, $stored['token_hash']);
        $this->assertNull($stored['accepted_at']);
        $this->assertNull($stored['revoked_at']);

        $expires = strtotime($stored['expires_at']);
        $this->assertNotFalse($expires);
        $this->assertGreaterThanOrEqual(
            $before + 604800,
            $expires
        );
        $this->assertLessThanOrEqual(
            $after + 604800,
            $expires
        );
    }

    /**
     * Comprueba que otro usuario no puede invitar al espacio.
     */
    public function testNonOwnerCannotInvite(): void {
        $owner_id = $this->createUser();
        $other_id = $this->createUser();
        $space = $this->createSharedSpace($owner_id);

        $service = new SpaceInvitationService();

        $this->expectException(DomainException::class);

        $service->create(
            $space->id,
            $other_id,
            'invitado@example.com'
        );
    }

    /**
     * Comprueba que no pueden crearse invitaciones a espacios personales.
     */
    public function testPersonalSpaceCannotInvite(): void {
        $owner_id = $this->createUser();

        $space = new Space();
        $space->name = 'Personal';
        $space->type = 'personal';
        $space->owner_id = $owner_id;
        $space->personal_owner_id = $owner_id;

        $this->assertTrue($space->save());
        $this->assertNotNull($space->id);
        $this->space_ids[] = $space->id;

        $service = new SpaceInvitationService();

        $this->expectException(DomainException::class);

        $service->create(
            $space->id,
            $owner_id,
            'invitado@example.com'
        );
    }

    /**
     * Comprueba que no se invita a quien ya pertenece al espacio.
     */
    public function testExistingMemberCannotBeInvited(): void {
        $owner_id = $this->createUser();
        $email = $this->uniqueEmail();
        $member_id = $this->createUser($email);
        $space = $this->createSharedSpace($owner_id);

        $member = new SpaceMember();
        $member->id_space = $space->id;
        $member->id_user = $member_id;

        $this->assertTrue($member->save());

        $service = new SpaceInvitationService();

        $this->expectException(LogicException::class);

        $service->create($space->id, $owner_id, $email);
    }

    /**
     * Comprueba que no se duplican invitaciones pendientes.
     */
    public function testPendingInvitationCannotBeDuplicated(): void {
        $owner_id = $this->createUser();
        $space = $this->createSharedSpace($owner_id);
        $service = new SpaceInvitationService();
        $email = 'invitado@example.com';

        $service->create($space->id, $owner_id, $email);

        try {
            $service->create($space->id, $owner_id, $email);

            $this->fail(
                'Se ha permitido crear una invitación duplicada.'
            );
        }
        catch (LogicException) {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM `space_invitation`
                 WHERE id_space = ? AND email = ?'
            );
            $stmt->execute([$space->id, $email]);

            $this->assertSame(1, (int) $stmt->fetchColumn());
        }
    }

    /**
     * Comprueba que no se admiten direcciones inválidas.
     */
    public function testInvalidEmail(): void {
        $owner_id = $this->createUser();
        $space = $this->createSharedSpace($owner_id);
        $service = new SpaceInvitationService();

        $this->expectException(InvalidArgumentException::class);

        $service->create(
            $space->id,
            $owner_id,
            'correo-invalido'
        );
    }

    /**
     * Comprueba que la creación bloquea el espacio y libera
     * correctamente la transacción.
     */
    public function testCreationLocksSpaceRow(): void {
        $owner_id = $this->createUser();
        $space = $this->createSharedSpace($owner_id);
        $db = $this->db;
        $locked_space = false;

        $proxy = $this->createStub(PDO::class);

        $proxy->method('prepare')->willReturnCallback(
            static function(string $sql) use (
                $db,
                &$locked_space
            ): PDOStatement|false {
                if (
                    str_contains($sql, 'FROM `space`') &&
                    str_contains($sql, 'FOR UPDATE')
                ) {
                    $locked_space = true;
                }

                return $db->prepare($sql);
            }
        );

        $proxy->method('beginTransaction')->willReturnCallback(
            static fn(): bool => $db->beginTransaction()
        );

        $proxy->method('commit')->willReturnCallback(
            static fn(): bool => $db->commit()
        );

        $proxy->method('rollBack')->willReturnCallback(
            static fn(): bool => $db->rollBack()
        );

        $proxy->method('inTransaction')->willReturnCallback(
            static fn(): bool => $db->inTransaction()
        );

        $proxy->method('lastInsertId')->willReturnCallback(
            static fn(): string|false => $db->lastInsertId()
        );

        $this->setOrmConnection($proxy);

        $service = new SpaceInvitationService();
        $result = $service->create(
            $space->id,
            $owner_id,
            'invitado@example.test'
        );

        $this->assertTrue($locked_space);
        $this->assertNotNull($result['invitation']->id);
        $this->assertFalse($db->inTransaction());
    }

    /**
     * Comprueba que el bloqueo no impide volver a invitar
     * después de revocar una invitación anterior.
     */
    public function testCanInviteAgainAfterRevocation(): void {
        $owner_id = $this->createUser();
        $space = $this->createSharedSpace($owner_id);
        $service = new SpaceInvitationService();
        $email = 'invitado@example.test';

        $first = $service->create(
            $space->id,
            $owner_id,
            $email
        );

        $service->revoke(
            $first['invitation']->id,
            $owner_id
        );

        $second = $service->create(
            $space->id,
            $owner_id,
            $email
        );

        $this->assertNotSame(
            $first['invitation']->id,
            $second['invitation']->id
        );
        $this->assertNotSame(
            $first['token'],
            $second['token']
        );

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM `space_invitation`
             WHERE id_space = ?
               AND email = ?
               AND accepted_at IS NULL
               AND revoked_at IS NULL
               AND expires_at > NOW()'
        );
        $stmt->execute([$space->id, $email]);

        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    /**
     * Comprueba que una invitación caducada no impide
     * crear una nueva para el mismo destinatario.
     */
    public function testCanInviteAgainAfterExpiration(): void {
        $owner_id = $this->createUser();
        $space = $this->createSharedSpace($owner_id);
        $service = new SpaceInvitationService();
        $email = 'invitado@example.test';

        $first = $service->create(
            $space->id,
            $owner_id,
            $email
        );

        $stmt = $this->db->prepare(
            'UPDATE `space_invitation`
             SET expires_at = DATE_SUB(NOW(), INTERVAL 1 DAY)
             WHERE id = ?'
        );
        $stmt->execute([$first['invitation']->id]);

        $second = $service->create(
            $space->id,
            $owner_id,
            $email
        );

        $this->assertNotSame(
            $first['invitation']->id,
            $second['invitation']->id
        );

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM `space_invitation`
             WHERE id_space = ? AND email = ?'
        );
        $stmt->execute([$space->id, $email]);

        $this->assertSame(2, (int) $stmt->fetchColumn());
    }

    /**
       * Comprueba que dos conexiones no pueden crear invitaciones
       * para el mismo espacio mientras existe un bloqueo activo.
       *
       * Utiliza dos sesiones reales de MariaDB y limita el tiempo
       * de espera para que el test no quede bloqueado.
       */
      public function testConcurrentCreationRespectsSpaceLock(): void {
          $owner_id = $this->createUser();
          $space = $this->createSharedSpace($owner_id);
          $email = $this->uniqueEmail();

          $dsn = getenv('GASTOS_TEST_DSN');
          $user = getenv('GASTOS_TEST_USER');
          $password = getenv('GASTOS_TEST_PASSWORD');

          if (
              !is_string($dsn) ||
              !is_string($user) ||
              !is_string($password)
          ) {
              throw new RuntimeException(
                  'Falta configurar la conexión de integración.'
              );
          }

          $second_db = new PDO($dsn, $user, $password, [
              PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
              PDO::ATTR_EMULATE_PREPARES => false
          ]);

          $this->assertSame(
              'gastos_test',
              $second_db->query('SELECT DATABASE()')->fetchColumn()
          );

          $this->assertNotSame(
              $this->db->query('SELECT CONNECTION_ID()')->fetchColumn(),
              $second_db->query('SELECT CONNECTION_ID()')->fetchColumn()
          );

          // Solo afecta a la segunda sesión de esta prueba.
          $second_db->exec('SET SESSION innodb_lock_wait_timeout = 1');

          try {
              // Primera conexión: mantiene bloqueado el espacio.
              $this->assertTrue($this->db->beginTransaction());

              $stmt = $this->db->prepare(
                  'SELECT id FROM `space`
                   WHERE id = ?
                   FOR UPDATE'
              );
              $stmt->execute([$space->id]);

              $this->assertNotFalse($stmt->fetch(PDO::FETCH_ASSOC));

              // El servicio utilizará ahora la segunda conexión.
              $this->setOrmConnection($second_db);
              $service = new SpaceInvitationService();

              try {
                  $service->create(
                      $space->id,
                      $owner_id,
                      $email
                  );

                  $this->fail(
                      'La segunda conexión ha ignorado el bloqueo.'
                  );
              }
              catch (PDOException $e) {
                  $this->assertSame(
                      1205,
                      (int) ($e->errorInfo[1] ?? 0),
                      'Se esperaba un timeout por bloqueo InnoDB.'
                  );
              }

              $this->assertFalse($second_db->inTransaction());

              // Liberamos el bloqueo de la primera conexión.
              $this->db->commit();

              // Ahora la segunda conexión debe poder crear.
              $result = $service->create(
                  $space->id,
                  $owner_id,
                  $email
              );

              $this->assertNotNull($result['invitation']->id);
              $this->assertFalse($second_db->inTransaction());

              // Y una nueva petición para el mismo correo debe fallar.
              $this->expectException(LogicException::class);

              $service->create(
                  $space->id,
                  $owner_id,
                  $email
              );
          }
          finally {
              if ($this->db->inTransaction()) {
                  $this->db->rollBack();
              }

              if ($second_db->inTransaction()) {
                  $second_db->rollBack();
              }

              // Restauramos la conexión original del test.
              $this->setOrmConnection($this->db);
          }
      }

    /**
     * Crea un usuario de prueba.
     *
     * @param string|null $email Correo opcional.
     * @return int Identificador del usuario.
     */
    private function createUser(?string $email = null): int {
        $stmt = $this->db->prepare(
            'INSERT INTO `user` (`name`, `email`, `password_hash`)
             VALUES (?, ?, ?)'
        );

        $stmt->execute([
            'Usuario integración',
            $email ?? $this->uniqueEmail(),
            password_hash('contraseña segura', PASSWORD_DEFAULT)
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->user_ids[] = $id;

        return $id;
    }

    /**
     * Crea un espacio compartido utilizando el servicio real.
     *
     * @param int $owner_id Identificador del propietario.
     * @return Space Espacio creado.
     */
    private function createSharedSpace(int $owner_id): Space {
        $service = new SpaceService();
        $space = $service->createSharedSpace(
            $owner_id,
            'Familia de prueba'
        );

        $this->assertNotNull($space->id);
        $this->space_ids[] = $space->id;

        return $space;
    }

    /**
     * Elimina datos asociados a un espacio de prueba.
     *
     * @param string $table Tabla permitida.
     * @param int $space_id Identificador del espacio.
     */
    private function deleteBySpace(string $table, int $space_id): void {
        if (!in_array(
            $table,
            ['space_invitation', 'space_member'],
            true
        )) {
            throw new InvalidArgumentException(
                'Tabla de limpieza no permitida.'
            );
        }

        $stmt = $this->db->prepare(
            "DELETE FROM `{$table}` WHERE id_space = ?"
        );
        $stmt->execute([$space_id]);
    }

    /**
     * Genera una dirección exclusiva para las pruebas.
     *
     * @return string Correo electrónico.
     */
    private function uniqueEmail(): string {
        return 'invitation-test-' .
            bin2hex(random_bytes(12)) .
            '@example.test';
    }
}
