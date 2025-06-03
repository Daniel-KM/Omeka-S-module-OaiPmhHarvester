<?php
namespace OaiPmhHarvester\Form\View\Helper;

use Laminas\Form\Element\Select;
use Laminas\Form\View\Helper\FormTextarea;
use Laminas\Form\ElementInterface;

class FormSetsTextarea extends FormTextarea
{
    public function render(ElementInterface $element): string
    {
        $view = $this->getView();

        $textarea = parent::render($element);

        return $view->partial('oai-pmh-harvester/common/sets-textarea-form', ['element' => $element, 'textarea' => $textarea]);
    }
}
