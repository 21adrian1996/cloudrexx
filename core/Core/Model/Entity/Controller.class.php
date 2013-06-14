<?php
/**
 * This is the superclass for all Controller classes
 * 
 * @author Michael Ritter <michael.ritter@comvation.com>
 */
namespace Cx\Core\Core\Model\Entity;

/**
 * This is the superclass for all Controller classes
 * 
 * @author Michael Ritter <michael.ritter@comvation.com>
 */
abstract class Controller {
    
    /**
     * Main class instance
     * @var \Cx\Core\Core\Controller\Cx
     */
    protected $cx = null;
    
    /**
     * SystemComponentController for this Component
     * @var \Cx\Core\Core\Model\Entity\SystemComponentController
     */
    private $systemComponentController = null;
    
    /**
     * Creates new controller
     * @param SystemComponentController $systemComponentController Main controller for this system component
     */
    public function __construct(SystemComponentController $systemComponentController, \Cx\Core\Core\Controller\Cx $cx) {
        $this->cx = $cx;
        $this->systemComponentController = $systemComponentController;
        $this->systemComponentController->registerController($this);
    }
    
    /**
     * Returns the main controller
     * @return SystemComponentController Main controller for this system component
     */
    public function getSystemComponentController() {
        return $this->systemComponentController;
    }
}
