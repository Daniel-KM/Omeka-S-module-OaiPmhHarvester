<?php

namespace OaiPmhHarvester\Entity;

use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Item;

/**
 * @Entity
 * @Table(
 *     name="oaipmhharvester_source_record",
 *     indexes={@Index(fields={"source", "identifier"})},
 *     uniqueConstraints={@UniqueConstraint(fields={"item", "source", "identifier"})}
 * )
 */
class SourceRecord extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @ManyToOne(targetEntity="Omeka\Entity\Item")
     * @JoinColumn(nullable=false, unique=true, onDelete="cascade")
     */
    protected Item $item;

    /**
     * @ManyToOne(targetEntity="Source", inversedBy="records")
     * @JoinColumn(nullable=false, onDelete="cascade")
     */
    protected Source $source;

    /**
     * @Column
     */
    protected string $identifier;

    public function getId()
    {
        return $this->id;
    }

    public function getItem(): Item
    {
        return $this->item;
    }

    public function setItem(Item $item)
    {
        $this->item = $item;
    }

    public function getSource(): Source
    {
        return $this->source;
    }

    public function setSource(Source $source)
    {
        $this->source = $source;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier)
    {
        $this->identifier = $identifier;
    }
}
