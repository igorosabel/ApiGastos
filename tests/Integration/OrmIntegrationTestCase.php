<?php declare(strict_types=1);

use Osumi\OsumiFramework\Core\OConfig;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\ORM\ODBContainer;
use PHPUnit\Framework\TestCase;

abstract class OrmIntegrationTestCase extends TestCase {
    protected PDO $db;

    private bool $core_existed = false;
    private mixed $previous_core = null;

    /**
     * Abre exclusivamente la base de datos de integración
     * y configura el contenedor ORM de OFW.
     */
    protected function setUp(): void {
        parent::setUp();

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

        $this->db = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);

        if (
            $this->db->query('SELECT DATABASE()')->fetchColumn()
            !== 'gastos_test'
        ) {
            throw new RuntimeException(
                'Las pruebas ORM solo pueden utilizar gastos_test.'
            );
        }

        $this->core_existed = array_key_exists('core', $GLOBALS);
        if ($this->core_existed) {
            $this->previous_core = $GLOBALS['core'];
        }

        $this->setOrmConnection($this->db);
    }

    /**
     * Restaura el núcleo anterior de OFW.
     */
    protected function tearDown(): void {
        if ($this->core_existed) {
            $GLOBALS['core'] = $this->previous_core;
        }
        else {
            unset($GLOBALS['core']);
        }

        parent::tearDown();
    }

    /**
     * Configura una conexión controlada para ODB.
     *
     * @param PDO $connection Conexión validada o conexión de prueba.
     */
    protected function setOrmConnection(PDO $connection): void {
        $config = $this->createStub(OConfig::class);
        $config->method('getDB')->willReturnCallback(
            static fn(string $key): string => match ($key) {
                'driver' => 'mysql',
                'host' => 'integration',
                'user' => 'integration',
                'pass' => 'integration',
                'name' => 'gastos_test',
                'charset' => 'utf8mb4',
                default => ''
            }
        );

        $container = $this->createStub(ODBContainer::class);
        $container->method('getConnection')->willReturn([
            'index' => 'gastos_test',
            'link' => $connection
        ]);

        $core = new OCore();
        $core->config = $config;
        $core->db_container = $container;

        $GLOBALS['core'] = $core;
    }
}
