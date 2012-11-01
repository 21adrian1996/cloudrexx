<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
namespace Cx\Core_Modules\License;
/**
 * Description of Version
 *
 * @author ritt0r
 */
class Version {
    private $number;
    private $name;
    private $codeName;
    private $state;
    private $releaseDate;
    
    public function __construct($number, $name, $codeName, $state, $releaseDate) {
        $this->number = $number;
        $this->name = $name;
        $this->codeName = $codeName;
        $this->state = $state;
        $this->releaseDate = $releaseDate;
    }
    
    public function getNumber($asInt = false) {
        if ($asInt) {
            return $this->stringNumberToInt($this->number);
        }
        return $this->number;
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function getCodeName() {
        return $this->codeName;
    }
    
    public function getState() {
        return $this->state;
    }
    
    public function getReleaseDate() {
        return $this->releaseDate;
    }
    
    public function isNewerThan($otherVersion) {
        return ($this->getNumber(true) > $otherVersion->getNumber(true));
    }
    
    public function isEqualTo($otherVersion) {
        return ($this->getNumber() === $otherVersion->getNumber());
    }

    /**
    * Converts an integer version number to a string version number
    * @param int $vInt Integer version number
    * @return string String version number
    */
    public function intNumberToString($vInt) {
        return  intval(intval($vInt/10000)%100).'.'.
                intval(intval($vInt/  100)%100).'.'.
                intval(intval($vInt      )%100);
    }

    /**
    * Converts a string version number to an integer version number
    * @param string $vString String version number
    * @return int Integer version number
    */
    public function stringNumberToInt($vString) {
        $parts = explode('.', $vString);
        return $parts[0]  * 10000 + $parts[1]  * 100 + $parts[2];
    }
}
