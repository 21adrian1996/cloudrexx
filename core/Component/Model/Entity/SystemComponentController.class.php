<?php

/**
 * This is the superclass for all main Controllers for a Component
 * 
 * Decorator for SystemComponent
 * Every component needs a SystemComponentController for initialization
 * @author Michael Ritter <michael.ritter@comvation.com>
 */
namespace Cx\Core\Component\Model\Entity;

/**
 * This is the superclass for all main Controllers for a Component
 * 
 * Decorator for SystemComponent
 * Every component needs a SystemComponentController for initialization
 * @author Michael Ritter <michael.ritter@comvation.com>
 */
abstract class SystemComponentController extends Controller {
    private $controllers = array();
    
    /**
     * @var Cx\Core\Component\Model\Entity\SystemComponent
     */
    protected $systemComponent;
    
    /**
     * Initializes a controller
     * @param \Cx\Core\Component\Model\Entity\SystemComponent $systemComponent SystemComponent to decorate
     */
    public function __construct(\Cx\Core\Component\Model\Entity\SystemComponent $systemComponent) {
        $this->systemComponent = $systemComponent;
    }
    
    /**
     * Returns the main controller
     * @return SystemComponentController Main controller for this system component
     */
    public function getSystemComponentController() {
        return $this;
    }
    
    /**
     * Returns the SystemComponent this Controller decorates
     * @return \Cx\Core\Component\Model\Entity\SystemComponent
     */
    public function getSystemComponent() {
        return $this->systemComponent;
    }
    
    public function registerController(Controller $controller) {
        if (isset($this->controllers[get_class($controller)])) {
            return;
        }
        $this->controllers[get_class($controller)] = $controller;
    }
    
    public function getControllers($loadedOnly = true) {
        if ($loadedOnly) {
            return $this->controllers;
        }
        foreach ($this->getControllerClasses() as $class) {
            if (isset($this->controllers[$class])) {
                continue;
            }
            new $class($this);
        }
        return $this->getControllers();
    }
    
    public function getControllerClasses() {
        return array('Frontend', 'Backend');
    }
    
    /**
     * Decoration: all methods that are not specified in this or child classes
     * call the corresponding method of the decorated SystemComponent
     * @param string $methodName Name of method to call
     * @param array $arguments List of arguments for the method to call
     * @return mixed Return value of the method to call
     */
    public function __call($methodName, $arguments) {
        return call_user_func(array($this->systemComponent, $methodName), $arguments);
    }
    
    /**
     * Returns a list of JsonAdapter class names
     * 
     * The array values might be a class name without namespace. In that case
     * the namespace \Cx\{component_type}\{component_name}\Controller is used.
     * If the array value starts with a backslash, no namespace is added.
     * 
     * Avoid calculation of anything, just return an array!
     * @return array List of ComponentController classes
     */
    public function getControllersAccessableByJson() {}
    
    /**
     * Do something before resolving is done
     * 
     * USE CAREFULLY, DO NOT DO ANYTHING COSTLY HERE!
     * CALCULATE YOUR STUFF AS LATE AS POSSIBLE
     * @param \Cx\Core\Cx                               $cx         The Contrexx main class
     * @param \Cx\Core\Routing\Url                      $request    The URL object for this request
     */
    public function preResolve(\Cx\Core\Cx $cx, \Cx\Core\Routing\Url $request) {}
    
    /**
     * Do something after resolving is done
     * 
     * USE CAREFULLY, DO NOT DO ANYTHING COSTLY HERE!
     * CALCULATE YOUR STUFF AS LATE AS POSSIBLE
     * @param \Cx\Core\Cx                               $cx         The Contrexx main class
     * @param \Cx\Core\ContentManager\Model\Entity\Page $page       The resolved page
     */
    public function postResolve(\Cx\Core\Cx $cx, \Cx\Core\ContentManager\Model\Entity\Page $page) {}
    
    /**
     * Do something before content is loaded from DB
     * 
     * USE CAREFULLY, DO NOT DO ANYTHING COSTLY HERE!
     * CALCULATE YOUR STUFF AS LATE AS POSSIBLE
     * @param \Cx\Core\Cx                               $cx         The Contrexx main class
     * @param \Cx\Core\ContentManager\Model\Entity\Page $page       The resolved page
     */
    public function preContentLoad(\Cx\Core\Cx $cx, \Cx\Core\ContentManager\Model\Entity\Page $page) {}
    
    /**
     * Do something before a module is loaded
     * 
     * This method is called only if any module
     * gets loaded for content parsing
     * USE CAREFULLY, DO NOT DO ANYTHING COSTLY HERE!
     * CALCULATE YOUR STUFF AS LATE AS POSSIBLE
     * @param \Cx\Core\Cx                               $cx         The Contrexx main class
     * @param \Cx\Core\ContentManager\Model\Entity\Page $page       The resolved page
     */
    public function preContentParse(\Cx\Core\Cx $cx, \Cx\Core\ContentManager\Model\Entity\Page $page){}
    
    /**
     * Load your component. It is needed for this request.
     * 
     * This loads your FrontendController or BackendController depending on the
     * mode Cx runs in. For modes other than frontend and backend, nothing is done.
     * If you you'd like to name your Controllers differently, or have another
     * use case, overwrite this.
     * @param \Cx\Core\Cx                               $cx         The Contrexx main class
     * @param \Cx\Core\ContentManager\Model\Entity\Page $page       The resolved page
     */
    public function load(\Cx\Core\Cx $cx, \Cx\Core\ContentManager\Model\Entity\Page $page) {
        $controllerClass = null;
        $baseNs = $cx->getDb()->getEntityManager()->getRepository('\\Cx\\Core\\Component\\Model\\Entity\\SystemComponent')->getNamespaceFor($this->getSystemComponent());
        $baseNs .= '\\Controller\\';
        if ($cx->getMode() == \Cx\Core\Cx::MODE_FRONTEND) {
            $controllerClass = $baseNs . 'FrontendController';
        } else if ($cx->getMode() == \Cx\Core\Cx::MODE_BACKEND) {
            $controllerClass = $baseNs . 'BackendController';
        }
        if (!$controllerClass && !class_exists($controllerClass)) {
            return;
        }
        $controller = new $controllerClass($this);
        $controller->getPage($cx, $page);
    }
    
    /**
     * Do something after a module is loaded
     * 
     * This method is called only if any module
     * gets loaded for content parsing
     * USE CAREFULLY, DO NOT DO ANYTHING COSTLY HERE!
     * CALCULATE YOUR STUFF AS LATE AS POSSIBLE
     * @param \Cx\Core\Cx                               $cx         The Contrexx main class
     * @param \Cx\Core\ContentManager\Model\Entity\Page $page       The resolved page
     */
    public function postContentParse(\Cx\Core\Cx $cx, \Cx\Core\ContentManager\Model\Entity\Page $page) {}
    
    /**
     * Do something after content is loaded from DB
     * 
     * USE CAREFULLY, DO NOT DO ANYTHING COSTLY HERE!
     * CALCULATE YOUR STUFF AS LATE AS POSSIBLE
     * @param \Cx\Core\Cx                               $cx         The Contrexx main class
     * @param \Cx\Core\ContentManager\Model\Entity\Page $page       The resolved page
     */
    public function postContentLoad(\Cx\Core\Cx $cx, \Cx\Core\ContentManager\Model\Entity\Page $page) {}
    
    /**
     * Do something before main template gets parsed
     * 
     * USE CAREFULLY, DO NOT DO ANYTHING COSTLY HERE!
     * CALCULATE YOUR STUFF AS LATE AS POSSIBLE
     * @param \Cx\Core\Cx                               $cx         The Contrexx main class
     * @param \Cx\Core\Html\Sigma                       $template   The main template
     */
    public function preFinalize(\Cx\Core\Cx $cx, \Cx\Core\Html\Sigma $template) {}
    
    /**
     * Do something after main template got parsed
     * 
     * USE CAREFULLY, DO NOT DO ANYTHING COSTLY HERE!
     * @param \Cx\Core\Cx                               $cx         The Contrexx main class
     */
    public function postFinalize(\Cx\Core\Cx $cx) {}
}
