<?php

namespace Cx\Model\Proxies;

/**
 * THIS CLASS WAS GENERATED BY THE DOCTRINE ORM. DO NOT EDIT THIS FILE.
 */
class GedmoTranslatableEntityTranslationProxy extends \Gedmo\Translatable\Entity\Translation implements \Doctrine\ORM\Proxy\Proxy
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

    public function setLocale($locale)
    {
        $this->_load();
        return parent::setLocale($locale);
    }

    public function getLocale()
    {
        $this->_load();
        return parent::getLocale();
    }

    public function setField($field)
    {
        $this->_load();
        return parent::setField($field);
    }

    public function getField()
    {
        $this->_load();
        return parent::getField();
    }

    public function setObjectClass($objectClass)
    {
        $this->_load();
        return parent::setObjectClass($objectClass);
    }

    public function getObjectClass()
    {
        $this->_load();
        return parent::getObjectClass();
    }

    public function setForeignKey($foreignKey)
    {
        $this->_load();
        return parent::setForeignKey($foreignKey);
    }

    public function getForeignKey()
    {
        $this->_load();
        return parent::getForeignKey();
    }

    public function setContent($content)
    {
        $this->_load();
        return parent::setContent($content);
    }

    public function getContent()
    {
        $this->_load();
        return parent::getContent();
    }


    public function __sleep()
    {
        return array('__isInitialized__', 'locale', 'objectClass', 'field', 'foreignKey', 'content', 'id');
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