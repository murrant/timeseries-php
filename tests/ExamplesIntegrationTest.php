<?php

namespace TimeSeriesPhp\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Integration test that runs all the examples.
 * The examples use the containers set up in docker compose.
 *
 * @group integration
 */
class ExamplesIntegrationTest extends TestCase
{
    /**
     * @var array<string> List of example files to run
     */
    private array $exampleFiles = [
        'aggregation_queries.php',
        'batch_writing.php',
        'error_handling.php',
        'graphite_example.php',
        'influxdb_example.php',
        'prometheus_example.php',
        'rrdtool_example.php',
        'simplified_api.php',
    ];

    /**
     * @var string Path to the examples directory
     */
    private string $examplesDir;

    protected function setUp(): void
    {
        // Skip test if exec function is not available
        if (! function_exists('exec')) {
            $this->markTestSkipped('exec function is not available');
        }

        // Set the examples directory
        $this->examplesDir = rtrim(dirname(__DIR__).'/examples', '/').'/';

        // Ensure the examples directory exists
        if (! is_dir($this->examplesDir)) {
            $this->fail('Examples directory does not exist: '.$this->examplesDir);
        }

        // Check if Docker containers are running
        exec('docker ps | grep timeseries-php', $output, $returnCode);
        if ($returnCode !== 0) {
            $this->markTestSkipped('Docker containers are not running. Run docker/run-integration-tests.sh to start them.');
        }
    }

    /**
     * Test that all examples run without errors
     */
    public function test_all_examples(): void
    {
        foreach ($this->exampleFiles as $exampleFile) {
            $this->runExample($exampleFile);
        }
    }

    /**
     * Run an example file and verify it completes without errors
     */
    private function runExample(string $exampleFile): void
    {
        $filePath = $this->examplesDir.$exampleFile;

        // Skip laravel_integration.php as it requires a Laravel application
        if ($exampleFile === 'laravel_integration.php') {
            $this->markTestSkipped('Skipping Laravel integration example as it requires a Laravel application');
        }

        // Ensure the example file exists
        $this->assertFileExists($filePath, "Example file does not exist: $filePath");

        // Run the example file and capture output
        $command = sprintf('php %s 2>&1', escapeshellarg($filePath));
        exec($command, $output, $returnCode);

        // Output the result for debugging
        echo "\nRunning example: $exampleFile\n";
        echo implode("\n", $output)."\n";

        // Check if the example completed successfully
        $this->assertEquals(0, $returnCode, "Example $exampleFile failed with return code $returnCode");

        // Check if the output contains "Example completed successfully"
        $outputString = implode("\n", $output);
        $this->assertStringContainsString('Example completed successfully', $outputString, "Example $exampleFile did not complete successfully");
    }
}
