<?php

namespace OaiPmhHarvester\Converter;

use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\View\Renderer\PhpRenderer;
use OaiPmhHarvester\OaiPmh\OaiRecord;

interface ConfigurableConverterInterface extends ConverterInterface
{
    public function addConfigurationFormElements(Form $form): void;

    public function addConfigurationFormInputFilters(Form $form, InputFilterInterface $inputFilter): void;

    public function getConfigurationDetails(PhpRenderer $renderer, array $settings): string;

    public function getConfigurationDetailsFull(PhpRenderer $renderer, array $settings): string;
}
