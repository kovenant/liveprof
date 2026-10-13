<?php

declare(strict_types=1);

namespace Badoo\LiveProfiler;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Tools\DsnParser;
use Psr\Log\LoggerInterface;

/**
 * Profiles a sample of the requests with xhprof, tideways_xhprof or the built-in SimpleProfiler and
 * saves the profiles into the "details" table of a database.
 */
class LiveProfiler
{
    /**
     * URL schemes of the connection url and their drivers, as DriverManager resolved them before the DSN parser.
     *
     * @var array<string, string>
     */
    private const DSN_SCHEME_MAP = [
        'db2' => 'ibm_db2',
        'mssql' => 'pdo_sqlsrv',
        'mysql' => 'pdo_mysql',
        'mysql2' => 'pdo_mysql',
        'postgres' => 'pdo_pgsql',
        'postgresql' => 'pdo_pgsql',
        'pgsql' => 'pdo_pgsql',
        'sqlite' => 'pdo_sqlite',
        'sqlite3' => 'pdo_sqlite',
    ];

    protected static ?self $instance = null;

    protected ?Connection $connection = null;
    protected LoggerInterface $logger;
    protected DataPackerInterface $dataPacker;
    protected string $connectionString;
    protected string $app = 'Default';
    protected string $label;
    protected string $datetime;
    /** Every $divider-th request with the same app and label is profiled. */
    protected int $divider = 1000;
    /** Every $totalDivider-th request is profiled with the label "All"; 0 (the default) disables it. */
    protected int $totalDivider = 0;
    protected ?\Closure $startCallback = null;
    protected ?\Closure $endCallback = null;
    protected bool $isEnabled = false;
    /** Start of the xhprof sampling as a unix timestamp with microseconds. */
    protected float $samplingStartedAt = 0.0;
    /** @var array<string, mixed> */
    protected array $lastProfileData = [];

    /**
     * @param string $connectionString database url; the LIVE_PROFILER_CONNECTION_URL environment variable is used when empty
     */
    public function __construct(string $connectionString = '')
    {
        $this->connectionString = $connectionString ?: self::env('LIVE_PROFILER_CONNECTION_URL');
        $this->label = $this->getAutoLabel();
        $this->datetime = date('Y-m-d H:i:s');
        $this->logger = new Logger();
        $this->dataPacker = new DataPacker();
        $this->detectProfiler();
    }

    public static function getInstance(string $connectionString = ''): self
    {
        // @phpstan-ignore new.static (a subclass with another constructor signature has to override getInstance())
        return self::$instance ??= new static($connectionString);
    }

    /**
     * Starts profiling for one of $divider requests, or for one of $totalDivider requests with the label "All"
     * when the total divider is set.
     * The profile is saved by end(), which is also registered as a shutdown function.
     */
    public function start(): bool
    {
        if ($this->isEnabled || null === $this->startCallback) {
            return true;
        }

        if ($this->needToStart($this->divider)) {
            $this->isEnabled = true;
        } elseif ($this->needToStart($this->totalDivider)) {
            $this->isEnabled = true;
            $this->label = 'All';
        }

        if ($this->isEnabled) {
            register_shutdown_function([$this, 'end']);
            ($this->startCallback)();
        }

        return true;
    }

    /**
     * Stops profiling and saves the profile. Returns false when the profiler returned no data or saving failed.
     */
    public function end(): bool
    {
        if (!$this->isEnabled) {
            return true;
        }

        $this->isEnabled = false;
        if (null === $this->endCallback) {
            return true;
        }

        /** @var array<string, mixed> $data */
        $data = ($this->endCallback)();
        if (!\is_array($data)) {
            $this->logger->warning('Invalid profiler data: ' . var_export($data, true));

            return false;
        }

        if ([] === $data) {
            return false;
        }

        $this->lastProfileData = $data;

        try {
            $result = $this->save($this->app, $this->label, $this->datetime, $data);
        } catch (\Throwable $e) {
            $this->logger->error('Error in saving profile data: ' . $e->getMessage());

            return false;
        }

        if (!$result) {
            $this->logger->warning('Can\'t insert profile data');
        }

        return $result;
    }

    /**
     * Uses xhprof, tideways_xhprof or the SimpleProfiler, whichever is available.
     */
    public function detectProfiler(): static
    {
        if (\function_exists('xhprof_enable')) {
            return $this->useXhprof();
        }

        if (\function_exists('tideways_xhprof_enable')) {
            return $this->useTidyWays();
        }

        return $this->useSimpleProfiler();
    }

    public function useXhprof(): static
    {
        if (!$this->canChangeProfiler()) {
            return $this;
        }

        $this->startCallback = static function (): void {
            xhprof_enable(\XHPROF_FLAGS_MEMORY | \XHPROF_FLAGS_CPU);
        };
        $this->endCallback = static fn (): array => xhprof_disable();

        return $this;
    }

    /**
     * Uses xhprof in the sampling mode: the call stack is recorded every xhprof.sampling_interval microseconds
     * (10000 by default) up to xhprof.sampling_depth frames (200 by default).
     */
    public function useXhprofSample(): static
    {
        if (!$this->canChangeProfiler()) {
            return $this;
        }

        if (!\ini_get('xhprof.sampling_interval')) {
            ini_set('xhprof.sampling_interval', '10000');
        }

        if (!\ini_get('xhprof.sampling_depth')) {
            ini_set('xhprof.sampling_depth', '200');
        }

        $this->startCallback = function (): void {
            $this->samplingStartedAt = microtime(true);
            xhprof_sample_enable();
        };
        $this->endCallback = fn (): array => $this->convertSampleDataToCommonFormat(
            xhprof_sample_disable(),
            $this->samplingStartedAt
        );

        return $this;
    }

    /**
     * Uses the Tideways XHProf extension.
     */
    public function useTidyWays(): static
    {
        if (!$this->canChangeProfiler()) {
            return $this;
        }

        $this->startCallback = static function (): void {
            tideways_xhprof_enable(\TIDEWAYS_XHPROF_FLAGS_MEMORY | \TIDEWAYS_XHPROF_FLAGS_CPU);
        };
        $this->endCallback = static fn (): array => tideways_xhprof_disable();

        return $this;
    }

    public function useSimpleProfiler(): static
    {
        if (!$this->canChangeProfiler()) {
            return $this;
        }

        $this->startCallback = static function (): void {
            SimpleProfiler::getInstance()->enable();
        };
        $this->endCallback = static fn (): array => SimpleProfiler::getInstance()->disable();

        return $this;
    }

    /**
     * Stops profiling without saving the profile.
     */
    public function reset(): bool
    {
        if ($this->isEnabled) {
            $this->isEnabled = false;
            if (null !== $this->endCallback) {
                ($this->endCallback)();
            }
        }

        return true;
    }

    public function setApp(string $app): static
    {
        $this->app = $app;

        return $this;
    }

    public function getApp(): string
    {
        return $this->app;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setDateTime(string $datetime): static
    {
        $this->datetime = $datetime;

        return $this;
    }

    public function getDateTime(): string
    {
        return $this->datetime;
    }

    public function setDivider(int $divider): static
    {
        $this->divider = $divider;

        return $this;
    }

    /**
     * @param int $totalDivider 0 disables the profiling with the label "All"
     */
    public function setTotalDivider(int $totalDivider): static
    {
        $this->totalDivider = $totalDivider;

        return $this;
    }

    /**
     * @param \Closure $startCallback starts the profiler
     */
    public function setStartCallback(\Closure $startCallback): static
    {
        $this->startCallback = $startCallback;

        return $this;
    }

    /**
     * @param \Closure $endCallback stops the profiler and returns the profile as "caller==>callee" => metrics
     */
    public function setEndCallback(\Closure $endCallback): static
    {
        $this->endCallback = $endCallback;

        return $this;
    }

    public function setLogger(LoggerInterface $logger): static
    {
        $this->logger = $logger;

        return $this;
    }

    public function setDataPacker(DataPackerInterface $dataPacker): static
    {
        $this->dataPacker = $dataPacker;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLastProfileData(): array
    {
        return $this->lastProfileData;
    }

    public function setConnection(Connection $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    public function setConnectionString(string $connectionString): static
    {
        $this->connectionString = $connectionString;

        return $this;
    }

    /**
     * Creates the "details" table with the SQL script for the database platform of the connection.
     *
     * @throws DBALException
     */
    public function createTable(): bool
    {
        $connection = $this->getConnection();
        $dialect = $this->detectSqlDialect($connection);
        if (null === $dialect) {
            $this->logger->error('Unsupported database platform: ' . get_debug_type($connection->getDatabasePlatform()));

            return false;
        }

        $sql = file_get_contents(\dirname(__DIR__) . '/bin/install_data/' . $dialect . '/source.sql');
        if (false === $sql) {
            $this->logger->error('Can\'t read the SQL script for ' . $dialect);

            return false;
        }

        $connection->executeStatement($sql);

        return true;
    }

    /**
     * @throws DBALException
     */
    protected function getConnection(): Connection
    {
        // @phpstan-ignore argument.type (the parameters come from the DSN parser, or it is the "url" of doctrine/dbal < 3.6)
        return $this->connection ??= DriverManager::getConnection($this->getConnectionParams());
    }

    /**
     * Inserts the profile into the "details" table.
     *
     * @param array<string, mixed> $data
     *
     * @throws \JsonException
     * @throws DBALException
     */
    protected function save(string $app, string $label, string $datetime, array $data): bool
    {
        return (bool)$this->getConnection()->insert('details', [
            'app' => $app,
            'label' => $label,
            'perfdata' => $this->dataPacker->pack($data),
            'timestamp' => $datetime,
        ]);
    }

    /**
     * The path of the request uri, or the script name in cli.
     */
    protected function getAutoLabel(): string
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? null;
        if (\is_string($requestUri) && '' !== $requestUri) {
            $path = parse_url($requestUri, \PHP_URL_PATH);

            return \is_string($path) && '' !== $path ? $path : $requestUri;
        }

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? null;

        return \is_string($scriptName) ? $scriptName : '';
    }

    protected function needToStart(int $divider): bool
    {
        return $divider > 0 && 1 === random_int(1, $divider);
    }

    /**
     * Converts the output of xhprof_sample_disable() (sample time => "main()==>f1==>f2" call stack)
     * into the "caller==>callee" => [ct, wt] format of xhprof_disable().
     *
     * @param array<array-key, string> $samplingData
     *
     * @return array<string, array{ct: int, wt: int}>
     */
    protected function convertSampleDataToCommonFormat(array $samplingData, float $samplingStartedAt): array
    {
        $result = [];
        $previousTime = $samplingStartedAt;
        foreach ($samplingData as $time => $callStack) {
            $time = (float)$time;
            $wallTime = (int)(($time - $previousTime) * 1000000);
            $previousTime = $time;

            $functions = explode('==>', $callStack);
            $key = $functions[0];
            foreach ($functions as $i => $function) {
                if ($i > 0) {
                    $key = $functions[$i - 1] . '==>' . $function;
                }

                $result[$key] ??= ['ct' => 0, 'wt' => 0];
                ++$result[$key]['ct'];
                $result[$key]['wt'] += $wallTime;
            }
        }

        return $result;
    }

    private function canChangeProfiler(): bool
    {
        if ($this->isEnabled) {
            $this->logger->warning('can\'t change profiler after profiling started');

            return false;
        }

        return true;
    }

    /**
     * doctrine/dbal 3.6 introduced the DSN parser and 4.0 removed the "url" connection parameter.
     *
     * @return array<string, mixed>
     */
    private function getConnectionParams(): array
    {
        if (class_exists(DsnParser::class)) {
            /** @var array<string, mixed> $params */
            $params = (new DsnParser(self::DSN_SCHEME_MAP))->parse($this->connectionString);

            return $params;
        }

        return ['url' => $this->connectionString];
    }

    /**
     * Name of the directory in bin/install_data with the SQL script for the platform of the connection.
     * The platform is checked instead of the driver because a driver may be wrapped in middlewares, and the
     * MySQLPlatform check matches the MySqlPlatform base class of doctrine/dbal 2 too.
     */
    private function detectSqlDialect(Connection $connection): ?string
    {
        $platform = $connection->getDatabasePlatform();

        return match (true) {
            $platform instanceof MySQLPlatform, $platform instanceof AbstractMySQLPlatform => 'mysql',
            $platform instanceof PostgreSQLPlatform => 'pgsql',
            $platform instanceof SQLitePlatform => 'sqlite',
            default => null,
        };
    }

    private static function env(string $name): string
    {
        $value = getenv($name);

        return false === $value ? '' : $value;
    }
}
