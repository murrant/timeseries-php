<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Laravel;

use GuzzleHttp\Client as GuzzleClient;
use Http\Adapter\Guzzle7\Client as GuzzleAdapter;
use Illuminate\Support\ServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use TimeSeriesPhp\Contracts\Connection\ConnectionAdapterInterface;
use TimeSeriesPhp\Contracts\Query\QueryBuilderInterface;
use TimeSeriesPhp\Core\Attributes\Driver as DriverAttribute;
use TimeSeriesPhp\Core\Driver\DriverFactory;
use TimeSeriesPhp\Drivers\Aggregate\AggregateDriver;
use TimeSeriesPhp\Drivers\Graphite\GraphiteDriver;
use TimeSeriesPhp\Drivers\InfluxDB\Connection\HttpConnectionAdapter;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;
use TimeSeriesPhp\Drivers\Null\NullDriver;
use TimeSeriesPhp\Drivers\Prometheus\PrometheusDriver;
use TimeSeriesPhp\Drivers\RRDtool\Connection\LocalConnectionAdapter;
use TimeSeriesPhp\Drivers\RRDtool\Connection\PersistentProcessConnectionAdapter;
use TimeSeriesPhp\Drivers\RRDtool\Connection\RRDCachedConnectionAdapter;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolDriver;
use TimeSeriesPhp\Laravel\Facades\TSDB as TSDBFacade;
use TimeSeriesPhp\TSDB;

class TimeSeriesServiceProvider extends ServiceProvider
{
    /**
     * All driver classes that should be registered.
     *
     * @var array<class-string>
     */
    protected array $driverClasses = [
        AggregateDriver::class,
        GraphiteDriver::class,
        InfluxDBDriver::class,
        NullDriver::class,
        PrometheusDriver::class,
        RRDtoolDriver::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/timeseries.php', 'timeseries'
        );

        // Register the driver factory
        $this->app->singleton(DriverFactory::class, function ($app) {
            return new DriverFactory($app);
        });

        // Register all drivers and their related classes
        $this->registerDriversAndConfigs();

        // Register the TSDB class
        $this->app->singleton(TSDB::class, function ($app) {
            $config = $app['config']['timeseries'];
            $driver = $config['default'] ?? 'influxdb';
            $driverConfig = $config['drivers'][$driver] ?? [];

            return new TSDB($driver, $driverConfig, true, $app);
        });

        // Register the TSDB facade
        $this->app->alias(TSDB::class, 'timeseries');

        // Register PSR Logger if not already registered
        if (! $this->app->bound(LoggerInterface::class)) {
            $this->app->bind(LoggerInterface::class, function ($app) {
                return $app['log']->channel();
            });
        }

        // Register ConnectionAdapterInterface implementations
        $this->app->singleton(HttpConnectionAdapter::class);
        $this->app->singleton(LocalConnectionAdapter::class);
        $this->app->singleton(PersistentProcessConnectionAdapter::class);
        $this->app->singleton(RRDCachedConnectionAdapter::class);

        // Bind ConnectionAdapterInterface to HttpConnectionAdapter for InfluxDB
        $this->app->bind(ConnectionAdapterInterface::class, HttpConnectionAdapter::class);

        // Create aliases for connection adapters
        $this->app->alias(HttpConnectionAdapter::class, 'timeseries.influxdb.connection_adapter');
        $this->app->alias(LocalConnectionAdapter::class, 'timeseries.rrdtool.connection_adapter');

        // Register PSR HTTP interfaces
        $this->registerPsrHttpInterfaces();

        // Bind QueryBuilderInterface to the current driver's query builder
        $this->app->bind(QueryBuilderInterface::class, function ($app) {
            $config = $app['config']['timeseries'] ?? [];
            $driver = $config['default'] ?? 'influxdb';

            // Try resolving via the alias created for the driver's query builder
            $aliasId = sprintf('timeseries.%s.query_builder', $driver);
            if ($app->bound($aliasId)) {
                return $app->make($aliasId);
            }

            // Fallback: attempt to resolve via driver attribute metadata
            /** @var array<string, class-string>|null $drivers */
            $drivers = $app->bound('timeseries.drivers') ? $app->make('timeseries.drivers') : null;
            $driverClass = $drivers[$driver] ?? null;
            if (is_string($driverClass) && class_exists($driverClass)) {
                $reflectionClass = new ReflectionClass($driverClass);
                $attributes = $reflectionClass->getAttributes(DriverAttribute::class);
                if (! empty($attributes)) {
                    /** @var DriverAttribute $attr */
                    $attr = $attributes[0]->newInstance();
                    if ($attr->queryBuilderClass && class_exists($attr->queryBuilderClass)) {
                        return $app->make($attr->queryBuilderClass);
                    }
                }
            }

            // Absolute fallback to avoid container resolution failure: use NullQueryBuilder by default
            return $app->make(\TimeSeriesPhp\Drivers\Null\NullQueryBuilder::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish the config file
        $this->publishes([
            __DIR__.'/../../config/timeseries.php' => config_path('timeseries.php'),
        ], 'timeseries-config');

        // Register the facade
        $this->app->booting(function () {
            $this->app->alias(TSDBFacade::class, 'TSDB');
        });
    }

    /**
     * Register PSR HTTP interfaces.
     */
    protected function registerPsrHttpInterfaces(): void
    {
        // Create a Nyholm PSR-17 factory
        $this->app->singleton(Psr17Factory::class, function () {
            return new Psr17Factory;
        });

        // Bind PSR-17 interfaces to Nyholm implementations
        $this->app->bind(RequestFactoryInterface::class, Psr17Factory::class);
        $this->app->bind(StreamFactoryInterface::class, Psr17Factory::class);

        // Bind PSR-18 ClientInterface to Guzzle adapter
        $this->app->bind(ClientInterface::class, function ($app) {
            $guzzleClient = new GuzzleClient([
                'timeout' => 30,
                'verify' => true,
            ]);

            return new GuzzleAdapter($guzzleClient);
        });
    }

    /**
     * Register all drivers and their related classes.
     */
    protected function registerDriversAndConfigs(): void
    {
        $drivers = [];

        foreach ($this->driverClasses as $driverClass) {
            // Skip if the class doesn't exist
            if (! class_exists($driverClass)) {
                continue;
            }

            // Get the reflection class
            $reflectionClass = new ReflectionClass($driverClass);

            // Check if the class has the Driver attribute
            $attributes = $reflectionClass->getAttributes(DriverAttribute::class);
            if (empty($attributes)) {
                continue;
            }

            // Get the attribute instance
            $driverAttribute = $attributes[0]->newInstance();
            $driverName = $driverAttribute->name;

            // Register the driver class
            $this->app->singleton($driverClass);
            $this->app->tag($driverClass, ['timeseries.driver']);

            // Add the driver to the drivers array
            $drivers[$driverName] = $driverClass;

            // Register a PSR-11 friendly alias for the driver so factories can resolve it
            // Example: timeseries.driver.influxdb -> InfluxDBDriver::class
            $driverAliasId = sprintf('timeseries.driver.%s', $driverName);
            $this->app->alias($driverClass, $driverAliasId);

            // Register the config class if specified
            if ($driverAttribute->configClass && class_exists($driverAttribute->configClass)) {
                $this->app->singleton($driverAttribute->configClass);
            }

            // Register the query builder class if specified
            if ($driverAttribute->queryBuilderClass && class_exists($driverAttribute->queryBuilderClass)) {
                $this->app->singleton($driverAttribute->queryBuilderClass);

                // Create an alias for the query builder
                $aliasId = sprintf('timeseries.%s.query_builder', $driverName);
                $this->app->alias($driverAttribute->queryBuilderClass, $aliasId);
            }

            // Register the schema manager class if specified
            if ($driverAttribute->schemaManagerClass && class_exists($driverAttribute->schemaManagerClass)) {
                $this->app->singleton($driverAttribute->schemaManagerClass);

                // Create an alias for the schema manager
                $aliasId = sprintf('timeseries.%s.schema_manager', $driverName);
                $this->app->alias($driverAttribute->schemaManagerClass, $aliasId);
            }
        }

        // Register the drivers in the container
        $this->app->instance('timeseries.drivers', $drivers);
    }
}
