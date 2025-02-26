<?php declare(strict_types=1);

namespace OaiPmhHarvester\Job;

use DateTime;
use DateTimeZone;
use OaiPmhHarvester\OaiPmh\OaiRecord;
use Omeka\Api\Representation\AbstractRepresentation;
use OaiPmhHarvester\Api\Representation\SourceRepresentation;
use Omeka\Job\AbstractJob;

class HarvestSource extends AbstractJob
{
    protected int $importedRecords = 0;

    public function perform()
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $logger = $services->get('Omeka\Logger');
        $client = $services->get('OaiPmhHarvester\Client');

        $logger->info('Job started');

        $sourceId = $this->getArg('source_id');
        $source = $api->read('oaipmhharvester_sources', $sourceId)->getContent();

        if ($this->getArg('delete_all_items', false)) {
            $this->deleteAllItems($source);
        }

        if ($this->shouldStop()) {
            $logger->info('Job stopped');
            return;
        }

        $client->setMaxTries(3);
        $sets = $source->sets();
        if ($sets) {
            foreach ($sets as $set) {
                if ($this->shouldStop()) {
                    break;
                }

                $this->harvest($source, $set);
            }
        } else {
            $this->harvest($source);
        }

        if ($this->shouldStop()) {
            $logger->info('Job stopped');
            return;
        }

        $logger->info(sprintf('Total records imported: %d', $this->importedRecords));
        $logger->info('Job ended normally');
    }

    protected function deleteAllItems(SourceRepresentation $source)
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $logger = $services->get('Omeka\Logger');

        $response = $api->search('items', ['oaipmhharvester_source_id' => $source->id()], ['returnScalar' => 'id']);

        $logger->info(sprintf('Deleting %d items', $response->getTotalResults()));

        // Batch delete the resources in chunks.
        foreach (array_chunk($response->getContent(), 100) as $idsChunk) {
            if ($this->shouldStop()) {
                return;
            }
            $api->batchDelete('items', $idsChunk, [], ['continueOnError' => true]);
        }

        $response = $api->search('items', ['oaipmhharvester_source_id' => $source->id()], ['returnScalar' => 'id']);
        if ($response->getTotalResults() > 0) {
            throw new \Exception('Failed to delete some or all items');
        }

        $logger->info('Deletion finished');
    }

    protected function harvest(SourceRepresentation $source, string $set = null)
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $logger = $services->get('Omeka\Logger');
        $client = $services->get('OaiPmhHarvester\Client');
        $settings = $services->get('Omeka\Settings');

        $configuration = $source->configuration();
        $converter = $configuration->converter();

        $from = $this->getArg('from');
        $until = $this->getArg('until');

        $resumptionToken = null;
        do {
            if ($this->shouldStop()) {
                break;
            }

            if ($resumptionToken) {
                $query = ['resumptionToken' => $resumptionToken];
            } else {
                $query = ['metadataPrefix' => $source->metadataPrefix(), 'from' => $from, 'until' => $until];
                if ($set !== null) {
                    $query['set'] = $set;
                }
            }

            $t0 = microtime(true);
            $document = $client->listRecords($source->baseUrl(), $query);
            $elapsed = microtime(true) - $t0;

            $resumptionToken = $document->getResumptionToken();
            $records = $document->getRecords();

            if ($set) {
                $logger->info(sprintf('Fetched %d records for set %s in %.3f s', count($records), $set, $elapsed));
            } else {
                $logger->info(sprintf('Fetched %d records in %.3f s', count($records), $elapsed));
            }

            $toCreate = [];
            foreach ($records as $record) {
                if ($this->shouldStop()) {
                    break;
                }

                if ($this->isDeletedRecord($record)) {
                    $logger->info(sprintf('Skipping deleted record %s', $identifier));
                    continue;
                }

                $identifier = $record->getIdentifier();

                $response = $api->search(
                    'oaipmhharvester_source_records',
                    ['source_id' => $source->id(), 'identifier' => $identifier],
                    ['returnScalar' => 'item']
                );
                if ($response->getTotalResults() > 0) {
                    $itemIds = $response->getContent();
                    $logger->info(sprintf('Skipping record %s because it already exists (items: %s)', $identifier, implode(',', $itemIds)));
                    continue;
                }

                $generator = $converter->convert($record, $configuration->settings());
                while ($generator->valid()) {
                    $itemData = $generator->current();

                    if (!is_array($itemData)) {
                        $logger->err('Converter did not return an array');
                        $generator->send(null);
                        continue;
                    }

                    if (!isset($itemData['o:is_public'])) {
                        $itemData['o:is_public'] = !$settings->get('default_to_private', false);
                    }

                    $response = $api->create('items', $itemData, [], ['continueOnError' => true]);
                    $item = $response->getContent();
                    $itemId = $item->id();

                    $sourceRecordData = [
                        'o:item' => ['o:id' => $itemId],
                        'o:source' => ['o:id' => $source->id()],
                        'o:identifier' => $identifier,
                    ];
                    $api->create('oaipmhharvester_source_records', $sourceRecordData);

                    $logger->info(sprintf('Imported record %s (item #%d)', $identifier, $item->id()));
                    $this->importedRecords++;

                    $generator->send($item->id());
                }
            }
        } while ($resumptionToken);
    }

    protected function isDeletedRecord(OaiRecord $record): bool
    {
        $status = $record->getDOMXPath()->evaluate('string(oai:header/@status)', $record->getDOMElement());

        return $status === 'deleted';
    }
}
