<?php

namespace TimeSeriesPhp\Tests\Drivers\RRDtool;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Drivers\RRDtool\Tags\TagCondition;
use TimeSeriesPhp\Drivers\RRDtool\Tags\TagSearch;
use TimeSeriesPhp\Exceptions\TSDBException;

class TagSearchTest extends TestCase
{
    private array $sampleTags;

    protected function setUp(): void
    {
        $this->sampleTags = [
            'environment' => 'production',
            'region' => 'us-east-1',
            'service' => 'web-server',
            'version' => '2.1.0',
            'cpu_usage' => '75',
            'team' => 'backend',
            'status' => 'active',
            'port' => '8080'
        ];
    }

    public function testEmptyConditions(): void
    {
        $result = TagSearch::search($this->sampleTags, []);
        $this->assertTrue($result, 'Empty conditions should return true');
    }

    public function testSimpleAndConditions(): void
    {
        $conditions = [
            new TagCondition('environment', '=', 'production'),
            new TagCondition('region', '=', 'us-east-1'),
        ];

        $result = TagSearch::search($this->sampleTags, $conditions);
        $this->assertTrue($result, 'AND conditions should pass when all match');
    }

    public function testSimpleAndConditionsFailure(): void
    {
        $conditions = [
            new TagCondition('environment', '=', 'production'),
            new TagCondition('region', '=', 'us-west-1'), // This will fail
        ];

        $result = TagSearch::search($this->sampleTags, $conditions);
        $this->assertFalse($result, 'AND conditions should fail when any condition fails');
    }

    public function testOrConditions(): void
    {
        $conditions = [
            new TagCondition('region', '=', 'us-west-1', 'OR'),
            new TagCondition('region', '=', 'us-east-1', 'OR'),
        ];

        $result = TagSearch::search($this->sampleTags, $conditions);
        $this->assertTrue($result, 'OR conditions should pass when at least one matches');
    }

    public function testOrConditionsFailure(): void
    {
        $conditions = [
            new TagCondition('region', '=', 'us-west-1', 'OR'),
            new TagCondition('region', '=', 'eu-central-1', 'OR'),
        ];

        $result = TagSearch::search($this->sampleTags, $conditions);
        $this->assertFalse($result, 'OR conditions should fail when none match');
    }

    public function testMixedAndOrConditions(): void
    {
        $conditions = [
            new TagCondition('environment', '=', 'production'), // AND
            new TagCondition('team', '=', 'frontend', 'OR'),   // OR
            new TagCondition('team', '=', 'backend', 'OR'),    // OR
        ];

        $result = TagSearch::search($this->sampleTags, $conditions);
        $this->assertTrue($result, 'Mixed AND/OR should pass when AND conditions pass and at least one OR condition passes');
    }

    public function testMixedAndOrConditionsFailure(): void
    {
        $conditions = [
            new TagCondition('environment', '=', 'development'), // AND - fails
            new TagCondition('team', '=', 'frontend', 'OR'),     // OR
            new TagCondition('team', '=', 'backend', 'OR'),      // OR
        ];

        $result = TagSearch::search($this->sampleTags, $conditions);
        $this->assertFalse($result, 'Mixed AND/OR should fail when AND conditions fail');
    }

    public function testInOperator(): void
    {
        $conditions = [
            new TagCondition('service', 'IN', ['web-server', 'api-server', 'database']),
        ];

        $result = TagSearch::search($this->sampleTags, $conditions);
        $this->assertTrue($result, 'IN operator should match when value is in array');
    }

    public function testInOperatorFailure(): void
    {
        $conditions = [
            new TagCondition('service', 'IN', ['api-server', 'database', 'cache']),
        ];

        $result = TagSearch::search($this->sampleTags, $conditions);
        $this->assertFalse($result, 'IN operator should fail when value is not in array');
    }

    public function testNotInOperator(): void
    {
        $conditions = [
            new TagCondition('environment', 'NOT IN', ['development', 'staging']),
        ];

        $result = TagSearch::search($this->sampleTags, $conditions);
        $this->assertTrue($result, 'NOT IN operator should pass when value is not in array');
    }

    public function testNotInOperatorFailure(): void
    {
        $conditions = [
            new TagCondition('environment', 'NOT IN', ['production', 'staging']),
        ];

        $result = TagSearch::search($this->sampleTags, $conditions);
        $this->assertFalse($result, 'NOT IN operator should fail when value is in array');
    }

    public function testRegexOperator(): void
    {
        $conditions = [
            new TagCondition('version', 'REGEX', '/^2\.\d+\.\d+$/'),
        ];

        $result = TagSearch::search($this->sampleTags, $conditions);
        $this->assertTrue($result, 'REGEX operator should match valid pattern');
    }

    public function testRegexOperatorFailure(): void
    {
        $conditions = [
            new TagCondition('version', 'REGEX', '/^3\.\d+\.\d+$/'),
        ];

        $result = TagSearch::search($this->sampleTags, $conditions);
        $this->assertFalse($result, 'REGEX operator should fail when pattern does not match');
    }

    public function testBetweenOperator(): void
    {
        $conditions = [
            new TagCondition('cpu_usage', 'BETWEEN', [50, 100]),
        ];

        $result = TagSearch::search($this->sampleTags, $conditions);
        $this->assertTrue($result, 'BETWEEN operator should pass when value is within range');
    }

    public function testBetweenOperatorFailure(): void
    {
        $conditions = [
            new TagCondition('cpu_usage', 'BETWEEN', [80, 100]),
        ];

        $result = TagSearch::search($this->sampleTags, $conditions);
        $this->assertFalse($result, 'BETWEEN operator should fail when value is outside range');
    }

    public function testNonExistentTag(): void
    {
        $conditions = [
            new TagCondition('non_existent_tag', '=', 'value'),
        ];

        $result = TagSearch::search($this->sampleTags, $conditions);
        $this->assertFalse($result, 'Non-existent tag should always fail');
    }

    public function testUnsupportedOperatorThrowsException(): void
    {
        $this->expectException(TSDBException::class);
        $this->expectExceptionMessage('Operator INVALID not supported');

        $condition = new TagCondition('environment', 'INVALID', 'production');
        $condition->matches('production');
    }

    public function testCaseInsensitiveConditionOperator(): void
    {
        // Test that 'or' (lowercase) is treated the same as 'OR'
        $conditions = [
            new TagCondition('region', '=', 'us-west-1', 'or'),
            new TagCondition('region', '=', 'us-east-1', 'OR'),
        ];

        $result = TagSearch::search($this->sampleTags, $conditions);
        $this->assertTrue($result, 'Condition operator should be case-insensitive');
    }

    public function testComplexScenario(): void
    {
        $conditions = [
            new TagCondition('environment', '=', 'production'),           // AND
            new TagCondition('status', '=', 'active'),                   // AND
            new TagCondition('service', 'IN', ['web-server', 'api']),    // AND
            new TagCondition('region', '=', 'us-west-1', 'OR'),          // OR
            new TagCondition('region', '=', 'us-east-1', 'OR'),          // OR
            new TagCondition('cpu_usage', 'BETWEEN', [70, 80], 'OR'),    // OR
        ];

        $result = TagSearch::search($this->sampleTags, $conditions);
        $this->assertTrue($result, 'Complex scenario with multiple operators should work correctly');
    }

    public function testAdvancedSearchMethod(): void
    {
        $conditions = [
            new TagCondition('environment', '=', 'production'),
            new TagCondition('region', '=', 'us-east-1', 'OR'),
        ];

        $result = TagSearch::advancedSearch($this->sampleTags, $conditions);
        $this->assertTrue($result, 'Advanced search should work with mixed conditions');
    }

    public function testAdvancedSearchSequential(): void
    {
        // Test: (env=prod) AND (region=us-east-1 OR region=us-west-1)
        // Should be: true AND (true OR false) = true AND true = true
        $conditions = [
            new TagCondition('environment', '=', 'production'),    // true
            new TagCondition('region', '=', 'us-east-1', 'OR'),   // true OR
            new TagCondition('region', '=', 'us-west-1', 'OR'),   // false = true
        ];

        $result = TagSearch::advancedSearch($this->sampleTags, $conditions);
        $this->assertTrue($result, 'Sequential advanced search should handle mixed operators correctly');
    }

    public function testGroupedSearch(): void
    {
        // Test grouped search: (env=prod AND status=active) OR (team=frontend AND region=us-west-1)
        $conditionGroups = [
            [
                'conditions' => [
                    new TagCondition('environment', '=', 'production'),
                    new TagCondition('status', '=', 'active'),
                ],
            ],
            [
                'operator' => 'OR',
                'conditions' => [
                    new TagCondition('team', '=', 'frontend'),
                    new TagCondition('region', '=', 'us-west-1'),
                ],
            ],
        ];

        $result = TagSearch::groupedSearch($this->sampleTags, $conditionGroups);
        $this->assertTrue($result, 'Grouped search should pass when first group passes');
    }

    public function testGroupedSearchBothGroupsFail(): void
    {
        // Test: (env=staging AND status=inactive) OR (team=frontend AND region=us-west-1)
        $conditionGroups = [
            [
                'conditions' => [
                    new TagCondition('environment', '=', 'staging'),    // false
                    new TagCondition('status', '=', 'inactive'),        // false
                ],
            ],
            [
                'operator' => 'OR',
                'conditions' => [
                    new TagCondition('team', '=', 'frontend'),          // false
                    new TagCondition('region', '=', 'us-west-1'),       // false
                ],
            ],
        ];

        $result = TagSearch::groupedSearch($this->sampleTags, $conditionGroups);
        $this->assertFalse($result, 'Grouped search should fail when both groups fail');
    }

    public function testGroupedSearchEmptyGroups(): void
    {
        $result = TagSearch::groupedSearch($this->sampleTags, []);
        $this->assertTrue($result, 'Grouped search with empty groups should return true');
    }

    public function testAdvancedSearchEmptyConditions(): void
    {
        $result = TagSearch::advancedSearch($this->sampleTags, []);
        $this->assertTrue($result, 'Advanced search with empty conditions should return true');
    }

    /**
     * @dataProvider tagValueTypeProvider
     */
    public function testDifferentTagValueTypes($tagValue, $searchValue, $operator, $expected): void
    {
        $tags = ['test_tag' => $tagValue];
        $conditions = [new TagCondition('test_tag', $operator, $searchValue)];

        $result = TagSearch::search($tags, $conditions);
        $this->assertEquals($expected, $result,
            "Tag value '$tagValue' with operator '$operator' and search value '$searchValue' should return " .
            ($expected ? 'true' : 'false')
        );
    }

    public function tagValueTypeProvider(): array
    {
        return [
            'integer_to_string_equal' => [123, '123', '=', true],
            'float_to_string_equal' => [45.67, '45.67', '=', true],
            'boolean_true_to_string' => [true, '1', '=', true],
            'boolean_false_to_string' => [false, '', '=', true],
            'null_to_string' => [null, '', '=', true],
        ];
    }
}
