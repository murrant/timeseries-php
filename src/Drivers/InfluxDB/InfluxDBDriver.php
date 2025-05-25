<?php

namespace TimeSeriesPhp\Drivers\InfluxDB;

use InfluxDB2\Client;
use InfluxDB2\Model\WritePrecision;
use InfluxDB2\Point;
use InfluxDB2\WriteApi;
use InfluxDB2\QueryApi;
use TimeSeriesPhp\Core\AbstractTimeSeriesDB;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\QueryResult;
use TimeSeriesPhp\Core\Query;
use Exception;

class InfluxDBDriver extends AbstractTimeSeriesDB
{
    private ?Client $client = null;
    private ?WriteApi $writeApi = null;
    private ?QueryApi $queryApi = null;
    
    private string $url;
    private string $token;
    private string $org;
    private string $bucket;
    private array $options;

    public function __construct(
        string $url,
        string $token,
        string $org,
        string $bucket,
        array $options = []
    ) {
        $this->url = $url;
        $this->token = $token;
        $this->org = $org;
        $this->bucket = $bucket;
        $this->options = array_merge([
            'timeout' => 30,
            'verify_ssl' => true,
            'debug' => false
        ], $options);
    }

    protected function doConnect(): bool
    {
        try {
            $this->client = new Client([
                "url" => $this->url,
                "token" => $this->token,
                "bucket" => $this->bucket,
                "org" => $this->org,
                "precision" => WritePrecision::NS,
                "timeout" => $this->options['timeout'],
                "verifySSL" => $this->options['verify_ssl'],
                "debug" => $this->options['debug']
            ]);

            $this->writeApi = $this->client->createWriteApi();
            $this->queryApi = $this->client->createQueryApi();

            // Test connection by pinging
            $health = $this->client->health();
            $this->connected = $health->getStatus() === 'pass';
            
            return $this->connected;
        } catch (Exception $e) {
            error_log("InfluxDB connection failed: " . $e->getMessage());
            $this->connected = false;
            return false;
        }
    }

    protected function buildQuery(Query $query): string
    {
        $fields = $query->getFields();
        $measurement = $query->getMeasurement();
        
        // Build Flux query (InfluxDB 2.x uses Flux, not InfluxQL)
        $fluxQuery = "from(bucket: \"{$this->bucket}\")\n";
        
        // Add time range
        if ($query->getStartTime() && $query->getEndTime()) {
            $start = $query->getStartTime()->format('c');
            $stop = $query->getEndTime()->format('c');
            $fluxQuery .= "  |> range(start: {$start}, stop: {$stop})\n";
        } elseif ($query->getStartTime()) {
            $start = $query->getStartTime()->format('c');
            $fluxQuery .= "  |> range(start: {$start})\n";
        } else {
            // Default to last hour if no time range specified
            $fluxQuery .= "  |> range(start: -1h)\n";
        }
        
        // Filter by measurement
        if ($measurement) {
            $fluxQuery .= "  |> filter(fn: (r) => r._measurement == \"{$measurement}\")\n";
        }
        
        // Add tag filters
        foreach ($query->getTags() as $tag => $value) {
            $fluxQuery .= "  |> filter(fn: (r) => r.{$tag} == \"{$value}\")\n";
        }
        
        // Filter by fields if specified
        if (!empty($fields) && !in_array('*', $fields)) {
            $fieldConditions = array_map(function($field) {
                return "r._field == \"{$field}\"";
            }, $fields);
            $fieldCondition = implode(' or ', $fieldConditions);
            $fluxQuery .= "  |> filter(fn: (r) => {$fieldCondition})\n";
        }
        
        // Add aggregation with windowing if specified
        if ($query->getAggregation()) {
            $aggregation = strtolower($query->getAggregation());
            $interval = $query->getInterval();
            
            if ($interval) {
                // Convert interval to Flux duration format
                $duration = $this->convertIntervalToDuration($interval);
                $fluxQuery .= "  |> aggregateWindow(every: {$duration}, fn: {$aggregation}, createEmpty: false)\n";
            } else {
                // Apply aggregation without windowing
                switch ($aggregation) {
                    case 'mean':
                    case 'avg':
                        $fluxQuery .= "  |> mean()\n";
                        break;
                    case 'sum':
                        $fluxQuery .= "  |> sum()\n";
                        break;
                    case 'count':
                        $fluxQuery .= "  |> count()\n";
                        break;
                    case 'min':
                        $fluxQuery .= "  |> min()\n";
                        break;
                    case 'max':
                        $fluxQuery .= "  |> max()\n";
                        break;
                    case 'first':
                        $fluxQuery .= "  |> first()\n";
                        break;
                    case 'last':
                        $fluxQuery .= "  |> last()\n";
                        break;
                    case 'stddev':
                        $fluxQuery .= "  |> stddev()\n";
                        break;
                    default:
                        // For custom or unsupported aggregations, try to use them directly
                        $fluxQuery .= "  |> {$aggregation}()\n";
                }
            }
        }
        
        // Add grouping
        if (!empty($query->getGroupBy())) {
            $groupCols = array_map(function($col) {
                return "\"{$col}\"";
            }, $query->getGroupBy());
            $fluxQuery .= "  |> group(columns: [" . implode(', ', $groupCols) . "])\n";
        }
        
        // Add ordering (sort)
        if (!empty($query->getOrderBy())) {
            foreach ($query->getOrderBy() as $field => $direction) {
                $desc = strtoupper($direction) === 'DESC' ? 'true' : 'false';
                $fluxQuery .= "  |> sort(columns: [\"{$field}\"], desc: {$desc})\n";
            }
        }
        
        // Add limit
        if ($query->getLimit()) {
            $fluxQuery .= "  |> limit(n: {$query->getLimit()})\n";
        }
        
        return $fluxQuery;
    }

    protected function executeQuery(string $query): array
    {
        if (!$this->isConnected()) {
            throw new Exception("Not connected to InfluxDB");
        }

        try {
            $result = $this->queryApi->query($query, $this->org);
            $data = [];
            
            foreach ($result as $table) {
                foreach ($table->records as $record) {
                    $values = $record->values;
                    $data[] = $values;
                }
            }
            
            return $data;
        } catch (Exception $e) {
            throw new Exception("Query execution failed: " . $e->getMessage());
        }
    }

    protected function formatDataPoint(DataPoint $dataPoint): Point
    {
        $point = Point::measurement($dataPoint->getMeasurement())
            ->time($dataPoint->getTimestamp(), WritePrecision::NS);
        
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
        if (!$this->isConnected()) {
            throw new Exception("Not connected to InfluxDB");
        }

        try {
            $point = $this->formatDataPoint($dataPoint);
            $this->writeApi->write($point);
            $this->writeApi->close();
            return true;
        } catch (Exception $e) {
            error_log("InfluxDB write failed: " . $e->getMessage());
            return false;
        }
    }

    public function writeBatch(array $dataPoints): bool
    {
        if (!$this->isConnected()) {
            throw new Exception("Not connected to InfluxDB");
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
            error_log("InfluxDB batch write failed: " . $e->getMessage());
            return false;
        }
    }

    public function rawQuery(string $query): QueryResult
    {
        $result = $this->executeQuery($query);
        return new QueryResult($result);
    }

    public function createDatabase(string $database): bool
    {
        // In InfluxDB 2.x, we create buckets instead of databases
        try {
            $bucketsApi = $this->client->createBucketsApi();
            $bucket = $bucketsApi->createBucket([
                'name' => $database,
                'orgID' => $this->getOrgId(),
                'retentionRules' => [
                    ['type' => 'expire', 'everySeconds' => 0] // No expiration
                ]
            ]);
            return $bucket !== null;
        } catch (Exception $e) {
            error_log("Failed to create bucket: " . $e->getMessage());
            return false;
        }
    }

    public function listDatabases(): array
    {
        // In InfluxDB 2.x, we list buckets instead of databases
        try {
            $bucketsApi = $this->client->createBucketsApi();
            $buckets = $bucketsApi->findBuckets();
            
            $bucketNames = [];
            foreach ($buckets->getBuckets() as $bucket) {
                $bucketNames[] = $bucket->getName();
            }
            
            return $bucketNames;
        } catch (Exception $e) {
            error_log("Failed to list buckets: " . $e->getMessage());
            return [];
        }
    }

    public function deleteMeasurement(string $measurement, \DateTime $start = null, \DateTime $stop = null): bool
    {
        try {
            $deleteApi = $this->client->createDeleteApi();
            
            $startTime = $start ? $start->format('c') : '1970-01-01T00:00:00Z';
            $stopTime = $stop ? $stop->format('c') : (new \DateTime())->format('c');
            
            $deleteApi->delete(
                $startTime,
                $stopTime,
                "_measurement=\"{$measurement}\"",
                $this->bucket,
                $this->org
            );
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to delete measurement: " . $e->getMessage());
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
                'version' => $health->getVersion()
            ];
        } catch (Exception $e) {
            return [
                'status' => 'fail',
                'message' => $e->getMessage(),
                'version' => null
            ];
        }
    }

    private function getOrgId(): string
    {
        static $orgId = null;
        
        if ($orgId === null) {
            $orgsApi = $this->client->createOrganizationsApi();
            $orgs = $orgsApi->findOrganizations();
            
            foreach ($orgs->getOrgs() as $org) {
                if ($org->getName() === $this->org) {
                    $orgId = $org->getId();
                    break;
                }
            }
        }
        
        return $orgId ?? '';
    }

    public function close(): void
    {
        if ($this->writeApi) {
            $this->writeApi->close();
        }
        
        if ($this->client) {
            $this->client = null;
        }
        
        $this->connected = false;
    }

    /**
     * Convert interval string to Flux duration format
     * Examples: '1h' -> '1h', '30m' -> '30m', '5s' -> '5s', '1d' -> '1d'
     */
    private function convertIntervalToDuration(string $interval): string
    {
        // If already in Flux format, return as-is
        if (preg_match('/^\d+[smhd]$/', $interval)) {
            return $interval;
        }
        
        // Convert common formats
        $interval = strtolower($interval);
        $conversions = [
            'second' => 's',
            'seconds' => 's',
            'sec' => 's',
            'minute' => 'm',
            'minutes' => 'm',
            'min' => 'm',
            'hour' => 'h',
            'hours' => 'h',
            'day' => 'd',
            'days' => 'd',
        ];
        
        foreach ($conversions as $from => $to) {
            if (strpos($interval, $from) !== false) {
                $number = (int) filter_var($interval, FILTER_SANITIZE_NUMBER_INT);
                return $number . $to;
            }
        }
        
        // Default fallback - assume it's already in correct format
        return $interval;
    }

    public function __destruct()
    {
        $this->close();
    }
}
