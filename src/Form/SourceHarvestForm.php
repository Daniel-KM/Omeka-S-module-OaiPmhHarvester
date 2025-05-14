<?php

namespace OaiPmhHarvester\Form;

use Laminas\Form\Form;
use Laminas\Form\Element\Checkbox;
use Laminas\Form\Element\Text;

class SourceHarvestForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'delete_all_items',
            'type' => Checkbox::class,
            'options' => [
                'label' => 'Delete all items before harvest', // @translate
                'info' => 'Delete all items that were harvested from this source before starting the new harvest.' // @translate
            ],
        ]);

        $this->add([
            'name' => 'from',
            'type' => Text::class,
            'options' => [
                'label' => 'From', // @translate
            ],
            'attributes' => [
                'placeholder' => '2020-12-31 or 2020-12-31T08:30:00Z', // @translate
                'pattern' => '\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}:\d{2}Z)?',
                'title' => 'YYYY-MM-DD or YYYY-MM-DDTHH:MM:SSZ', // @translate
            ],
        ]);

        $this->add([
            'name' => 'until',
            'type' => Text::class,
            'options' => [
                'label' => 'Until', // @translate
            ],
            'attributes' => [
                'placeholder' => '2020-12-31 or 2020-12-31T08:30:00Z', // @translate
                'pattern' => '\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}:\d{2}Z)?',
                'title' => 'YYYY-MM-DD or YYYY-MM-DDTHH:MM:SSZ', // @translate
            ],
        ]);
    }
}
