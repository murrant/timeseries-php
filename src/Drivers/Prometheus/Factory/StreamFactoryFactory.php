<?php

namespace TimeSeriesPhp\Drivers\Prometheus\Factory;

use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default implementation of StreamFactoryFactoryInterface.
 */
class StreamFactoryFactory implements StreamFactoryFactoryInterface
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
     * Create a new stream factory.
     *
     * @return StreamFactoryInterface The stream factory
     */
    public function create(): StreamFactoryInterface
    {
        // If the container has a StreamFactoryInterface service, use it
        if ($this->container->has(StreamFactoryInterface::class)) {
            return $this->container->get(StreamFactoryInterface::class);
        }

        // Fall back to discovery if the container doesn't have a factory
        return Psr17FactoryDiscovery::findStreamFactory();
    }
}
