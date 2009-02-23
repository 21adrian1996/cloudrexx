<?php

/**
 * JS class
 *
 * @author Stefan Heinemann <sh@comvation.com>
 * @copyright Comvation AG <info@comvation.com>
 */
class JS
{
    /**
     * An offset that shall be used before all paths
     *
     * When the JS files are used e.g. in the cadmin
     * section, all paths need a '../' before the path.
     * This variable holds that offset.
     * @see setOffset($offset)
     * @access private
     * @static
     * @var string
     */
    private static $offset = "";

    /**
     * The array containing all the registered stuff
     *
     * @access private
     * @static
     * @var array
     */
    private static $active = array();

    /**
     * Holding the last error
     *
     * @access private
     * @static
     * @var string
     */
    private static $error;

    /**
     * Available JS libs
     * These JS files are per default available
     * in every Contrexx CMS.
     * The format is the following:
     * array(
     *      scriptname : array (
     *          jsfiles :   array of strings containing
     *                      all needed javascript files
     *          cssfiles :  array of strings containing
     *                      all needed css files 
     *          dependencies :  array of strings containing
     *                          all dependencies in the right
     *                          order
     *          specialcode :   special js code to be executed
     *          loadcallback:   function that will be executed with
     *                          the options as parameter when chosen
     *                          to activate that JS library, so the
     *                          options can be parsed
     *          makecallback:   function that will be executed when
     *                          the code is generated
     *      )
     * )
     * @access private
     * @static
     * @var array
     */    
    private static $available = array(
        'prototype'     => array(
            'jsfiles'       => array(
                'lib/javascript/prototype.js'
            ),
        ),
        'scriptaculous' => array(
            'jsfiles'       => array(
                'lib/javascript/scriptaculous/scriptaculous.js'
            ), 
            'dependencies'  => array(
                'prototype'
            ),
        ),
        'datepicker'    => array(
            'jsfiles'       => array(
                'lib/javascript/datepickercontrol/datepickercontrol.js'
            ),
            'cssfiles'      => array(
                'lib/javascript/datepickercontrol/datepickercontrol.js'
            )
        ),
        'shadowbox'     => array(
            'jsfiles'       => array(
                'lib/javascript/shadowbox/shadowbox-prototype.js',
                'lib/javascript/shadowbox/shadowbox.js'
            ),    
            'dependencies'  => array(
                'prototype'
            ),
            'specialcode'  => 'window.onload = Shadowbox.init;',
            'loadcallback' => 'parseShadowBoxOptions',
            'makecallback' => 'makeShadowBoxOptions'
        )
    );

    /**
     * Holds the custom JS files
     *
     * @static
     * @access private
     * @var array
     */
    private static $customJS = array();

    /**
     * The custom CSS files
     *
     * @static
     * @access private
     * @var array
     */
    private static $customCSS = array();

    /**
     * The custom Code
     *
     * @static
     * @access private
     * @var array
     */
    private static $customCode = array();

    /**
     * The players of the shadowbox
     * @access private
     * @static
     * @var array
     */
    private static $shadowBoxPlayers = array('img', 'swf', 'flv', 'qt', 'wmp', 'iframe','html');

    /**
     * The language of the shadobox to be used
     * 
     * @access private
     * @static
     * @var string
     */
    private static $shadowBoxLanguage = "en";

    /**
     * Set the offset parameter
     *
     * @param string
     * @static
     * @access public
     */
    public static function setOffset($offset)
    {
        if (!preg_match("/\/$/", $offset)) {
            $offset .= '/';
        }

        self::$offset = $offset;
    }

    /**
     * Activate an available js file
     *
     * The options parameter is specific for the chosen
     * library. The library must define callback methods for
     * the options to be used.
     * @param string $name 
     * @param array $options
     * @access public
     * @static
     * @return bool
     */
    public static function activate($name, $options = null)
    {
        $name = strtolower($name);
        if (array_key_exists($name, self::$available) === false) {
            self::$error = $name.' is not a valid name for
                an available javascript type';
            return false;
        }
    
        $data = self::$available[$name];
        if (!empty($data['dependencies'])) {
            foreach ($data['dependencies'] as $dep) {
                self::activate($dep);
            }
        }

        if (isset($data['loadcallback']) && isset($options)) {
            self::$data['loadcallback']($options);
        }
        
        if (array_search($name, self::$active) === false) {
            self::$active[] = $name;
        }

        return true;
    }

    /**
     * Register a custom js file
     *
     * Adds a new, individual JS file to the list.
     * The filename has to be relative to the document root.
     * @param mixed $file
     * @access public
     * @return bool Return true if successful 
     * @static
     */
    public static function registerJS($file)
    {
        if (!file_exists(ASCMS_DOCUMENT_ROOT.'/'.$file)) {
            self::$error = "The file ".$file." doesn't exist\n";
            return false;
        }

        if (array_search($file, self::$customJS) === false) {
            self::$customJS[] = $file;
        }

        return true;
    }

    /**
     * Register a custom css file
     *
     * Add a new, individual CSS file to the list.
     * The filename has to be relative to the document root.
     * @static
     * @access public
     * @return bool
     */
    public static function registerCSS($file)
    {
        if (!file_exists(ASCMS_DOCUMENT_ROOT.'/'.$file)) {
            self::$error = "The file ".$file." doesn't exist\n";
            return false;
        }

        if (array_search(file, self::$customCSS) === false) {
            self::$customCSS[] = $file;
        }

        return true;
    }

    /**
     * Register special code
     *
     * Add special code to the List
     * @static
     * @access public
     * @return bool
     */
    public static function registerCode($code)
    {
        // try to see if this code already exists
        $code = trim($code);
        if (array_search($code, self::$customCode) === false) {
            self::$customCode[] = $file;
        }

        return true;
    }


    /**
     * Return the code for the placeholder
     *
     * @access public
     * @static
     * @return string
     */
    public static function getCode()
    {
        $jsfiles = array();
        $cssfiles = array();
        $specialcode = array();

        if (count(self::$active) > 0) {
            foreach (self::$active as $name) {
                $data = self::$available[$name];
                if (!isset($data['jsfiles'])) {
                    self::$error = "A JS entry should at least contain
                        one js file...";
                    return false;
                } 
                $jsfiles = array_merge($jsfiles, $data['jsfiles']);

                if (!empty($data['cssfiles'])) {
                    $cssfiles = array_merge($cssfiles, $data['cssfiles']);
                }

                if (isset($data['specialcode']) && strlen($data['specialcode']) > 0) {
                    $specialcode[] = $data['specialcode'];
                }

                if (isset($data['makecallback'])) {
                    self::$data['makecallback']();
                }
            }
        }

        $retstring  = self::makeJSFiles($jsfiles);
        $retstring .= self::makeJSFiles(self::$customJS);
        $retstring .= self::makeCSSFiles($cssfiles);
        $retstring .= self::makeCSSFiles(self::$customCSS);
        $specialcode = array_merge($specialcode, self::$customCode);
        $retstring .= self::makeSpecialCode($specialcode);

        return $retstring;
    }

    /**
     * Return the last error
     *
     * @return string
     * @static
     * @access public
     */
    public static function getLastError()
    {
        return self::$error;
    }

    /**
     * Return the available libs
     *
     * @access public
     * @static
     * @return array
     */
    public static function getAvailableLibs()
    {
        return self::$available;
    }


    /**
     * Make the code for the Javascript files
     *
     * @param array $files
     * @return string
     * @static
     * @access private
     */
    private static function makeJSFiles($files)
    {
        $code = "";

        foreach ($files as $file) {
            $code .= "<script type=\"text/javascript\" src=\"".self::$offset.$file."\"></script>\n\t";
        }

        return $code;
    }

    /**
     * Make the code for the CSS files
     *
     * @param array $files
     * @return string
     * @static
     * @access private
     */
    private static function makeCSSFiles($files)
    {
        $code = "";

        foreach ($files as $file) {
            $code .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"".self::$offset.$file."\" />\n\t";
        }

        return $code;
    }

    /**
     * Make the code section for 
     *
     * @access private
     * @param array $code
     * @return string
     * @static
     */
    private static function makeSpecialCode($code)
    {
        $retcode = "";
        if (!empty($code)) {
            $retcode .= "<script type=\"text/javascript\">\n/* <![CDATA[ */\n"; 

            foreach ($code as $segment) {
                $retcode .= $segment."\n\t";
            }

            $retcode .= "\n/* ]]> */\n</script>\n";
        }

        return $retcode;
    }

    /**
     * Callback function for the shadowbox library
     *
     * Called when the shadowbox is loaded and when parameters are given.
     * Add the the players to a list. Set the language. 
     * Format of the options passed through JS::activate
     * (everything is optional):
     * array(
     *      players => array(img, swf, flv, qt, wmp, iframe, html),
     *      language => [ar, ca, cs, de-CH, de-DE, en, es
     *                          et, fi, fr, gl, he, id, is, it,
     *                          ko, my, nl, no, pl, pt-BR, pt-PT,
     *                          ro, ru, sk, svn, tr, zh-CN, zh-TW])
     * )
     * @static
     * @access private
     * @param array $options
     */
    private static function parseShadowBoxOptions($options = null)
    {
        $available_players = array('img', 'swf', 'flv', 'qt', 'wmp', 'iframe','html');
        $available_langs = array('ar', 'ca', 'cs', 'de-CH', 'de-DE', 'en', 
            'es', 'et', 'fi', 'fr', 'gl', 'he', 'id', 'is', 'it', 'ko', 
            'my', 'nl', 'no', 'pl', 'pt-BR', 'pt-PT', 'ro', 'ru', 'sk', 'sv', 
            'tr', 'zh-CN', 'zh-TW');
        $players = "";
        $options = (isset($options)) ? $options : array();
        if (!empty($options['players'])) {
            foreach ($options['players']  as $player) {
                if (array_search($player, $available_players) !== false) {
                    // valid player
                    if (array_search($player, self::$shadowBoxPlayers) === false) {
                        self::$shadowBoxPlayers[] = $player;
                    }
                }
            }
        } else {
            // set all players
            self::$shadowBoxPlayers = $available_players;
        }

        if (!empty($options['language'])) {
            if (array_search($options['language'], $available_langs) !== false) {
                self::$shadowBoxLanguage = $options['language'];
            } 
        }
    }

    /**
     * Callback function for the shadowbox library
     *
     * Called when the shadowbox was loaded and the code is 
     * generated. Makes the initial-lines to provide the chosen
     * players and to load the skin. If there is a directory
     * called 'shadowbox' in the current theme directory, this one
     * will be taken, otherwise the default skin under lib/javascript/shadowbox.
     * @static
     * @access private
     * @global object $objInit
     */
    private static function makeShadowBoxOptions()
    {
        global $objInit;

        // make the code for loading the players
        if (!empty(self::$shadowBoxPlayers)) {
            $players = "";
            foreach (self::$shadowBoxPlayers as $player) {
                $players .= " '".$player."',";
            }
            $players = substr($players, 1, -1);
            self::$customCode[] = "Shadowbox.loadPlayer([".$players."]," 
                ."'".self::$offset."lib/javascript/shadowbox/player/');";
        } 

        // make the code for loading the skins
        $skindir = self::$offset."lib/javascript/shadowbox/skin";
        $skin = 'standard';
        if ($objInit->mode == "frontend") {
            $themePath = $objInit->getCurrentThemesPath();
            if (file_exists(ASCMS_THEMES_PATH.'/'.$themePath.'/shadowbox/')) {
                $skindir = $themePath.'/shadowbox';
            }
        }
        self::$customCode[] = "Shadowbox.loadSkin('".$skin."', '".self::$offset.$skindir."');";

        // make the code for loading the language
        self::$customCode[] = "Shadowbox.loadLanguage('".self::$shadowBoxLanguage."', '".self::$offset."lib/javascript/shadowbox/lang')"; 
    }
}

