<?php declare(strict_types=1);

namespace OaiPmhHarvester\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class SourceRecordAdapter extends AbstractEntityAdapter
{
    protected $sortFields = [
        'id' => 'id',
        'identifier' => 'identifier',
    ];

    protected $scalarFields = [
        'id' => 'id',
        'identifier' => 'identifier',
        'item' => 'item',
    ];

    public function getEntityClass()
    {
        return \OaiPmhHarvester\Entity\SourceRecord::class;
    }

    public function getResourceName()
    {
        return 'oaipmhharvester_source_records';
    }

    public function getRepresentationClass()
    {
        return \OaiPmhHarvester\Api\Representation\SourceRecordRepresentation::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        if (isset($query['item_id']) && is_numeric($query['item_id'])) {
            $itemAlias = $this->createAlias();
            $qb->innerJoin(
                'omeka_root.item',
                $itemAlias
            );
            $qb->andWhere($qb->expr()->eq(
                "$itemAlias.id",
                $this->createNamedParameter($qb, $query['item_id']))
            );
        }


        if (isset($query['source_id']) && is_numeric($query['source_id'])) {
            $sourceAlias = $this->createAlias();
            $qb->innerJoin(
                'omeka_root.source',
                $sourceAlias
            );
            $qb->andWhere($qb->expr()->eq(
                "$sourceAlias.id",
                $this->createNamedParameter($qb, $query['source_id']))
            );
        }

        if (isset($query['identifier'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root.identifier',
                $this->createNamedParameter($qb, $query['identifier']))
            );
        }
    }

    public function hydrate(Request $request, EntityInterface $entity, ErrorStore $errorStore): void
    {
        /** @var \OaiPmhHarvester\Entity\SourceRecord $entity */

        if ($this->shouldHydrate($request, 'o:item')) {
            $item = null;
            $data = $request->getContent();
            if (array_key_exists('o:item', $data)
                && is_array($data['o:item'])
                && array_key_exists('o:id', $data['o:item'])
            ) {
                $newItemId = $data['o:item']['o:id'];
                $newItemId = is_numeric($newItemId) ? (int) $newItemId : null;

                $item = $newItemId
                    ? $this->getAdapter('items')->findEntity($newItemId)
                    : null;
            }
            $entity->setItem($item);
        }

        if ($this->shouldHydrate($request, 'o:source')) {
            $source = null;
            $data = $request->getContent();
            if (array_key_exists('o:source', $data)
                && is_array($data['o:source'])
                && array_key_exists('o:id', $data['o:source'])
            ) {
                $newSourceId = $data['o:source']['o:id'];
                $newSourceId = is_numeric($newSourceId) ? (int) $newSourceId : null;

                $source = $newSourceId
                    ? $this->getAdapter('oaipmhharvester_sources')->findEntity($newSourceId)
                    : null;
            }
            $entity->setSource($source);
        }

        if ($this->shouldHydrate($request, 'o:identifier')) {
            $entity->setIdentifier($request->getValue('o:identifier'));
        }
    }
}
