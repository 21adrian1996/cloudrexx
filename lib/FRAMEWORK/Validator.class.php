<?php

/**
 * Framework Validator
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Comvation Development Team <info@comvation.com>
 * @version     1.0.1
 * @package     contrexx
 * @subpackage  lib_framework
 * @todo        Edit PHP DocBlocks!
 */

/**
 * Regular Expression for e-mail addresses
 * TKaelin @ 2.0.2: wrote new regex based on http://en.wikipedia.org/wiki/E-mail_address
 * Dave V, @ 2.1.2: re-wrote regex according to http://www.regular-expressions.info/email.html
 * Reto Kohli @ 2.1.4: Fixed e-mail regex for PHP by adding more backslashes for special characters
 * @since   2.0.0
 */
define('VALIDATOR_REGEX_EMAIL',
    '[a-zäöü0-9!\#\$\%\&\'\*\+\/\=\?\^_\`\{\|\}\~-]+(?:\.[a-zäöü0-9!\#\$\%\&\'\*\+\/\=\?\^_\`\{\|\}\~-]+)*@(?:[a-zäöü0-9](?:[a-zäöü0-9-]*[a-zäöü0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?'
);

/**
 * Regular Expression for URI protocols
 *
 * Known protocols include HTTP, HTTPS, FTP, and FTPS.
 * @author  Reto Kohli <reto.kohli@comvation.com>
 * @since   2.2.0
 */
define('VALIDATOR_REGEX_URI_PROTO',
      '(?:(?:ht|f)tps?\:\/\/)'
);

/**
 * Regular Expression for URIs
 * @author  Reto Kohli <reto.kohli@comvation.com>
 * @since   2.2.0
 */
define('VALIDATOR_REGEX_URI',
      VALIDATOR_REGEX_URI_PROTO.
      '?((([\w\d-]{2,}\.)+[a-z]{2,})|((?:(?:25[0-5]|2[0-4]\d|[01]\d\d|\d?\d)(?:(\.?\d)\.)) {4}))(?:[\w\d]+)?(\/[\w\d\-\.\?\,\'\/\\\+\&\%\$\#\=\~]*)?'
//    '(https?|ftp)\:\/\/([-a-z0-9.]+)(\/[-a-z0-9+&@#\/%=~_|!:,.;]*)?(\?[-a-z0-9+&@#\/%=~_|!:,.;]*)?'
);

/**
 * Framework Validator
 * @copyright   CONTREXX CMS - COMVATION AG
 * @version     1.0.1
 * @package     contrexx
 * @subpackage  lib_framework
 * @author      Comvation Development Team <info@comvation.com>
 * @author      Reto Kohli <reto.kohli@comvation.com> (parts)
 * @todo        Edit PHP DocBlocks!
 * @todo        Most, if not all, should be static
 */
class FWValidator
{
    /**
     * Validate an E-mail address
     *
     * Note:  This used to have a stripslashes() around the string.
     * This is bollocks.  If you want to match a string, you match the string,
     * not transformed version.  Strip whatever you want, but do it *before*
     * you call this function.
     * @param  string $string
     * @return boolean          True if it's an e-mail address, false otherwise
     * @access public
     */
    static function isEmail($string)
    {
        return (bool)preg_match('/^'.VALIDATOR_REGEX_EMAIL.'$/i', $string);
    }


    /**
     * Returns true if the given string is a valid URI
     * @param   string  $string The string to be tested
     * @return  boolean         True if the string represents an URI,
     *                          false otherwise
     * @author  Reto Kohli <reto.kohli@comvation.com>
     */
    static function isUri($string)
    {
        return (bool)preg_match('/^'.VALIDATOR_REGEX_URI.'$/i', $string);
    }


    /**
     * Returns true if the given string starts with a protocol
     *
     * See {@see VALIDATOR_REGEX_URI_PROTO} for known protocols.
     * @param   string  $string The string to be tested
     * @return  boolean         True if the string starts with an URI,
     *                          false otherwise
     * @author  Reto Kohli <reto.kohli@comvation.com>
     */
    static function hasProto($string)
    {
        return (bool)preg_match('/^'.VALIDATOR_REGEX_URI_PROTO.'/i', $string);
    }


    /**
     * Find all e-mail addresses in a string
     * @param   string  $string     String potentially containing email addresses
     * @return  array               Array with all e-mail addresses found
     * @access  public
     * @todo    This function does not belong in here
     */
    static function getEmailAsArray($string)
    {
        $arrMatches = array();
        preg_match_all(
            '/\s('.VALIDATOR_REGEX_EMAIL.')\.?\s/', $string, $arrMatches);
        return $arrMatches[0]; // include spaces
        // return $arrMatches[1]; // exclude spaces
    }


    /**
     * Adds a leading protocol ("http://") prefix to the string, if
     * there is none.
     *
     * Note:  This accepts any known and unknown protocol already present.
     * Mind your step!
     * @access  public
     * @param   string  $string   The URL with possibly missing protocol
     * @return  string            The complete URL with protocol
     * @todo    This function does not belong in here
     */
    static function getUrl($string)
    {
        if (preg_match('/^[a-z]+:\/\//i', $string) || empty($string))
            return $string;
        return 'http://'.$string;
    }


    /**
     * Returns true if the ending of the given file name is harmless.
     *
     * We consider all executable as well as
     * all scripts (server and client side) as harmful.
     * You should NOT allow to upload these.
     * This function returns true if the given filename
     * is safe to upload.
     * @param   string  $file   The file name
     */
    static function is_file_ending_harmless($file)
    {
        $evil = array(
            # windows executables:
            'exe', 'bat', 'pif', 'com',
            # client scripts:
            'vs', 'vbs', 'js',
            # client script containers:
            'html', 'xhtml', 'xml', 'svg', 'shtml', 'htm',
            # server scripts:
            'php', 'cgi', 'pl', 'jsp', 'jspx', 'asp', 'aspx',
            'jsp', 'jspx', 'jhtml', 'phtml', 'cfm',
        );
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, $evil)) return false;
        return true;
    }


    private static function __fix_flash($html)
    {
        // we come from a preg_replace_callback
        $html = $html[0];

        // already done
        if (strstr($html, 'wmode=')) return $html;

        // INJECT <param> for IE
        $new_param = '<param name="wmode" value="transparent" />'."\n";
        $html = preg_replace('/<param/i', "$new_param<param", $html, 1);

        // INJECT <embed> attribute for FF and the rest of the gang
        $embed_attr = 'wmode="transparent"';
        return preg_replace('/<embed/i', "<embed $embed_attr", $html, 1);
    }


    /**
     * This function fixes the given HTML so that any embedded flash
     * objects will get the "wmode=transprent" set.
     *
     * This is neccessary for the frontend login box for example, when a
     * flash object is on the page.
     * Takes un-escaped HTML code as parameter, returns the fixed HTML.
     */
    static function fix_flash_transparency($html_code) {
        $result = preg_replace_callback(
            '!<object.*?.*?<param.*?</object>!ims',
            array('FWValidator', '__fix_flash'),
            $html_code
        );
        return $result;
    }
    /**
     * Get a file name that is allowed on all file systems.
     */
    public static function getCleanFileName($fileName)
    {
        // replace $change with ''
        $change = array('\\', '/', ':', '*', '?', '"', '<', '>', '|');
        $fileName = str_replace($change, '_', $fileName);

        return $fileName;
    }

}

/**
 * An abstract base for ZendValidator-Style instantiable Validators
 */
abstract class CxValidate {
    protected $constraints;
    protected $passesValidation;
    protected $messages;

    // TODO: Possibly throw an Exception if an unknown/typoed constraint was provided
    public function __construct($constraints) {
	$this->messages = array();
	$this->constraints = $constraints;
    }

    public abstract function isValid($value);

    public function getMessages() {
	return $this->messages;
    }

}

/**
 * Validates Strings to a set of constraints
 */
class CxValidateString extends CxValidate {
    public function __construct($constraints) {
	parent::__construct($constraints);
    }

    public function isValid($value) {
	$this->passesValidation = true;

	if (isset($this->constraints['maxlength'])) {
	    if (strlen($value) > $this->constraints['maxlength']) {
		// TODO: Translate messages
		$this->messages[] = 'is too long.';
		$this->passesValidation = false;
	    }
	}

	if (isset($this->constraints['alphanumeric']) && $this->constraints['alphanumeric']) {
	    if (!ctype_alnum($value)) {
		$this->passesValidation = false;
	    }
	}

	return $this->passesValidation;
    }
}

class CxValidateRegexp extends CxValidate {
    public function __construct($constraints) {
	parent::__construct($constraints);
    }

    public function isValid($value) {
	$this->passesValidation = false;

	if (isset($this->constraints['pattern']) &&
	    preg_match($this->constraints['pattern'], $value)) {
	    $this->passesValidation = true;
	}
	else {
	    // TODO: Translate messages
	    $this->messages[] = 'doesn\'t match required pattern.';
	}

	return $this->passesValidation;
    }   
}

class CxValidateInteger extends CxValidate {
    public function __construct($constraints = array()) {
	parent::__construct($constraints);
    }

    public function isValid($value) {
	$this->passesValidation = false;

	if(is_numeric($value) || is_int($value)) {
	    $this->passesValidation = true;
	}
	else {
	    // TODO: Translate messages
	    $this->messages[] = 'is not a number.';
	}

	return $this->passesValidation;
    }
}