<?php
/**
 * DefaultEventHandler Class CRM
 *
 * @category   DefaultEventHandler
 * @package    contrexx
 * @subpackage module_crm
 * @author     SoftSolutions4U Development Team <info@softsolutions4u.com>
 * @copyright  2012 and CONTREXX CMS - COMVATION AG
 * @license    trial license
 * @link       www.contrexx.com
 */

/**
 * DefaultEventHandler Class CRM
 *
 * @category   DefaultEventHandler
 * @package    contrexx
 * @subpackage module_crm
 * @author     SoftSolutions4U Development Team <info@softsolutions4u.com>
 * @copyright  2012 and CONTREXX CMS - COMVATION AG
 * @license    trial license
 * @link       www.contrexx.com
 */
class DefaultEventHandler implements EventHandler
{
    /**
     * Default Information
     *
     * @access protected
     * @var array
     */
    protected $default_info = array(
        'section'   => 'crm',
        'lang_id'   => FRONTEND_LANG_ID,
    );

    /**
     * Event handler
     * 
     * @param Event $event calling event
     *
     * @return boolean
     */
    function handleEvent(Event $event)
    {
        $info = $event->getInfo();
        $substitutions = isset($info['substitution']) ? $info['substitution'] : array();
        $arrMailTemplate = array_merge(
            $this->default_info,
            array(
                'key'     => $event->getName(),
                'substitution' => $substitutions,
        ));

        if (false === MailTemplate::send($arrMailTemplate)) {
            $event->cancel();
            return false;
        };
        return true;
    }
}

