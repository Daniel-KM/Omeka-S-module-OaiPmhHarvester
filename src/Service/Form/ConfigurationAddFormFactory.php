<?php declare(strict_types=1);

namespace OaiPmhHarvester\Service\Form;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use OaiPmhHarvester\Form\ConfigurationAddForm;

class ConfigurationAddFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new ConfigurationAddForm(null, $options ?? []);

        $form->setConverterManager($services->get('OaiPmhHarvester\ConverterManager'));

        return $form;
    }
}
