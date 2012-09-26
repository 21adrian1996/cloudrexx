<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
namespace Cx\Core\License;

/**
 * Description of Message
 *
 * @author ritt0r
 */
class Message {
    private $langCode;
    private $text;
    private $type;
    private $link;
    private $linkTarget;
    private $showInDashboard = true;
    
    public function __construct($langCode, $text, $type, $link, $linkTarget, $showInDashboard = true) {
        $this->langCode = $langCode;
        $this->text = $text;
        $this->type = $type;
        $this->link = $link;
        $this->linkTarget = $linkTarget;
        $this->showInDashboard = $showInDashboard;
    }
    
    public function getLangCode() {
        return $this->langCode;
    }
    
    public function getText() {
        return $this->text;
    }
    
    public function getType() {
        return $this->type;
    }
    
    public function getLink() {
        return $this->link;
    }
    
    public function getLinkTarget() {
        return $this->linkTarget;
    }
    
    public function showInDashboard() {
        return $this->showInDashboard;
    }
}
