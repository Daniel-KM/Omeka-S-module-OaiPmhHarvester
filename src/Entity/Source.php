<?php declare(strict_types=1);

namespace OaiPmhHarvester\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Omeka\Entity\AbstractEntity;

/**
 * @Entity
 * @Table(
 *     name="oaipmhharvester_source",
 * )
 */
class Source extends AbstractEntity
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
    protected string $baseUrl;

    /**
     * @Column
     */
    protected string $metadataPrefix;

    /**
     * @ManyToOne(targetEntity="Configuration")
     * @JoinColumn(nullable=false)
     */
    protected Configuration $configuration;

    /**
     * @Column(type="json")
     */
    protected array $sets = [];

    /**
     * @OneToMany(targetEntity="SourceRecord", mappedBy="source")
     */
    protected Collection $records;

    /**
     * @ManyToMany(targetEntity="Omeka\Entity\Job")
     * @JoinTable(
     *     name="oaipmhharvester_source_job",
     *     joinColumns={@JoinColumn(name="source_id", referencedColumnName="id", onDelete="cascade")},
     *     inverseJoinColumns={@JoinColumn(name="job_id", referencedColumnName="id", unique=true, onDelete="cascade")}
     * )
     */
    protected Collection $jobs;

    public function __construct()
    {
        $this->records = new ArrayCollection();
        $this->jobs = new ArrayCollection();
    }

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

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(string $baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    public function getMetadataPrefix(): string
    {
        return $this->metadataPrefix;
    }

    public function setMetadataPrefix(string $metadataPrefix)
    {
        $this->metadataPrefix = $metadataPrefix;
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    public function setConfiguration(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    public function getSets(): array
    {
        return $this->sets;
    }

    public function setSets(array $sets)
    {
        $this->sets = $sets;
    }

    public function getRecords(): Collection
    {
        return $this->records;
    }

    public function getJobs(): Collection
    {
        return $this->jobs;
    }
}
