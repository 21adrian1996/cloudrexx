<?php
/**
 * Class Recommend
 *
 * Recommend module class
 *
 * @copyright   CONTREXX CMS - ASTALAVISTA IT AG
 * @author		Astalavista Development Team <thun@astalvista.ch>
 * @access		public
 * @version		1.0.0
 * @package     contrexx
 * @subpackage  module_recommend
 * @todo        Edit PHP DocBlocks!
 */

require_once ASCMS_MODULE_PATH . '/recommend/Lib.class.php';

class Recommend extends RecommendLibrary 
{
	/**
	 * Template object
	 *
	 * @access private
	 * @var object
	 */
	var $_objTpl;
	var $langId;
	var $_pageMessage;
	
	/**
	 * Constructor
	 */
	function Recommend($pageContent)
	{
		$this->__construct($pageContent);
	}
	
	/**
	 * PHP5 constructor
	 *
	 * @global object $objTemplate
	 * @global array $_ARRAYLANG
	 */
	function __construct($pageContent)
	{
	    global $_LANGID;
	    
	    $this->langId=$_LANGID;
	    $this->_objTpl = &new HTML_Template_Sigma('.');
		$this->_objTpl->setErrorHandling(PEAR_ERROR_DIE);
		$this->_objTpl->setTemplate($pageContent);
	}
	
	/**
	 * Get content page
	 *
	 * @access public
	 */
	function getPage() 
	{
		switch ($_GET['act']) {
			case "sendRecomm":
				$this->_sendRecomm();
				break;
			default:
				$this->_showForm();
				break;
		}
		
		return $this->_objTpl->get();
	}
	
	/**
	 * Just shows the form
	 */
	function _showForm()
	{
		global $_LANGID, $_ARRAYLANG;
		
		$this->_objTpl->setVariable(array(
			"RECOM_REFERER"			=> $_SERVER['HTTP_REFERER'],
			"RECOM_FEMALE_CHECKED"  => "checked",
			"RECOM_SCRIPT"			=> $this->getJs(),
			"RECOM_PREVIEW"			=> $this->getMessageBody($_LANGID),
			"RECOM_FEMALE_SALUTATION_TEXT" => $this->getFemaleSalutation($_LANGID),
			"RECOM_MALE_SALUTATION_TEXT" => $this->getMaleSalutation($_LANGID),
			"RECOM_TXT_RECEIVER_NAME"	=> $_ARRAYLANG['TXT_RECEIVERNAME_FRONTEND'],
			"RECOM_TXT_RECEIVER_MAIL"	=> $_ARRAYLANG['TXT_RECEIVERMAIL_FRONTEND'],
			"RECOM_TXT_GENDER"			=> $_ARRAYLANG['TXT_GENDER_FRONTEND'],
			"RECOM_TXT_SENDER_NAME"		=> $_ARRAYLANG['TXT_SENDERNAME_FRONTEND'],
			"RECOM_TXT_SENDER_MAIL"		=> $_ARRAYLANG['TXT_SENDERMAIL_FRONTEND'],
			"RECOM_TXT_COMMENT"			=> $_ARRAYLANG['TXT_COMMENT_FRONTEND'],
			"RECOM_TXT_PREVIEW"			=> $_ARRAYLANG['TXT_PREVIEW_FRONTEND'],
			"RECOM_TXT_FEMALE"			=> $_ARRAYLANG['TXT_FEMALE_FRONTEND'],
			"RECOM_TXT_MALE"			=> $_ARRAYLANG['TXT_MALE_FRONTEND'],
			"RECOM_TEXT"				=> $_ARRAYLANG['TXT_INTRODUCTION']
			));
		$this->_objTpl->parse('recommend_form');
	}
	
	/**
	 * Send Recommendation
	 *
	 * Send an email if the input is valid. Otherwise
	 * Show some error messages and the form again
	 */
	function _sendRecomm()
	{
		global $_ARRAYLANG, $_CONFIG, $_LANGID;
		
		$empty_error = array();
		$mail_error = array();
		if (empty($_POST['receivername'])) {
			$this->_pageMessage .= $_ARRAYLANG['TXT_STATUS_RECEIVER_NAME']." ".$_ARRAYLANG['TXT_IS_EMPTY']."<br />";
		}
		
		if (empty($_POST['receivermail'])) {
			$this->_pageMessage .= $_ARRAYLANG['TXT_STATUS_RECEIVER_MAIL']." ".$_ARRAYLANG['TXT_IS_EMPTY']."<br />";	
		} elseif (!$this->isEmail($_POST['receivermail'])) {
			$this->_pageMessage .= $_ARRAYLANG['TXT_STATUS_RECEIVER_MAIL']." ".$_ARRAYLANG['TXT_IS_INVALID']."<br />";
		}
		
		if (empty($_POST['sendername'])) {
			$this->_pageMessage .= $_ARRAYLANG['TXT_STATUS_SENDER_NAME']." ".$_ARRAYLANG['TXT_IS_EMPTY']."<br />";
		}
		
		if (empty($_POST['sendermail'])) {
			$this->_pageMessage .= $_ARRAYLANG['TXT_STATUS_SENDER_MAIL']." ".$_ARRAYLANG['TXT_IS_EMPTY']."<br />";
		} elseif (!$this->isEmail($_POST['sendermail'])) {
			$this->_pageMessage .= $_ARRAYLANG['TXT_STATUS_SENDER_MAIL']." ".$_ARRAYLANG['TXT_IS_INVALID']."<br />";
		}

		if (empty($_POST['comment'])) {
			$this->_pageMessage .= $_ARRAYLANG['TXT_STATUS_COMMENT']." ".$_ARRAYLANG['TXT_IS_EMPTY']."<br />";
		}
		
		$receivername 	= $_POST['receivername'];
		$receivermail 	= $_POST['receivermail'];
		$sendername 	= $_POST['sendername'];		
		$sendermail 	= $_POST['sendermail'];		
		$comment	 	= $_POST['comment'];
		
		if (!empty($this->_pageMessage)) {
			//something's missing or wrong		
			$this->_objTpl->setVariable("RECOM_STATUS", "<div style=\"color: red\">".$this->_pageMessage."</div>");
			$this->_objTpl->setCurrentBlock("recommend_form");
			$this->_objTpl->setVariable(array(
				"RECOM_SCRIPT"				=> $this->getJs(),
				"RECOM_RECEIVER_NAME"		=> stripslashes($receivername),
				"RECOM_RECEIVER_MAIL"		=> stripslashes($receivermail),
				"RECOM_SENDER_NAME"			=> stripslashes($sendername),
				"RECOM_SENDER_MAIL"			=> stripslashes($sendermail),
				"RECOM_COMMENT"				=> stripslashes($comment),
				"RECOM_PREVIEW"					=> $this->getMessageBody($_LANGID),
				"RECOM_FEMALE_SALUTATION_TEXT" 	=> $this->getFemaleSalutation($_LANGID),
				"RECOM_MALE_SALUTATION_TEXT" 	=> $this->getMaleSalutation($_LANGID),
				"RECOM_TXT_RECEIVER_NAME"	=> $_ARRAYLANG['TXT_RECEIVERNAME_FRONTEND'],
				"RECOM_TXT_RECEIVER_MAIL"	=> $_ARRAYLANG['TXT_RECEIVERMAIL_FRONTEND'],
				"RECOM_TXT_GENDER"			=> $_ARRAYLANG['TXT_GENDER_FRONTEND'],
				"RECOM_TXT_SENDER_NAME"		=> $_ARRAYLANG['TXT_SENDERNAME_FRONTEND'],
				"RECOM_TXT_SENDER_MAIL"		=> $_ARRAYLANG['TXT_SENDERMAIL_FRONTEND'],
				"RECOM_TXT_COMMENT"			=> $_ARRAYLANG['TXT_COMMENT_FRONTEND'],
				"RECOM_TXT_PREVIEW"			=> $_ARRAYLANG['TXT_PREVIEW_FRONTEND'],
				"RECOM_TXT_FEMALE"			=> $_ARRAYLANG['TXT_FEMALE_FRONTEND'],
				"RECOM_TXT_MALE"			=> $_ARRAYLANG['TXT_MALE_FRONTEND'],
				"RECOM_TEXT"				=> $_ARRAYLANG['TXT_INTRODUCTION']
				));
			$this->_objTpl->parseCurrentBlock("recommend_form");
			$this->_objTpl->parse();
		} else {
			//data is valid
			
			if (empty($_POST['uri'])) {
				$url = ASCMS_PROTOCOL."://".$_SERVER['HTTP_HOST'].ASCMS_PATH_OFFSET;
			} else {
				$url = $_POST['uri'];
			}
			
			if ($_POST['gender'] == "male") {
				$salutation = $this->getMaleSalutation($_LANGID);
			} else {
				$salutation = $this->getFemaleSalutation($_LANGID);
			}
			
			$body = $this->getMessageBody($_LANGID);
			
			$body = preg_replace("/<SENDER_NAME>/", $sendername, $body);
			$body = preg_replace("/<SENDER_MAIL>/", $sendermail, $body);
			$body = preg_replace("/<RECEIVER_NAME>/", $receivername, $body);
			$body = preg_replace("/<RECEIVER_MAIL>/", $receivermail, $body);
			$body = preg_replace("/<URL>/", $url, $body);
			$body = preg_replace("/<COMMENT>/", $comment, $body);
			$body = preg_replace("/<SALUTATION>/", $salutation, $body);
			
			$subject = $this->getMessageSubject($_LANGID);
			
			$subject = preg_replace("/<SENDER_NAME>/", $sendername, $subject);
			$subject = preg_replace("/<SENDER_MAIL>/", $sendermail, $subject);
			$subject = preg_replace("/<RECEIVER_NAME>/", $receivername, $subject);
			$subject = preg_replace("/<RECEIVER_MAIL>/", $receivermail, $subject);
			$subject = preg_replace("/<URL>/", $url, $subject);
			$subject = preg_replace("/<COMMENT>/", $comment, $subject);
			$subject = preg_replace("/<SALUTATION>/", $salutation, $subject);
			
			mail($receivermail, $subject, $body, "From: ".$sendermail."\r\n");
			mail($_CONFIG['contactFormEmail'], $subject, $body, "From: ".$sendermail."\r\n");

			$this->_objTpl->setVariable("RECOM_STATUS", $_ARRAYLANG['TXT_SENT_OK']);
			$this->_objTpl->parse();
		}
	}
	
	/**
	 * Validate the email
	 *
	 * @param  string  $string
	 * @return boolean result
	 */
	function isEmail($string) 
	{
		if (eregi("^" . "[a-z0-9]+([_\\.-][a-z0-9]+)*" .	//user
			"@" . "([a-z0-9]+([\.-][a-z0-9]+)*)+" .			//domain
			"\\.[a-z]{2,4}" . 								//sld, tld
			"$", $string)) {
	        return true;
		} else {
		    return false;
	    }
	}
	
	function getJs()
	{
		return "<script type=\"text/javascript\">
// <![CDATA[
function update()
{
	var inhalt = document.recommend.preview_text.value;
	
	if (document.recommend.sendername.value != '') {
		var inhalt = inhalt.replace(/<SENDER_NAME>/g, document.recommend.sendername.value);
	}
	if (document.recommend.sendermail.value != '') {
		var inhalt = inhalt.replace(/<SENDER_MAIL>/g, document.recommend.sendermail.value);
	}
	if (document.recommend.receivername.value != '') {
		var inhalt = inhalt.replace(/<RECEIVER_NAME>/g, document.recommend.receivername.value);
	}
	if (document.recommend.receivermail.value != '') {
		var inhalt = inhalt.replace(/<RECIEVER_MAIL>/g, document.recommend.receivermail.value);
	}
	if (document.recommend.comment.value != '') {
		var inhalt = inhalt.replace(/<COMMENT>/g, document.recommend.comment.value);
	}
	
	if (document.recommend.uri.value != '') {
		var inhalt = inhalt.replace(/<URL>/g, document.recommend.uri.value);
	} else {
		var inhalt = inhalt.replace(/<URL>/g, document.URL);
	}
	
	if (document.recommend.gender[0].checked) {
		var inhalt = inhalt.replace(/<SALUTATION>/g, document.recommend.female_salutation_text.value);
	} else {
		var inhalt = inhalt.replace(/<SALUTATION>/g, document.recommend.male_salutation_text.value);
	}

	document.recommend.preview.value = inhalt
}
// ]]>
</script>";	
	}
}
?>
