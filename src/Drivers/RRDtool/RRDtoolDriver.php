<?php

namespace TimeSeriesPhp\Drivers\RRDtool;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use TimeSeriesPhp\Contracts\Query\RawQueryInterface;
use TimeSeriesPhp\Core\Attributes\Driver;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Core\Driver\AbstractTimeSeriesDB;
use TimeSeriesPhp\Drivers\RRDtool\Config\RRDtoolConfig;
use TimeSeriesPhp\Drivers\RRDtool\Exception\RRDtoolCommandTimeoutException;
use TimeSeriesPhp\Drivers\RRDtool\Exception\RRDtoolException;
use TimeSeriesPhp\Drivers\RRDtool\Exception\RRDtoolPrematureUpdateException;
use TimeSeriesPhp\Drivers\RRDtool\Factory\InputStreamFactory;
use TimeSeriesPhp\Drivers\RRDtool\Factory\InputStreamFactoryInterface;
use TimeSeriesPhp\Drivers\RRDtool\Factory\ProcessFactory;
use TimeSeriesPhp\Drivers\RRDtool\Factory\ProcessFactoryInterface;
use TimeSeriesPhp\Drivers\RRDtool\Factory\QueryBuilderFactory;
use TimeSeriesPhp\Drivers\RRDtool\Factory\QueryBuilderFactoryInterface;
use TimeSeriesPhp\Drivers\RRDtool\Factory\TagStrategyFactory;
use TimeSeriesPhp\Drivers\RRDtool\Factory\TagStrategyFactoryInterface;
use TimeSeriesPhp\Drivers\RRDtool\Query\RRDtoolRawQuery;
use TimeSeriesPhp\Drivers\RRDtool\Tags\RRDTagStrategyInterface;
use TimeSeriesPhp\Exceptions\Driver\ConnectionException;
use TimeSeriesPhp\Exceptions\Driver\DriverException;
use TimeSeriesPhp\Exceptions\Driver\WriteException;
use TimeSeriesPhp\Exceptions\Query\RawQueryException;
use TimeSeriesPhp\Services\Logs\Logger;

#[Driver(name: 'rrdtool', configClass: RRDtoolConfig::class)]
class RRDtoolDriver extends AbstractTimeSeriesDB
{
    protected bool $debug = false;

    protected string $rrdDir = '';

    protected string $rrdtoolPath = 'rrdtool';

    protected string $rrdcachedAddress = '';

    protected RRDTagStrategyInterface $tagStrategy;

    /**
     * @var ProcessFactoryInterface The process factory
     */
    protected ProcessFactoryInterface $processFactory;

    /**
     * @var InputStreamFactoryInterface The input stream factory
     */
    protected InputStreamFactoryInterface $inputStreamFactory;

    /**
     * @var TagStrategyFactoryInterface The tag strategy factory
     */
    protected TagStrategyFactoryInterface $tagStrategyFactory;

    /**
     * @var QueryBuilderFactoryInterface The RRDtool query builder factory
     */
    protected QueryBuilderFactoryInterface $rrdQueryBuilderFactory;

    /**
     * Constructor
     *
     * @param  ProcessFactoryInterface|null  $processFactory  The process factory
     * @param  InputStreamFactoryInterface|null  $inputStreamFactory  The input stream factory
     * @param  TagStrategyFactoryInterface|null  $tagStrategyFactory  The tag strategy factory
     * @param  QueryBuilderFactoryInterface|null  $queryBuilderFactory  The query builder factory
     * @param  \TimeSeriesPhp\Contracts\Query\QueryBuilderInterface|null  $parentQueryBuilderFactory  The parent query builder factory
     */
    public function __construct(
        ?ProcessFactoryInterface $processFactory = null,
        ?InputStreamFactoryInterface $inputStreamFactory = null,
        ?TagStrategyFactoryInterface $tagStrategyFactory = null,
        ?QueryBuilderFactoryInterface $queryBuilderFactory = null,
        ?\TimeSeriesPhp\Contracts\Query\QueryBuilderInterface $parentQueryBuilderFactory = null
    ) {
        parent::__construct($parentQueryBuilderFactory);

        $this->processFactory = $processFactory ?? new ProcessFactory;
        $this->inputStreamFactory = $inputStreamFactory ?? new InputStreamFactory;
        $this->tagStrategyFactory = $tagStrategyFactory ?? new TagStrategyFactory;
        $this->rrdQueryBuilderFactory = $queryBuilderFactory ?? new QueryBuilderFactory;
    }

    /**
     * Get the rrdcached address if configured
     */
    public function getRrdcachedAddress(): string
    {
        return $this->rrdcachedAddress;
    }

    private ?Process $persistentProcess = null;

    private ?InputStream $persistentInput = null;

    private int $commandTimeout = 180;

    /**
     * @throws ConnectionException
     */
    protected function doConnect(): bool
    {
        $this->debug = $this->config->getBool('debug');
        $this->rrdDir = $this->config->getString('rrd_dir');
        $this->commandTimeout = $this->config->getInt('command_timeout');
        $this->rrdtoolPath = $this->config->getString('rrdtool_path');
        if ($this->config->getBool('use_rrdcached')) {
            $this->rrdcachedAddress = $this->config->getString('rrdcached_address');
            if (empty($this->rrdcachedAddress)) {
                throw new ConnectionException('rrdcached address must be specified when use_rrdcached is true');
            }
        }

        if (! is_dir($this->rrdDir)) {
            if (! mkdir($this->rrdDir, 0755, true)) {
                throw new ConnectionException("Cannot create RRD directory: {$this->rrdDir}");
            }
        }

        if (! is_writable($this->rrdDir)) {
            throw new ConnectionException("RRD directory is not writable: {$this->rrdDir}");
        }

        $tagStrategyClass = $this->config->getString('tag_strategy');
        $this->tagStrategy = $this->tagStrategyFactory->create($tagStrategyClass, $this->rrdDir);
        $this->queryBuilder = $this->rrdQueryBuilderFactory->create($this->tagStrategy);

        if ($this->config->getBool('persistent_process')) {
            $this->persistentProcess = $this->processFactory->create([$this->rrdtoolPath, '-']);
            $this->persistentInput = $this->inputStreamFactory->create();
            $this->persistentProcess->setInput($this->persistentInput);
            $this->persistentProcess->setTimeout(null);
            $this->persistentProcess->start();
        }

        $this->connected = true;

        Logger::info('Connected to RRDtool successfully', [
            'rrd_dir' => $this->rrdDir,
            'rrdtool_path' => $this->rrdtoolPath,
            'use_rrdcached' => ! empty($this->rrdcachedAddress),
            'persistent_process' => $this->persistentProcess !== null,
            'tag_strategy' => get_class($this->tagStrategy),
        ]);

        return true;
    }

    /**
     * Get the RRD file path for a measurement and tags
     *
     * @param  string  $measurement  The measurement name
     * @param  array<string, string>  $tags  The tags as key-value pairs
     * @return string The full path to the RRD file
     */
    public function getRRDPath(string $measurement, array $tags = []): string
    {
        return $this->tagStrategy->getFilePath($measurement, $tags);
    }

    /**
     * Build an rrdtool command with rrdcached support if configured
     *
     * @param  string  $command  The rrdtool command (create, update, fetch, etc.)
     * @param  string[]  $args  The command arguments
     * @return string The command output
     *
     * @throws RRDtoolException
     */
    private function runRrdtoolCommand(string $command, array $args): string
    {
        array_unshift($args, $command);
        if ($this->rrdcachedAddress) {
            array_push($args, '--daemon', $this->rrdcachedAddress);
        }

        if ($this->debug) {
            Logger::debug('Running rrdtool command', [
                'command' => $command,
                'args' => $args,
                'full_command' => implode(' ', $args),
            ]);
        }

        if ($this->persistentProcess) {
            if ($this->persistentInput == null) {
                throw new RRDtoolException('Persistent process is not started, input stream missing');
            }

            $this->persistentInput->write(implode(' ', $args)."\n");
            $timeout = time() + $this->commandTimeout;
            $this->persistentProcess->waitUntil(function (string $type, string $output) use ($command, $args, $timeout) {
                // FIXME debug output?
                if ($this->debug && function_exists('dump')) {
                    dump("Type: $type  $output");
                }

                if (preg_match('/^OK/m', $output)) {
                    return true;
                }

                if ($type == Process::OUT && str_starts_with($output, 'ERROR:')) {
                    if (preg_match('/illegal attempt to update using time \d+ when last update time is/', $output)) {
                        throw new RRDtoolPrematureUpdateException($command, $args, $output);
                    }

                    throw new RRDtoolException($command, $args, $output);
                }

                if (time() > $timeout) {
                    throw new RRDtoolCommandTimeoutException($command, $args, $output);
                }

                return false;
            });

            $output = $this->persistentProcess->getOutput();

            // trim ok line
            $lastLinePos = strrpos($output, "\n", -2);
            if ($lastLinePos !== false) {
                $output = substr($output, 0, $lastLinePos);
            }

            $this->persistentProcess->clearOutput();

            return $output;
        }

        array_unshift($args, $this->rrdtoolPath);
        $process = new Process($args);
        $process->setTimeout($this->commandTimeout);

        try {
            $process->run();

            if ($process->getExitCode() !== 0) {
                throw (new RRDtoolException($command, $args))
                    ->setOutput($process->getOutput())
                    ->setErrorOutput($process->getErrorOutput());
            }

            return $process->getOutput();
        } catch (ProcessTimedOutException) {
            throw new RRDtoolCommandTimeoutException($command, $args);
        }
    }

    private function guessDataSourceType(mixed $value): string
    {
        if (is_int($value)) {
            return 'GAUGE'; // or COUNTER, DERIVE, ABSOLUTE based on your needs
        }
        if (is_float($value)) {
            return 'GAUGE';
        }

        return 'GAUGE'; // Default fallback
    }

    protected function doWrite(DataPoint $dataPoint): bool
    {
        // Create RRD if it doesn't exist
        $rrdPath = $this->createRRD($dataPoint->getMeasurement(), $dataPoint->getTags(), $dataPoint->getFields());

        // Prepare update string
        $timestamp = $dataPoint->getTimestamp()->getTimestamp();
        $values = [];

        // Get RRD info to determine field order
        $info = $this->getRRDInfo($rrdPath);
        $dataSourceOrder = $this->getDataSourceOrder($info);

        foreach ($dataSourceOrder as $dsName) {
            $fields = $dataPoint->getFields();

            // Use 'U' for any non-numeric value including null
            $values[] = isset($fields[$dsName]) && is_numeric($fields[$dsName]) ? $fields[$dsName] : 'U';
        }

        $updateString = $timestamp.':'.implode(':', $values);

        // Build the command using the helper
        $args = [$rrdPath, $updateString];

        try {
            $this->runRrdtoolCommand('update', $args);

            return true;
        } catch (RRDtoolException $e) {
            throw new WriteException($e->getDebugMessage($this->debug), $e->getCode(), $e);
        }
    }

    /**
     * @return array<string, string>
     */
    private function getRRDInfo(string $rrdPath): array
    {
        try {
            $output = $this->runRrdtoolCommand('info', [$rrdPath]);

            $info = [];
            foreach (explode("\n", $output) as $line) {
                if (preg_match('/^([^=]+)\s*=\s*(.+)$/', $line, $matches)) {
                    $info[trim($matches[1])] = trim($matches[2], '"');
                }
            }

            return $info;
        } catch (RRDtoolException) {
            return [];
        }
    }

    /**
     * @param  array<string, string>  $info
     * @return string[]
     */
    private function getDataSourceOrder(array $info): array
    {
        $dataSources = [];
        foreach ($info as $key => $value) {
            if (preg_match('/^ds\[([^]]+)]\.type$/', $key, $matches)) {
                $dataSources[] = $matches[1];
            }
        }

        return $dataSources;
    }

    /**
     * @param  array{'meta': array{'legend': array<int, string>, 'start': int, 'end': int, 'step': int}, 'data': array<int, array<int, float|int|null>>}  $json
     * @param  string[]  $requestedFields
     */
    private function parseRRDXportJson(array $json, array $requestedFields): QueryResult
    {
        // If we have legend data but no matches with requestedFields, use the legend as is
        // This handles the case where the xport command uses different field names than the ones requested
        $legend = array_filter($json['meta']['legend'], function ($field) use ($requestedFields) {
            return in_array('*', $requestedFields)
                || in_array($field, $requestedFields);
        });

        // If no fields matched, use all legend fields
        if (empty($legend) && ! empty($json['meta']['legend'])) {
            $legend = $json['meta']['legend'];
        }

        $start = $json['meta']['start'];
        $step = $json['meta']['step'];
        $result = new QueryResult(metadata: $json['meta']);

        foreach ($json['data'] as $index => $values) {
            $timestamp = $start + $step * $index;

            foreach ($legend as $field => $name) {
                $result->appendPoint($timestamp, $name, $values[$field]);
            }
        }

        return $result;
    }

    public function rawQuery(RawQueryInterface $query): QueryResult
    {
        if (! $query instanceof RRDtoolRawQuery) {
            throw new RawQueryException($query, 'Invalid query type');
        }

        // For RRDtool, raw query would be a direct rrdtool command
        try {
            $output = $this->runRrdtoolCommand($query->command, $query->getArgs());
            if ($query->command === 'xport') {
                $json = json_decode($output, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new RawQueryException($query, 'Failed to parse RRD command output: '.json_last_error_msg().PHP_EOL.$output, json_last_error());
                }

                /** @var array{'meta': array{'legend': array<int, string>, 'start': int, 'end': int, 'step': int}, 'data': array<int, array<int, float|int|null>>} $json */
                return $this->parseRRDXportJson($json, $query->getFields() ?: ['*']);
            }

            // Create a result with a time key and a value key for non-xport queries
            return new QueryResult([
                'output' => [[
                    'date' => time(),
                    'value' => $output,
                ]],
            ]);
        } catch (RRDtoolException $e) {
            throw new RawQueryException($query, $e->getDebugMessage($this->debug), $e->getCode(), $e);
        }
    }

    public function createDatabase(string $database): bool
    {
        // RRDtool doesn't have databases, but we can create a subdirectory
        $dbDir = $this->rrdDir.'/'.$database;

        if (! is_dir($dbDir)) {
            return mkdir($dbDir, 0755, true);
        }

        return true;
    }

    /**
     * @return string[]
     */
    public function getDatabases(): array
    {
        $databases = [];
        $items = scandir($this->rrdDir);

        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..' && is_dir($this->rrdDir.'/'.$item)) {
                $databases[] = $item;
            }
        }

        return $databases;
    }

    public function close(): void
    {
        $this->persistentProcess?->stop();
        $this->connected = false;
    }

    /**
     * @param  string  $measurement_or_filename  The tsdb measurement or the full path to the file
     * @param  array<string, string>|null  $tags
     */
    public function rrdExists(string $measurement_or_filename, ?array $tags = null): bool
    {
        $filename = $tags === null ? $measurement_or_filename : $this->getRRDPath($measurement_or_filename, $tags);

        return file_exists($filename);
    }

    /**
     * @param  array<string, string>  $tags
     * @param  array<string, ?scalar>  $fields
     *
     * @throws WriteException
     */
    protected function createRRD(string $measurement, array $tags, array $fields): string
    {
        $rrdPath = $this->getRRDPath($measurement, $tags);

        $dataSources = [];
        foreach ($fields as $field => $value) {
            $type = $this->guessDataSourceType($value);
            $dataSources[] = "DS:{$field}:{$type}:600:U:U";
        }

        $this->createRRDWithCustomConfig($rrdPath, $dataSources);

        return $rrdPath;
    }

    /**
     * @param  string[]  $data_sources
     * @param  string[]|null  $archives
     *
     * @throws WriteException
     */
    public function createRRDWithCustomConfig(string $filename, array $data_sources, ?int $step = null, ?array $archives = null): bool
    {
        if ($this->rrdExists($filename)) {
            return false;
        }

        $step ??= $this->config->getInt('default_step');
        $archives ??= $this->config->getArray('default_archives');

        if (empty($data_sources)) {
            throw new WriteException('Data sources must be specified for custom RRD creation');
        }

        if (empty($archives)) {
            throw new WriteException('Archives must be specified for custom RRD creation');
        }

        /** @var string[] $archives */
        $args = [$filename, '--step', (string) $step];
        $args = array_merge($args, $data_sources, $archives);

        try {
            $this->runRrdtoolCommand('create', $args);

            return true;
        } catch (RRDtoolException $e) {
            throw new WriteException('Failed to create RRD file: '.$e->getDebugMessage($this->debug), $e->getCode(), $e);
        }
    }

    /**
     * @return string filename or raw graph data
     *
     * @throws DriverException
     */
    public function getRRDGraph(RRDtoolRawQuery $graphConfig): string
    {
        try {
            // set up a temp file for output if file output is requested and no file is specified
            if ($this->config->getString('graph_output') === 'file' && $graphConfig->filename == '-') {
                $imgFormat = $graphConfig->getParam('--imgformat') ?? $graphConfig->getParam('-a');
                $suffix = $imgFormat ? '.'.strtolower($imgFormat) : '.png';

                $graphConfig->filename = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('rrdgraph_').$suffix;
            }

            $result = $this->runRrdtoolCommand('graph', $graphConfig->getArgs());

            if ($graphConfig->filename == '-') {
                return $result;
            }

            if (! file_exists($graphConfig->filename)) {
                throw new DriverException('Failed to create RRD graph');
            }

            return $graphConfig->filename;
        } catch (RRDtoolException $e) {
            throw new DriverException('Failed to create RRD graph: '.$e->getDebugMessage($this->debug), $e->getCode(), $e);
        }
    }
}
