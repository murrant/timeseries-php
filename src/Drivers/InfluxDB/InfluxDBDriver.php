<?php

namespace TimeSeriesPhp\Drivers\InfluxDB;

use DateTime;
use Exception;
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
use RuntimeException;
use TimeSeriesPhp\Core\AbstractTimeSeriesDB;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\QueryResult;
use TimeSeriesPhp\Core\RawQueryContract;
use TimeSeriesPhp\Exceptions\ConfigurationException;
use TimeSeriesPhp\Exceptions\ConnectionException;
use TimeSeriesPhp\Exceptions\QueryException;
use TimeSeriesPhp\Exceptions\WriteException;

class InfluxDBDriver extends AbstractTimeSeriesDB
{
    protected ?Client $client = null;

    protected ?WriteApi $writeApi = null;

    protected ?QueryApi $queryApi = null;

    protected ?BucketsService $bucketsService = null;

    protected string $org;

    protected ?string $orgId = null;

    protected string $bucket;

    protected function doConnect(): bool
    {
        if (! $this->config instanceof InfluxDBConfig) {
            throw new ConfigurationException('Invalid configuration type. Expected InfluxDBConfig.');
        }

        try {
            $this->client = new Client($this->config->getClientConfig());
            $this->writeApi = $this->client->createWriteApi();
            $this->queryApi = $this->client->createQueryApi();

            // Store config values for easier access
            $this->org = $this->config->get('org');
            $this->bucket = $this->config->get('bucket');

            $this->queryBuilder = new InfluxDBQueryBuilder($this->bucket);

            // Test connection by pinging
            $ping = $this->client->ping();
            $this->connected = ! empty($ping);

            return $this->connected;
        } catch (Exception $e) {
            error_log('InfluxDB connection failed: '.$e->getMessage());
            $this->connected = false;

            return false;
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
                if (is_int($value)) {
                    $point->addField($key, $value);
                } else {
                    $point->addField($key, floatval($value));
                }
            } elseif (is_bool($value)) {
                $point->addField($key, $value);
            } else {
                $point->addField($key, strval($value));
            }
        }

        return $point;
    }

    public function write(DataPoint $dataPoint): bool
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
        } catch (Exception $e) {
            error_log('InfluxDB write failed: '.$e->getMessage());

            return false;
        }
    }

    public function writeBatch(array $dataPoints): bool
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
        } catch (Exception $e) {
            error_log('InfluxDB batch write failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * @throws QueryException
     */
    public function rawQuery(RawQueryContract $query): QueryResult
    {
        if (! $this->isConnected()) {
            throw new QueryException($query, 'Not connected to InfluxDB');
        }

        if ($this->queryApi === null) {
            throw new QueryException($query, 'QueryApi is not initialized');
        }

        try {
            $queryResult = $this->queryApi->query($query->getRawQuery(), $this->org);
            $data = [];

            if ($queryResult !== null) {
                foreach ($queryResult as $table) {
                    foreach ($table->records as $record) {
                        $values = $record->values;
                        $data[] = $values;
                    }
                }
            }

            return new QueryResult($data);
        } catch (Exception $e) {
            throw new QueryException($query, 'Query execution failed: '.$e->getMessage());
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
        } catch (Exception $e) {
            error_log('Failed to create bucket: '.$e->getMessage());

            return false;
        }
    }

    /**
     * @return string[]
     */
    public function listDatabases(): array
    {
        try {
            $bucketsService = $this->getBucketsService();
            $buckets = $bucketsService->getBuckets(org_id: $this->getOrgId());
            if (! $buckets instanceof Buckets) {
                throw new RuntimeException('Invalid object returned from getBuckets');
            }

            $bucketNames = [];
            $bucketsList = $buckets->getBuckets();
            if ($bucketsList !== null) {
                foreach ($bucketsList as $bucket) {
                    $bucketNames[] = $bucket->getName();
                }
            }

            return $bucketNames;
        } catch (Exception $e) {
            error_log('Failed to list buckets: '.$e->getMessage());

            return [];
        }
    }

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

            $service->postDelete($predicate, bucket: $this->bucket, org_id: $this->getOrgId());

            return true;
        } catch (Exception $e) {
            error_log('Failed to delete measurement: '.$e->getMessage());

            return false;
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

            return [
                'status' => 'success',
                'build' => $health['X-Influxdb-Build'][0] ?? 'Unknown',
                'version' => $health['X-Influxdb-Version'][0] ?? 'Unknown',
            ];
        } catch (Exception $e) {
            return [
                'status' => 'fail',
                'build' => $e->getMessage(),
                'version' => 'Unknown',
            ];
        }
    }

    private function getOrgId(): string
    {
        if ($this->orgId === null) {
            if ($this->client === null) {
                throw new ConnectionException('Client is not initialized');
            }

            $orgService = $this->client->createService(OrganizationsService::class);
            if (! $orgService instanceof OrganizationsService) {
                throw new RuntimeException('Failed to create OrganizationsService');
            }

            $organizations = $orgService->getOrgs();
            if (! $organizations instanceof Organizations) {
                throw new RuntimeException('Invalid object returned from getOrgs');
            }

            $orgs = $organizations->getOrgs();

            if ($orgs !== null) {
                foreach ($orgs as $org) {
                    if ($org->getName() == $this->org) {
                        $this->orgId = $org->getId() ?? '';

                        return $this->orgId;
                    }
                }
            }

            $this->orgId = '';
        }

        return $this->orgId;
    }

    private function getBucketsService(): BucketsService
    {
        if ($this->bucketsService === null) {
            if ($this->client === null) {
                throw new ConnectionException('Client is not initialized');
            }

            $service = $this->client->createService(BucketsService::class);

            if (! $service instanceof BucketsService) {
                throw new RuntimeException('Failed to create BucketsService');
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
