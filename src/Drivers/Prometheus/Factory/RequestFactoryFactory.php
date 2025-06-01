<?php

namespace TimeSeriesPhp\Drivers\Prometheus\Factory;

use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Message\RequestFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default implementation of RequestFactoryFactoryInterface.
 */
class RequestFactoryFactory implements RequestFactoryFactoryInterface
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
     * Create a new request factory.
     *
     * @return RequestFactoryInterface The request factory
     */
    public function create(): RequestFactoryInterface
    {
        // If the container has a RequestFactoryInterface service, use it
        if ($this->container->has(RequestFactoryInterface::class)) {
            return $this->container->get(RequestFactoryInterface::class);
        }

        // Fall back to discovery if the container doesn't have a factory
        return Psr17FactoryDiscovery::findRequestFactory();
    }
}
