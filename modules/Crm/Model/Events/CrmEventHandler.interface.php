<?php
/**
 * CrmEventHandler Interface CRM
 *
 * @category   CrmEventHandler
 * @package    contrexx
 * @subpackage module_crm
 * @author     SoftSolutions4U Development Team <info@softsolutions4u.com>
 * @copyright  2012 and CONTREXX CMS - COMVATION AG
 * @license    trial license
 * @link       www.contrexx.com
 */

namespace Cx\Modules\Crm\Model\Events;

/**
 * CrmEventHandler Interface CRM
 *
 * @category   CrmEventHandler
 * @package    contrexx
 * @subpackage module_crm
 * @author     SoftSolutions4U Development Team <info@softsolutions4u.com>
 * @copyright  2012 and CONTREXX CMS - COMVATION AG
 * @license    trial license
 * @link       www.contrexx.com
 */
interface CrmEventHandler
{
    /**
     * Event handler
     * 
     * @param Event $event event name
     *
     * @return null
     */
    function handleEvent(\Cx\Modules\Crm\Model\Entity\CrmEvent $event);
}
 
