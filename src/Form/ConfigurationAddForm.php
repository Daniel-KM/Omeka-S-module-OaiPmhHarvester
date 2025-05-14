<?php declare(strict_types=1);

namespace OaiPmhHarvester\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;
use OaiPmhHarvester\Converter\Manager as ConverterManager;

class ConfigurationAddForm extends Form
{
    protected ConverterManager $converterManager;

    public function init()
    {
        $this->add([
            'name' => 'o:converter_name',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Converter', // @translate
                'value_options' => $this->getConverterValueOptions(),
            ],
            'attributes' => [
                'id' => 'converter-name',
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'o:name',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Name', // @translate
            ],
            'attributes' => [
                'id' => 'name',
                'required' => true,
            ],
        ]);
    }

    protected function getConverterValueOptions(): array
    {
        $converters = $this->getConverterManager();
        $names = $converters->getRegisteredNames();

        return array_map(fn ($name) => ['value' => $name, 'label' => $converters->get($name)->getLabel()], $names);
    }

    public function setConverterManager(ConverterManager $converterManager)
    {
        $this->converterManager = $converterManager;
    }

    public function getConverterManager(): ConverterManager
    {
        return $this->converterManager;
    }
}
