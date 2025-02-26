<?php declare(strict_types=1);

namespace OaiPmhHarvester\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;
use OaiPmhHarvester\Converter\ConfigurableConverterInterface;

class ConfigurationEditForm extends Form
{
    public function init()
    {
        $configuration = $this->getOption('configuration');

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

        $this->add([
            'name' => 'o:settings',
            'type' => Fieldset::class,
        ]);

        $converter = $configuration->converter();
        if ($converter instanceof ConfigurableConverterInterface) {
            $converter->addConfigurationFormElements($this);

            $inputFilter = $this->getInputFilter();
            $converter->addConfigurationFormInputFilters($this, $inputFilter);
        }
    }
}
