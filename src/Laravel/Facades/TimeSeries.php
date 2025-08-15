<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use TimeSeriesPhp\TSDB as TSDBClass;

/**
 * @method static \TimeSeriesPhp\Contracts\Driver\TimeSeriesInterface getDriver()
 * @method static bool write(string $measurement, array $fields, array $tags = [], ?\DateTime $timestamp = null)
 * @method static bool writeBatch(array $dataPoints)
 * @method static \TimeSeriesPhp\Core\Data\QueryResult query(\TimeSeriesPhp\Core\Query\Query $query)
 * @method static \TimeSeriesPhp\Core\Data\QueryResult queryLast(string $measurement, string $field, array $tags = [])
 * @method static \TimeSeriesPhp\Core\Data\QueryResult queryFirst(string $measurement, string $field, array $tags = [])
 * @method static \TimeSeriesPhp\Core\Data\QueryResult queryAvg(string $measurement, string $field, \DateTime $start, \DateTime $end, array $tags = [])
 * @method static \TimeSeriesPhp\Core\Data\QueryResult querySum(string $measurement, string $field, \DateTime $start, \DateTime $end, array $tags = [])
 * @method static \TimeSeriesPhp\Core\Data\QueryResult queryCount(string $measurement, string $field, \DateTime $start, \DateTime $end, array $tags = [])
 * @method static \TimeSeriesPhp\Core\Data\QueryResult queryMin(string $measurement, string $field, \DateTime $start, \DateTime $end, array $tags = [])
 * @method static \TimeSeriesPhp\Core\Data\QueryResult queryMax(string $measurement, string $field, \DateTime $start, \DateTime $end, array $tags = [])
 * @method static bool deleteMeasurement(string $measurement, ?\DateTime $start = null, ?\DateTime $stop = null)
 * @method static \TimeSeriesPhp\Contracts\Schema\SchemaManagerInterface getSchemaManager()
 * @method static void close()
 *
 * @see \TimeSeriesPhp\TSDB
 */
class TimeSeries extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return TSDBClass::class;
    }
}
