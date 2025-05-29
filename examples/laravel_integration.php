<?php

require_once __DIR__.'/../vendor/autoload.php';

use TimeSeriesPhp\Core\TSDBFactory;

/**
 * Laravel Integration Example
 * 
 * This example demonstrates how to use TimeSeriesPhp with Laravel, including
 * configuration, service provider, facade, and dependency injection.
 * 
 * Note: This is a simulation of Laravel integration since we can't run a full
 * Laravel application in this example. The code shows how you would integrate
 * TimeSeriesPhp in a real Laravel application.
 */

echo "TimeSeriesPhp Laravel Integration Example\n";
echo "=======================================\n\n";

// Step 1: Laravel Configuration
echo "Step 1: Laravel Configuration...\n";

// In a real Laravel application, you would create a config file at config/time-series.php
// Here we'll simulate the Laravel configuration array
$laravelConfig = [
    // Default driver to use
    'driver' => 'influxdb',

    // Available drivers
    'drivers' => [
        'influxdb' => [
            'url' => 'http://localhost:8086',
            'token' => file_exists(__DIR__ . '/.influx_db_token') 
                ? trim(file_get_contents(__DIR__ . '/.influx_db_token')) 
                : 'your-token',
            'org' => 'example-org',
            'bucket' => 'example-bucket',
            'timeout' => 30,
            'verify_ssl' => true,
            'debug' => false,
            'precision' => 'ns',
        ],

        'prometheus' => [
            'url' => 'http://localhost:9090',
            // Add authentication if needed
            // 'username' => 'your-username',
            // 'password' => 'your-password',
        ],

        'graphite' => [
            'host' => 'localhost',
            'port' => 2003,
            'protocol' => 'tcp',
            'prefix' => 'laravel.',
        ],

        'rrdtool' => [
            'rrdtool_path' => '/usr/bin/rrdtool',
            'rrd_dir' => __DIR__ . '/rrd_files',
            'use_rrdcached' => false,
            'rrdcached_address' => '',
            'default_step' => 300, // 5 minutes
            'tag_strategy' => \TimeSeriesPhp\Drivers\RRDtool\Tags\FileNameStrategy::class,
            'default_archives' => [
                'RRA:AVERAGE:0.5:1:2016',      // 5min for 1 week
                'RRA:AVERAGE:0.5:12:1488',     // 1hour for 2 months
                'RRA:AVERAGE:0.5:288:366',     // 1day for 1 year
                'RRA:MAX:0.5:1:2016',          // 5min max for 1 week
                'RRA:MAX:0.5:12:1488',         // 1hour max for 2 months
                'RRA:MIN:0.5:1:2016',          // 5min min for 1 week
                'RRA:MIN:0.5:12:1488',         // 1hour min for 2 months
            ],
        ],
    ],
];

echo "Laravel configuration would be stored in config/time-series.php\n";
echo "Example configuration:\n";
echo json_encode($laravelConfig, JSON_PRETTY_PRINT) . "\n";

// Step 2: Service Provider
echo "\nStep 2: Service Provider...\n";

// In a real Laravel application, you would create a service provider
// Here we'll simulate the service provider's register and boot methods

/**
 * Simulated Laravel Service Provider for TimeSeriesPhp
 */
class TimeSeriesServiceProvider
{
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function register()
    {
        echo "Registering TimeSeriesPhp services...\n";

        // Register the main TimeSeriesManager that handles connections
        $this->app['time-series'] = new TimeSeriesManager($this->app);

        // Register the facade
        $this->app['TimeSeries'] = function ($app) {
            return $app['time-series'];
        };

        // Register the base contract for dependency injection
        $this->app->bind('TimeSeriesPhp\Contracts\TimeSeriesInterface', function ($app) {
            return $app['time-series']->connection();
        });

        echo "TimeSeriesPhp services registered.\n";
    }

    public function boot()
    {
        echo "Booting TimeSeriesPhp services...\n";

        // In a real Laravel application, you would publish the config file
        // $this->publishes([
        //     __DIR__.'/../config/time-series.php' => config_path('time-series.php'),
        // ], 'time-series-config');

        echo "TimeSeriesPhp services booted.\n";
    }
}

/**
 * Simulated Laravel Application Container
 */
class Application implements \ArrayAccess
{
    protected $bindings = [];

    public function __construct()
    {
        $this->bindings['config'] = [
            'time-series' => $GLOBALS['laravelConfig']
        ];
    }

    public function bind($abstract, $concrete)
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function make($abstract, array $parameters = [])
    {
        if (isset($this->bindings[$abstract])) {
            if ($this->bindings[$abstract] instanceof \Closure) {
                return call_user_func($this->bindings[$abstract], $this);
            }

            return $this->bindings[$abstract];
        }

        return null;
    }

    public function offsetGet($key)
    {
        return $this->make($key);
    }

    public function offsetSet($key, $value)
    {
        $this->bindings[$key] = $value;
    }

    public function offsetExists($key)
    {
        return isset($this->bindings[$key]);
    }

    public function offsetUnset($key)
    {
        unset($this->bindings[$key]);
    }
}

/**
 * Simulated TimeSeriesManager for Laravel
 */
class TimeSeriesManager
{
    protected $app;
    protected $connections = [];

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function connection($name = null)
    {
        $name = $name ?: $this->getDefaultConnection();

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->makeConnection($name);
        }

        return $this->connections[$name];
    }

    protected function makeConnection($name)
    {
        $config = $this->getConnectionConfig($name);

        if (!isset($config['driver'])) {
            throw new \InvalidArgumentException("The time-series driver [{$name}] is missing a driver configuration.");
        }

        $driver = $config['driver'];
        $method = 'create' . ucfirst($driver) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method($config);
        }

        // Default driver creation
        return $this->createDriver($driver, $config);
    }

    protected function createDriver($driver, array $config)
    {
        // Create the driver configuration
        $configClass = "\\TimeSeriesPhp\\Drivers\\".ucfirst($driver)."\\".ucfirst($driver)."Config";
        $driverConfig = new $configClass($config);

        // Create the driver instance
        return TSDBFactory::create($driver, $driverConfig);
    }

    protected function createInfluxdbDriver(array $config)
    {
        // Special handling for InfluxDB if needed
        return $this->createDriver('influxdb', $config);
    }

    protected function getConnectionConfig($name)
    {
        $drivers = $this->app['config']['time-series']['drivers'];

        if (!isset($drivers[$name])) {
            throw new \InvalidArgumentException("Time-series driver [{$name}] is not configured.");
        }

        return $drivers[$name];
    }

    protected function getDefaultConnection()
    {
        return $this->app['config']['time-series']['driver'];
    }

    public function __call($method, $parameters)
    {
        return $this->connection()->$method(...$parameters);
    }
}

/**
 * Simulated Laravel Facade
 */
class TimeSeries
{
    protected static $app;

    public static function setApplication($app)
    {
        static::$app = $app;
    }

    public static function __callStatic($method, $args)
    {
        $instance = static::$app['TimeSeries'];

        return $instance->$method(...$args);
    }

    public static function connection($name = null)
    {
        return static::$app['time-series']->connection($name);
    }
}

// Create a simulated Laravel application
$app = new Application();

// Register the service provider
$serviceProvider = new TimeSeriesServiceProvider($app);
$serviceProvider->register();
$serviceProvider->boot();

// Set up the facade
TimeSeries::setApplication($app);

// Step 3: Using the Facade
echo "\nStep 3: Using the Facade...\n";

// In a Laravel controller, you would use the facade like this:
echo "Example of using the TimeSeries facade in a controller:\n";

echo "```php\n";
echo "namespace App\\Http\\Controllers;\n\n";
echo "use Illuminate\\Http\\Request;\n";
echo "use TimeSeriesPhp\\Support\\TimeSeriesFacade as TimeSeries;\n";
echo "use TimeSeriesPhp\\Core\\Data\\DataPoint;\n";
echo "use TimeSeriesPhp\\Core\\Query\\Query;\n\n";
echo "class MetricsController extends Controller\n";
echo "{\n";
echo "    public function store(Request \$request)\n";
echo "    {\n";
echo "        // Create a data point from the request\n";
echo "        \$dataPoint = new DataPoint(\n";
echo "            'api_requests',\n";
echo "            ['duration' => \$request->input('duration')],\n";
echo "            [\n";
echo "                'method' => \$request->method(),\n";
echo "                'path' => \$request->path(),\n";
echo "                'status' => 200\n";
echo "            ]\n";
echo "        );\n\n";
echo "        // Write the data point using the facade\n";
echo "        \$success = TimeSeries::write(\$dataPoint);\n\n";
echo "        return response()->json(['success' => \$success]);\n";
echo "    }\n\n";
echo "    public function index(Request \$request)\n";
echo "    {\n";
echo "        // Create a query\n";
echo "        \$query = new Query('api_requests');\n";
echo "        \$query->select(['duration'])\n";
echo "              ->where('method', '=', \$request->input('method', 'GET'))\n";
echo "              ->timeRange(new \\DateTime('-1 day'), new \\DateTime())\n";
echo "              ->groupByTime('1h')\n";
echo "              ->avg('duration', 'avg_duration');\n\n";
echo "        // Execute the query using the facade\n";
echo "        \$result = TimeSeries::query(\$query);\n\n";
echo "        return response()->json(\$result->getSeries());\n";
echo "    }\n";
echo "}\n";
echo "```\n";

// Step 4: Using Multiple Drivers
echo "\nStep 4: Using Multiple Drivers...\n";

// In a Laravel application, you can use multiple drivers
echo "Example of using multiple drivers:\n";

echo "```php\n";
echo "// Using the default driver\n";
echo "\$defaultDb = TimeSeries::connection();\n";
echo "\$defaultDb->write(\$dataPoint);\n\n";
echo "// Using a specific driver\n";
echo "\$influxDb = TimeSeries::connection('influxdb');\n";
echo "\$prometheusDb = TimeSeries::connection('prometheus');\n\n";
echo "\$influxDb->write(\$dataPoint);\n";
echo "\$prometheusDb->write(\$dataPoint);\n";
echo "```\n";

// Step 5: Dependency Injection
echo "\nStep 5: Dependency Injection...\n";

// In a Laravel controller, you can use dependency injection
echo "Example of using dependency injection in a controller:\n";

echo "```php\n";
echo "namespace App\\Http\\Controllers;\n\n";
echo "use Illuminate\\Http\\Request;\n";
echo "use TimeSeriesPhp\\Contracts\\TimeSeriesInterface;\n";
echo "use TimeSeriesPhp\\Core\\Data\\DataPoint;\n\n";
echo "class MetricsController extends Controller\n";
echo "{\n";
echo "    protected \$timeSeries;\n\n";
echo "    public function __construct(TimeSeriesInterface \$timeSeries)\n";
echo "    {\n";
echo "        \$this->timeSeries = \$timeSeries;\n";
echo "    }\n\n";
echo "    public function store(Request \$request)\n";
echo "    {\n";
echo "        \$dataPoint = new DataPoint(\n";
echo "            'api_requests',\n";
echo "            ['duration' => \$request->input('duration')],\n";
echo "            [\n";
echo "                'method' => \$request->method(),\n";
echo "                'path' => \$request->path(),\n";
echo "                'status' => 200\n";
echo "            ]\n";
echo "        );\n\n";
echo "        // Use the injected instance\n";
echo "        \$success = \$this->timeSeries->write(\$dataPoint);\n\n";
echo "        return response()->json(['success' => \$success]);\n";
echo "    }\n";
echo "}\n";
echo "```\n";

// Step 6: Laravel Middleware for Request Metrics
echo "\nStep 6: Laravel Middleware for Request Metrics...\n";

// Example of a middleware that records request metrics
echo "Example of a middleware that records request metrics:\n";

echo "```php\n";
echo "namespace App\\Http\\Middleware;\n\n";
echo "use Closure;\n";
echo "use Illuminate\\Http\\Request;\n";
echo "use TimeSeriesPhp\\Support\\TimeSeriesFacade as TimeSeries;\n\n";
echo "class TrackRequestMetrics\n";
echo "{\n";
echo "    public function handle(Request \$request, Closure \$next)\n";
echo "    {\n";
echo "        \$startTime = microtime(true);\n\n";
echo "        \$response = \$next(\$request);\n\n";
echo "        \$duration = microtime(true) - \$startTime;\n\n";
echo "        // Record request metrics\n";
echo "        \$dataPoint = new DataPoint(\n";
echo "            'http_requests',\n";
echo "            ['duration' => \$duration * 1000], // Convert to milliseconds\n";
echo "            [\n";
echo "                'method' => \$request->method(),\n";
echo "                'path' => \$request->path(),\n";
echo "                'status' => \$response->getStatusCode(),\n";
echo "                'route' => \$request->route() ? \$request->route()->getName() : 'unknown'\n";
echo "            ]\n";
echo "        );\n\n";
echo "        TimeSeries::write(\$dataPoint);\n\n";
echo "        return \$response;\n";
echo "    }\n";
echo "}\n";
echo "```\n";

// Step 7: Laravel Command for Maintenance Tasks
echo "\nStep 7: Laravel Command for Maintenance Tasks...\n";

// Example of a Laravel command for database maintenance
echo "Example of a Laravel command for database maintenance:\n";

echo "```php\n";
echo "namespace App\\Console\\Commands;\n\n";
echo "use Illuminate\\Console\\Command;\n";
echo "use TimeSeriesPhp\\Support\\TimeSeriesFacade as TimeSeries;\n\n";
echo "class CleanupTimeSeriesData extends Command\n";
echo "{\n";
echo "    protected \$signature = 'time-series:cleanup {--days=30 : Number of days to keep}'\n";
echo "    protected \$description = 'Clean up old time series data';\n\n";
echo "    public function handle()\n";
echo "    {\n";
echo "        \$days = \$this->option('days');\n";
echo "        \$this->info(\"Cleaning up time series data older than {\$days} days...\");\n\n";
echo "        \$measurements = ['api_requests', 'http_requests', 'system_metrics'];\n\n";
echo "        foreach (\$measurements as \$measurement) {\n";
echo "            \$endTime = new \\DateTime(\"-{\$days} days\");\n";
echo "            \$success = TimeSeries::deleteMeasurement(\$measurement, null, \$endTime);\n\n";
echo "            if (\$success) {\n";
echo "                \$this->info(\"Successfully cleaned up {\$measurement} data.\");\n";
echo "            } else {\n";
echo "                \$this->error(\"Failed to clean up {\$measurement} data.\");\n";
echo "            }\n";
echo "        }\n\n";
echo "        \$this->info('Cleanup completed.');\n";
echo "    }\n";
echo "}\n";
echo "```\n";

// Step 8: Practical Example - Dashboard Data
echo "\nStep 8: Practical Example - Dashboard Data...\n";

// Example of a controller method that provides data for a dashboard
echo "Example of a controller method that provides data for a dashboard:\n";

echo "```php\n";
echo "namespace App\\Http\\Controllers;\n\n";
echo "use Illuminate\\Http\\Request;\n";
echo "use TimeSeriesPhp\\Support\\TimeSeriesFacade as TimeSeries;\n";
echo "use TimeSeriesPhp\\Core\\Query\\Query;\n\n";
echo "class DashboardController extends Controller\n";
echo "{\n";
echo "    public function metrics(Request \$request)\n";
echo "    {\n";
echo "        // Get time range from request or use defaults\n";
echo "        \$startTime = \$request->input('start') \n";
echo "            ? new \\DateTime(\$request->input('start')) \n";
echo "            : new \\DateTime('-24 hours');\n\n";
echo "        \$endTime = \$request->input('end') \n";
echo "            ? new \\DateTime(\$request->input('end')) \n";
echo "            : new \\DateTime();\n\n";
echo "        // API request duration over time\n";
echo "        \$apiDurationQuery = new Query('api_requests');\n";
echo "        \$apiDurationQuery->select(['duration'])\n";
echo "                         ->timeRange(\$startTime, \$endTime)\n";
echo "                         ->groupByTime('5m')\n";
echo "                         ->avg('duration', 'avg_duration')\n";
echo "                         ->max('duration', 'max_duration')\n";
echo "                         ->percentile('duration', 95, 'p95_duration');\n\n";
echo "        \$apiDuration = TimeSeries::query(\$apiDurationQuery)->getSeries();\n\n";
echo "        // HTTP request count by status code\n";
echo "        \$httpStatusQuery = new Query('http_requests');\n";
echo "        \$httpStatusQuery->select(['duration'])\n";
echo "                        ->timeRange(\$startTime, \$endTime)\n";
echo "                        ->groupBy(['status'])\n";
echo "                        ->count('duration', 'request_count');\n\n";
echo "        \$httpStatus = TimeSeries::query(\$httpStatusQuery)->getSeries();\n\n";
echo "        // System metrics\n";
echo "        \$systemQuery = new Query('system_metrics');\n";
echo "        \$systemQuery->select(['cpu', 'memory', 'disk'])\n";
echo "                    ->timeRange(\$startTime, \$endTime)\n";
echo "                    ->groupByTime('5m')\n";
echo "                    ->avg('cpu', 'avg_cpu')\n";
echo "                    ->avg('memory', 'avg_memory')\n";
echo "                    ->avg('disk', 'avg_disk');\n\n";
echo "        \$systemMetrics = TimeSeries::query(\$systemQuery)->getSeries();\n\n";
echo "        return response()->json([\n";
echo "            'api_duration' => \$apiDuration,\n";
echo "            'http_status' => \$httpStatus,\n";
echo "            'system_metrics' => \$systemMetrics,\n";
echo "        ]);\n";
echo "    }\n";
echo "}\n";
echo "```\n";

// Step 9: Using the DriverManager
echo "\nStep 9: Using the DriverManager...\n";

// Example of using the DriverManager class
echo "Example of using the DriverManager class:\n";

echo "```php\n";
echo "namespace App\\Services;\n\n";
echo "use TimeSeriesPhp\\Core\\Factory\\DriverManager;\n";
echo "use TimeSeriesPhp\\Contracts\\Driver\\TimeSeriesInterface;\n\n";
echo "class TimeSeriesService\n";
echo "{\n";
echo "    protected DriverManager \$driverManager;\n\n";
echo "    public function __construct(DriverManager \$driverManager)\n";
echo "    {\n";
echo "        \$this->driverManager = \$driverManager;\n";
echo "    }\n\n";
echo "    public function getDriver(string \$driver): TimeSeriesInterface\n";
echo "    {\n";
echo "        // Create a driver instance with the DriverManager\n";
echo "        return \$this->driverManager->create(\$driver);\n";
echo "    }\n\n";
echo "    public function listAvailableDrivers(): array\n";
echo "    {\n";
echo "        // Get a list of all registered drivers\n";
echo "        return \$this->driverManager->getAvailableDrivers();\n";
echo "    }\n\n";
echo "    public function registerCustomDriver(string \$name, string \$className, ?string \$configClassName = null): void\n";
echo "    {\n";
echo "        // Register a custom driver\n";
echo "        \$this->driverManager->registerDriver(\$name, \$className, \$configClassName);\n";
echo "    }\n";
echo "}\n";
echo "```\n";

// Step 10: Using Driver-Specific Raw Queries
echo "\nStep 10: Using Driver-Specific Raw Queries...\n";

// Example of using the InfluxDBRawQuery class
echo "Example of using the InfluxDBRawQuery class:\n";

echo "```php\n";
echo "namespace App\\Http\\Controllers;\n\n";
echo "use Illuminate\\Http\\Request;\n";
echo "use TimeSeriesPhp\\Support\\TimeSeriesFacade as TimeSeries;\n";
echo "use TimeSeriesPhp\\Drivers\\InfluxDB\\Query\\InfluxDBRawQuery;\n\n";
echo "class AdvancedMetricsController extends Controller\n";
echo "{\n";
echo "    public function queryWithFlux(Request \$request)\n";
echo "    {\n";
echo "        // Create a Flux query using the InfluxDBRawQuery class\n";
echo "        \$fluxQuery = new InfluxDBRawQuery(\n";
echo "            'from(bucket: \"' . config('time-series.drivers.influxdb.bucket') . '\")'\n";
echo "            . '|> range(start: -24h)'\n";
echo "            . '|> filter(fn: (r) => r._measurement == \"api_requests\")'\n";
echo "            . '|> filter(fn: (r) => r._field == \"duration\")'\n";
echo "            . '|> aggregateWindow(every: 1h, fn: mean)'\n";
echo "            . '|> yield(name: \"mean\")',\n";
echo "            true // isFlux parameter\n";
echo "        );\n\n";
echo "        // Execute the raw query\n";
echo "        \$result = TimeSeries::connection('influxdb')->rawQuery(\$fluxQuery);\n\n";
echo "        return response()->json(\$result->getSeries());\n";
echo "    }\n\n";
echo "    public function queryWithInfluxQL(Request \$request)\n";
echo "    {\n";
echo "        // Create an InfluxQL query using the InfluxDBRawQuery class\n";
echo "        \$influxQLQuery = new InfluxDBRawQuery(\n";
echo "            'SELECT mean(\"duration\") FROM \"api_requests\" '\n";
echo "            . 'WHERE time > now() - 24h '\n";
echo "            . 'GROUP BY time(1h)',\n";
echo "            false // isFlux parameter (false for InfluxQL)\n";
echo "        );\n\n";
echo "        // Execute the raw query\n";
echo "        \$result = TimeSeries::connection('influxdb')->rawQuery(\$influxQLQuery);\n\n";
echo "        return response()->json(\$result->getSeries());\n";
echo "    }\n";
echo "}\n";
echo "```\n";

// Step 11: Testing with Laravel
echo "\nStep 11: Testing with Laravel...\n";

// Example of testing TimeSeriesPhp in a Laravel application
echo "Example of testing TimeSeriesPhp in a Laravel application:\n";

echo "```php\n";
echo "namespace Tests\\Feature;\n\n";
echo "use Tests\\TestCase;\n";
echo "use Mockery;\n";
echo "use TimeSeriesPhp\\Contracts\\TimeSeriesInterface;\n";
echo "use TimeSeriesPhp\\Core\\Data\\DataPoint;\n\n";
echo "class MetricsTest extends TestCase\n";
echo "{\n";
echo "    public function testStoreMetrics()\n";
echo "    {\n";
echo "        // Mock the TimeSeriesInterface\n";
echo "        \$mock = Mockery::mock(TimeSeriesInterface::class);\n";
echo "        \$mock->shouldReceive('write')\n";
echo "             ->once()\n";
echo "             ->with(Mockery::type(DataPoint::class))\n";
echo "             ->andReturn(true);\n\n";
echo "        // Bind the mock to the container\n";
echo "        \$this->app->instance(TimeSeriesInterface::class, \$mock);\n\n";
echo "        // Make the request\n";
echo "        \$response = \$this->postJson('/api/metrics', [\n";
echo "            'duration' => 150,\n";
echo "            'method' => 'POST',\n";
echo "            'path' => '/api/users',\n";
echo "        ]);\n\n";
echo "        // Assert the response\n";
echo "        \$response->assertStatus(200)\n";
echo "                 ->assertJson(['success' => true]);\n";
echo "    }\n";
echo "}\n";
echo "```\n";

echo "\nExample completed successfully!\n";
echo "Note: This is a simulation of Laravel integration. In a real Laravel application, you would need to install the package and follow the Laravel integration instructions in the documentation.\n";
