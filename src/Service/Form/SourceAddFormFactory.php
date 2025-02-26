<?php declare(strict_types=1);

namespace OaiPmhHarvester\Service\Form;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use OaiPmhHarvester\Form\SourceAddForm;

class SourceAddFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $apiManager = $services->get('Omeka\ApiManager');

        $form = new SourceAddForm(null, $options ?? []);

        $form->setApiManager($apiManager);

        return $form;
    }
}
