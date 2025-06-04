<?php

namespace TimeSeriesPhp\Tests\Drivers\RRDtool;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Drivers\RRDtool\Exception\RRDtoolTagException;
use TimeSeriesPhp\Drivers\RRDtool\Tags\NoTagsStrategy;
use TimeSeriesPhp\Drivers\RRDtool\Tags\TagCondition;

class NoTagsStrategyTest extends TestCase
{
    private NoTagsStrategy $strategy;

    private string $baseDir;

    private string $tempDir;

    protected function setUp(): void
    {
        // Create a temporary directory for testing
        $this->tempDir = sys_get_temp_dir().'/rrdtool_test_'.uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->baseDir = $this->tempDir.DIRECTORY_SEPARATOR;
        $this->strategy = new NoTagsStrategy($this->baseDir);
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

        new NoTagsStrategy($this->tempDir); // Missing trailing slash
    }

    public function test_get_base_dir(): void
    {
        $this->assertEquals($this->baseDir, $this->strategy->getBaseDir());
    }

    public function test_get_file_path_ignores_tags(): void
    {
        $measurement = 'cpu_usage';
        $tags = [
            'host' => 'server1',
            'region' => 'us-east',
            'env' => 'prod',
        ];

        // Should ignore all tags and use only the measurement name
        $expectedPath = $this->baseDir.'cpu_usage.rrd';

        $this->assertEquals($expectedPath, $this->strategy->getFilePath($measurement, $tags));
        $this->assertEquals($expectedPath, $this->strategy->getFilePath($measurement)); // Same result with no tags
    }

    public function test_resolve_file_paths_ignores_tag_conditions(): void
    {
        // Create some test files
        file_put_contents($this->baseDir.'cpu_usage.rrd', 'test');
        file_put_contents($this->baseDir.'memory_usage.rrd', 'test');
        file_put_contents($this->baseDir.'disk_usage.rrd', 'test');

        // Even with tag conditions, it should return all files matching the measurement
        $conditions = [
            new TagCondition('host', '=', 'server1'),
            new TagCondition('region', '=', 'us-east'),
        ];

        $files = $this->strategy->resolveFilePaths('cpu', $conditions);

        $this->assertCount(1, $files);
        $this->assertStringContainsString('cpu_usage.rrd', $files[0]);
    }

    public function test_find_measurements_by_tags_ignores_tag_conditions(): void
    {
        // Create some test files
        file_put_contents($this->baseDir.'cpu_usage.rrd', 'test');
        file_put_contents($this->baseDir.'memory_usage.rrd', 'test');
        file_put_contents($this->baseDir.'disk_usage.rrd', 'test');

        // Even with tag conditions, it should return all measurements
        $conditions = [
            new TagCondition('host', '=', 'server1'),
            new TagCondition('region', '=', 'us-east'),
        ];

        $measurements = $this->strategy->findMeasurementsByTags($conditions);

        $this->assertCount(3, $measurements);
        $this->assertContains('cpu_usage', $measurements);
        $this->assertContains('memory_usage', $measurements);
        $this->assertContains('disk_usage', $measurements);
    }

    public function test_resolve_file_paths_with_wildcard_measurement(): void
    {
        // Create some test files
        file_put_contents($this->baseDir.'cpu_usage.rrd', 'test');
        file_put_contents($this->baseDir.'cpu_idle.rrd', 'test');
        file_put_contents($this->baseDir.'memory_usage.rrd', 'test');

        // Should return all files starting with 'cpu'
        $files = $this->strategy->resolveFilePaths('cpu', []);

        $this->assertCount(2, $files);
        $this->assertTrue(in_array($this->baseDir.'cpu_usage.rrd', $files) ||
                         in_array($this->baseDir.'cpu_idle.rrd', $files));
    }

    public function test_sanitizes_filenames(): void
    {
        $measurement = 'cpu/usage:with?invalid*chars';
        $expectedPath = $this->baseDir.'cpuusagewithinvalidchars.rrd';

        $this->assertEquals($expectedPath, $this->strategy->getFilePath($measurement));
    }
}
