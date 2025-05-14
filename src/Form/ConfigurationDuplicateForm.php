<?php declare(strict_types=1);

namespace OaiPmhHarvester\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class ConfigurationDuplicateForm extends Form
{
    public function init()
    {
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
}
