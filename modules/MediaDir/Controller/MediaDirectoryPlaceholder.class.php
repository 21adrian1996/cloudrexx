<?php

/**
 * Media  Directory Placeholder Class
 *
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Comvation Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  module_mediadir
 * @todo        Edit PHP DocBlocks!
 */
namespace Cx\Modules\MediaDir\Controller;
/**
 * Media Directory Placeholder Class
 *
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      COMVATION Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  module_mediadir
 */
class MediaDirectoryPlaceholder extends MediaDirectoryLibrary
{
    function __construct($name)
    {
        parent::__construct('.', $name);
    }
    
    function getPlaceholder($strPlaceHolder)
    {
        if(substr($strPlaceHolder,0,14) == '[[ACCESS_USER_'){
// TODO: seams not to be working in the frontend right now
        	$strValue = self::__getAccessUserPlaceholder($strPlaceHolder);
        }
        
        return $strValue;
    }
    
    function __getAccessUserPlaceholder($strPlaceHolder)
    {
        global $objDatabase, $objInit;
        
        if($objInit->mode == 'frontend') {
            if (!\FWUser::getFWUserObject()->objUser->login()) {
                return;
            }
	        $objFWUser  = \FWUser::getFWUserObject();
	        $objUser        = $objFWUser->objUser;
	        
	    	$strFieldName = substr($strPlaceHolder,14);
	        $strFieldName = strtolower(substr($strFieldName,0,-2));
	    	
	        if ($objUser->getId()) {
	            $intUserId = intval($objUser->getId());
	        	
	        	 switch($strFieldName) {
	                case 'email':
	                    $strValue = ($objUser->getEmail() != "") ? $objUser->getEmail() : '';
	        	   	    break;
	                case 'username':
	                    $strValue = ($objUser->getUsername() != "") ? $objUser->getUsername() : '';
	                    break;	
                    case 'country':
                    	//if(intval($strFieldName) != 0) {
                    		$strValue = $objUser->getProfileAttribute($strFieldName);
                    	//} else {
                    	//	$strValue = $objFWUser->objUser->objAttribute->getById('country_'.$objUser->getProfileAttribute($strFieldName))->getId();
                    	//}
                        break;
	                default:
	               	    $strValue = ($objUser->getProfileAttribute($strFieldName) != "") ? $objUser->getProfileAttribute($strFieldName) : '';
	                    break;  
	        	 }    
	        }
        }
    	
        return $strValue;
    }
}

?>
