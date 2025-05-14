<?php

namespace OaiPmhHarvester\OaiPmh;


class ListSetsDocument extends OaiDocument
{
    public function getSets(): array
    {
        $sets = [];
        $xpath = $this->getDOMXPath();
        foreach ($xpath->query('//oai:ListSets/oai:set') as $setNode) {
            $setSpec = $xpath->evaluate('string(oai:setSpec)', $setNode);
            $setName = $xpath->evaluate('string(oai:setName)', $setNode);
            $sets[] = [
                'setSpec' => $setSpec,
                'setName' => $setName,
            ];
        }

        return $sets;
    }

    public function getResumptionToken(): ?string
    {
        return $this->getDOMXPath()->evaluate('string(//oai:ListSets/oai:resumptionToken)') ?: null;
    }

    public function getCompleteListSize(): ?int
    {
        $completeListSizeAttrNodeList = $this->getDOMXPath()->query('//oai:ListSets/oai:resumptionToken/@completeListSize');
        if ($completeListSizeAttrNodeList->count() === 0) {
            return null;
        }

        return (int) $completeListSizeAttrNodeList->item(0)->value;
    }
}
