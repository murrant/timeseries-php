<?php

namespace TimeseriesPhp\Core\Discovery;

use Composer\InstalledVersions;

class DriverDiscovery
{
    /**
     * @return array<string, string> Map of ['driver_name' => 'FactoryClass']
     */
    public static function discover(): array
    {
        $drivers = [];
        $packages = InstalledVersions::getAllRawData();

        foreach ($packages[0]['versions'] as $packageName => $data) {
            if (!isset($data['type']) || $data['type'] !== 'timeseries-php-driver') {
                continue;
            }

            $installPath = InstalledVersions::getInstallPath($packageName);

            if ($installPath && file_exists($configFile = $installPath . '/composer.json')) {
                $manifest = json_decode(file_get_contents($configFile), true);

                $config = $manifest['extra']['timeseries-php'] ?? [];
                if (isset($config['driver']) && isset($config['factory'])) {
                    $drivers[$config['driver']] = $config['factory'];
                }
            }
        }

        return $drivers;
    }
}
