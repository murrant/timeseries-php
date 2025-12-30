<?php

namespace TimeseriesPhp\Driver\InfluxDB2;

use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use PsrDiscovery\Discover;
use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Contracts\Result;
use TimeseriesPhp\Core\Contracts\TsdbClient;
use TimeseriesPhp\Core\Enum\QueryType;
use TimeseriesPhp\Core\Exceptions\TimeseriesException;
use TimeseriesPhp\Core\Results\DataPoint;
use TimeseriesPhp\Core\Results\LabelResult;
use TimeseriesPhp\Core\Results\TimeSeries;
use TimeseriesPhp\Core\Results\TimeSeriesResult;

/** @template TResult of Result */
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

    /**
     * @param  CompiledQuery<TResult>  $query
     * @return TResult
     *
     * @throws TimeseriesException
     */
    public function execute(CompiledQuery $query): Result
    {
        if (! $query instanceof InfluxQuery) {
            throw new InvalidArgumentException('Query must be an instance of InfluxQuery');
        }

        $url = $this->config->host.':'.$this->config->port.'/api/v2/query?'.http_build_query([
            'org' => $this->config->org,
        ]);

        $queryString = (string) $query;

        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Authorization', 'Token '.$this->config->token)
            ->withHeader('Content-Type', 'application/vnd.flux')
            ->withBody($this->streamFactory->createStream($queryString));

        try {
            $response = $this->httpClient->sendRequest($request);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                throw new TimeseriesException(
                    sprintf(
                        "Failed to query InfluxDB2. Status code: %d. Response: %s\nQuery: %s",
                        $response->getStatusCode(),
                        $response->getBody()->getContents(),
                        $queryString,
                    )
                );
            }

            $csv = $response->getBody()->getContents();

            // We need to determine if it's a LabelResult or TimeSeriesResult.
            // This is tricky from just the CSV, but we can look at the query or the generic type.
            // However, at runtime we don't have the generic type easily.
            // InfluxQuery holds the range and resolution, which might help,
            // but the most reliable way is if InfluxQuery knew what result it's supposed to produce.

            // For now, let's check if the CSV contains columns like _time, _value which are typical for TimeSeries.
            // If it's a label query, it usually has distinct values of a tag or measurement.

            /** @var TResult */
            return $this->parseResponse($csv, $query);
        } catch (ClientExceptionInterface $e) {
            throw new TimeseriesException('HTTP error while querying InfluxDB2: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @param  InfluxQuery<TResult>  $query
     * @return TResult
     */
    private function parseResponse(string $csv, InfluxQuery $query): Result
    {
        $lines = explode("\n", $csv);
        $data = [];
        $headers = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = str_getcsv($line, ',', '"', '');

            if (empty($headers) || (isset($parts[1]) && $parts[1] === 'result' && $parts[2] === 'table')) {
                $headers = $parts;

                continue;
            }

            $row = [];
            foreach ($parts as $index => $value) {
                if (isset($headers[$index])) {
                    $row[$headers[$index]] = $value;
                }
            }

            if (! empty($row)) {
                $data[] = $row;
            }
        }

        if ($query->type === QueryType::Label) {
            return $this->parseLabelResult($data);
        }

        return $this->parseTimeSeriesResult($data, $query);
    }

    /**
     * @param  array<int, array<string, string>>  $data
     */
    private function parseLabelResult(array $data): LabelResult
    {
        $values = [];
        $labelNames = [];

        foreach ($data as $row) {
            // In Flux metadata queries, the value is usually in _value or the specific tag column
            // schema.measurements() returns measurements in _value
            // schema.tagValues() returns tag values in _value
            // Our custom distinct() queries keep specific columns.

            $exclude = ['result', 'table', '_start', '_stop', ''];
            foreach ($row as $key => $value) {
                if (! in_array($key, $exclude)) {
                    if ($key === '_value' || $key === '_measurement') {
                        $values[] = $value;
                    } else {
                        $values[] = $value;
                        $labelNames[$key] = true;
                    }
                }
            }
        }

        return new LabelResult(array_keys($labelNames), array_values(array_unique($values)));
    }

    /**
     * @param  array<int, array<string, string>>  $data
     * @param  InfluxQuery<TimeSeriesResult>  $query
     */
    private function parseTimeSeriesResult(array $data, InfluxQuery $query): TimeSeriesResult
    {
        $seriesData = [];
        foreach ($data as $row) {
            $resultId = $row['result'] ?? '_result';
            $tableId = $row['table'] ?? '0';
            $seriesData[$resultId.'|'.$tableId][] = $row;
        }

        $resultSeries = [];
        foreach ($seriesData as $rows) {
            $firstRow = $rows[0];
            $alias = $firstRow['result'] ?? null;
            $measurement = $firstRow['_measurement'] ?? 'unknown';

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

                $timestamp = (int) strtotime($row['_time']);
                $value = is_numeric($row['_value']) ? (float) $row['_value'] : null;

                $points[] = new DataPoint($timestamp, $value);
            }

            $resultSeries[] = new TimeSeries($measurement, $alias, $labels, $points);
        }

        return new TimeSeriesResult($resultSeries, $query->range, $query->resolution);
    }
}
