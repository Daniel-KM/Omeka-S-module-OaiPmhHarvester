<?php

namespace OaiPmhHarvester\OaiPmh;


class ListMetadataFormatsDocument extends OaiDocument
{
    public function getMetadataFormats(): array
    {
        $metadataFormats = [];
        $xpath = $this->getDOMXPath();
        foreach ($xpath->query('//oai:ListMetadataFormats/oai:metadataFormat') as $metadataFormatNode) {
            $metadataPrefix = $xpath->evaluate('string(oai:metadataPrefix)', $metadataFormatNode);
            $metadataFormats[] = [
                'metadataPrefix' => $metadataPrefix,
            ];
        }

        return $metadataFormats;
    }

    public function getResumptionToken(): ?string
    {
        return $this->getDOMXPath()->evaluate('string(//oai:ListMetadataFormats/oai:resumptionToken)') ?: null;
    }

    public function getCompleteListSize(): ?int
    {
        $completeListSizeAttrNodeList = $this->getDOMXPath()->query('//oai:ListMetadataFormats/oai:resumptionToken/@completeListSize');
        if ($completeListSizeAttrNodeList->count() === 0) {
            return null;
        }

        return (int) $completeListSizeAttrNodeList->item(0)->value;
    }
}
