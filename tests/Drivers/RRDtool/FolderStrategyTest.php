<?php

namespace TimeSeriesPhp\Tests\Drivers\RRDtool;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Drivers\RRDtool\Exception\RRDtoolTagException;
use TimeSeriesPhp\Drivers\RRDtool\Tags\FolderStrategy;
use TimeSeriesPhp\Drivers\RRDtool\Tags\TagCondition;

class FolderStrategyTest extends TestCase
{
    private FolderStrategy $strategy;

    private string $baseDir;

    private string $tempDir;

    /**
     * @var string[]
     */
    private array $folderTags;

    protected function setUp(): void
    {
        // Create a temporary directory for testing
        $this->tempDir = sys_get_temp_dir().'/rrdtool_test_'.uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->baseDir = $this->tempDir.DIRECTORY_SEPARATOR;
        $this->folderTags = ['region', 'host'];
        $this->strategy = new FolderStrategy($this->baseDir, $this->folderTags);
    }

    protected function tearDown(): void
    {
        // Clean up temporary files and directories recursively
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object === '.' || $object === '..') {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$object;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function test_constructor_requires_trailing_slash(): void
    {
        $this->expectException(RRDtoolTagException::class);
        $this->expectExceptionMessage('Base directory must end with a slash');

        new FolderStrategy($this->tempDir, $this->folderTags); // Missing trailing slash
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

    public function test_get_file_path_with_folder_tags(): void
    {
        $measurement = 'cpu_usage';
        $tags = [
            'region' => 'us-east',
            'host' => 'server1',
            'env' => 'prod',
        ];

        // Should create folders for 'region' and 'host', and encode 'env' in the filename
        $expectedPath = $this->baseDir.'us-east/server1/cpu_usage_env-prod.rrd';

        $this->assertEquals($expectedPath, $this->strategy->getFilePath($measurement, $tags));

        // Verify that directories were created
        $this->assertDirectoryExists($this->baseDir.'us-east');
        $this->assertDirectoryExists($this->baseDir.'us-east/server1');
    }

    public function test_get_file_path_with_missing_folder_tags(): void
    {
        $measurement = 'cpu_usage';
        $tags = [
            'region' => 'us-east',
            // 'host' is missing
            'env' => 'prod',
        ];

        // Should create folder for 'region', use '_unset' for missing 'host', and encode 'env' in the filename
        $expectedPath = $this->baseDir.'us-east/_unset/cpu_usage_env-prod.rrd';

        $this->assertEquals($expectedPath, $this->strategy->getFilePath($measurement, $tags));

        // Verify that directories were created
        $this->assertDirectoryExists($this->baseDir.'us-east');
        $this->assertDirectoryExists($this->baseDir.'us-east/_unset');
    }

    public function test_resolve_file_paths_with_no_conditions(): void
    {
        // Create test directory structure and files
        mkdir($this->baseDir.'us-east/server1', 0777, true);
        mkdir($this->baseDir.'us-west/server2', 0777, true);
        file_put_contents($this->baseDir.'us-east/server1/cpu_usage_env-prod.rrd', 'test');
        file_put_contents($this->baseDir.'us-west/server2/cpu_usage_env-dev.rrd', 'test');
        file_put_contents($this->baseDir.'us-east/server1/memory_usage_env-prod.rrd', 'test');

        $files = $this->strategy->resolveFilePaths('cpu', []);

        $this->assertCount(2, $files);
        $this->assertStringContainsString('us-east/server1/cpu_usage_env-prod.rrd', $files[0]);
        $this->assertStringContainsString('us-west/server2/cpu_usage_env-dev.rrd', $files[1]);
    }

    public function test_resolve_file_paths_with_tag_conditions(): void
    {
        // Create test directory structure and files
        mkdir($this->baseDir.'us-east/server1', 0777, true);
        mkdir($this->baseDir.'us-west/server2', 0777, true);
        file_put_contents($this->baseDir.'us-east/server1/cpu_usage_env-prod.rrd', 'test');
        file_put_contents($this->baseDir.'us-west/server2/cpu_usage_env-dev.rrd', 'test');
        file_put_contents($this->baseDir.'us-east/server1/memory_usage_env-prod.rrd', 'test');

        $conditions = [
            new TagCondition('region', '=', 'us-east'),
            new TagCondition('host', '=', 'server1'),
            new TagCondition('env', '=', 'prod'),
        ];

        $files = $this->strategy->resolveFilePaths('cpu_usage', $conditions);

        $this->assertCount(1, $files);
        $this->assertStringContainsString('us-east/server1/cpu_usage_env-prod.rrd', $files[0]);
    }

    public function test_find_measurements_by_tags(): void
    {
        // Create test directory structure and files
        mkdir($this->baseDir.'us-east/server1', 0777, true);
        mkdir($this->baseDir.'us-west/server2', 0777, true);
        file_put_contents($this->baseDir.'us-east/server1/cpu_usage_env-prod.rrd', 'test');
        file_put_contents($this->baseDir.'us-east/server1/memory_usage_env-prod.rrd', 'test');
        file_put_contents($this->baseDir.'us-west/server2/disk_usage_env-dev.rrd', 'test');

        $conditions = [
            new TagCondition('region', '=', 'us-east'),
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
        // Create test directory structure and files
        mkdir($this->baseDir.'us-east/server1', 0777, true);
        mkdir($this->baseDir.'us-west/server2', 0777, true);
        mkdir($this->baseDir.'eu-west/server3', 0777, true);
        file_put_contents($this->baseDir.'us-east/server1/cpu_usage_env-prod.rrd', 'test');
        file_put_contents($this->baseDir.'us-west/server2/cpu_usage_env-dev.rrd', 'test');
        file_put_contents($this->baseDir.'eu-west/server3/cpu_usage_env-prod.rrd', 'test');

        $conditions = [
            new TagCondition('env', '=', 'prod'),
            new TagCondition('region', '=', 'us-east', 'OR'),
            new TagCondition('region', '=', 'eu-west', 'OR'),
        ];

        $files = $this->strategy->resolveFilePaths('cpu', $conditions);

        $this->assertCount(2, $files);
        $this->assertCount(1, array_filter($files, fn ($f) => str_contains($f, 'us-east/server1/cpu_usage_env-prod.rrd')));
        $this->assertCount(1, array_filter($files, fn ($f) => str_contains($f, 'eu-west/server3/cpu_usage_env-prod.rrd')));
    }

    public function test_optimized_search_path(): void
    {
        // Create test directory structure and files
        mkdir($this->baseDir.'us-east/server1', 0777, true);
        mkdir($this->baseDir.'us-west/server2', 0777, true);
        file_put_contents($this->baseDir.'us-east/server1/cpu_usage_env-prod.rrd', 'test');
        file_put_contents($this->baseDir.'us-west/server2/cpu_usage_env-dev.rrd', 'test');

        // This should optimize the search to only look in us-east directory
        $conditions = [
            new TagCondition('region', '=', 'us-east'),
            new TagCondition('env', '=', 'prod'),
        ];

        $files = $this->strategy->resolveFilePaths('cpu', $conditions);

        $this->assertCount(1, $files);
        $this->assertStringContainsString('us-east/server1/cpu_usage_env-prod.rrd', $files[0]);
    }
}
