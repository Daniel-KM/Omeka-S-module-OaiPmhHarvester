<?php declare(strict_types=1);

namespace OaiPmhHarvester\Job;

use OaiPmhHarvester\Entity\Harvest;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Job\AbstractJob;

class DeleteHarvestedEntities extends AbstractJob
{
    public function perform(): void
    {
        /**
         * @var \Laminas\Log\LoggerInterface $logger
         * @var \Omeka\Api\Manager $api
         */
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        $api = $services->get('Omeka\ApiManager');

        // The reference id is the job id for now.
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('oai-pmh/delete/job_' . $this->job->getId());
        $logger->addProcessor($referenceIdProcessor);

        /** @var \OaiPmhHarvester\Api\Representation\HarvestRepresentation $harvest */
        $harvest = $this->getArg('harvestId');
        if (!$harvest) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $logger->err('The harvest id is not defined.'); // @translate
            return;
        }

        try {
            $harvest = $api->read('oaipmhharvester_harvests', $harvest)->getContent();
        } catch (NotFoundException $e) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $logger->err('The harvest id is not valid.'); // @translate
            return;
        }

        // Check harvest mode.
        $modeHarvests = [
            Harvest::MODE_SKIP,
            Harvest::MODE_APPEND,
            Harvest::MODE_UPDATE,
            Harvest::MODE_REPLACE,
            Harvest::MODE_DUPLICATE,
        ];
        $modeHarvest = $harvest->modeHarvest();
        if (!$modeHarvest) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $logger->err('The harvest mode is not defined.'); // @translate
            return;
        }

        if (!in_array($modeHarvest, $modeHarvests)) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $logger->err(
                'The harvest mode "{mode}" is not supported.', // @translate
                ['mode' => $modeHarvest]
            );
            return;
        }

        // For now, entity name is always "items".

        $harvestedResourceEntities = $harvest->harvestedEntities();

        $harvestEntities = $api
            ->search(
                'oaipmhharvester_entities',
                [
                    'harvest_id' => $harvest->id(),
                ],
                ['returnScalar' => 'entity_id']
            )
            ->getContent();

        $index = 0;
        foreach (array_chunk($harvestedResourceEntities, 100, true) as $loop => $chunk) {
            if ($this->shouldStop()) {
                $logger->warn(
                    'The job "Undo" was stopped: {count}/{total} resources processed.', // @translate
                    ['count' => $index, 'total' => count($harvestedResourceEntities)]
                );
                $stats = $harvest->stats();
                $stats['deleted'] = $loop === 0 ? count($chunk) : $index;
                $harvestData = [
                    'o-oai-pmh:message' => 'Undo stopped.', // @translate
                    'o-oai-pmh:has_err' => $harvest->hasErr(),
                    'o-oai-pmh:stats' => array_filter($stats),
                ];
                $api->update('oaipmhharvester_harvests', $harvest->id(), $harvestData);
                return;
            }

            try {
                // Group harvested entity ids by entity name.
                $harvestedEntityNames = array_unique($chunk);
                foreach ($harvestedEntityNames as $entityName) {
                    $harvestedEntityIds = array_keys($chunk, $entityName);
                    if (!count($harvestedEntityIds)) {
                        continue;
                    }
                    $api->batchDelete($entityName, $harvestedEntityIds);
                    $deletedHarvestEntities = array_intersect($harvestEntities, $harvestedEntityIds);
                    if (!count($deletedHarvestEntities)) {
                        continue;
                    }
                    $api->batchDelete('oaipmhharvester_entities', array_keys($deletedHarvestEntities));
                }
            } catch (NotFoundException $e) {
            }

            $index += 100;
        }

        $stats = $harvest->stats();
        $stats['deleted'] = count($harvestedResourceEntities);
        $harvestData = [
            'o-oai-pmh:message' => 'Undone', // @translate
            'o-oai-pmh:has_err' => $harvest->hasErr(),
            'o-oai-pmh:stats' => array_filter($stats),
        ];
        $api->update('oaipmhharvester_harvests', $harvest->id(), $harvestData);
    }
}
