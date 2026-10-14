<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Service\Auth\EmailAlreadyRegisteredException;
use Osumi\OsumiFramework\App\Service\Auth\RegistrationService;
use PHPUnit\Framework\TestCase;

final class RegistrationServiceIntegrationTest extends TestCase {
    private PDO $db;
    private ?int $user_id = null;
    private ?int $space_id = null;

    /**
     * Conecta a la base de datos exclusiva de pruebas.
     */
    protected function setUp(): void {
        parent::setUp();

        $dsn = getenv('GASTOS_TEST_DSN');
        $user = getenv('GASTOS_TEST_USER');
        $password = getenv('GASTOS_TEST_PASSWORD');

        if (
            $dsn === false ||
            $user === false ||
            $password === false
        ) {
            throw new RuntimeException(
                'Falta configurar la conexión de integración.'
            );
        }

        $this->db = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);

        $database = $this->db->query('SELECT DATABASE()')
            ->fetchColumn();

        if ($database !== 'gastos_test') {
            throw new RuntimeException(
                'Las pruebas solo pueden utilizar gastos_test.'
            );
        }
    }

    /**
     * Elimina los datos creados por la prueba.
     */
    protected function tearDown(): void {
        try {
            if ($this->user_id !== null) {
                $this->db->beginTransaction();

                if ($this->space_id !== null) {
                    $stmt = $this->db->prepare(
                        'DELETE FROM space_member WHERE id_space = ?'
                    );
                    $stmt->execute([$this->space_id]);

                    $stmt = $this->db->prepare(
                        'DELETE FROM space WHERE id = ?'
                    );
                    $stmt->execute([$this->space_id]);
                }

                $stmt = $this->db->prepare(
                    'DELETE FROM user WHERE id = ?'
                );
                $stmt->execute([$this->user_id]);

                $this->db->commit();
            }
        }
        finally {
            parent::tearDown();
        }
    }

    /**
     * Comprueba que el registro persiste las tres entidades relacionadas.
     */
    public function testSuccessfulRegistration(): void {
        $email = $this->uniqueEmail();
        $service = new RegistrationService($this->db);

        $result = $service->register(
            'Usuario de prueba',
            $email,
            'contraseña de prueba segura'
        );

        $this->user_id = $result['user_id'];
        $this->space_id = $result['space_id'];

        $stmt = $this->db->prepare(
            'SELECT name, email, password_hash
             FROM user WHERE id = ?'
        );
        $stmt->execute([$this->user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($user);
        $this->assertSame('Usuario de prueba', $user['name']);
        $this->assertSame($email, $user['email']);
        $this->assertNotSame(
            'contraseña de prueba segura',
            $user['password_hash']
        );
        $this->assertTrue(password_verify(
            'contraseña de prueba segura',
            $user['password_hash']
        ));

        $stmt = $this->db->prepare(
            'SELECT type, owner_id, personal_owner_id
             FROM space WHERE id = ?'
        );
        $stmt->execute([$this->space_id]);
        $space = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($space);
        $this->assertSame('personal', $space['type']);
        $this->assertEquals($this->user_id, $space['owner_id']);
        $this->assertEquals(
            $this->user_id,
            $space['personal_owner_id']
        );

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM space_member
             WHERE id_space = ? AND id_user = ?'
        );
        $stmt->execute([$this->space_id, $this->user_id]);

        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    /**
     * Comprueba la excepción y la ausencia de registros adicionales
     * cuando se intenta reutilizar un correo.
     */
    public function testDuplicateEmail(): void {
        $email = $this->uniqueEmail();
        $service = new RegistrationService($this->db);

        $result = $service->register(
            'Primer usuario',
            $email,
            'contraseña de prueba segura'
        );

        $this->user_id = $result['user_id'];
        $this->space_id = $result['space_id'];

        try {
            $service->register(
                'Segundo usuario',
                $email,
                'otra contraseña segura'
            );

            $this->fail(
                'Se ha permitido registrar un correo duplicado.'
            );
        }
        catch (EmailAlreadyRegisteredException) {
            // Excepción esperada.
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM user WHERE email = ?'
        );
        $stmt->execute([$email]);

        $this->assertSame(1, (int) $stmt->fetchColumn());

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM space WHERE personal_owner_id = ?'
        );
        $stmt->execute([$this->user_id]);

        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    /**
     * Genera un correo exclusivo para cada ejecución.
     */
    private function uniqueEmail(): string {
        return 'test-' . bin2hex(random_bytes(12)) .
            '@example.test';
    }
}
