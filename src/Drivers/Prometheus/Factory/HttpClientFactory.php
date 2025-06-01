<?php

namespace TimeSeriesPhp\Drivers\Prometheus\Factory;

use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default implementation of HttpClientFactoryInterface.
 */
class HttpClientFactory implements HttpClientFactoryInterface
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
     * Create a new HTTP client.
     *
     * @return ClientInterface The HTTP client
     */
    public function create(): ClientInterface
    {
        // If the container has a ClientInterface service, use it
        if ($this->container->has(ClientInterface::class)) {
            return $this->container->get(ClientInterface::class);
        }

        // Fall back to discovery if the container doesn't have a client
        return Psr18ClientDiscovery::find();
    }
}
