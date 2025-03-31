<?php declare(strict_types=1);

namespace OaiPmhHarvester\Api\Adapter;

use DateTime;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class HarvestAdapter extends AbstractEntityAdapter
{
    protected $sortFields = [
        'id' => 'id',
        'job' => 'job',
        'undo_job' => 'undoJob',
        'message' => 'message',
        'endpoint' => 'endpoint',
        'entity_name' => 'entityName',
        'item_set' => 'itemSet',
        'metadata_prefix' => 'metadataPrefix',
        'mode_harvest' => 'modeHarvest',
        'mode_delete' => 'modeDelete',
        'from' => 'from',
        'until' => 'until',
        'set_spec' => 'setSpec',
        'set_name' => 'setName',
        'set_description' => 'setDescription',
        'has_err' => 'hasErr',
        'stats' => 'stats',
        'resumption_token' => 'resumptionToken',
    ];

    protected $scalarFields = [
        'id' => 'id',
        'job' => 'job',
        'undo_job' => 'undoJob',
        'message' => 'message',
        'endpoint' => 'endpoint',
        'entity_name' => 'entityName',
        'item_set' => 'itemSet',
        'metadata_prefix' => 'metadataPrefix',
        'mode_harvest' => 'modeHarvest',
        'mode_delete' => 'modeDelete',
        'from' => 'from',
        'until' => 'until',
        'set_spec' => 'setSpec',
        'set_name' => 'setName',
        'set_description' => 'setDescription',
        'has_err' => 'hasErr',
        'stats' => 'stats',
        'resumption_token' => 'resumptionToken',
    ];

    public function getEntityClass()
    {
        return \OaiPmhHarvester\Entity\Harvest::class;
    }

    public function getResourceName()
    {
        return 'oaipmhharvester_harvests';
    }

    public function getRepresentationClass()
    {
        return \OaiPmhHarvester\Api\Representation\HarvestRepresentation::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        $expr = $qb->expr();

        if (!empty($query['job_id'])) {
            $qb->andWhere($expr->eq(
                'omeka_root.job',
                $this->createNamedParameter($qb, $query['job_id']))
            );
        }

        if (!empty($query['entity_name'])) {
            $qb->andWhere($expr->eq(
                'omeka_root.entityName',
                $this->createNamedParameter($qb, $query['entity_name']))
            );
        }
    }

    public function hydrate(Request $request, EntityInterface $entity, ErrorStore $errorStore): void
    {
        /** @var \OaiPmhHarvester\Entity\Harvest $entity */

        $data = $request->getContent();

        if (array_key_exists('o:job', $data)) {
            $job = isset($data['o:job']['o:id'])
                ? $this->getAdapter('jobs')->findEntity($data['o:job']['o:id'])
                : null;
            $entity->setJob($job);
        }

        if (array_key_exists('o:undo_job', $data)) {
            $job = isset($data['o:undo_job']['o:id'])
                ? $this->getAdapter('jobs')->findEntity($data['o:undo_job']['o:id'])
                : null;
            $entity->setUndoJob($job);
        }

        if (array_key_exists('o-oai-pmh:message', $data)) {
            $value = (string) $data['o-oai-pmh:message'];
            $value = $value === '' ? null : $value;
            $entity->setMessage($value);
        }

        if (array_key_exists('o-oai-pmh:endpoint', $data)) {
            $entity->setEndpoint((string) $data['o-oai-pmh:endpoint']);
        }

        if (array_key_exists('o-oai-pmh:entity_name', $data)) {
            $entity->setEntityName((string) $data['o-oai-pmh:entity_name']);
        }

        if (array_key_exists('o:item_set', $data)) {
            $itemSet = isset($data['o:item_set']['o:id'])
                ? $this->getAdapter('item_sets')->findEntity($data['o:item_set']['o:id'])
                : null;
            $entity->setItemSet($itemSet);
        }

        if (array_key_exists('o-oai-pmh:metadata_prefix', $data)) {
            $entity->setMetadataPrefix((string) $data['o-oai-pmh:metadata_prefix']);
        }

        if (array_key_exists('o-oai-pmh:mode_harvest', $data)) {
            $entity->setModeHarvest((string) $data['o-oai-pmh:mode_harvest']);
        }

        if (array_key_exists('o-oai-pmh:mode_delete', $data)) {
            $entity->setModeDelete((string) $data['o-oai-pmh:mode_delete']);
        }

        if (array_key_exists('o-oai-pmh:from', $data)) {
            $value = $data['o-oai-pmh:from'];
            if (empty($value)) {
                $value = null;
            } elseif (!is_object($value)) {
                $value = new DateTime($value);
            }
            $entity->setFrom($value);
        }

        if (array_key_exists('o-oai-pmh:until', $data)) {
            $value = $data['o-oai-pmh:until'];
            if (empty($value)) {
                $value = null;
            } elseif (!is_object($value)) {
                $value = new DateTime($value);
            }
            $entity->setUntil($value);
        }

        if (array_key_exists('o-oai-pmh:set_spec', $data)) {
            $value = (string) $data['o-oai-pmh:set_spec'];
            $value = $value === '' ? null : $value;
            $entity->setSetSpec($value);
        }

        if (array_key_exists('o-oai-pmh:set_name', $data)) {
            $value = (string) $data['o-oai-pmh:set_name'];
            $value = $value === '' ? null : $value;
            $entity->setSetName($value);
        }

        if (array_key_exists('o-oai-pmh:set_description', $data)) {
            $value = (string) $data['o-oai-pmh:set_description'];
            $value = $value === '' ? null : $value;
            $entity->setSetDescription($value);
        }

        if (array_key_exists('o-oai-pmh:has_err', $data)) {
            $entity->setHasErr((bool) $data['o-oai-pmh:has_err']);
        }

        if (array_key_exists('o-oai-pmh:stats', $data)) {
            $entity->setStats($data['o-oai-pmh:stats'] ?: []);
        }

        if (array_key_exists('o-oai-pmh:resumption_token', $data)) {
            $value = (string) $data['o-oai-pmh:resumption_token'];
            $value = $value === '' ? null : $value;
            $entity->setResumptionToken($value);
        }
    }
}
