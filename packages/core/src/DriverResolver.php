<?php

declare(strict_types=1);

namespace TimeseriesPhp\Core;

use Composer\InstalledVersions;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use TimeseriesPhp\Core\Attributes\TsdbDriver;
use TimeseriesPhp\Core\Exceptions\DriverException;
use TimeseriesPhp\Core\Exceptions\DriverNotFoundException;

final class DriverResolver
{
    /**
     * @param  class-string  $driverClass
     *
     * @throws DriverException
     * @throws DriverNotFoundException
     */
    public static function resolve(string $driverClass, ?LoggerInterface $logger = null): TsdbDriver
    {
        $logger ??= new NullLogger;
        if (! class_exists($driverClass)) {
            $logger->error('Driver not found', ['class' => $driverClass]);
            throw new DriverNotFoundException('Driver not found: '.$driverClass);
        }

        $reflection = new ReflectionClass($driverClass);
        $attributes = $reflection->getAttributes(TsdbDriver::class);

        if (empty($attributes)) {
            $logger->error('Driver missing TsdbDriver attribute', ['class' => $driverClass]);
            throw new DriverException("Driver $driverClass must have TsdbDriver attribute");
        }

        /** @var TsdbDriver $newInstance */
        $newInstance = $attributes[0]->newInstance();

        $logger->debug('Driver resolved', ['name' => $newInstance->name, 'class' => $driverClass]);

        return $newInstance;
    }

    /**
     * @return array<string, class-string<TsdbDriver>>
     */
    public static function discoverDrivers(?LoggerInterface $logger = null): array
    {
        $logger ??= new NullLogger;
        $packageNames = InstalledVersions::getInstalledPackagesByType('timeseries-php-driver');

        if (empty($packageNames)) {
            $logger->debug('No driver packages found');

            return [];
        }

        $drivers = [];

        // FIXME maybe not parsing composer.json files every boot
        foreach ($packageNames as $packageName) {
            $packagePath = InstalledVersions::getInstallPath($packageName);
            $composerJsonPath = $packagePath.'/composer.json';
            if (file_exists($composerJsonPath)) {
                $packageJson = json_decode((string) file_get_contents($composerJsonPath), true);
                $driverClass = $packageJson['extra']['timeseries-php']['driver-class'] ?? null;
                if ($driverClass) {
                    $logger->debug('Discovered driver', ['package' => $packageName, 'class' => $driverClass]);
                    $drivers[$packageName] = $driverClass;
                }
            }
        }

        return $drivers;
    }

    /**
     * @return TsdbDriver[]
     *
     * @throws DriverException
     * @throws DriverNotFoundException
     */
    public static function resolveAll(?LoggerInterface $logger = null): array
    {
        $resolved = [];

        foreach (self::discoverDrivers($logger) as $class) {
            $driver = self::resolve($class, $logger);
            $resolved[$driver->name] = $driver;
        }

        return $resolved;
    }
}
