<?php

namespace TimeSeriesPhp\Tests\Drivers\RRDtool;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Drivers\RRDtool\Exception\RRDtoolTagException;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolConfig;
use TimeSeriesPhp\Drivers\RRDtool\Tags\FileNameStrategy;
use TimeSeriesPhp\Drivers\RRDtool\Tags\TagCondition;

class FileNameStrategyTest extends TestCase
{
    private FileNameStrategy $strategy;

    private string $baseDir;

    private string $tempDir;

    protected function setUp(): void
    {
        // Create a temporary directory for testing
        $this->tempDir = sys_get_temp_dir().'/rrdtool_test_'.uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->baseDir = $this->tempDir.DIRECTORY_SEPARATOR;
        $this->strategy = new FileNameStrategy(new RRDtoolConfig(rrd_dir: $this->baseDir));
    }

    protected function tearDown(): void
    {
        // Clean up temporary files and directory
        $files = glob($this->baseDir.'*.rrd') ?: [];
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($this->baseDir);
    }

    public function test_constructor_requires_trailing_slash(): void
    {
        $this->expectException(RRDtoolTagException::class);
        $this->expectExceptionMessage('Base directory must end with a slash');

        new FileNameStrategy(new RRDtoolConfig(rrd_dir: $this->tempDir)); // Missing trailing slash
    }

    public function test_get_base_dir(): void
    {
        $this->assertEquals($this->baseDir, $this->strategy->getBaseDir());
    }

    public function test_get_file_path_with_no_tags(): void
    {
        $measurement = 'cpu_usage';
        $expectedPath = $this->baseDir.'cpu_usage.rrd';

        $this->assertEquals($expectedPath, $this->strategy->getFilePath($measurement));
    }

    public function test_get_file_path_with_tags(): void
    {
        $measurement = 'cpu_usage';
        $tags = [
            'host' => 'server1',
            'region' => 'us-east',
        ];

        // The tags should be sorted alphabetically (host, region)
        $expectedPath = $this->baseDir.'cpu_usage_host-server1_region-us.east.rrd';

        $this->assertEquals($expectedPath, $this->strategy->getFilePath($measurement, $tags));
    }

    public function test_resolve_file_paths_with_no_conditions(): void
    {
        // Create some test files
        file_put_contents($this->baseDir.'cpu_usage.rrd', 'test');
        file_put_contents($this->baseDir.'memory_usage.rrd', 'test');

        $files = $this->strategy->resolveFilePaths('cpu', []);

        $this->assertCount(1, $files);
        $this->assertStringContainsString('cpu_usage.rrd', $files[0]);
    }

    public function test_resolve_file_paths_with_tag_conditions(): void
    {
        // Create test files with different tags
        file_put_contents($this->baseDir.'cpu_usage_host-server1_region-us.east.rrd', 'test');
        file_put_contents($this->baseDir.'cpu_usage_host-server2_region-us.west.rrd', 'test');
        file_put_contents($this->baseDir.'memory_usage_host-server1_region-us.east.rrd', 'test');

        $conditions = [
            new TagCondition('host', '=', 'server1'),
            new TagCondition('region', '=', 'us-east'),
        ];

        $files = $this->strategy->resolveFilePaths('cpu_usage', $conditions);

        $this->assertCount(1, $files);
        $this->assertStringContainsString('cpu_usage_host-server1_region-us.east.rrd', $files[0]);
    }

    public function test_find_measurements_by_tags(): void
    {
        // Create test files with different measurements and tags
        file_put_contents($this->baseDir.'cpu_usage_host-server1_region-us-east.rrd', 'test');
        file_put_contents($this->baseDir.'memory_usage_host-server1_region-us-east.rrd', 'test');
        file_put_contents($this->baseDir.'disk_usage_host-server2_region-us-west.rrd', 'test');

        $conditions = [
            new TagCondition('host', '=', 'server1'),
        ];

        $measurements = $this->strategy->findMeasurementsByTags($conditions);

        $this->assertCount(2, $measurements);
        $this->assertContains('cpu_usage', $measurements);
        $this->assertContains('memory_usage', $measurements);
        $this->assertNotContains('disk_usage', $measurements);
    }

    public function test_resolve_file_paths_with_complex_conditions(): void
    {
        // Create test files with different tags
        file_put_contents($this->baseDir.'cpu_usage_host-server1_region-us.east_env-prod.rrd', 'test');
        file_put_contents($this->baseDir.'cpu_usage_host-server2_region-us.west_env-dev.rrd', 'test');
        file_put_contents($this->baseDir.'cpu_usage_host-server3_region-eu.west_env-prod.rrd', 'test');

        $conditions = [
            new TagCondition('env', '=', 'prod'),
            new TagCondition('region', '=', 'us-east', 'OR'),
            new TagCondition('region', '=', 'eu-west', 'OR'),
        ];

        $files = $this->strategy->resolveFilePaths('cpu_usage', $conditions);

        $this->assertCount(2, $files);
        $this->assertStringContainsString('cpu_usage_host-server3_region-eu.west_env-prod.rrd', $files[0]);
        $this->assertStringContainsString('cpu_usage_host-server1_region-us.east_env-prod.rrd', $files[1]);
    }
}
