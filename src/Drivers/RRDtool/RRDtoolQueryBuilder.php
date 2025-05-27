<?php

namespace TimeSeriesPhp\Drivers\RRDtool;

use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Core\QueryBuilderContract;
use TimeSeriesPhp\Core\RawQueryContract;
use TimeSeriesPhp\Drivers\RRDtool\Tags\RRDTagStrategyContract;

class RRDtoolQueryBuilder implements QueryBuilderContract
{
    private RRDTagStrategyContract $tagStrategy;
    private string $rrdDir;

    public function __construct(RRDTagStrategyContract $tagStrategy, string $rrdDir)
    {
        $this->tagStrategy = $tagStrategy;
        $this->rrdDir = $rrdDir;
    }

    public function build(Query $query): RawQueryContract
    {
        $rawQuery = new RRDtoolRawQuery();
        $measurement = $query->getMeasurement();
        $tags = $query->getTags();
        $fields = $query->getFields();
        $startTime = $query->getStartTime();
        $endTime = $query->getEndTime();
        $aggregation = $query->getAggregation();

        // Get the RRD file path
        $rrdPath = $this->getRRDPath($measurement, $tags);

        // Set the start and end time parameters
        if ($startTime) {
            $rawQuery->param('--start', $startTime->format('U'));
        } else {
            // Default to last hour if not specified
            $rawQuery->param('--start', 'end-1h');
        }

        if ($endTime) {
            $rawQuery->param('--end', $endTime->format('U'));
        }

        // Map the aggregation function to RRDtool consolidation function
        $cf = $this->mapAggregationToConsolidationFunction($aggregation);

        // Add data definitions (DEFs) and export definitions (XPORTs)
        // If specific fields are requested, use only those, otherwise use all
        if (!empty($fields) && !in_array('*', $fields)) {
            foreach ($fields as $field) {
                $varName = 'v' . md5($field);
                $rawQuery->def($varName, $rrdPath, $field, $cf);
                $rawQuery->xport($varName, $field);
            }
        } else {
            // We'll need to determine available fields from the RRD file
            // For now, we'll use a placeholder approach
            $rawQuery->def('v1', $rrdPath, 'value', $cf);
            $rawQuery->xport('v1', 'value');
        }

        return $rawQuery;
    }

    private function mapAggregationToConsolidationFunction(?string $aggregation): string
    {
        return match (strtolower($aggregation ?? 'average')) {
            'max' => 'MAX',
            'min' => 'MIN',
            'last' => 'LAST',
            default => 'AVERAGE',
        };
    }

    private function getRRDPath(string $measurement, array $tags = []): string
    {
        return $this->tagStrategy->getFilePath($measurement, $tags, $this->rrdDir);
    }
}
