<?php

declare(strict_types=1);

namespace TimeseriesPhp\Driver\InfluxDB2;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PsrDiscovery\Discover;
use TimeseriesPhp\Core\Contracts\Writer;
use TimeseriesPhp\Core\Exceptions\TimeseriesException;
use TimeseriesPhp\Core\Metrics\MetricSample;
use TimeseriesPhp\Driver\InfluxDB2\Contracts\FieldStrategy;

class InfluxWriter implements Writer
{
    private readonly ClientInterface $httpClient;

    private readonly RequestFactoryInterface $requestFactory;

    private readonly StreamFactoryInterface $streamFactory;

    public function __construct(
        private readonly InfluxConfig $config,
        private readonly FieldStrategy $fieldStrategy,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        private readonly LoggerInterface $logger = new NullLogger,
    ) {
        $this->httpClient = $httpClient ?? Discover::httpClient();
        $this->requestFactory = $requestFactory ?? Discover::httpRequestFactory();
        $this->streamFactory = $streamFactory ?? Discover::httpStreamFactory();
    }

    /**
     * @throws TimeseriesException
     */
    public function write(MetricSample $sample): void
    {
        $line = $this->formatLineProtocol($sample);

        $this->sendWrite($line);
    }

    /**
     * @param  MetricSample[]  $samples
     *
     * @throws TimeseriesException
     */
    public function writeBatch(array $samples): void
    {
        $lines = array_reduce($samples, fn ($prev, $sample) => $prev."\n".$this->formatLineProtocol($sample), '');

        $this->sendWrite($lines);
    }

    private function sendWrite(string $body): void
    {
        $url = $this->config->host.':'.$this->config->port.'/api/v2/write?'.http_build_query([
            'org' => $this->config->org,
            'bucket' => $this->config->bucket,
            'precision' => 's',
        ]);

        //        $this->logger->debug('Writing to InfluxDB2', ['url' => $url, 'body' => $body]);

        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Authorization', 'Token '.$this->config->token)
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withBody($this->streamFactory->createStream($body));

        try {
            $response = $this->httpClient->sendRequest($request);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                throw new TimeseriesException(
                    sprintf(
                        'Failed to write to InfluxDB2. Status code: %d. Response: %s',
                        $response->getStatusCode(),
                        $response->getBody()->getContents()
                    )
                );
            }
        } catch (ClientExceptionInterface $e) {
            throw new TimeseriesException('HTTP error while writing to InfluxDB2: '.$e->getMessage(), 0, $e);
        }
    }

    private function formatLineProtocol(MetricSample $sample): string
    {
        $measurement = $this->fieldStrategy->getMeasurementName($sample->metric);
        $fieldname = $this->fieldStrategy->getFieldName($sample->metric);

        $tags = [];
        foreach ($sample->labels as $key => $value) {
            $tags[] = "{$this->escape($key)}={$this->escape($value)}";
        }

        $tagStr = $tags !== [] ? ','.implode(',', $tags) : '';

        $value = $sample->value;
        $fieldValue = is_float($value) ? sprintf('%F', $value) : "{$value}i";

        $timestamp = $sample->timestamp?->getTimestamp();

        return "{$this->escape($measurement)}{$tagStr} $fieldname={$fieldValue} {$timestamp}";
    }

    private function escape(string $value): string
    {
        return str_replace([' ', ',', '='], ['\ ', '\,', '\='], $value);
    }
}
