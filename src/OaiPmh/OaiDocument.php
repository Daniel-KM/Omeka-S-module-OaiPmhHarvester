<?php

namespace OaiPmhHarvester\OaiPmh;

use DOMDocument;
use DOMXPath;

class OaiDocument extends DOMDocument
{
    protected DOMXPath $domxpath;

    public bool $preserveWhiteSpace = false;

    public function getDOMXPath(): DOMXPath
    {
        if (!isset($this->domxpath)) {
            $this->domxpath = new DOMXPath($this);
            $this->domxpath->registerNamespace('oai', 'http://www.openarchives.org/OAI/2.0/');
        }

        return $this->domxpath;
    }

    public function registerNamespace(string $prefix, string $namespace): bool
    {
        return $this->getDOMXPath()->registerNamespace($prefix, $namespace);
    }
}
