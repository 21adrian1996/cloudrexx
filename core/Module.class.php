<?php

/**
 * Module
 *
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      COMVATION Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  core
 */

/**
 * ModuleException
 *
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      COMVATION Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  core
 */
class ModuleException extends Exception {}

/**
 * Module
 *
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      COMVATION Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  core
 */
class Module {
    protected $defaultAct = '';

    protected $act = '';
    /**
     *
     * @var \Cx\Core\Html\Sigma
     */
    protected $template = null;

    public function __construct($act, $template) {
        $this->act = $act;
        $this->template = $template;
	}

    public function getPage() {
        if($this->act == '') {
            $this->act = $this->defaultAct;
        }

        /*
         * TODO: Carify with Severin why an act method must start with 'act'.
         * (Manuel, Florian, Thomas) decided to remove this, as no benefit can be seen from this constraint.
         */
        //prevent execution of non-act methods.
        /*if(substr($this->act, 0, 3) != 'act') {
            throw new ModuleException('acts start with "act", "' . $this->act . '" given');
        }*/

        //call the right act.
        $act = $this->act;
        if(method_exists($this, $act))
            $this->$act();
        else
            throw new ModuleException('unknown act: "' . $this->act . '"');
    }
}
