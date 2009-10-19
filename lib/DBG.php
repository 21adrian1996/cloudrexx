<?php

define('DBG_NONE'       ,  0);
define('DBG_PHP'        ,  1);
define('DBG_ADODB'      ,  2);
define('DBG_ADODB_TRACE',  4);
define('DBG_ADODB_ERROR',  8);
define('DBG_LOG_FILE'   , 16);
define('DBG_LOG_FIREPHP', 32);
define('DBG_ALL'        , 63);


class DBG
{
    private static $dbg_fh = null;
    private static $fileskiplength = 0;
    private static $enable_msg   = null;
    private static $enable_trace = null;
    private static $enable_dump  = null;
    private static $enable_time  = null;
    private static $firephp      = null;
    private static $log_file     = null;
    private static $log_firephp  = null;
    private static $log_adodb    = null;
    private static $last_time    = null;
    private static $start_time   = null;
    private static $mode         = 0;

    public function __construct()
    {
        throw new Exception('This is a static class! No need to create an object!');
    }


    public static function activate($mode = null)
    {
        if (!self::$fileskiplength) {
            self::$fileskiplength = strlen(dirname(dirname(__FILE__))) +1;
        }

        if (empty($mode)) {
            self::$mode = DBG_ALL;
        } else {
            self::$mode = self::$mode | $mode;
        }

        self::__internal__setup();

        self::enable_all();
    }


    public static function deactivate($mode = null)
    {
        if (empty($mode)) {
            self::$mode = DBG_NONE;
            self::disable_all();
        } else {
            self::$mode = self::$mode  & ~$mode;
        }

        self::__internal__setup();
    }


    public static function __internal__setup()
    {
        // log to file dbg.log
        if (self::$mode & DBG_LOG_FILE) {
            self::enable_file();
        } else {
            self::disable_file();
        }

        // log to FirePHP
        if (self::$mode & DBG_LOG_FIREPHP) {
            self::enable_firephp();
        } else {
            self::disable_firephp();
        }

        // log mysql queries
        if ((self::$mode & DBG_ADODB) || (self::$mode & DBG_ADODB_TRACE) || (self::$mode & DBG_ADODB_ERROR)) {
            self::enable_adodb();
        } else {
            self::disable_adodb_debug();
        }

        // log php warnings/erros/notices...
        if (self::$mode & DBG_PHP) {
            self::enable_error_reporting();
        } else {
            self::disable_error_reporting();
        }
    }


    public static function getMode()
    {
        return self::$mode;
    }


    private static function enable_file()
    {
        if (self::$log_file) return;

        // disable firephp first
        self::disable_firephp();

        self::$log_file = true;
        self::setup('dbg.log', 'w');
        set_error_handler('DBG::phpErrorHandler');
    }


    private static function disable_file()
    {
        if (!self::$log_file) return;

        self::$log_file = false;
        fclose(self::$dbg_fh);
        self::$dbg_fh = null;
        restore_error_handler();
    }


    private static function enable_firephp()
    {
        if (self::$log_firephp) return;

        if (headers_sent($file, $line)) {
            trigger_error("Can't activate FirePHP! Headers already sent in $file on line $line'", E_USER_NOTICE);
            return;
        }

        // disable file first
        self::disable_file();

        ob_start();
        if (!isset(self::$firephp)) {
            require_once 'firephp/FirePHP.class.php';
            self::$firephp = FirePHP::getInstance(true);
        }
        self::$firephp->registerErrorHandler(false);
        self::$firephp->setEnabled(true);
        self::$log_firephp = true;
    }


    private static function disable_firephp()
    {
        if (!self::$log_firephp) return;

        self::$firephp->setEnabled(false);
        self::$log_firephp = false;
        ob_end_clean();
        restore_error_handler();
    }


    static function enable_all()
    {
        self::enable_msg  ();
        self::enable_trace();
        self::enable_dump ();
        self::enable_time ();
    }


    static function disable_all()
    {
        self::disable_msg();
        self::disable_trace();
        self::disable_dump();
        self::disable_time();
    }


    static function time()
    {
        if (self::$enable_time) {
            $t = self::$last_time;
            self::$last_time = microtime(true);
            $diff_last  = round(self::$last_time  - $t, 5);
            $diff_start = round(self::$last_time-self::$start_time, 5);
            $callers = debug_backtrace();
            $f = self::_cleanfile($callers[0]['file']);
            $l = $callers[0]['line'];
            $d = date('H:i:s');
            self::_log("TIME AT: $f:$l $d (diff: $diff_last, startdiff: $diff_start)");
        }
    }


    static function setup($file, $mode='a')
    {
        if (self::$dbg_fh) fclose(self::$dbg_fh);
        if (self::$log_firephp) return true; //no need to setup ressources, we're using firephp
        self::$dbg_fh = fopen($file, $mode);
        return true;
    }


    static function enable_trace()
    {
        if (self::$enable_trace) return;

        //self::_log('XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX ENABLING TRACE XXXXXXXXXXXXXXXX');
        self::$enable_trace = 1;
    }


    // Redirect ADODB output to us instead of STDOUT.
    static function enable_adodb()
    {
        if (!self::$log_adodb) {
            if (!(self::$mode & DBG_LOG_FILE)) self::setup('php://output');
            if (!defined('ADODB_OUTP')) define('ADODB_OUTP', 'DBG_log_adodb');
            self::$log_adodb = true;
        }
        self::enable_adodb_debug(self::$mode & DBG_ADODB_TRACE);
    }


    static function enable_adodb_debug($flagTrace=false)
    {
        global $objDatabase;

        if (!isset($objDatabase)) return;

        if ($flagTrace) {
            $objDatabase->debug = 99;
        } else {
            $objDatabase->debug = 1;
        }
    }


    static function disable_adodb_debug()
    {
        global $objDatabase;

        if (!isset($objDatabase)) return;

        $objDatabase->debug = 0;
        self::$log_adodb = false;
    }


    static function disable_trace()
    {
        if (!self::$enable_trace) return;

        //self::_log('XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX DISABLING TRACE XXXXXXXXXXXXXXX');
        self::$enable_trace = 0;
    }


    static function enable_time()
    {
        if (!self::$enable_time) {
            //self::_log('XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX ENABLING TIME XXXXXXXXXXXXXXXXX');
            self::$enable_time = 1;
            self::$start_time = microtime(true);
            self::$last_time  = microtime(true);
        }
    }


    static function disable_time()
    {
        if (!self::$enable_time) return;

        //self::_log('XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX DISABLING TIME XXXXXXXXXXXXXXXX');
        self::$enable_time = 0;
    }


    static function enable_dump()
    {
        if (self::$enable_dump) return;

        //self::_log('XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX ENABLING DUMP XXXXXXXXXXXXXXXXX');
        self::$enable_dump = 1;
    }


    static function disable_dump()
    {
        if (!self::$enable_dump) return;

        //self::_log('XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX DISABLING DUMP XXXXXXXXXXXXXXXX');
        self::$enable_dump = 0;
    }


    static function enable_msg()
    {
        if (self::$enable_msg) return;

        //self::_log('XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX ENABLING MSG XXXXXXXXXXXXXXXXXX');
        self::$enable_msg = 1;
    }


    static function disable_msg()
    {
        if (!self::$enable_msg) return;

        //self::_log('XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX DISABLING MSG XXXXXXXXXXXXXXXXX');
        self::$enable_msg = 0;
    }


    static function enable_error_reporting()
    {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    }


    static function disable_error_reporting()
    {
        error_reporting(0);
        ini_set('display_errors', 0);
    }


    static function _cleanfile($f)
    {
        return substr($f, self::$fileskiplength);
    }


    static function trace($level=0)
    {
        if (self::$enable_trace) {
            $callers = debug_backtrace();
            $f = self::_cleanfile($callers[$level]['file']);
            $l = $callers[$level]['line'];
            self::_log("TRACE:  $f : $l");
        }
    }


    static function calltrace()
    {
        if (self::$enable_trace) {
            $level = 1;
            $callers = debug_backtrace();
            $c = isset($callers[$level]['class']) ? $callers[$level]['class'] : null;
            $f = $callers[$level]['function'];
            self::trace($level);
            $sf = self::_cleanfile($callers[$level]['file']);
            $sl = $callers[$level]['line'];
            self::_log("        ".(empty($c) ? $c : "$c::$f")." FROM $sf : $sl");
        }
    }


    static function dump($val)
    {
        if (!self::$enable_dump) return;
        if (self::$log_firephp) {
            self::$firephp->log($val);
            return;
        }
        ob_start();
        print_r($val);
        $out = ob_get_clean();
        $out = str_replace("\n", "\n        ", $out);
        self::_log('DUMP:   '.$out);
    }


    static function stack()
    {
        $callers = debug_backtrace();
        self::_log("TRACE:  === STACKTRACE BEGIN ===");
        $err = error_reporting(E_ALL ^ E_NOTICE);
        foreach ($callers as $c) {
            $file  = self::_cleanfile($c['file']);
            $line  = $c['line'];
            $class = $c['class'];
            $func  = $c['function'];
            self::_log("        $file : $line (".(empty($class) ? $func : "$class::$func").")");
        }
        error_reporting($err);
        self::_log("        === STACKTRACE END ====");
    }


    static function msg($message)
    {
        if (self::$enable_msg) {
            self::_log('LOGMSG: '.$message);
        }
    }


    public static function phpErrorHandler($errno, $errstr, $errfile, $errline)
    {
        // this error handler methode is only used if we are logging to a file
        if (error_reporting() & $errno) {
            switch ($errno) {
                case E_ERROR:
                    $type = 'FATAL ERROR';
                    break;
                case E_WARNING:
                    $type = 'WARNING';
                    break;
                case E_PARSE:
                    $type = 'PARSE ERROR';
                    break;
                case E_NOTICE:
                    $type = 'NOTICE';
                    break;
                default:
                    $type = $errno;
                    break;
            }
            self::_log("(php): $type: $errstr in $errfile on line $errline");
        }
    }


    static function log($text, $firephp_action='log', $additional_args=null)
    {
        self::_log($text, $firephp_action, $additional_args);
    }


    private static function _log($text, $firephp_action='log', $additional_args=null)
    {
        if (self::$log_firephp
            && method_exists(self::$firephp, $firephp_action)) {
            self::$firephp->$firephp_action($additional_args, $text);
        } else {
            if (self::$dbg_fh) {
                fputs(self::$dbg_fh, $text."\n");
            }
        }
    }

}

function DBG_log_adodb($msg)
{
    $error = preg_match('#^[0-9]+:#', $msg);
    if ($error || (DBG::getMode() & DBG_ADODB) || (DBG::getMode() & DBG_ADODB_TRACE)) {
        DBG::log(
            DBG::getMode() & DBG_LOG_FILE || DBG::getMode() & DBG_LOG_FIREPHP
                ? html_entity_decode(strip_tags($msg), ENT_QUOTES, CONTREXX_CHARSET) : $msg, $error ? 'error' : (preg_match('#\(mysql\):\s(UPDATE|DELETE|INSERT)#', $msg) ? 'info' : 'log'));
    }
}

?>
