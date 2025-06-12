<?php declare(strict_types=1);

namespace OaiPmhHarvester\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;
use OaiPmhHarvester\Entity\Harvest;
use Omeka\Form\Element as OmekaElement;

class SetsForm extends Form
{
    public function __construct($name = null, $options = [])
    {
        is_array($name)
            ? parent::__construct($name['name'] ?? null, $name)
            : parent::__construct($name, $options ?? []);
    }

    public function init(): void
    {
        // Construct form
        $this
            ->appendHiddenElements()
            ->appendFilters()
            ->appendConfiguration()
            ->appendSetList()
        ;

        $this->configureInputFilters();
    }

    private function appendHiddenElements(): self
    {
        $this
            ->setAttribute('id', 'harvest-list-sets-form')
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
            ]);
        return $this;
    }

    private function appendFilters(): self
    {
        $this
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
                'name' => 'page_start',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'Start page', // @translate
                    'info' => 'This option allows to skip the first pages sent by the repository. It is useful to resume a harvest when the repository has issues.', // @translate
                ],
                'attributes' => [
                    'id' => 'page_start',
                    'step' => 1,
                    'min' => 0,
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
            ]);
        return $this;
    }

    private function appendConfiguration(): self
    {
        $this
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
                'name' => 'mode_delete',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Process for oai record marked as deleted', // @translate
                    'value_options' => [
                        Harvest::MODE_SKIP => 'Skip', // @translate
                        Harvest::MODE_DELETE_FILTERED => 'Delete resources previously imported', // @translate
                        Harvest::MODE_DELETE => 'Delete resources previously imported, whatever the filters', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'mode_delete',
                    'value' => 'skip',
                ],
            ])

            ->add([
                'name' => 'item_set',
                'type' => OmekaElement\ItemSetSelect::class,
                'options' => [
                    'label' => 'Item set for items', // @translate
                    'prepend_value_options' => [
                        'none' => 'No item set', // @translate
                        'new' => 'Create a new item set', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'item_set',
                    'value' => 'none',
                ],
            ])

            ->add([
                'name' => 'store_xml',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'label' => 'Store oai-pmh xml responses', // @translate
                    'info' => 'This option allows to investigate issues. Xml files are stored in directory /files/oai-pmh-harvest. The url is indicated in the logs.', // @translate
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
            ]);
        return $this;
    }

    /**
     * This form is dynamic, so allows to append elements.
     */
    private function appendSetList(
        ?bool $harvestAllRecords = null,
        ?array $formats = null,
        ?string $favoriteFormat = null,
        ?array $sets = null,
        ?bool $hasPredefinedSets = null
    ): self {
        $harvestAllRecords ??= $this->getOption('harvest_all_records') ?? false;
        $formats ??= $this->getOption('formats') ?? ['oai_dc'];
        $favoriteFormat ??= $this->getOption('favorite_format') ?? 'oai_dc';
        $sets ??= $this->getOption('sets') ?? [];
        $hasPredefinedSets ??= $this->getOption('has_predefined_sets') ?? [];

        if ($harvestAllRecords) {
            $this
                ->add([
                    'type' => Element\Select::class,
                    'name' => 'namespace[0]',
                    'options' => [
                        'required' => false,
                        'label' => 'Whole repository', // @translate
                        'value_options' => $formats,
                    ],
                    'attributes' => [
                        'required' => false,
                        'id' => 'namespace-0',
                        'value' => $favoriteFormat,
                    ],
                ])
            ;
        } elseif ($hasPredefinedSets) {
            // The predefined sets are already formatted, but have no label.
            // So we use the set identifier as label too
            foreach ($sets as $setIdentifier => $format) {
                $this->appendSet($setIdentifier, $setIdentifier, $formats, $format, true);
            }
        } elseif (!empty($sets)) {
            foreach ($sets as $setIdentifier => $setName) {
                $this->appendSet($setIdentifier, $setName, $formats, $favoriteFormat, false);
            }
        } else {
            $fieldset = new Fieldset("error",
                ["label" => "Nothing to harvest."]
            );
            $this->add($fieldset);
        }

        return $this;
    }

    /**
     * Add an OAI-PMH set to the form
     * @param string $setIdentifier the OAI-PMH set identifier
     * @param string $setName the OAI-PMH set name
     * @param array $availableFormats the list of available formats
     * @param string $preSelectedFormat the pre-selected format (must be in the $availableFormats array)
     * @param bool $isChecked if there is or not checked to be harvest by default
     * @return self this Form itself
     */
    private function appendSet(string $setIdentifier, string $setName, array $availableFormats, string $preSelectedFormat, bool $isChecked = false): self
    {
        $label = strip_tags($setName);
        if ($setIdentifier != $setName) {
            $label .= " ($setIdentifier)";
        }
        $fieldset = new Fieldset("$setIdentifier",
            [
                "label" => $label,
            ]
        );

        $classList = $fieldset->getAttribute('class') ?? '';
        $classList .= ' set';
        $fieldset->setAttribute('class', $classList);

        $this->add($fieldset);

        $fieldset
            ->add([
                'type' => Element\Select::class,
                'name' => 'namespace',
                'options' => [
                    'label' => "Format",
                    'value_options' => $availableFormats,
                ],
                'attributes' => [
                    'display' => 'none',
                    'id' => 'namespace-' . $setIdentifier,
                    'value' => $preSelectedFormat,
                ],
            ])
            ->add([
                'type' => Element\Hidden::class,
                'name' => 'label',
                'attributes' => [
                    'id' => 'label-' . $setIdentifier,
                    'value' => strip_tags($setName),
                ],
            ])
            ->add([
                'type' => Element\Checkbox::class,
                'name' => 'harvest',
                'options' => [
                    'required' => false,
                    'label' => 'Harvest this set', // @translate
                    'use_hidden_element' => false,
                ],
                'attributes' => [
                    'id' => 'harvest-' . $setIdentifier,
                    'value' => $isChecked,
                    'class' => 'fieldset-checkbox',
                ],
            ]);
        return $this;
    }

    private function configureInputFilters(): self
    {
        $inputFilter = $this->getInputFilter();

        // everything optional by default -- no works on FieldSets
        foreach ($this->getElements() as $element) {
            $inputFilter
                ->add([
                    'name' => $element->getName(),
                    'required' => false,
                ]);
        }

        // everything optional by default -- on FieldSets
        foreach ($this->getFieldsets() as $fieldset) {
            foreach ($fieldset->getElements() as $element) {
                $inputFilter->get($fieldset->getName())->add([
                    'name' => $element->getName(),
                    'required' => false,
                ]);
            }
        }

        return $this;
    }
}
