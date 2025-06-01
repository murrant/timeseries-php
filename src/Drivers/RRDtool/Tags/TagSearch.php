<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Tags;

use TimeSeriesPhp\Drivers\RRDtool\Exception\RRDtoolTagException;

class TagSearch
{
    /**
     * Search tags based on given conditions
     *
     * @param  array<string, ?scalar>  $tags  Array of tag => value pairs
     * @param  TagCondition[]  $conditions  Array of TagCondition objects
     * @return bool True if all conditions are satisfied
     *
     * @throws RRDtoolTagException
     */
    public static function search(array $tags, array $conditions): bool
    {
        if (empty($conditions)) {
            return true;
        }

        // Group conditions by their logical operator
        $andConditions = [];
        $orConditions = [];

        foreach ($conditions as $condition) {
            if (strtoupper($condition->condition) === 'OR') {
                $orConditions[] = $condition;
            } else {
                $andConditions[] = $condition;
            }
        }

        // Evaluate AND conditions - all must be true
        $andResult = true;
        foreach ($andConditions as $condition) {
            if (! self::evaluateCondition($tags, $condition)) {
                $andResult = false;
                break;
            }
        }

        // Evaluate OR conditions - at least one must be true
        $orResult = empty($orConditions);
        if (! empty($orConditions)) {
            foreach ($orConditions as $condition) {
                if (self::evaluateCondition($tags, $condition)) {
                    $orResult = true;
                    break;
                }
            }
        }

        // Final result: AND conditions must pass AND at least one OR condition must pass (if any)
        return $andResult && $orResult;
    }

    /**
     * Evaluate a single condition against the tags
     *
     * @param  array<string, ?scalar>  $tags
     *
     * @throws RRDtoolTagException
     */
    private static function evaluateCondition(array $tags, TagCondition $condition): bool
    {
        // Check if the tag exists
        if (! array_key_exists($condition->tag, $tags)) {
            return false;
        }

        $tagValue = (string) $tags[$condition->tag];

        return $condition->matches($tagValue);
    }

    /**
     * Advanced search with support for sequential logical operations
     * Processes conditions left-to-right with proper operator precedence
     *
     * @param  array<string, ?scalar>  $tags  Array of tag => value pairs
     * @param  TagCondition[]  $conditions  Array of TagCondition objects
     * @return bool True if the logical expression evaluates to true
     *
     * @throws RRDtoolTagException
     */
    public static function advancedSearch(array $tags, array $conditions): bool
    {
        if (empty($conditions)) {
            return true;
        }

        // Start with the first condition result
        $result = self::evaluateCondition($tags, $conditions[0]);

        // Process remaining conditions sequentially
        for ($i = 1; $i < count($conditions); $i++) {
            $condition = $conditions[$i];
            $conditionResult = self::evaluateCondition($tags, $condition);

            if (strtoupper($condition->condition) === 'OR') {
                $result = $result || $conditionResult;
            } else {
                $result = $result && $conditionResult;
            }
        }

        return $result;
    }

    /**
     * Complex search with explicit grouping support
     * Allows for more sophisticated logical operations by grouping conditions
     *
     * @param  array<string, ?scalar>  $tags  Array of tag => value pairs
     * @param  array<array{'conditions': TagCondition[], 'operator'?: 'OR'|'AND'}>  $conditionGroups  Array of condition groups, each with 'conditions' and 'operator'
     * @return bool True if the grouped expression evaluates to true
     *
     * @throws RRDtoolTagException
     */
    public static function groupedSearch(array $tags, array $conditionGroups): bool
    {
        if (empty($conditionGroups)) {
            return true;
        }

        $groupResults = [];

        // Evaluate each group
        foreach ($conditionGroups as $group) {
            $conditions = $group['conditions'];
            $groupResult = self::search($tags, $conditions);
            $groupResults[] = $groupResult;
        }

        // Combine group results
        $finalResult = $groupResults[0];
        for ($i = 1; $i < count($conditionGroups); $i++) {
            $operator = strtoupper($conditionGroups[$i]['operator'] ?? 'AND');
            if ($operator === 'OR') {
                $finalResult = $finalResult || $groupResults[$i];
            } else {
                $finalResult = $finalResult && $groupResults[$i];
            }
        }

        return $finalResult;
    }
}
