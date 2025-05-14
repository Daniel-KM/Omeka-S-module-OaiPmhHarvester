<?php declare(strict_types=1);

namespace OaiPmhHarvester\Entity;

use Omeka\Entity\AbstractEntity;

/**
 * @Entity
 * @Table(
 *     name="oaipmhharvester_configuration",
 * )
 */
class Configuration extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @Column
     */
    protected string $name;

    /**
     * @Column
     */
    protected string $converterName;

    /**
     * @Column(type="json")
     */
    protected array $settings = [];

    public function getId()
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name)
    {
        $this->name = $name;
    }

    public function getConverterName(): string
    {
        return $this->converterName;
    }

    public function setConverterName(string $converterName)
    {
        $this->converterName = $converterName;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function setSettings(array $settings)
    {
        $this->settings = $settings;
    }
}
