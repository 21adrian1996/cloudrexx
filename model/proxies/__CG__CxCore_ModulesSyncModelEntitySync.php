<?php

namespace Cx\Model\Proxies\__CG__\Cx\Core_Modules\Sync\Model\Entity;

/**
 * DO NOT EDIT THIS FILE - IT WAS CREATED BY DOCTRINE'S PROXY GENERATOR
 */
class Sync extends \Cx\Core_Modules\Sync\Model\Entity\Sync implements \Doctrine\ORM\Proxy\Proxy
{
    /**
     * @var \Closure the callback responsible for loading properties in the proxy object. This callback is called with
     *      three parameters, being respectively the proxy object to be initialized, the method that triggered the
     *      initialization process and an array of ordered parameters that were passed to that method.
     *
     * @see \Doctrine\Common\Persistence\Proxy::__setInitializer
     */
    public $__initializer__;

    /**
     * @var \Closure the callback responsible of loading properties that need to be copied in the cloned object
     *
     * @see \Doctrine\Common\Persistence\Proxy::__setCloner
     */
    public $__cloner__;

    /**
     * @var boolean flag indicating if this object was already initialized
     *
     * @see \Doctrine\Common\Persistence\Proxy::__isInitialized
     */
    public $__isInitialized__ = false;

    /**
     * @var array properties to be lazy loaded, with keys being the property
     *            names and values being their default values
     *
     * @see \Doctrine\Common\Persistence\Proxy::__getLazyProperties
     */
    public static $lazyPropertiesDefaults = array();



    /**
     * @param \Closure $initializer
     * @param \Closure $cloner
     */
    public function __construct($initializer = null, $cloner = null)
    {

        $this->__initializer__ = $initializer;
        $this->__cloner__      = $cloner;
    }

    /**
     * {@inheritDoc}
     * @param string $name
     */
    public function __get($name)
    {
        $this->__initializer__ && $this->__initializer__->__invoke($this, '__get', array($name));

        return parent::__get($name);
    }





    /**
     * 
     * @return array
     */
    public function __sleep()
    {
        if ($this->__isInitialized__) {
            return array('__isInitialized__', 'id', 'toUri', 'apiKey', 'active', 'dataAccess', 'relations', 'hostEntities', 'changes', 'oldHostEntities', 'validators', 'virtual');
        }

        return array('__isInitialized__', 'id', 'toUri', 'apiKey', 'active', 'dataAccess', 'relations', 'hostEntities', 'changes', 'oldHostEntities', 'validators', 'virtual');
    }

    /**
     * 
     */
    public function __wakeup()
    {
        if ( ! $this->__isInitialized__) {
            $this->__initializer__ = function (Sync $proxy) {
                $proxy->__setInitializer(null);
                $proxy->__setCloner(null);

                $existingProperties = get_object_vars($proxy);

                foreach ($proxy->__getLazyProperties() as $property => $defaultValue) {
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
        $this->__cloner__ && $this->__cloner__->__invoke($this, '__clone', array());
    }

    /**
     * Forces initialization of the proxy
     */
    public function __load()
    {
        $this->__initializer__ && $this->__initializer__->__invoke($this, '__load', array());
    }

    /**
     * {@inheritDoc}
     * @internal generated method: use only when explicitly handling proxy specific loading logic
     */
    public function __isInitialized()
    {
        return $this->__isInitialized__;
    }

    /**
     * {@inheritDoc}
     * @internal generated method: use only when explicitly handling proxy specific loading logic
     */
    public function __setInitialized($initialized)
    {
        $this->__isInitialized__ = $initialized;
    }

    /**
     * {@inheritDoc}
     * @internal generated method: use only when explicitly handling proxy specific loading logic
     */
    public function __setInitializer(\Closure $initializer = null)
    {
        $this->__initializer__ = $initializer;
    }

    /**
     * {@inheritDoc}
     * @internal generated method: use only when explicitly handling proxy specific loading logic
     */
    public function __getInitializer()
    {
        return $this->__initializer__;
    }

    /**
     * {@inheritDoc}
     * @internal generated method: use only when explicitly handling proxy specific loading logic
     */
    public function __setCloner(\Closure $cloner = null)
    {
        $this->__cloner__ = $cloner;
    }

    /**
     * {@inheritDoc}
     * @internal generated method: use only when explicitly handling proxy specific cloning logic
     */
    public function __getCloner()
    {
        return $this->__cloner__;
    }

    /**
     * {@inheritDoc}
     * @internal generated method: use only when explicitly handling proxy specific loading logic
     * @static
     */
    public function __getLazyProperties()
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


        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getId', array());

        return parent::getId();
    }

    /**
     * {@inheritDoc}
     */
    public function setToUri($toUri)
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setToUri', array($toUri));

        return parent::setToUri($toUri);
    }

    /**
     * {@inheritDoc}
     */
    public function getToUri($entityIndexData = array (
))
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getToUri', array($entityIndexData));

        return parent::getToUri($entityIndexData);
    }

    /**
     * {@inheritDoc}
     */
    public function setApiKey($apiKey)
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setApiKey', array($apiKey));

        return parent::setApiKey($apiKey);
    }

    /**
     * {@inheritDoc}
     */
    public function getApiKey()
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getApiKey', array());

        return parent::getApiKey();
    }

    /**
     * {@inheritDoc}
     */
    public function setActive($active)
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setActive', array($active));

        return parent::setActive($active);
    }

    /**
     * {@inheritDoc}
     */
    public function setTempActive($tempActive)
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setTempActive', array($tempActive));

        return parent::setTempActive($tempActive);
    }

    /**
     * {@inheritDoc}
     */
    public function getActive()
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getActive', array());

        return parent::getActive();
    }

    /**
     * {@inheritDoc}
     */
    public function setDataAccess(\Cx\Core_Modules\DataAccess\Model\Entity\DataAccess $dataAccess)
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setDataAccess', array($dataAccess));

        return parent::setDataAccess($dataAccess);
    }

    /**
     * {@inheritDoc}
     */
    public function getDataAccess()
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getDataAccess', array());

        return parent::getDataAccess();
    }

    /**
     * {@inheritDoc}
     */
    public function addRelation(\Cx\Core_Modules\Sync\Model\Entity\Relation $relation)
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'addRelation', array($relation));

        return parent::addRelation($relation);
    }

    /**
     * {@inheritDoc}
     */
    public function getRelations()
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getRelations', array());

        return parent::getRelations();
    }

    /**
     * {@inheritDoc}
     */
    public function setRelations($relations)
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setRelations', array($relations));

        return parent::setRelations($relations);
    }

    /**
     * {@inheritDoc}
     */
    public function addHostEntity(\Cx\Core_Modules\Sync\Model\Entity\HostEntity $hostEntity)
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'addHostEntity', array($hostEntity));

        return parent::addHostEntity($hostEntity);
    }

    /**
     * {@inheritDoc}
     */
    public function getHostEntities()
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getHostEntities', array());

        return parent::getHostEntities();
    }

    /**
     * {@inheritDoc}
     */
    public function getHostEntitiesIncludingLegacy($cached = true)
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getHostEntitiesIncludingLegacy', array($cached));

        return parent::getHostEntitiesIncludingLegacy($cached);
    }

    /**
     * {@inheritDoc}
     */
    public function setOldHostEntitiesIncludingLegacy($hostEntities)
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setOldHostEntitiesIncludingLegacy', array($hostEntities));

        return parent::setOldHostEntitiesIncludingLegacy($hostEntities);
    }

    /**
     * {@inheritDoc}
     */
    public function getOldHostEntitiesIncludingLegacy()
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getOldHostEntitiesIncludingLegacy', array());

        return parent::getOldHostEntitiesIncludingLegacy();
    }

    /**
     * {@inheritDoc}
     */
    public function getRemovedHosts($entityIndexData)
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getRemovedHosts', array($entityIndexData));

        return parent::getRemovedHosts($entityIndexData);
    }

    /**
     * {@inheritDoc}
     */
    public function setHostEntities($hostEntities)
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setHostEntities', array($hostEntities));

        return parent::setHostEntities($hostEntities);
    }

    /**
     * {@inheritDoc}
     */
    public function addChange(\Cx\Core_Modules\Sync\Model\Entity\Change $change)
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'addChange', array($change));

        return parent::addChange($change);
    }

    /**
     * {@inheritDoc}
     */
    public function getChanges()
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getChanges', array());

        return parent::getChanges();
    }

    /**
     * {@inheritDoc}
     */
    public function setChanges($changes)
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setChanges', array($changes));

        return parent::setChanges($changes);
    }

    /**
     * {@inheritDoc}
     */
    public function calculateRelations($spooler, $eventType, $entityClassName, $entityIndexData, $entity)
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'calculateRelations', array($spooler, $eventType, $entityClassName, $entityIndexData, $entity));

        return parent::calculateRelations($spooler, $eventType, $entityClassName, $entityIndexData, $entity);
    }

    /**
     * {@inheritDoc}
     */
    public function getRelatedHosts($entityIndexData)
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getRelatedHosts', array($entityIndexData));

        return parent::getRelatedHosts($entityIndexData);
    }

    /**
     * {@inheritDoc}
     */
    public function getComponentController()
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getComponentController', array());

        return parent::getComponentController();
    }

    /**
     * {@inheritDoc}
     */
    public function setVirtual($virtual)
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setVirtual', array($virtual));

        return parent::setVirtual($virtual);
    }

    /**
     * {@inheritDoc}
     */
    public function isVirtual()
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'isVirtual', array());

        return parent::isVirtual();
    }

    /**
     * {@inheritDoc}
     */
    public function validate()
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'validate', array());

        return parent::validate();
    }

    /**
     * {@inheritDoc}
     */
    public function __call($methodName, $arguments)
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, '__call', array($methodName, $arguments));

        return parent::__call($methodName, $arguments);
    }

    /**
     * {@inheritDoc}
     */
    public function __toString()
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, '__toString', array());

        return parent::__toString();
    }

}
