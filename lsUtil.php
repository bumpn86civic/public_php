<?php
/*****************************************************************************
* Copyright (C) 2015  Barry Robertson
*
* This library is free software; you can redistribute it and/or
* modify it under the terms of the GNU Lesser General Public
* License as published by the Free Software Foundation; either
* version 2.1 of the License, or (at your option) any later version.
*
* This library is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
* Lesser General Public License for more details.
*
* You should have received a copy of the GNU Lesser General Public
* License along with this library; if not, write to the Free Software
* Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
*
******************************************************************************
**
**  Filename:       lsUtil.php
**  Project:        EcoSphere
**  Author:         Barry Robertson (barry@logixstorm.com)
**  Created:        11-28-2014
**
**  Abstract:       This is a depot of various utility functions we need.  Most
**  of them are implemented as classes but are just static methods designed to
**  allow us to extend them in the future if we want to actually have some
**  instance-specific data stored along with them.
**
**  Note: I've left the logging structure as
**
**
**  Change Log:
**  Barry Robertson; 11-28-2014; Initial version.
**  <engr. name>; <date>; <reason>
*****************************************************************************/

//********************************************************
//* Use Modules
//********************************************************
require_once( "lsErrno.php" );
require_once( "KLogger.php" );

//********************************************************
//* Include/Require Files
//********************************************************

//********************************************************
//* Name Space
//********************************************************

//********************************************************
//* Globals
//********************************************************
$gTimeZoneStr = null;
$gLsSys = new lsSys;
$gLsEchoLogs = false;


function LsLogEcho( $bEchoMessages )
{
    global $gLsEchoLogs;
    $gLsEchoLogs = $bEchoMessages;
}

function LsLog( $logKey, $message, $level )
{
    global $gLsSys;
    global $gLsEchoLogs;

    if( $gLsEchoLogs == true )
    {
        echo "$message\n";
    }

    $gLsSys->Log($logKey, $message, $level);
}

function LsLogWarn( $logKey, $message )
{
    global $gLsSys;
    global $gLsEchoLogs;

    if( $gLsEchoLogs == true )
    {
        echo "$message\n";
    }

    $gLsSys->LogWarn($logKey, $message);
}

function LsLogInfo( $logKey, $message )
{
    global $gLsSys;
    global $gLsEchoLogs;

    if( $gLsEchoLogs == true )
    {
        echo "$message\n";
    }

    $gLsSys->LogInfo($logKey, $message);
}

function LsLogDebug( $logKey, $message )
{
    global $gLsSys;
    global $gLsEchoLogs;

    if( $gLsEchoLogs == true )
    {
        echo "$message\n";
    }

    $gLsSys->LogDebug($logKey, $message);
}

function LsLogError( $logKey, $message )
{
    global $gLsSys;
    global $gLsEchoLogs;

    if( $gLsEchoLogs == true )
    {
        echo "$message\n";
    }

    $gLsSys->LogError($logKey, $message);
}

function LsLogFatal( $logKey, $message )
{
    global $gLsSys;
    global $gLsEchoLogs;

    if( $gLsEchoLogs == true )
    {
        echo "$message\n";
    }

    $gLsSys->LogFatal($logKey, $message);
}

function LsJsonLastError( )
{
    return( LsJsonError( json_last_error() ) );
}

function LsJsonError( $error )
{
    global $gLsSys;

    return( $gLsSys->JsonError($error) );
}

//
// <a href="<?php$_SERVER['PHP_SELF'] ? >">here</a>
//
$postfilter =    // set up the filters to be used with the trimmed post array
array(
        'user_tasks' => array('filter' => FILTER_SANITIZE_STRING, 'flags' => !FILTER_FLAG_STRIP_LOW),    // removes tags. formatting code is encoded -- add nl2br() when displaying
        'username'   => array('filter' => FILTER_SANITIZE_ENCODED, 'flags' => FILTER_FLAG_STRIP_LOW),    // we are using this in the url
        'mod_title'  => array('filter' => FILTER_SANITIZE_ENCODED, 'flags' => FILTER_FLAG_STRIP_LOW),    // we are using this in the url
    );



//*************************************************************************
// This function is called for catchable fatal errors.
//*************************************************************************
function fatal_handler()
{
    $errfile = "unknown file";
    $errstr  = "shutdown";
    $errno   = E_CORE_ERROR;
    $errline = 0;

    $error = error_get_last();

    if( $error !== NULL)
    {
        $errno   = $error["type"];
        $errfile = $error["file"];
        $errline = $error["line"];
        $errstr  = $error["message"];

        $errorString = format_error( $errno, $errstr, $errfile, $errline);
    }
    else
    {
        $errorString = format_error();
        echo $errorString."\n";
    }
}

function LsSysEnableFatalHandler()
{
    //
    // Set the catchable fatal error handler.
    //
    register_shutdown_function( "fatal_handler" );
}



function format_error( $errno="", $errstr="", $errfile="", $errline="" )
{
  $trace = print_r( debug_backtrace( false ), true );

  $content  = "<table><thead bgcolor='#c8c8c8'><th>Item</th><th>Description</th></thead><tbody>";
  $content .= "<tr valign='top'><td><b>Error</b></td><td><pre>$errstr</pre></td></tr>";
  $content .= "<tr valign='top'><td><b>Errno</b></td><td><pre>$errno</pre></td></tr>";
  $content .= "<tr valign='top'><td><b>File</b></td><td>$errfile</td></tr>";
  $content .= "<tr valign='top'><td><b>Line</b></td><td>$errline</td></tr>";
  $content .= "<tr valign='top'><td><b>Trace</b></td><td><pre>$trace</pre></td></tr>";
  $content .= '</tbody></table>';

  return $content;
}



//****************************************************************************
//************************ Classes *******************************************
//****************************************************************************



/**
 * @abstract System utility methods.
 */
class LsSys
{
    //*******************************************************
    //*******************************************************
    // Private Members
    //*******************************************************
    //*******************************************************
    private $loggers = array();
    private $logFileNames = array();
    private $constants = array();
    private $jsonErrors = array();

    //*******************************************************
    //*******************************************************
    // Protected Members
    //*******************************************************
    //*******************************************************

    //*******************************************************
    //*******************************************************
    // Public Members
    //*******************************************************
    //*******************************************************
    function __construct( )
    {
        //
        // Create the default loggers.
        //
        $this->logFileNames[LsSys::LS_LOG_SERVER]     = 'server.log';
        $this->logFileNames[LsSys::LS_LOG_WEB_SERVER] = 'webServer.log';

        foreach( $this->logFileNames as $key => $logFileName )
        {
            $err = $this->CreateLoggerInstance(
                            $key,               // Associative array key into the loggers array.
                            $logFileName,       // Filename of the logfile.
                            KLogger::DEBUG,     // Mode we're logging as.
                            $pLogger );         // Reference to the logger object created.
            if( $err != EOK )
            {
                LsSys::LsDie( "Failure to create system logger for '".$logFileName."'", $err );
            }
        }


        //*****************************************************************************
        // Load up the defined constants array and precompute the json error codes
        //*****************************************************************************
        $this->constants = get_defined_constants(true);

        foreach( $this->constants["json"] as $name => $value )
        {
            if( !strncmp($name, "JSON_ERROR_", 11) )
            {
                $this->jsonErrors[$value] = $name;
            }
        }
    }

    public function JsonError( $errCode )
    {
        return( $this->jsonErrors[$errCode] );
    }

    public function Log( $logKey, $message, $level )
    {
        $this->loggers[$logKey]->Log( $message, $level );
    }

    public function LogError( $logKey, $message )
    {
        $this->loggers[$logKey]->LogError( $message );
    }

    public function LogWarn( $logKey, $message )
    {
        $this->loggers[$logKey]->LogWarn( $message );
    }

    public function LogInfo( $logKey, $message )
    {
        $this->loggers[$logKey]->LogInfo( $message );
    }

    public function LogDebug( $logKey, $message )
    {
        $this->loggers[$logKey]->LogDebug( $message );
    }

    public function LogFatal( $logKey, $message )
    {
        $this->loggers[$logKey]->LogFatal( $message );
    }


    //*******************************************************
    //*******************************************************
    // Constants
    //*******************************************************
    //*******************************************************
    //
    // Operating System Types
    //
    const LS_OS_LINUX       = "linux";
    const LS_OS_WINDOWS     = "windows";

    const DEFAULT_TIMEZONE  = "America/Los_Angeles";
    const LOGDIR_BASE       = "MyCompany";

    //
    // System Resource Files
    //
    const ECO_IPC_LOCK_FILE = "ecosphere.lock";
    const ECO_IPC_QUEUE_ID  = 1335;
    const ECO_IPC_QUEUE_DEPTH = 32;
    const ECO_IPC_QUEUE_MAX_MSG_SIZE = 65536;

    //
    // Webcache Resource Key
    //
    const ECO_WEB_CACHE_KEY = 2209;

    //
    // Default loggers
    //
    const LS_LOG_SERVER     = "LS_LOG_SERVER";
    const LS_LOG_WEB_SERVER = "LS_LOG_WEB_SERVER";

    //
    // Simulated document root for cli/debugger run.
    //
    const LS_WINDOWS_CLI_DOCUMENT_ROOT  = "file:///wamp/www";
    const LS_LINUX_CLI_DOCUMENT_ROOT    = "file:///var/www";

    //*******************************************************
    //*******************************************************
    // Static Public Methods
    //*******************************************************
    //*******************************************************
    /**
     *********************************************************************************************
     * @return String Returns the operating system type as:
     * <p>   LS_SERVER_OS_LINUX = "linux"<\p>
     * <p>   LS_SERVER_OS_WINDOWS = "windows"<\p>
     */
    static public function GetOS()
    {
        global $gTimeZoneStr;

        //
        // Detect the OS and return it.
        //
        if( preg_match( "/linux/i", PHP_OS) )
        {
            if( $gTimeZoneStr == null )
            {
                $gTimeZoneStr = LsSys::DEFAULT_TIMEZONE;
                date_default_timezone_set($gTimeZoneStr);
            }
            return( LsSys::LS_OS_LINUX );
        }
        else
        {
            if( $gTimeZoneStr == null )
            {
                $gTimeZoneStr = LsSys::DEFAULT_TIMEZONE;
                date_default_timezone_set($gTimeZoneStr);
            }
            return( LsSys::LS_OS_WINDOWS );
        }
    }



    static public function GetTimezone()
    {
        global $gTimeZoneStr;
        return( $gTimeZoneStr );
    }

    static public function SetTimezone( $timeZone )
    {
        date_default_timezone_set($timeZone);
    }


    static public function GetHomeDir( )
    {
        if( LsSys::GetOS() == LsSys::LS_OS_LINUX )
        {
            $userinfo = posix_getpwuid( getmyuid() );
            return( $userinfo['dir'] );
        }
        else
        {
            // Windows
            $username = get_current_user() ?: getenv('USERNAME');

            if ($username && is_dir($win7path = 'C:\Users\\'.$username.'\\'))
            { // is Vista or older
                return "/Users/".$username;
            } elseif ($username) {
                return "/Documents and Settings/".$username;
            } elseif (is_dir('C:\Users\\')) { // is Vista or older
                return '/Users';
            } else {
                return '/Documents and Settings';
            }

        }
    }

    static public function GetRootPath()
    {
        $path = "";
        $homeDir = LsSys::GetHomeDir();


        switch( LsSys::GetOS() )
        {
            case LsSys::LS_OS_LINUX:
            {
                $path = "/".LsSys::LOGDIR_BASE;
                break;
            }

            case LsSys::LS_OS_WINDOWS:
            {
                $path = getenv("HOME")."/".LsSys::LOGDIR_BASE;
                break;
            }

            default:
            {
                return( "invalid_path" );
            }
        }

        //
        // Return the log path.
        //
        return( "file://".$path );
    }

    /**
     *********************************************************************************************
     * @return String Returns the company log-dir path directory.
     */
    static public function GetRootLogPath()
    {
        return( LsSys::GetRootPath()."/log" );
    }

    static public function GetEcosphereIpcLockFile( $rsrcKey )
    {
        return( LsSys::GetRootPath()."/".LsSys::ECO_IPC_LOCK_FILE."_".$rsrcKey );
    }

    static public function GetDocumentRoot()
    {
        $documentRoot = $_SERVER['DOCUMENT_ROOT'];

        if( PHP_SAPI === 'cli' )
        {
            if( LsSys::GetOS() === LsSys::LS_OS_WINDOWS )
            {
                $documentRoot = LsSys::LS_WINDOWS_CLI_DOCUMENT_ROOT;
            }
            else if( LsSys::GetOS() === LsSys::LS_OS_LINUX )
            {
                $documentRoot = LsSys::LS_LINUX_CLI_DOCUMENT_ROOT;
            }
        }

        return( $documentRoot );
    }

    /**
     *********************************************************************************************
     * @param String $filename This may be a filename under the default logdir
     * or it may be an explicit filename which is flagged by the $bCreateUnderDefaultLogDir param.
     * @param Integer $priority Specified from the class constants of the KLogger class.
     * @param KLogger $pLogger Reference to the logger instance we create here.
     * @param Boolean $bCreateUnderDefaultLogDir 'true' if you want to use the default-Company log dir
     * and false if you want to specify your own specific filename as the $filename param.
     * @return errno -- EOK if no error.
     *                   EPERM if we can't create the filename.
     */
    public function CreateLoggerInstance
        (
        $loggerKeyName,
        $filename,
        $priority,
        &$pLogger,
        $bCreateUnderDefaultLogDir=true
        )
    {
        //
        // Check to see if this logger already exists.
        //
        if( isset($this->loggers[$loggerKeyName]) )
        {
            return( EEXIST );
        }

        //
        // Check to see if the caller wants to use the default log file location.
        //
        if( $bCreateUnderDefaultLogDir == true )
        {
            if( !file_exists(LsSys::GetRootLogPath()) )
            {
                // Owner:  RW
                // Group:  RW
                // Others: R
                mkdir( LsSys::GetRootLogPath(), 0664, true );
            }

            $logFile = LsSys::GetRootLogPath()."/".$filename;
        }
        else
        {
            //
            // In this case we're creating a specific logfile.
            //
            $logFile = $filename;
        }

        //
        // Create the logger instance in the reference provided.
        //
        $pLogger = new KLogger( $logFile, $priority );
        if( $pLogger->Log_Status != KLogger::LOG_OPEN )
        {
            echo "Failure to create logger $logFile\n";
            return( EPERM );
        }

        //
        // Store the logger on the sysinfo structure.
        //
        $this->loggers[$loggerKeyName] = $pLogger;
        $this->logFileNames[$loggerKeyName] = $filename;

        return( EOK );
    }

    static public function LsDie( $string="JustDieBitch", $exitCode=EFAULT )
    {
        $dieString = "LsSys::LsDie() Exiting process on fatal error! Msg='".$string."' ExitCode=".$exitCode."\n";
        echo( $dieString.PHP_EOL );
        fwrite( STDERR, $dieString );

        exit( $exitCode );
    }



    //*******************************************************
    //*******************************************************
    // Constructor & Abstract (Virtual) Methods
    //*******************************************************
    //*******************************************************

    //*******************************************************
    //*******************************************************
    // Public Methods
    //*******************************************************
    //*******************************************************

    //*******************************************************
    //*******************************************************
    // Protected Methods
    //*******************************************************
    //*******************************************************

    //*******************************************************
    //*******************************************************
    // Private Methods
    //*******************************************************
    //*******************************************************

} /* End Class Definition */

/**
 * @param AssociativeArray $array This is an array of var=>value entries you want to turn
 * into a JSON object.
 * @param Boolean $bObjBrackets This allows you to create list fragments that have no bounding brackets.
 * @param Boolean $bPrettyOutput This adds new-lines into the format string so you can print it out nicely.
 * @returns String JSON-formatted data string representing the associative array passed in on $array.
 */
class LsJson
{
    const LS_EMPTY_ARRAY = 'LS_EMPTY_ARRAY';
    const LS_NULL = 'LS_NULL';

    static public function OpenObject( $bPrettyOutput=false )
    {
        if( $bPrettyOutput === true )
        {
            return( "{\n" );
        }
        else
        {
            return( "{" );
        }
    }

    static public function CloseObject( $bPrettyOutput=false )
    {
        if( $bPrettyOutput === true )
        {
            return( "}\n" );
        }
        else
        {
            return( "}" );
        }
    }

    static public function Label( $value )
    {
        return( "\"".$value."\":" );
    }

    static public function DecodeValue( &$value )
    {
        if( $value === LsJson::LS_EMPTY_ARRAY )
        {
            return( array() );
        }
        else if( $value === LsJson::LS_NULL )
        {
            return( null );
        }
        else
        {
            return( $value );
        }
    }



    static public function CreateList( &$array, $bObjBrackets=true, $bPrettyOutput=false )
    {
        $json = "";
        $bRemoveComma = false;

        if( $bObjBrackets === true )
        {
            $json .= LsJson::OpenObject( $bPrettyOutput );
        }

        //
        // Create the var/value pairs
        //
        foreach( $array as $key => $value )
        {
            if( is_array($array[$key]) === true )
            {
                $value = LsJson::CreateList( $array[$key], $bObjBrackets, $bPrettyOutput );
            }

            $json .= LsJson::VarValue( $key, $value, $bPrettyOutput ).",";
            $bRemoveComma = true;
        }

        if( $bRemoveComma === true )
        {
            $json = substr($json, 0, -1);
        }

        if( $bObjBrackets === true )
        {
            $json .= LsJson::CloseObject( $bPrettyOutput );
        }

        return( $json );
    }



    static public function CreateList_backup( &$array, $bObjBrackets=true, $bPrettyOutput=false )
    {
        $json = "";
        $bRemoveComma = false;

        if( $bObjBrackets === true )
        {
            $json .= LsJson::OpenObject( $bPrettyOutput );
        }

        //
        // Create the var/value pairs
        //
        foreach( $array as $key => $value )
        {
            if( is_array($array[$key]) === true )
            {
                $value = LsJson::CreateList( $array[$key], $bObjBrackets, $bPrettyOutput );
            }
            else if( is_object($array[$key]) === true )
            {
                //
                // Check to see if the ToJson() method exists.
                //
                if( method_exists($array[$key],'ToJson') === true )
                {
                    $value = $array[$key]->ToJson();
                }
            }

            $json .= LsJson::VarValue( $key, $value, $bPrettyOutput ).",";
            $bRemoveComma = true;
        }

        if( $bRemoveComma === true )
        {
            $json = substr($json, 0, -1);
        }

        if( $bObjBrackets === true )
        {
            $json .= LsJson::CloseObject( $bPrettyOutput );
        }

        return( $json );
    }

    static public function VarValue( $key, $value, $bPrettyOutput=false )
    {

        if( isset($value[0]) && ($value[0] == "{" || $value[0] == "\"") )
        {
            //
            // Don't wrap embedded objects in quotes.
            //
            $json = "\"".$key."\":".$value;
            return( $json );
        }

        if( is_bool($value) )
        {
            if( $value == true )
            {
                $json = "\"".$key."\":true";
            }
            else
            {
                $json = "\"".$key."\":false";
            }

            return( $json );
        }


        if( is_string($value) )
        {
            //
            // If something is a string type in php, we want to
            // leave it as a string in the json representation.
            //
            $value = str_replace( "\"", "\\\"", $value );
            $json = "\"".$key."\":\"".$value."\"";
            return( $json );
        }


        if( is_numeric($value) &&
                strstr($value,"0x")===false &&
                strstr($value,"0X")===false )
        {
            $json = "\"".$key."\":".$value;
            return( $json );
        }


        return( "" );
    }
}


class LsDevSim
{
    const GUID_DFLT = "GUID_DFLT";

    static public function CreateVirtualGuid()
    {
        //
        // Create a random 64-bit guid.
        //
        $guid =  sprintf( "%02X", mt_rand(0,255) );
        $guid .= sprintf( "%02X", mt_rand(0,255) );
        $guid .= sprintf( "%02X", mt_rand(0,255) );
        $guid .= sprintf( "%02X", mt_rand(0,255) );
        $guid .= sprintf( "%02X", mt_rand(0,255) );
        $guid .= sprintf( "%02X", mt_rand(0,255) );
        $guid .= sprintf( "%02X", mt_rand(0,255) );
        $guid .= sprintf( "%02X", mt_rand(0,255) );
        return($guid);
    }
}


function LsRoundUp( $value, $mod )
{
    $value = intval($value);
    $mod = intval($mod);

    $result  = intval(($value + $mod - 1) / $mod) * $mod;

    return( $result );
}

function LsRoundDown( $value, $mod )
{
    $value = intval($value);
    $mod = intval($mod);

    $result = intval($value / $mod) * $mod;
    return( $result );
}

function lsRandomFloat( $min = 0, $max = 1 )
{
    return $min + mt_rand() / mt_getrandmax() * ($max - $min);
}

if( function_exists('LsMin') == false )
{
    function LsMin( $a, $b )
    {
        return( ($a<$b)?$a:$b );
    }
}

if( function_exists('LsMax') == false )
{
    function LsMax( $a, $b )
    {
        return( ($a>$b)?$a:$b );
    }
}

if( function_exists('LsChop') == false )
{
    function LsChop( &$strVal )
    {
        $strVal = substr( $strVal, 0, -1 );
    }
}

if( function_exists('LsStringIsValidJson') == false )
{
    function LsStringIsValidJson( &$string )
    {
        $results = json_decode( $string );
        if(  $results === null || $results === false )
        {
            return( false );
        }
        return( true );
    }
}


if( function_exists('LsJsonLastError') == false )
{
    function LsJsonLastError( )
    {
        return( json_last_error() );
    }
}

if( function_exists('LsIsValidUrl') == false )
{
    function LsIsValidUrl( $url )
    {
        if( !$url ||
            !is_string($url) ||
            ! preg_match('/^http(s)?:\/\/[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(\/.*)?$/i', $url))
        {
            return false;
        }
        return true;
    }
}



function LsIsAssoc( $arr )
{
    return array_keys($arr) !== range(0, count($arr) - 1);
}

function LsIsSet( $value )
{
    if( isset($value) == false )
    {
        return( false );
    }

    if( is_null($value) || $value === LsDb::UNSET_STR )
    {
        return( false );
    }

    if( is_string($value) == true )
    {
        if( strlen($value) === 0 )
        {
            return( false );
        }
    }
    return( true );
}

function LsMixedParamToStringArray( &$arg, &$result )
{
    //********************************************************************
    // See if the caller is asking for specific devices to be
    // refreshed from the database into memory.
    //********************************************************************
    if( is_string($arg) == true )
    {
        //
        // Create a single-item array
        //
        $result = array( $arg );
    }
    else if( is_array($arg) == true )
    {
        //
        // If the caller provides a specific list of guids
        // to refresh from the database, we just copy that list
        // into the same array that we -would- have gotten from
        // the result of asking GetAllConfigStateGuidList() above.
        //
        $result = $arg;
    }
    else
    {
        //
        // Only arrays and strings are allowed here...
        //
        return( EINVAL );
    }
}

function LsMixedParamToNumericArray( &$arg, &$result )
{
    //********************************************************************
    // See if the caller is asking for specific devices to be
    // refreshed from the database into memory.
    //********************************************************************
    if( is_numeric($arg) == true )
    {
        //
        // Create a single-item array
        //
        $result = array( $arg );
    }
    else if( is_array($arg) == true )
    {
        //
        // If the caller provides a specific list of guids
        // to refresh from the database, we just copy that list
        // into the same array that we -would- have gotten from
        // the result of asking GetAllConfigStateGuidList() above.
        //
        $result = $arg;
    }
    else
    {
        //
        // Only arrays and strings are allowed here...
        //
        return( EINVAL );
    }
}

function LsDeleteElement( &$array, $element )
{
    $tmpArray = array();

    foreach( $array as $arrayEle )
    {
        if( $arrayEle === $element )
        {
            continue;
        }

        array_push( $tmpArray, $arrayEle );
    }

    $array = $tmpArray;
}

function LsGetPostVar( $key=null, $bEmptyAsNull=false )
{
    if( is_null($key) == true )
    {
        return $_POST;
    }

    if( isset($_POST[$key]) == false )
    {
        return( null );
    }

    if( strlen($_POST[$key]) == 0 && $bEmptyAsNull==true )
    {
        return( null );
    }

    return( $_POST[$key] );
}

function LsGetGetVar( $key=null, $bEmptyAsNull=false )
{
    if( is_null($key) == true )
    {
        return $_GET;
    }

    if( isset($_GET[$key]) == false )
    {
        return( null );
    }

    if( strlen($_GET[$key]) == 0 && $bEmptyAsNull==true )
    {
        return( null );
    }

    return( $_GET[$key] );
}

function LsGetSessionVar( $key=null, $bEmptyAsNull=false )
{
    //
    // Check to see if the session has started or not.
    //
    if( session_status() == PHP_SESSION_NONE )
    {
        return( null );
    }

    if( is_null($key) == true )
    {
        return $_SESSION;
    }

    if( isset($_SESSION[$key]) == false )
    {
        return( null );
    }

    if( strlen($_SESSION[$key]) == 0 && $bEmptyAsNull==true )
    {
        return( null );
    }

    return( $_SESSION[$key] );
}

function &LsGetSessionVarRef( $key )
{
    $nullObj = null;

    if( isset($_SESSION[$key]) == false )
    {
        return $nullObj;
    }

    return $_SESSION[$key];
}



function LsTimestampToSeconds( $timestamp )
{
    $seconds;

    $sa = explode( ' ', $timestamp );

    if( count($sa) != 2 )
    {
        return( null );
    }

    $ymd = explode( '-', $sa[0] );
    $hms = explode( ':', $sa[1] );

    if( count($ymd) != 3 || count($hms) != 3 )
    {
        return( null );
    }

    $y = $ymd[0];
    $mo = $ymd[1];
    $d = $ymd[2];

    $h = $hms[0];
    $m = $hms[1];
    $s = $hms[2];

    $seconds = mktime ( $h, $m, $s, $m0, $d, $y );

    return( $seconds );
}

function LsGetClassList( $parentClass=null, $bExactMatch=true )
{
    $allClasses = get_declared_classes();
    $classResults = array();

    if( $parentClass === null )
    {
        return( $allClasses );
    }

    foreach( $allClasses as $className )
    {
        if( $bExactMatch === true )
        {
            if( get_parent_class( $className) === $parentClass )
            {
                array_push( $classResults, $className );
            }
        }
        else
        {
            if( strpos($className, $parentClass) !== false )
            {
                array_push( $classResults, $className );
            }
        }
    }

    return( $classResults );
}

function LsTimestampToHMS( $timestamp )
{
    if( is_array($timestamp) == true )
    {
        if( count($timestamp) != 6 )
        {
            return "EINVAL";
        }

        $year = $timestamp[0];
        $month = $timestamp[1];
        $day = $timestamp[2];
        $h = $timestamp[3];
        $m = $timestamp[4];
        $s = $timestamp[5];
    }
    else if( is_string($timestamp) == true )
    {
        if( strlen($timestamp) != 14 )
        {
            return "EINVAL";
        }

        $year = substr( $timestamp, 0, 4 );
        $month = substr( $timestamp, 4, 2 );
        $day = substr( $timestamp, 6, 2 );
        $h = substr( $timestamp, 8, 2 );
        $m = substr( $timestamp, 10, 2 );
        $s = substr( $timestamp, 12, 2 );
    }
    else
    {
        return "EINVAL";
    }

    return $month."/".$day."/".$year." $h:$m:$s";
}

//
// Removes whitespace from beginning and end of th string.
//
function lsTrim( &$string )
{
    $string = trim_value( $string );
}

function lsPOSTIsSet( $keyName )
{
    trim($keyName);

    if( filter_input(INPUT_POST,$keyName) == null )
    {
        return FALSE;
    }
    else
    {
        return TRUE;
    }
}

function lsPOSTGetValue( $keyName )
{
    if( lsPOSTIsSet($keyName) )
    {
        return( filter_input(INPUT_POST,$keyName) );
    }
    else
    {
        return( null );
    }
}
