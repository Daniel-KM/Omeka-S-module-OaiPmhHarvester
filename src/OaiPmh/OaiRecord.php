<?php

namespace OaiPmhHarvester\OaiPmh;

use DOMElement;
use DOMXPath;

class OaiRecord
{
    protected DOMElement $element;

    public function __construct(DOMElement $element)
    {
        $this->element = $element;
    }

    public function getIdentifier(): string
    {
        $xpath = $this->element->ownerDocument->getDOMXPath();

        return $xpath->evaluate('string(oai:header/oai:identifier)', $this->element);
    }

    public function getDOMElement(): DOMElement
    {
        return $this->element;
    }

    public function getDOMXPath(): DOMXPath
    {
        return $this->getDOMElement()->ownerDocument->getDOMXPath();
    }
}
