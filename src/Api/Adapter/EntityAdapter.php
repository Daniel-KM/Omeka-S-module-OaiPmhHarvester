<?php declare(strict_types=1);

namespace OaiPmhHarvester\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class EntityAdapter extends AbstractEntityAdapter
{
    protected $sortFields = [
        'id' => 'id',
        'harvest_id' => 'harvest',
        'entity_id' => 'entityId',
        'entity_name' => 'entityName',
        'identifier' => 'identifier',
        'created' => 'created',
    ];

    protected $scalarFields = [
        'id' => 'id',
        'harvest_id' => 'harvest',
        'entity_id' => 'entityId',
        'entity_name' => 'entityName',
        'identifier' => 'identifier',
        'created' => 'created',
    ];

    public function getEntityClass()
    {
        return \OaiPmhHarvester\Entity\Entity::class;
    }

    public function getResourceName()
    {
        return 'oaipmhharvester_entities';
    }

    public function getRepresentationClass()
    {
        return \OaiPmhHarvester\Api\Representation\EntityRepresentation::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        $expr = $qb->expr();

        if (isset($query['harvest_id'])) {
            $qb->andWhere($expr->eq(
                'omeka_root.harvest',
                $this->createNamedParameter($qb, $query['harvest_id']))
            );
        }

        if (isset($query['job_id'])) {
            $harvestAlias = $this->createAlias();
            $qb
                ->innerJoin(
                    'omeka_root.harvest', $harvestAlias
                )
                ->andWhere($expr->eq(
                    "$harvestAlias.job",
                    $this->createNamedParameter($qb, $query['job_id']))
                );
        }

        if (isset($query['entity_id'])) {
            $qb->andWhere($expr->eq(
                'omeka_root.entity_id',
                $this->createNamedParameter($qb, $query['entity_id']))
            );
        }

        if (isset($query['entity_name'])) {
            $qb->andWhere($expr->eq(
                'omeka_root.entity_name',
                $this->createNamedParameter($qb, $query['entity_name']))
            );
        }

        if (isset($query['identifier'])) {
            $qb->andWhere($expr->eq(
                'omeka_root.identifier',
                $this->createNamedParameter($qb, $query['identifier']))
            );
        }
    }

    public function hydrate(Request $request, EntityInterface $entity, ErrorStore $errorStore): void
    {
        /** @var \OaiPmhHarvester\Entity\Entity $entity */

        $data = $request->getContent();

        if (array_key_exists('o-oai-pmh:harvest', $data)) {
            $harvest = isset($data['o-oai-pmh:harvest']['o:id'])
                ? $this->getAdapter('oaipmhharvester_harvests')->findEntity($data['o-oai-pmh:harvest']['o:id'])
                : null;
            $entity->setHarvest($harvest);
        }

        if (array_key_exists('o-oai-pmh:entity_id', $data)) {
            $entity->setEntityId((int) $data['o-oai-pmh:entity_id']);
        }

        if (array_key_exists('o-oai-pmh:entity_name', $data)) {
            $entity->setEntityName((string) $data['o-oai-pmh:entity_name']);
        }

        if (array_key_exists('o-oai-pmh:identifier', $data)) {
            $entity->setIdentifier((string) $data['o-oai-pmh:identifier']);
        }

        if (Request::CREATE === $request->getOperation()) {
            $entity->setCreated(new \DateTime('now'));
        }
    }
}
