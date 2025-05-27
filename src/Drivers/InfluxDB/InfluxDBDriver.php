<?php

namespace TimeSeriesPhp\Drivers\InfluxDB;

use DateTime;
use Exception;
use InfluxDB2\Client;
use InfluxDB2\Model\BucketRetentionRules;
use InfluxDB2\Model\DeletePredicateRequest;
use InfluxDB2\Model\PostBucketRequest;
use InfluxDB2\Point;
use InfluxDB2\QueryApi;
use InfluxDB2\Service\BucketsService;
use InfluxDB2\Service\DeleteService;
use InfluxDB2\Service\OrganizationsService;
use InfluxDB2\WriteApi;
use TimeSeriesPhp\Core\AbstractTimeSeriesDB;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\QueryResult;
use TimeSeriesPhp\Core\RawQueryContract;
use TimeSeriesPhp\Exceptions\ConfigurationException;
use TimeSeriesPhp\Exceptions\ConnectionException;
use TimeSeriesPhp\Exceptions\QueryException;

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
            throw new ConnectionException('Not connected to InfluxDB');
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
            throw new ConnectionException('Not connected to InfluxDB');
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

        try {
            $result1 = $this->queryApi->query($query->getRawQuery(), $this->org);
            $data = [];

            foreach ($result1 as $table) {
                foreach ($table->records as $record) {
                    $values = $record->values;
                    $data[] = $values;
                }
            }

            $executeQuery = $data;
        } catch (Exception $e) {
            throw new QueryException($query, 'Query execution failed: '.$e->getMessage());
        }
        $result = $executeQuery;

        return new QueryResult($result);
    }

    public function createDatabase(string $database): bool
    {
        try {
            $this->bucketsService ??= $this->client->createService(BucketsService::class);

            $rule = new BucketRetentionRules;
            $rule->setEverySeconds(0); // No expiration
            $bucketRequest = new PostBucketRequest;

            $bucketRequest->setName($database)
                ->setRetentionRules([$rule])
                ->setOrgId($this->getOrgId());
            $this->bucketsService->postBuckets($bucketRequest);

            return true;
        } catch (Exception $e) {
            error_log('Failed to create bucket: '.$e->getMessage());

            return false;
        }
    }

    public function listDatabases(): array
    {
        try {
            $this->bucketsService ??= $this->client->createService(BucketsService::class);
            $buckets = $this->bucketsService->getBuckets(org_id: $this->getOrgId());

            $bucketNames = [];
            foreach ($buckets->getBuckets() as $bucket) {
                $bucketNames[] = $bucket->getName();
            }

            return $bucketNames;
        } catch (Exception $e) {
            error_log('Failed to list buckets: '.$e->getMessage());

            return [];
        }
    }

    public function deleteMeasurement(string $measurement, ?DateTime $start = null, ?DateTime $stop = null): bool
    {
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

    public function getHealth(): array
    {
        try {
            $health = $this->client->health();

            return [
                'status' => $health->getStatus(),
                'message' => $health->getMessage(),
                'version' => $health->getVersion(),
            ];
        } catch (Exception $e) {
            return [
                'status' => 'fail',
                'message' => $e->getMessage(),
                'version' => null,
            ];
        }
    }

    private function getOrgId(): string
    {
        if ($this->orgId === null) {
            $orgService = $this->client->createService(OrganizationsService::class);
            $orgs = $orgService->getOrgs()->getOrgs();

            foreach ($orgs as $org) {
                if ($org->getName() == $this->org) {
                    $this->orgId = $org->getId();

                    return $this->orgId;
                }
            }

            $this->orgId = '';
        }

        return $this->orgId;
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
