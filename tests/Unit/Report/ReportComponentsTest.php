<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Component\Model\ForecastEvolution\ForecastEvolutionComponent;
use Osumi\OsumiFramework\App\Component\Model\MonthlyCategoryReport\MonthlyCategoryReportComponent;
use Osumi\OsumiFramework\App\Component\Model\MonthlyForecast\MonthlyForecastComponent;
use Osumi\OsumiFramework\App\Component\Model\MonthlyReport\MonthlyReportComponent;
use Osumi\OsumiFramework\App\Component\Model\ReportEvolution\ReportEvolutionComponent;
use Osumi\OsumiFramework\App\DTO\Report\GetMonthlyReportDTO;
use Osumi\OsumiFramework\App\DTO\Report\GetReportEvolutionDTO;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Module\Report\GetForecastEvolution\GetForecastEvolutionComponent;
use Osumi\OsumiFramework\App\Module\Report\GetMonthlyCategoryReport\GetMonthlyCategoryReportComponent;
use Osumi\OsumiFramework\App\Module\Report\GetMonthlyForecast\GetMonthlyForecastComponent;
use Osumi\OsumiFramework\App\Module\Report\GetMonthlyReport\GetMonthlyReportComponent;
use Osumi\OsumiFramework\App\Module\Report\GetReportEvolution\GetReportEvolutionComponent;
use Osumi\OsumiFramework\App\Service\Auth\AuthService;
use Osumi\OsumiFramework\App\Service\Report\ReportService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OConfig;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReportComponentsTest extends TestCase {
    private bool $core_existed = false;
    private mixed $previous_core = null;

    /**
     * Prepara el contexto de OFW necesario para
     * autenticar peticiones y construir componentes.
     */
    protected function setUp(): void {
        parent::setUp();

        $this->core_existed = array_key_exists(
            'core',
            $GLOBALS
        );

        if ($this->core_existed) {
            $this->previous_core = $GLOBALS['core'];
        }

        $core = new OCore();

        // Los componentes OFW crean un OLog al inicializarse.
        // Proporcionamos una configuración aislada para
        // evitar cargar la configuración de producción.
        $config = $this->createStub(OConfig::class);

        $config->method('getDir')->willReturn(
            rtrim(
                sys_get_temp_dir(),
                DIRECTORY_SEPARATOR
            ) . DIRECTORY_SEPARATOR
        );

        $config->method('getLog')->willReturnCallback(
            static fn(string $key): string|int => match ($key) {
                'name' => 'gastos-report-tests',
                'level' => 'ERROR',
                'max_file_size' => 1,
                'max_num_files' => 1,
                default => throw new RuntimeException(
                    'Configuración de log no esperada.'
                )
            }
        );

        $core->config = $config;
        $GLOBALS['core'] = $core;

        OMiddleware::reset();
        OMiddleware::setRoute([
            OMiddleware::PHASE_BEFORE => [
                LoginMiddleware::class
            ]
        ]);
    }

    /**
     * Restaura el estado global de OFW.
     */
    protected function tearDown(): void {
        OMiddleware::reset();

        if ($this->core_existed) {
            $GLOBALS['core'] = $this->previous_core;
        }
        else {
            unset($GLOBALS['core']);
        }

        parent::tearDown();
    }

    /**
     * Devuelve los cinco contratos que deben probarse.
     *
     * @return array<string, array{
     *     class-string,
     *     string,
     *     class-string,
     *     bool
     * }>
     */
    public static function reportCases(): array {
        return [
            'monthly' => [
                GetMonthlyReportComponent::class,
                'getMonthly',
                MonthlyReportComponent::class,
                false
            ],
            'categories' => [
                GetMonthlyCategoryReportComponent::class,
                'getMonthlyByCategory',
                MonthlyCategoryReportComponent::class,
                false
            ],
            'evolution' => [
                GetReportEvolutionComponent::class,
                'getEvolution',
                ReportEvolutionComponent::class,
                true
            ],
            'forecast' => [
                GetMonthlyForecastComponent::class,
                'getMonthlyForecast',
                MonthlyForecastComponent::class,
                false
            ],
            'forecast_evolution' => [
                GetForecastEvolutionComponent::class,
                'getForecastEvolution',
                ForecastEvolutionComponent::class,
                true
            ]
        ];
    }

    /**
     * Verifica la ejecución correcta de cada componente.
     *
     * @param class-string $component_class Componente.
     * @param string $method Método del servicio.
     * @param class-string $report_class Modelo de respuesta.
     * @param bool $evolution Si consulta un rango mensual.
     */
    #[DataProvider('reportCases')]
    public function testSuccessfulReport(
        string $component_class,
        string $method,
        string $report_class,
        bool $evolution
    ): void {
        $this->authenticateAs(10);

        $service = $this->createMock(ReportService::class);
        $expected = $this->serviceResult($method);

        $service->expects($this->once())
            ->method($method)
            ->with(...$this->serviceArguments($evolution))
            ->willReturn($expected);

        $component = $this->createComponent(
            $component_class,
            $service
        );

        $component->run($this->createDto($evolution));

        $this->assertSame(200, OMiddleware::getStatusCode());
        $this->assertTrue($component->success);
        $this->assertNull($component->error);
        $this->assertNull($component->message);

        $this->assertInstanceOf(
            $report_class,
            $component->report
        );

        $this->assertSame(
            25,
            $component->report->space_id
        );

        // Verificar también los componentes anidados.
        if ($method === 'getMonthlyForecast') {
            $this->assertInstanceOf(
                MonthlyReportComponent::class,
                $component->report->actual
            );

            $this->assertSame(
                '100.00',
                $component->report->actual->income
            );
            $this->assertSame(
                '75.00',
                $component->report->projected_balance
            );
        }

        if ($method === 'getMonthlyByCategory') {
            $this->assertCount(
                1,
                $component->report->categories->list
            );
        }

        if ($method === 'getEvolution') {
            $this->assertSame(
                $expected['months'],
                $component->report->months->list
            );
        }

        if ($method === 'getForecastEvolution') {
            $this->assertSame(
                $expected['months'],
                $component->report->months->list
            );
        }
    }

    /**
     * Un DTO incompleto devuelve 400 y no llama al servicio.
     *
     * @param class-string $component_class Componente.
     * @param string $method Método del servicio.
     * @param class-string $report_class Modelo esperado.
     * @param bool $evolution Si consulta un rango.
     */
    #[DataProvider('reportCases')]
    public function testMissingRequiredParameter(
        string $component_class,
        string $method,
        string $report_class,
        bool $evolution
    ): void {
        $this->authenticateAs(10);

        $service = $this->createMock(ReportService::class);
        $service->expects($this->never())->method($method);

        $component = $this->createComponent(
            $component_class,
            $service
        );

        $component->run(
            $this->createDto($evolution, true)
        );

        $this->assertSame(400, OMiddleware::getStatusCode());
        $this->assertFalse($component->success);
        $this->assertNull($component->report);
        $this->assertSame(
            'validation_error',
            $component->error
        );
        $this->assertNotEmpty($component->message);
    }

    /**
     * Los errores de validación del servicio devuelven 400.
     *
     * @param class-string $component_class Componente.
     * @param string $method Método del servicio.
     * @param class-string $report_class Modelo esperado.
     * @param bool $evolution Si consulta un rango.
     */
    #[DataProvider('reportCases')]
    public function testInvalidBusinessParameters(
        string $component_class,
        string $method,
        string $report_class,
        bool $evolution
    ): void {
        $this->authenticateAs(10);

        $service = $this->createMock(ReportService::class);

        $service->expects($this->once())
            ->method($method)
            ->with(...$this->serviceArguments($evolution))
            ->willThrowException(
                new InvalidArgumentException(
                    'El periodo no es válido.'
                )
            );

        $component = $this->createComponent(
            $component_class,
            $service
        );

        $component->run($this->createDto($evolution));

        $this->assertSame(400, OMiddleware::getStatusCode());
        $this->assertFalse($component->success);
        $this->assertNull($component->report);
        $this->assertSame(
            'validation_error',
            $component->error
        );
        $this->assertSame(
            'El periodo no es válido.',
            $component->message
        );
    }

    /**
     * Las peticiones sin permisos devuelven 403.
     *
     * @param class-string $component_class Componente.
     * @param string $method Método del servicio.
     * @param class-string $report_class Modelo esperado.
     * @param bool $evolution Si consulta un rango.
     */
    #[DataProvider('reportCases')]
    public function testForbiddenReport(
        string $component_class,
        string $method,
        string $report_class,
        bool $evolution
    ): void {
        $this->authenticateAs(10);

        $service = $this->createMock(ReportService::class);

        $service->expects($this->once())
            ->method($method)
            ->with(...$this->serviceArguments($evolution))
            ->willThrowException(
                new DomainException(
                    'No tienes acceso a este espacio.'
                )
            );

        $component = $this->createComponent(
            $component_class,
            $service
        );

        $component->run($this->createDto($evolution));

        $this->assertSame(403, OMiddleware::getStatusCode());
        $this->assertFalse($component->success);
        $this->assertNull($component->report);
        $this->assertSame(
            'forbidden',
            $component->error
        );
        $this->assertSame(
            'No tienes acceso a este espacio.',
            $component->message
        );
    }

    /**
     * Instancia el componente sin ejecutar su constructor
     * e inyecta el servicio simulado.
     *
     * @param class-string $component_class Componente.
     * @param ReportService $service Servicio.
     * @return object Componente preparado.
     */
    private function createComponent(
        string $component_class,
        ReportService $service
    ): object {
        $reflection = new ReflectionClass($component_class);
        $component = $reflection->newInstanceWithoutConstructor();

        $property = new ReflectionProperty(
            $component_class,
            'report_service'
        );
        $property->setValue($component, $service);

        return $component;
    }

    /**
     * Construye el DTO autenticado del endpoint.
     *
     * @param bool $evolution Si consulta un rango.
     * @param bool $missing Si falta un campo obligatorio.
     * @return GetMonthlyReportDTO|GetReportEvolutionDTO
     */
    private function createDto(
        bool $evolution,
        bool $missing = false
    ): GetMonthlyReportDTO|GetReportEvolutionDTO {
        $params = [
            'spaceId' => 25,
            'idUser' => 999
        ];

        if ($evolution) {
            $params['monthFrom'] = '2026-10';
            $params['monthTo'] = '2026-10';

            if ($missing) {
                unset($params['monthTo']);
            }
        }
        else {
            $params['month'] = '2026-10';

            if ($missing) {
                unset($params['month']);
            }
        }

        $request = new ORequest([
            'method' => 'GET',
            'headers' => [],
            'params' => $params
        ]);

        return $evolution
            ? new GetReportEvolutionDTO($request)
            : new GetMonthlyReportDTO($request);
    }

    /**
     * Argumentos que debe recibir el servicio.
     *
     * El identificador 10 procede del middleware,
     * no del idUser=999 enviado en la petición.
     *
     * @param bool $evolution Si consulta un rango.
     * @return array<int, int|string>
     */
    private function serviceArguments(bool $evolution): array {
        return $evolution
            ? [25, 10, '2026-10', '2026-10']
            : [25, 10, '2026-10'];
    }

    /**
     * Autentica un usuario de prueba.
     *
     * @param int $id Usuario.
     * @return void
     */
    private function authenticateAs(int $id): void {
        $service = $this->createMock(AuthService::class);

        $service->expects($this->once())
            ->method('authenticateToken')
            ->with('token-prueba')
            ->willReturn([
                'id' => $id,
                'name' => 'Usuario',
                'email' => 'usuario@example.test'
            ]);

        $GLOBALS['core']->services['Auth'] = $service;

        $result = OMiddleware::runPhase(
            OMiddleware::PHASE_BEFORE,
            [
                'headers' => [
                    'Authorization' => 'Bearer token-prueba'
                ]
            ]
        );

        $this->assertFalse($result['stop']);
    }

    /**
     * Datos representativos de los cinco servicios.
     *
     * @param string $method Método consultado.
     * @return array<string, mixed>
     */
    private function serviceResult(string $method): array {
        $monthly = [
            'space_id' => 25,
            'month' => '2026-10',
            'income' => '100.00',
            'expense' => '0.00',
            'balance' => '100.00',
            'income_count' => 1,
            'expense_count' => 0,
            'transaction_count' => 1
        ];

        $forecast = [
            'space_id' => 25,
            'month' => '2026-10',
            'actual' => $monthly,
            'pending_income' => '0.00',
            'pending_expense' => '25.00',
            'pending_balance' => '-25.00',
            'pending_income_count' => 0,
            'pending_expense_count' => 1,
            'pending_occurrence_count' => 1,
            'projected_income' => '100.00',
            'projected_expense' => '25.00',
            'projected_balance' => '75.00'
        ];

        return match ($method) {
            'getMonthly' => $monthly,
            'getMonthlyByCategory' => [
                'space_id' => 25,
                'month' => '2026-10',
                'categories' => [
                    [
                        'category_id' => 8,
                        'parent_id' => null,
                        'name' => 'Nómina',
                        'type' => 'income',
                        'amount' => '100.00',
                        'transaction_count' => 1
                    ]
                ]
            ],
            'getEvolution' => [
                'space_id' => 25,
                'month_from' => '2026-10',
                'month_to' => '2026-10',
                'months' => [$monthly]
            ],
            'getMonthlyForecast' => $forecast,
            'getForecastEvolution' => [
                'space_id' => 25,
                'month_from' => '2026-10',
                'month_to' => '2026-10',
                'months' => [$forecast]
            ],
            default => throw new LogicException(
                'Servicio no contemplado en la prueba.'
            )
        };
    }
}
