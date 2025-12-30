<?php

declare(strict_types=1);

namespace TimeseriesPhp\Core;

use Composer\InstalledVersions;
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
    public static function resolve(string $driverClass): TsdbDriver
    {
        if (! class_exists($driverClass)) {
            throw new DriverNotFoundException('Driver not found: '.$driverClass);
        }

        $reflection = new ReflectionClass($driverClass);
        $attributes = $reflection->getAttributes(TsdbDriver::class);

        if (empty($attributes)) {
            throw new DriverException("Driver $driverClass must have TsdbDriver attribute");
        }

        /** @var TsdbDriver $newInstance */
        $newInstance = $attributes[0]->newInstance();

        return $newInstance;
    }

    /**
     * @return array<string, class-string<TsdbDriver>>
     */
    public static function discoverDrivers(): array
    {
        $packageNames = InstalledVersions::getInstalledPackagesByType('timeseries-php-driver');

        if (empty($packageNames)) {
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
    public static function resolveAll(): array
    {
        $resolved = [];

        foreach (self::discoverDrivers() as $class) {
            $driver = self::resolve($class);
            $resolved[$driver->name] = $driver;
        }

        return $resolved;
    }
}
