<?PHP
/**
 * Create error pages
 *
 * @copyright	CONTREXX CMS - ASTALAVISTA IT AG
 * @author		Astalavista Development Team <thun@astalvista.ch>
 * @package	    contrexx
 * @subpackage  core
 * @version		1.0.0
 * @todo        Edit PHP DocBlocks!
 */

/**
 * Handles all errors.
 *
 * @copyright	CONTREXX CMS - Astalavista IT Engineering GmbH Thun
 * @author		Astalavista Development Team <thun@astalvista.ch>
 * @access		public
 * @package	    contrexx
 * @subpackage  core
 * @version		1.0.0
 * @todo        Declare and document class variable 'pageContent'
 */
class error
{
    /**
     * HTML Template
     * @access private
     */
    var $_objTpl;

	/**
	 * Error page constructor
	 * @param  string $pageContent Page content
     */
    function error($pageContent)
    {
        $this->pageContent = $pageContent;
        $this->_objTpl = &new HTML_Template_Sigma();
    }


    /**
     * Set up page containing the error message.
     *
     * This is a synonym for {@link errorHandling()}.
     * @todo Is this deprecated?
     * @return string  Page content
     */
    function getErrorPage()
    {
        return $this->errorHandling();
    }


    /**
     * Set up page containing the error message.
     *
     * Sets up an error page determined by the error ID found in the 'id'
     * field of the $_REQUEST array.
     * @return string  Page content
     * @todo Handle more errors.
     */
    function errorHandling()
    {
        global $_CORELANG;
        $this->_objTpl->setTemplate($this->pageContent);

        if (!isset($_REQUEST['id'])) {
            $_REQUEST['id'] = "";
        }

        switch ($_REQUEST['id']){
        case '404':
            $errorNo = 404;
            $errorMsg = "Not found";
            break;

        default:
            $errorNo = 404;
            $errorMsg = "Not found";
            break;
        }

        /*
        100 Continue
        101 Switching Protocols
        200 OK
        201 Created
        202 Accepted
        203 Non-Authoritative Information
        204 No Content
        205 Reset Content
        206 Partial Content
        300 Multiple Choices
        301 Moved Permanently
        302 Moved Temporarily
        303 See Other
        304 Not Modified
        305 Use Proxy
        400 Bad Request
        401 Unauthorised
        402 Payment Required
        403 Forbidden
        404 Not Found
        405 Method Not Allowed
        406 Not Acceptable
        407 Proxy Authentication Required
        408 Request Time-Out
        409 Conflict
        410 Gone
        411 Length Required
        412 Precondition Failed
        413 Request Entity Too Large
        414 Request-URL Too Large
        415 Unsupported Media Type
        500 Server Error
        501 Not Implemented
        502 Bad Gateway
        503 Out of Resources
        504 Gateway Time-Out
        505 HTTP Version not supported
        */

        $this->_objTpl->setVariable(array(
            'ERROR_NUMBER'    => $errorNo,
            'ERROR_MESSAGE'    => $errorMsg
        ));
        return $this->_objTpl->get();
    }
}
?>