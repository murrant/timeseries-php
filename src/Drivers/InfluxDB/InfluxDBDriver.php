<?php

namespace TimeSeriesPhp\Drivers\InfluxDB;

use DateTime;
use InfluxDB2\Client;
use InfluxDB2\Model\BucketRetentionRules;
use InfluxDB2\Model\Buckets;
use InfluxDB2\Model\DeletePredicateRequest;
use InfluxDB2\Model\Organizations;
use InfluxDB2\Model\PostBucketRequest;
use InfluxDB2\Point;
use InfluxDB2\QueryApi;
use InfluxDB2\Service\BucketsService;
use InfluxDB2\Service\DeleteService;
use InfluxDB2\Service\OrganizationsService;
use InfluxDB2\WriteApi;
use Psr\Log\LoggerInterface;
use TimeSeriesPhp\Contracts\Driver\ConfigurableInterface;
use TimeSeriesPhp\Contracts\Query\RawQueryInterface;
use TimeSeriesPhp\Core\Attributes\Driver;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Core\Driver\AbstractTimeSeriesDB;
use TimeSeriesPhp\Drivers\InfluxDB\Query\InfluxDBQueryBuilder;
use TimeSeriesPhp\Exceptions\Driver\ConnectionException;
use TimeSeriesPhp\Exceptions\Driver\DatabaseException;
use TimeSeriesPhp\Exceptions\Driver\WriteException;
use TimeSeriesPhp\Exceptions\Query\RawQueryException;
use TimeSeriesPhp\Utils\Convert;

#[Driver(name: 'influxdb', queryBuilderClass: InfluxDBQueryBuilder::class, configClass: InfluxDBConfig::class)]
class InfluxDBDriver extends AbstractTimeSeriesDB implements ConfigurableInterface
{
    protected Client $client;

    protected ?WriteApi $writeApi = null;

    protected ?QueryApi $queryApi = null;

    protected ?BucketsService $bucketsService = null;

    protected ?string $orgId = null;

    protected string $bucket = '';

    /**
     * @var bool Whether the driver is connected
     */
    protected bool $connected = false;
    protected InfluxDBQueryBuilder $influxQueryBuilder;

    public function __construct(
        InfluxDBQueryBuilder $queryBuilder,
        LoggerInterface $logger,
        protected InfluxDBConfig $config,
        ?Client $client = null,
    ) {
        parent::__construct($queryBuilder, $logger);

        $this->client = $client ?? new Client($this->config->getClientConfig());
        $this->influxQueryBuilder = $queryBuilder;
    }

    /**
     * Configure the driver with the given configuration
     *
     * @param  array<string, mixed>  $config
     */
    public function configure(array $config): void
    {
        $this->config = $this->config->createFromArray($config);
    }

    protected function doConnect(): bool
    {
        try {
            // Use the injected client
            $this->client = $this->influxClient;
            $this->writeApi = $this->client->createWriteApi();
            $this->queryApi = $this->client->createQueryApi();
            $this->influxQueryBuilder->bucket = $this->config->bucket;

            // Test connection by pinging
            $ping = $this->client->ping();
            $this->connected = ! empty($ping);

            $this->logger->info('Connected to InfluxDB successfully', [
                'org' => $this->config->org,
                'bucket' => $this->config->bucket,
            ]);

            return $this->connected;
        } catch (\Throwable $e) {
            $this->logger->error('InfluxDB connection failed: '.$e->getMessage(), [
                'exception' => get_class($e),
                'org' => $this->config->org,
                'bucket' => $this->config->bucket,
            ]);
            $this->connected = false;

            throw new ConnectionException('Failed to connect to InfluxDB: '.$e->getMessage(), 0, $e);
        }
    }

    protected function formatDataPoint(DataPoint $dataPoint): Point
    {
        $point = Point::measurement($dataPoint->getMeasurement())
            ->time($dataPoint->getTimestamp());

        // Add tags
        foreach ($dataPoint->getTags() as $key => $value) {
            $point->addTag($key, $value);
        }

        // Add fields
        foreach ($dataPoint->getFields() as $key => $value) {
            if (is_numeric($value)) {
                $point->addField($key, Convert::toNumber($value));
            } else {
                $point->addField($key, $value);
            }
        }

        return $point;
    }

    protected function doWrite(DataPoint $dataPoint): bool
    {
        if (! $this->isConnected()) {
            throw new WriteException('Not connected to InfluxDB');
        }

        if ($this->writeApi === null) {
            throw new WriteException('WriteApi is not initialized');
        }

        try {
            $point = $this->formatDataPoint($dataPoint);
            $this->writeApi->write($point);
            $this->writeApi->close();

            return true;
        } catch (\Throwable $e) {
            throw new WriteException('Failed to write data point: '.$e->getMessage(), 0, $e);
        }
    }

    protected function doWriteBatch(array $dataPoints): bool
    {
        if (! $this->isConnected()) {
            throw new WriteException('Not connected to InfluxDB');
        }

        if ($this->writeApi === null) {
            throw new WriteException('WriteApi is not initialized');
        }

        try {
            $points = [];
            foreach ($dataPoints as $dataPoint) {
                $points[] = $this->formatDataPoint($dataPoint);
            }

            $this->writeApi->write($points);
            $this->writeApi->close();

            return true;
        } catch (\Throwable $e) {
            throw new WriteException('Failed to write batch data: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws RawQueryException
     */
    public function rawQuery(RawQueryInterface $query): QueryResult
    {
        if (! $this->isConnected()) {
            throw new RawQueryException($query, 'Not connected to InfluxDB');
        }

        if ($this->queryApi === null) {
            throw new RawQueryException($query, 'QueryApi is not initialized');
        }

        try {
            $queryResult = $this->queryApi->query($query->getRawQuery(), $this->config->org);
            $result = new QueryResult;

            if ($queryResult !== null) {
                foreach ($queryResult as $table) {
                    foreach ($table->records as $record) {
                        $values = (array) $record->values;
                        $timestamp = $values['_time'] ?? $values['time'] ?? time();

                        // Add all other values as dynamic keys
                        foreach ($values as $key => $value) {
                            if ($key !== '_time' && $key !== 'time' && $key !== '_measurement' && $key !== '_field') {
                                $result->appendPoint(Convert::toString($timestamp), $key, Convert::toScalar($value));
                            }
                        }
                    }
                }
            }

            return $result;
        } catch (\Throwable $e) {
            throw new RawQueryException($query, 'Query execution failed: '.$e->getMessage(), 0, $e);
        }
    }

    public function createDatabase(string $database): bool
    {
        try {
            $bucketsService = $this->getBucketsService();

            $rule = new BucketRetentionRules;
            $rule->setEverySeconds(0); // No expiration
            $bucketRequest = new PostBucketRequest;

            $bucketRequest->setName($database)
                ->setRetentionRules([$rule])
                ->setOrgId($this->getOrgId());
            $bucketsService->postBuckets($bucketRequest);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create bucket: '.$e->getMessage(), [
                'exception' => get_class($e),
                'database' => $database,
            ]);

            throw new DatabaseException('Failed to create database: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @return string[]
     */
    public function getDatabases(): array
    {
        try {
            $bucketsService = $this->getBucketsService();
            $buckets = $bucketsService->getBuckets(org_id: $this->getOrgId());
            if (! $buckets instanceof Buckets) {
                throw new DatabaseException('Invalid object returned from getBuckets');
            }

            $bucketNames = [];
            $bucketsList = $buckets->getBuckets();
            if ($bucketsList !== null) {
                foreach ($bucketsList as $bucket) {
                    $bucketNames[] = $bucket->getName();
                }
            }

            return $bucketNames;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to list buckets: '.$e->getMessage(), [
                'exception' => get_class($e),
            ]);

            throw new DatabaseException('Failed to list databases: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws ConnectionException
     */
    public function deleteMeasurement(string $measurement, ?DateTime $start = null, ?DateTime $stop = null): bool
    {
        if ($this->client === null) {
            throw new ConnectionException('Client is not initialized');
        }

        try {
            /** @var DeleteService $service */
            $service = $this->client->createService(DeleteService::class);
            $predicate = new DeletePredicateRequest;
            $predicate->setStart($start ?? new DateTime('1970-01-01T00:00:00Z'));
            $predicate->setStop($stop ?? new DateTime);
            $predicate->setPredicate("_measurement=\"{$measurement}\"");

            $service->postDelete($predicate, bucket: $this->config->bucket, org_id: $this->getOrgId());

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to delete measurement: '.$e->getMessage(), [
                'exception' => get_class($e),
                'measurement' => $measurement,
                'start' => $start ? $start->format('c') : null,
                'stop' => $stop ? $stop->format('c') : null,
            ]);

            throw new DatabaseException('Failed to delete measurement: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array{'status': 'success'|'fail', 'build': string, 'version': string}
     */
    public function getHealth(): array
    {
        try {
            if ($this->client === null) {
                throw new ConnectionException('Client is not initialized');
            }

            $health = $this->client->ping();

            // The return types from upstream give us a bit of trouble :/
            $build = 'Unknown';
            $version = 'Unknown';

            if (isset($health['X-Influxdb-Build']) && is_array($health['X-Influxdb-Build']) && isset($health['X-Influxdb-Build'][0])) {
                if (is_scalar($health['X-Influxdb-Build'][0])) {
                    $build = (string) $health['X-Influxdb-Build'][0];
                }
            }

            if (isset($health['X-Influxdb-Version']) && is_array($health['X-Influxdb-Version']) && isset($health['X-Influxdb-Version'][0])) {
                if (is_scalar($health['X-Influxdb-Version'][0])) {
                    $version = (string) $health['X-Influxdb-Version'][0];
                }
            }

            return [
                'status' => 'success',
                'build' => $build,
                'version' => $version,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'fail',
                'build' => $e->getMessage(),
                'version' => 'Unknown',
            ];
        }
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     */
    private function getOrgId(): string
    {
        if ($this->orgId === null) {
            if ($this->client === null) {
                throw new ConnectionException('Client is not initialized');
            }

            $orgService = $this->client->createService(OrganizationsService::class);
            if (! $orgService instanceof OrganizationsService) {
                throw new DatabaseException('Failed to create OrganizationsService');
            }

            $organizations = $orgService->getOrgs();
            if (! $organizations instanceof Organizations) {
                throw new DatabaseException('Invalid object returned from getOrgs');
            }

            $orgs = $organizations->getOrgs();

            if ($orgs !== null) {
                foreach ($orgs as $org) {
                    if ($org->getName() == $this->config->org) {
                        $this->orgId = $org->getId() ?? '';

                        return $this->orgId;
                    }
                }
            }

            $this->orgId = '';
        }

        return $this->orgId;
    }

    /**
     * @throws ConnectionException
     * @throws DatabaseException
     */
    private function getBucketsService(): BucketsService
    {
        if ($this->bucketsService === null) {
            if ($this->client === null) {
                throw new ConnectionException('Client is not initialized');
            }

            $service = $this->client->createService(BucketsService::class);

            if (! $service instanceof BucketsService) {
                throw new DatabaseException('Failed to create BucketsService');
            }

            $this->bucketsService = $service;
        }

        return $this->bucketsService;
    }

    public function close(): void
    {
        $this->writeApi?->close();
        $this->client = null;
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function __destruct()
    {
        $this->close();
    }
}
