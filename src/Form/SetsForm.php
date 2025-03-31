<?php declare(strict_types=1);

namespace OaiPmhHarvester\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;
use OaiPmhHarvester\Entity\Harvest;
use Omeka\Form\Element as OmekaElement;

class SetsForm extends Form
{
    public function __construct($name = null, $options = [])
    {
        is_array($name)
            ? parent::__construct($name['name'] ?? null, $name)
            : parent::__construct($name, $options);
    }

    public function init(): void
    {
        $this
            ->setAttribute('id', 'harvest-list-sets')
            ->setAttribute('class', 'oai-pmh-harvester')

            ->add([
                'type' => Element\Hidden::class,
                'name' => 'repository_name',
                'attributes' => [
                    'id' => 'repository_name',
                ],
            ])
            ->add([
                'type' => Element\Hidden::class,
                'name' => 'endpoint',
                'attributes' => [
                    'id' => 'endpoint',
                ],
            ])
            ->add([
                'type' => Element\Hidden::class,
                'name' => 'harvest_all_records',
                'attributes' => [
                    'id' => 'harvest_all_records',
                ],
            ])
            ->add([
                'type' => Element\Hidden::class,
                'name' => 'predefined_sets',
                'attributes' => [
                    'id' => 'predefined_sets',
                ],
            ])

            ->add([
                'name' => 'from',
                // 'type' => Element\DateTimeLocal::class,
                'type' => Element\Date::class,
                'options' => [
                    'label' => 'From date', // @translate
                    'info' => 'Date should be UTC. Time is optional. Value is included (≥).', // @translate
                    'should_show_seconds' => true,
                ],
                'attributes' => [
                    'id' => 'from',
                    'step' => 1,
                    'placeholder' => '2025-01-01',
                    'class' => 'datetime-date datetime-from',
                ],
            ])
            ->add([
                'name' => 'from_time',
                'type' => Element\Time::class,
                'options' => [
                    'label' => 'Optional from time', // @translate
                    'should_show_seconds' => true,
                ],
                'attributes' => [
                    'id' => 'from-time',
                    'step' => 1,
                    'placeholder' => '00:00:00',
                    'class' => 'datetime-time datetime-from',
                ],
            ])
            ->add([
                'name' => 'until',
                // 'type' => Element\DateTimeLocal::class,
                'type' => Element\Date::class,
                'options' => [
                    'label' => 'From date', // @translate
                    'label' => 'Until date', // @translate
                    'info' => 'Date should be UTC. Time is optional. Value is included (≤).', // @translate
                    'should_show_seconds' => true,
                ],
                'attributes' => [
                    'id' => 'until',
                    'step' => 1,
                    'placeholder' => '2025-01-31',
                    'class' => 'datetime-date datetime-until',
                ],
            ])
            ->add([
                'name' => 'until_time',
                'type' => Element\Time::class,
                'options' => [
                    'label' => 'Optional until time', // @translate
                    'should_show_seconds' => true,
                ],
                'attributes' => [
                    'id' => 'until-time',
                    'step' => 1,
                    'placeholder' => '23:59:59',
                    'class' => 'datetime-time datetime-until',
                ],
            ])

            ->add([
                'name' => 'filters_whitelist',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'Filters (whitelist)', // @translate
                    'info' => 'Add strings to filter the input, for example to import only some articles of a journal.', // @translate
                ],
                'attributes' => [
                    'id' => 'filters_whitelist',
                ],
            ])
            ->add([
                'name' => 'filters_blacklist',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'Filters (blacklist)', // @translate
                    'info' => 'Add strings to filter the input, for example to import only some articles of a journal.', // @translate
                ],
                'attributes' => [
                    'id' => 'filters_blacklist',
                ],
            ])

            ->add([
                'name' => 'mode_harvest',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Import/update mode for atomic formats', // @translate
                    'info' => 'An atomic format is a format where an oai record with an oai identifier maps to a single resource in Omeka. Ead via oai-pmh is not an atomic format, so a reharvest will duplicate records.', // @translate
                    'value_options' => [
                        Harvest::MODE_SKIP => 'Skip record (keep existing resource)', // @translate
                        Harvest::MODE_APPEND => 'Append new values', // @translate
                        Harvest::MODE_UPDATE => 'Replace existing values and let values of properties not present in harvested record', // @translate
                        Harvest::MODE_REPLACE => 'Replace the whole existing resource', // @translate
                        Harvest::MODE_DUPLICATE => 'Create a new resource (not recommended)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'mode_harvest',
                    'value' => 'skip',
                ],
            ])

            ->add([
                'name' => 'store_xml',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'label' => 'Store oai-pmh xml responses', // @translate
                    'info' => 'This option allows to investigate issues. Xml files are stored in directory /files/oai-pmh-harvest.', // @translate
                    'value_options' => [
                        'page' => 'By page', // @translate
                        'record' => 'By record', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'store_xml',
                ],
            ])

            ->add([
                'type' => Element\Hidden::class,
                'name' => 'step',
                'attributes' => [
                    'id' => 'step',
                    'value' => 'harvest-list-sets',
                ],
            ])

            ->appendSets()
        ;

        $inputFilter = $this->getInputFilter();
        $inputFilter
            ->add([
                'name' => 'from',
                'required' => false,
            ])
            ->add([
                'name' => 'from_time',
                'required' => false,
            ])
            ->add([
                'name' => 'until',
                'required' => false,
            ])
            ->add([
                'name' => 'until_time',
                'required' => false,
            ])
        ;

        $this
            ->appendSetsInputFilter();
    }

    /**
     * This form is dynamic, so allows to append elements.
     */
    public function appendSets(
        ?bool $harvestAllRecords = null,
        ?array $formats = null,
        ?string $favoriteFormat = null,
        ?array $sets = null,
        ?bool $hasPredefinedSets = null
    ): self {
        $harvestAllRecords ??= $this->getOption('harvest_all_records', false);
        $formats ??= $this->getOption('formats', ['oai_dc']);
        $favoriteFormat ??= $this->getOption('favorite_format', 'oai_dc');
        $sets ??= $this->getOption('sets', []);
        $hasPredefinedSets ??= $this->getOption('has_predefined_sets', []);

        // TODO Normalize sets form with collection, fieldsets and better names.

        // The predefined sets are already formatted, but have no label.
        if ($hasPredefinedSets) {
            foreach ($sets as $setSpec => $prefix) {
                $this
                    ->add([
                        'type' => Element\Select::class,
                        'name' => 'namespace[' . $setSpec . ']',
                        'options' => [
                            'label' => $setSpec,
                            'value_options' => $formats,
                        ],
                        'attributes' => [
                            'id' => 'namespace[' . $setSpec . ']',
                            'value' => $prefix,
                        ],
                    ])
                    ->add([
                        'type' => Element\Hidden::class,
                        'name' => 'setSpec[' . $setSpec . ']',
                        'attributes' => [
                            'id' => 'setSpec' . $setSpec,
                            'value' => $setSpec,
                        ],
                    ])
                    ->add([
                        'type' => Element\Checkbox::class,
                        'name' => 'harvest[' . $setSpec . ']',
                        'options' => [
                            'label' => 'Harvest this set?', // @translate
                            'use_hidden_element' => false,
                        ],
                        'attributes' => [
                            'id' => 'harvest[' . $setSpec . ']',
                            'value' => true,
                            'checked' => 'checked',
                        ],
                    ]);
            }
        } elseif ($sets && !$harvestAllRecords) {
            foreach ($sets as $setSpec => $set) {
                $this
                    ->add([
                        'type' => Element\Select::class,
                        'name' => 'namespace[' . $setSpec . ']',
                        'options' => [
                            'label' => strip_tags($set) . " ($setSpec)",
                            'value_options' => $formats,
                        ],
                        'attributes' => [
                            'id' => 'namespace-' . $setSpec,
                            'value' => $favoriteFormat,
                        ],
                    ])
                    ->add([
                        'type' => Element\Hidden::class,
                        'name' => 'setSpec[' . $setSpec . ']',
                        'attributes' => [
                            'id' => 'setSpec-' . $setSpec,
                            'value' => strip_tags($set),
                        ],
                    ])
                    ->add([
                        'type' => Element\Checkbox::class,
                        'name' => 'harvest[' . $setSpec . ']',
                        'options' => [
                            'label' => 'Harvest this set', // @translate
                            'use_hidden_element' => false,
                        ],
                        'attributes' => [
                            'id' => 'harvest-' . $setSpec,
                        ],
                    ]);
            }
        } else {
            $this
                ->add([
                    'type' => Element\Select::class,
                    'name' => 'namespace[0]',
                    'options' => [
                        'label' => 'Whole repository', // @translate
                        'value_options' => $formats,
                    ],
                    'attributes' => [
                        'id' => 'namespace-0',
                        'value' => $favoriteFormat,
                    ],
                ])
            ;
        }

        return $this;
    }

    public function appendSetsInputFilter(): self
    {
        $inputFilters = $this->getInputFilter();

        foreach ($this->getElements() as $element) {
            $elementName = $element->getName();
            if (strpos($elementName, 'namespace[') === 0
                || strpos($elementName, 'setSpec[') === 0
                || strpos($elementName, 'harvest[') === 0
                || $elementName === 'store_xml'
            ) {
                $inputFilters
                    ->add([
                        'name' => $elementName,
                        'required' => false,
                    ]);
            }
        }

        return $this;
    }
}
