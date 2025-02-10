<?php

namespace DoctrineProxies\__CG__\OaiPmhHarvester\Entity;


/**
 * DO NOT EDIT THIS FILE - IT WAS CREATED BY DOCTRINE'S PROXY GENERATOR
 */
class Harvest extends \OaiPmhHarvester\Entity\Harvest implements \Doctrine\ORM\Proxy\Proxy
{
    /**
     * @var \Closure the callback responsible for loading properties in the proxy object. This callback is called with
     *      three parameters, being respectively the proxy object to be initialized, the method that triggered the
     *      initialization process and an array of ordered parameters that were passed to that method.
     *
     * @see \Doctrine\Common\Proxy\Proxy::__setInitializer
     */
    public $__initializer__;

    /**
     * @var \Closure the callback responsible of loading properties that need to be copied in the cloned object
     *
     * @see \Doctrine\Common\Proxy\Proxy::__setCloner
     */
    public $__cloner__;

    /**
     * @var boolean flag indicating if this object was already initialized
     *
     * @see \Doctrine\Persistence\Proxy::__isInitialized
     */
    public $__isInitialized__ = false;

    /**
     * @var array<string, null> properties to be lazy loaded, indexed by property name
     */
    public static $lazyPropertiesNames = array (
);

    /**
     * @var array<string, mixed> default values of properties to be lazy loaded, with keys being the property names
     *
     * @see \Doctrine\Common\Proxy\Proxy::__getLazyProperties
     */
    public static $lazyPropertiesDefaults = array (
);



    public function __construct(?\Closure $initializer = null, ?\Closure $cloner = null)
    {

        $this->__initializer__ = $initializer;
        $this->__cloner__      = $cloner;
    }







    /**
     * 
     * @return array
     */
    public function __sleep()
    {
        if ($this->__isInitialized__) {
            return ['__isInitialized__', 'id', 'job', 'undoJob', 'message', 'endpoint', 'entityName', 'itemSet', 'metadataPrefix', 'from', 'until', 'setSpec', 'setName', 'setDescription', 'hasErr', 'stats', 'resumptionToken'];
        }

        return ['__isInitialized__', 'id', 'job', 'undoJob', 'message', 'endpoint', 'entityName', 'itemSet', 'metadataPrefix', 'from', 'until', 'setSpec', 'setName', 'setDescription', 'hasErr', 'stats', 'resumptionToken'];
    }

    /**
     * 
     */
    public function __wakeup()
    {
        if ( ! $this->__isInitialized__) {
            $this->__initializer__ = function (Harvest $proxy) {
                $proxy->__setInitializer(null);
                $proxy->__setCloner(null);

                $existingProperties = get_object_vars($proxy);

                foreach ($proxy::$lazyPropertiesDefaults as $property => $defaultValue) {
                    if ( ! array_key_exists($property, $existingProperties)) {
                        $proxy->$property = $defaultValue;
                    }
                }
            };

        }
    }

    /**
     * 
     */
    public function __clone()
    {
        $this->__cloner__ && $this->__cloner__->__invoke($this, '__clone', []);
    }

    /**
     * Forces initialization of the proxy
     */
    public function __load(): void
    {
        $this->__initializer__ && $this->__initializer__->__invoke($this, '__load', []);
    }

    /**
     * {@inheritDoc}
     * @internal generated method: use only when explicitly handling proxy specific loading logic
     */
    public function __isInitialized(): bool
    {
        return $this->__isInitialized__;
    }

    /**
     * {@inheritDoc}
     * @internal generated method: use only when explicitly handling proxy specific loading logic
     */
    public function __setInitialized($initialized): void
    {
        $this->__isInitialized__ = $initialized;
    }

    /**
     * {@inheritDoc}
     * @internal generated method: use only when explicitly handling proxy specific loading logic
     */
    public function __setInitializer(\Closure $initializer = null): void
    {
        $this->__initializer__ = $initializer;
    }

    /**
     * {@inheritDoc}
     * @internal generated method: use only when explicitly handling proxy specific loading logic
     */
    public function __getInitializer(): ?\Closure
    {
        return $this->__initializer__;
    }

    /**
     * {@inheritDoc}
     * @internal generated method: use only when explicitly handling proxy specific loading logic
     */
    public function __setCloner(\Closure $cloner = null): void
    {
        $this->__cloner__ = $cloner;
    }

    /**
     * {@inheritDoc}
     * @internal generated method: use only when explicitly handling proxy specific cloning logic
     */
    public function __getCloner(): ?\Closure
    {
        return $this->__cloner__;
    }

    /**
     * {@inheritDoc}
     * @internal generated method: use only when explicitly handling proxy specific loading logic
     * @deprecated no longer in use - generated code now relies on internal components rather than generated public API
     * @static
     */
    public function __getLazyProperties(): array
    {
        return self::$lazyPropertiesDefaults;
    }

    
    /**
     * {@inheritDoc}
     */
    public function getId()
    {
        if ($this->__isInitialized__ === false) {
            return (int)  parent::getId();
        }


        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getId', []);

        return parent::getId();
    }

    /**
     * {@inheritDoc}
     */
    public function setJob(\Omeka\Entity\Job $job): \OaiPmhHarvester\Entity\Harvest
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setJob', [$job]);

        return parent::setJob($job);
    }

    /**
     * {@inheritDoc}
     */
    public function getJob(): \Omeka\Entity\Job
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getJob', []);

        return parent::getJob();
    }

    /**
     * {@inheritDoc}
     */
    public function setUndoJob(?\Omeka\Entity\Job $undoJob): \OaiPmhHarvester\Entity\Harvest
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setUndoJob', [$undoJob]);

        return parent::setUndoJob($undoJob);
    }

    /**
     * {@inheritDoc}
     */
    public function getUndoJob(): ?\Omeka\Entity\Job
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getUndoJob', []);

        return parent::getUndoJob();
    }

    /**
     * {@inheritDoc}
     */
    public function setMessage(?string $message): \OaiPmhHarvester\Entity\Harvest
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setMessage', [$message]);

        return parent::setMessage($message);
    }

    /**
     * {@inheritDoc}
     */
    public function getMessage(): ?string
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getMessage', []);

        return parent::getMessage();
    }

    /**
     * {@inheritDoc}
     */
    public function setEndpoint(string $endpoint): \OaiPmhHarvester\Entity\Harvest
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setEndpoint', [$endpoint]);

        return parent::setEndpoint($endpoint);
    }

    /**
     * {@inheritDoc}
     */
    public function getEndpoint(): string
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getEndpoint', []);

        return parent::getEndpoint();
    }

    /**
     * {@inheritDoc}
     */
    public function setEntityName(string $entityName): \OaiPmhHarvester\Entity\Harvest
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setEntityName', [$entityName]);

        return parent::setEntityName($entityName);
    }

    /**
     * {@inheritDoc}
     */
    public function getEntityName(): string
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getEntityName', []);

        return parent::getEntityName();
    }

    /**
     * {@inheritDoc}
     */
    public function setItemSet(?\Omeka\Entity\ItemSet $itemSet): \OaiPmhHarvester\Entity\Harvest
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setItemSet', [$itemSet]);

        return parent::setItemSet($itemSet);
    }

    /**
     * {@inheritDoc}
     */
    public function getItemSet(): ?\Omeka\Entity\ItemSet
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getItemSet', []);

        return parent::getItemSet();
    }

    /**
     * {@inheritDoc}
     */
    public function setMetadataPrefix($metadataPrefix): \OaiPmhHarvester\Entity\Harvest
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setMetadataPrefix', [$metadataPrefix]);

        return parent::setMetadataPrefix($metadataPrefix);
    }

    /**
     * {@inheritDoc}
     */
    public function getMetadataPrefix(): string
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getMetadataPrefix', []);

        return parent::getMetadataPrefix();
    }

    /**
     * {@inheritDoc}
     */
    public function setFrom(?\DateTime $from): \OaiPmhHarvester\Entity\Harvest
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setFrom', [$from]);

        return parent::setFrom($from);
    }

    /**
     * {@inheritDoc}
     */
    public function getFrom(): ?\DateTime
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getFrom', []);

        return parent::getFrom();
    }

    /**
     * {@inheritDoc}
     */
    public function setUntil(?\DateTime $until): \OaiPmhHarvester\Entity\Harvest
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setUntil', [$until]);

        return parent::setUntil($until);
    }

    /**
     * {@inheritDoc}
     */
    public function getUntil(): ?\DateTime
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getUntil', []);

        return parent::getUntil();
    }

    /**
     * {@inheritDoc}
     */
    public function setSetSpec(?string $setSpec): \OaiPmhHarvester\Entity\Harvest
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setSetSpec', [$setSpec]);

        return parent::setSetSpec($setSpec);
    }

    /**
     * {@inheritDoc}
     */
    public function getSetSpec(): ?string
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getSetSpec', []);

        return parent::getSetSpec();
    }

    /**
     * {@inheritDoc}
     */
    public function setSetName(?string $setName): \OaiPmhHarvester\Entity\Harvest
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setSetName', [$setName]);

        return parent::setSetName($setName);
    }

    /**
     * {@inheritDoc}
     */
    public function getSetName(): ?string
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getSetName', []);

        return parent::getSetName();
    }

    /**
     * {@inheritDoc}
     */
    public function setSetDescription(?string $setDescription): \OaiPmhHarvester\Entity\Harvest
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setSetDescription', [$setDescription]);

        return parent::setSetDescription($setDescription);
    }

    /**
     * {@inheritDoc}
     */
    public function getSetDescription(): ?string
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getSetDescription', []);

        return parent::getSetDescription();
    }

    /**
     * {@inheritDoc}
     */
    public function setHasErr($hasErr): \OaiPmhHarvester\Entity\Harvest
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setHasErr', [$hasErr]);

        return parent::setHasErr($hasErr);
    }

    /**
     * {@inheritDoc}
     */
    public function getHasErr(): bool
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getHasErr', []);

        return parent::getHasErr();
    }

    /**
     * {@inheritDoc}
     */
    public function setStats(?array $stats): \OaiPmhHarvester\Entity\Harvest
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setStats', [$stats]);

        return parent::setStats($stats);
    }

    /**
     * {@inheritDoc}
     */
    public function getStats(): ?array
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getStats', []);

        return parent::getStats();
    }

    /**
     * {@inheritDoc}
     */
    public function setResumptionToken(?string $resumptionToken): \OaiPmhHarvester\Entity\Harvest
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setResumptionToken', [$resumptionToken]);

        return parent::setResumptionToken($resumptionToken);
    }

    /**
     * {@inheritDoc}
     */
    public function getResumptionToken(): ?string
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getResumptionToken', []);

        return parent::getResumptionToken();
    }

    /**
     * {@inheritDoc}
     */
    public function getResourceId()
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getResourceId', []);

        return parent::getResourceId();
    }

}
