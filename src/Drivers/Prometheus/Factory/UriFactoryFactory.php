<?php

namespace TimeSeriesPhp\Drivers\Prometheus\Factory;

use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Message\UriFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default implementation of UriFactoryFactoryInterface.
 */
class UriFactoryFactory implements UriFactoryFactoryInterface
{
    /**
     * @var ContainerInterface The service container
     */
    private ContainerInterface $container;

    /**
     * Constructor
     *
     * @param ContainerInterface|null $container The service container
     */
    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container ?? \TimeSeriesPhp\Core\ContainerFactory::create();
    }

    /**
     * Create a new URI factory.
     *
     * @return UriFactoryInterface The URI factory
     */
    public function create(): UriFactoryInterface
    {
        // If the container has a UriFactoryInterface service, use it
        if ($this->container->has(UriFactoryInterface::class)) {
            return $this->container->get(UriFactoryInterface::class);
        }

        // Fall back to discovery if the container doesn't have a factory
        return Psr17FactoryDiscovery::findUriFactory();
    }
}
