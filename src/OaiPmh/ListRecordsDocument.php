<?php

namespace OaiPmhHarvester\OaiPmh;

use OaiPmhHarvester\OaiPmh\OaiRecord;

class ListRecordsDocument extends OaiDocument
{
    public function getRecords(): array
    {
        $records = [];
        $xpath = $this->getDOMXPath();
        foreach ($xpath->query('//oai:ListRecords/oai:record') as $recordNode) {
            $records[] = new OaiRecord($recordNode);
        }

        return $records;
    }

    public function getResumptionToken(): ?string
    {
        return $this->getDOMXPath()->evaluate('string(//oai:ListRecords/oai:resumptionToken)') ?: null;
    }

    public function getCompleteListSize(): ?int
    {
        $completeListSizeAttrNodeList = $this->getDOMXPath()->query('//oai:ListRecords/oai:resumptionToken/@completeListSize');
        if ($completeListSizeAttrNodeList->count() === 0) {
            return null;
        }

        return (int) $completeListSizeAttrNodeList->item(0)->value;
    }
}
