<?php

namespace Cx\Model\Proxies;

/**
 * THIS CLASS WAS GENERATED BY THE DOCTRINE ORM. DO NOT EDIT THIS FILE.
 */
class CxCore_ModulesMultiSiteModelEntityWebsiteCollectionProxy extends \Cx\Core_Modules\MultiSite\Model\Entity\WebsiteCollection implements \Doctrine\ORM\Proxy\Proxy
{
    private $_entityPersister;
    private $_identifier;
    public $__isInitialized__ = false;
    public function __construct($entityPersister, $identifier)
    {
        $this->_entityPersister = $entityPersister;
        $this->_identifier = $identifier;
    }
    private function _load()
    {
        if (!$this->__isInitialized__ && $this->_entityPersister) {
            $this->__isInitialized__ = true;
            if ($this->_entityPersister->load($this->_identifier, $this) === null) {
                throw new \Doctrine\ORM\EntityNotFoundException();
            }
            unset($this->_entityPersister, $this->_identifier);
        }
    }

    
    public function getId()
    {
        $this->_load();
        return parent::getId();
    }

    public function setId($id)
    {
        $this->_load();
        return parent::setId($id);
    }

    public function getQuota()
    {
        $this->_load();
        return parent::getQuota();
    }

    public function setQuota($quota)
    {
        $this->_load();
        return parent::setQuota($quota);
    }

    public function getWebsites()
    {
        $this->_load();
        return parent::getWebsites();
    }

    public function setWebsite(\Cx\Core_Modules\MultiSite\Model\Entity\Website $website)
    {
        $this->_load();
        return parent::setWebsite($website);
    }

    public function addWebsite(\Cx\Core_Modules\MultiSite\Model\Entity\Website $website)
    {
        $this->_load();
        return parent::addWebsite($website);
    }

    public function getWebsiteTemplate()
    {
        $this->_load();
        return parent::getWebsiteTemplate();
    }

    public function setWebsiteTemplate(\Cx\Core_Modules\MultiSite\Model\Entity\WebsiteTemplate $websiteTemplate)
    {
        $this->_load();
        return parent::setWebsiteTemplate($websiteTemplate);
    }

    public function __get($name)
    {
        $this->_load();
        return parent::__get($name);
    }

    public function getComponentController()
    {
        $this->_load();
        return parent::getComponentController();
    }

    public function setVirtual($virtual)
    {
        $this->_load();
        return parent::setVirtual($virtual);
    }

    public function isVirtual()
    {
        $this->_load();
        return parent::isVirtual();
    }

    public function validate()
    {
        $this->_load();
        return parent::validate();
    }

    public function __toString()
    {
        $this->_load();
        return parent::__toString();
    }


    public function __sleep()
    {
        return array('__isInitialized__', 'id', 'quota', 'websites', 'websiteTemplate');
    }

    public function __clone()
    {
        if (!$this->__isInitialized__ && $this->_entityPersister) {
            $this->__isInitialized__ = true;
            $class = $this->_entityPersister->getClassMetadata();
            $original = $this->_entityPersister->load($this->_identifier);
            if ($original === null) {
                throw new \Doctrine\ORM\EntityNotFoundException();
            }
            foreach ($class->reflFields AS $field => $reflProperty) {
                $reflProperty->setValue($this, $reflProperty->getValue($original));
            }
            unset($this->_entityPersister, $this->_identifier);
        }
        
    }
}