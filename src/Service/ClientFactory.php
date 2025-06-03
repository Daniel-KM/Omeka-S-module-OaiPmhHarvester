<?php

namespace OaiPmhHarvester\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use OaiPmhHarvester\Client;

class ClientFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $httpClient = $services->get('Omeka\HttpClient');
        $logger = $services->get('Omeka\Logger');

        $client = new Client($httpClient, $logger);

        return $client;
    }
}
