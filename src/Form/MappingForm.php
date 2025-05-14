<?php

namespace OaiPmhHarvester\Form;

use Laminas\Form\Element\Text;
use Laminas\Form\Form;

class MappingForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'name',
            'type' => Text::class,
            'options' => [
                'label' => 'Name', // @translate
            ],
            'attributes' => [
                'disabled' => true,
            ],
        ]);

        $this->add([
            'name' => 'xpath',
            'type' => Text::class,
            'options' => [
                'label' => 'XPath', // @translate
                'info' => 'XPath expression, relative to the <oai:record> element, for instance "oai:metadata/oai_dc:dc/dc:title"', // @translate
            ],
            'attributes' => [
                'data-field-data-key' => 'xpath',
                'class' => 'oaipmhharvester-monospace',
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'property',
            'type' => \Omeka\Form\Element\PropertySelect::class,
            'options' => [
                'label' => 'Property', // @translate
                'term_as_value' => true,
            ],
            'attributes' => [
                'data-field-data-key' => 'property',
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'type',
            'type' => \Laminas\Form\Element\Select::class,
            'options' => [
                'label' => 'Type', // @translate
                'value_options' => [
                    'literal' => 'Text', // @translate
                    'uri' => 'URI', // @translate
                ],
            ],
            'attributes' => [
                'data-field-data-key' => 'type',
                'required' => true,
            ],
        ]);

        $this->add([
            'name' => 'replacements',
            'type' => \Laminas\Form\Element\Textarea::class,
            'options' => [
                'label' => 'Replacements', // @translate
                'info' => 'Text replacements to perform. One per line. Format: old-value = new-value. Replacement is done only if the value matches exactly.', // @translate
            ],
            'attributes' => [
                'data-field-data-key' => 'replacements',
                'placeholder' => "old value 1 = new value 1\nold value 2 = new value 2", // @translate
            ],
        ]);
    }
}
