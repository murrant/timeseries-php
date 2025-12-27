<?php

namespace TimeseriesPhp\Driver\InfluxDB2;

use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use PsrDiscovery\Discover;
use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Contracts\TsdbClient;
use TimeseriesPhp\Core\Exceptions\TimeseriesException;
use TimeseriesPhp\Core\Timeseries\DataPoint;
use TimeseriesPhp\Core\Timeseries\TimeSeries;
use TimeseriesPhp\Core\Timeseries\TimeSeriesResult;

class InfluxClient implements TsdbClient
{
    private readonly ClientInterface $httpClient;

    private readonly RequestFactoryInterface $requestFactory;

    private readonly StreamFactoryInterface $streamFactory;

    public function __construct(
        private readonly InfluxConfig $config,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        $this->httpClient = $httpClient ?? Discover::httpClient();
        $this->requestFactory = $requestFactory ?? Discover::httpRequestFactory();
        $this->streamFactory = $streamFactory ?? Discover::httpStreamFactory();
    }

    public function query(CompiledQuery $query): TimeSeriesResult
    {
        if (! $query instanceof InfluxQuery) {
            throw new InvalidArgumentException('Query must be an instance of InfluxQuery');
        }

        $url = $this->config->host.':'.$this->config->port.'/api/v2/query?'.http_build_query([
            'org' => $this->config->org,
        ]);

        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Authorization', 'Token '.$this->config->token)
            ->withHeader('Content-Type', 'application/vnd.flux')
            ->withBody($this->streamFactory->createStream((string) $query));

        try {
            $response = $this->httpClient->sendRequest($request);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                throw new TimeseriesException(
                    sprintf(
                        'Failed to query InfluxDB2. Status code: %d. Response: %s',
                        $response->getStatusCode(),
                        $response->getBody()->getContents()
                    )
                );
            }

            return $this->parseResponse($response->getBody()->getContents(), $query);
        } catch (ClientExceptionInterface $e) {
            throw new TimeseriesException('HTTP error while querying InfluxDB2: '.$e->getMessage(), 0, $e);
        }
    }

    private function parseResponse(string $csv, InfluxQuery $query): TimeSeriesResult
    {
        // InfluxDB returns CSV. We need to parse it.
        // The CSV format is annotated.
        // Example:
        // ,result,table,_start,_stop,_time,_value,_field,_measurement,host
        // ,_result,0,2023-01-01T00:00:00Z,2023-01-01T01:00:00Z,2023-01-01T00:00:00Z,10,value,cpu,server1

        $lines = explode("\n", $csv);
        $seriesData = [];

        // We need to group by series (table).
        // In Flux, each table result corresponds to a unique combination of tags.
        // The 'result' column (usually _result) or 'table' column helps identify groups.

        // We need to find the header line to map columns.
        $headers = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Skip annotation lines (start with #)
            if (str_starts_with($line, '#')) {
                continue;
            }

            $parts = str_getcsv($line);

            // If it's the header line
            if (empty($headers) || (isset($parts[1]) && $parts[1] === 'result' && $parts[2] === 'table')) {
                // This looks like a header line.
                // Note: InfluxDB CSV can have multiple tables concatenated, each with its own header if the schema differs?
                // Usually for a single query, the schema is consistent or we handle multiple tables.
                // Let's assume we might encounter headers again.
                $headers = $parts;

                continue;
            }

            // It's a data line
            $row = [];
            foreach ($parts as $index => $value) {
                if (isset($headers[$index])) {
                    $row[$headers[$index]] = $value;
                }
            }

            if (empty($row)) {
                continue;
            }

            // Group by table ID
            $tableId = $row['table'] ?? '0';
            $seriesData[$tableId][] = $row;
        }

        $resultSeries = [];

        foreach ($seriesData as $rows) {
            if (empty($rows)) {
                continue;
            }

            // Extract metric name and labels from the first row
            $firstRow = $rows[0];
            $metric = $firstRow['_measurement'] ?? 'unknown';

            // Labels are all columns that are not internal fields
            $labels = [];
            $exclude = ['result', 'table', '_start', '_stop', '_time', '_value', '_field', '_measurement', ''];
            foreach ($firstRow as $key => $value) {
                if (! in_array($key, $exclude)) {
                    $labels[$key] = $value;
                }
            }

            $points = [];
            foreach ($rows as $row) {
                if (! isset($row['_time']) || ! isset($row['_value'])) {
                    continue;
                }

                $timestamp = strtotime($row['_time']);
                $value = is_numeric($row['_value']) ? (float) $row['_value'] : $row['_value'];

                $points[] = new DataPoint($timestamp, $value);
            }

            $resultSeries[] = new TimeSeries($metric, $labels, $points);
        }

        return new TimeSeriesResult($resultSeries, $query->range, $query->resolution);
    }
}
