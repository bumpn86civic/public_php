<?php
/*****************************************************************************
* Copyright (C) 2014-2015  Barry Robertson
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
**  Filename:       lsSampleTelnetServerStart.php
**  Project:        EcoSphere
**  Author:         Barry Robertson (barry@logixstorm.com)
**  Created:        11-14-2014
**
**  Abstract:       This is the 'main' entrypoint to setup and start a simple
**  telnet server defined in lsSampleTelnetServer.php.
**
**
**  Change Log:
**  Barry Robertson; 11-14-2014; Initial version.
**  <engr. name>; <date>; <reason>
*****************************************************************************/

//********************************************************
//* Use Modules
//********************************************************

//********************************************************
//* Include/Require Files
//********************************************************
require_once "lsUtil.php";
require_once "lsSampleTelnetServer.php";

//********************************************************
//* Globals
//********************************************************
$gDaemonize = false;
$gServerInstance;

// Report simple running errors
//error_reporting(E_ERROR | E_WARNING | E_PARSE);

// Reporting E_NOTICE can be good too (to report uninitialized
// variables or catch variable name misspellings ...)
//error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);

// Report all errors except E_NOTICE
//error_reporting(E_ALL & ~E_NOTICE);

// Report all PHP errors (see changelog)
error_reporting(E_ALL);


//********************************************************
//* Public Functions
//********************************************************

function PrintUsage()
{
    echo( "Usage: lsSampleTelnetServerStart.php [options]\n".
                 "\n".
                 "    -daemonize           Background the server and turn it into a system daemon process.\n".
                 "\n\n\n" );
}

function ParseArgs( &$args )
{
    foreach( $args as $arg )
    {
        if( strcmp($arg,"-help") == 0 ||
            strcmp($arg,"--help") == 0 ||
            strcmp($arg,"-h") == 0 ||
            strcmp($arg,"-H") == 0 )
        {
            PrintUsage();
            exit(0);
        }

        if( strcmp($arg,"-daemonize") == 0 )
        {
            global $gDaemonize;

            $gDaemonize = true;
            continue;
        }
    }
}

function CreateServerInstance( &$server )
{
    $server = new LsSampleTelnetServer( );
}

function StartServerAndWaitOnThreads( &$server )
{
    if( $server === null )
    {
        die( "Server instance not initialized!\n" );
    }

    echo "Server:  IP Address ".$server->GetHostIp()."\n";
    echo "Server:  Port ".$server->GetPortNumber()."\n";

    $server->StartServer( true );
    $server->SleepWaitForServerShutdown();
}

function Daemonize()
{
    //
    // We don't daemonize on windows
    //
    if( LsServer::GetOS() == LsServer::LS_SERVER_OS_WINDOWS )
    {
        return;
    }

    //
    // Clear file creation mask.
    //
    umask( 0 );

    //
    // Get the max number of file descriptors.
    //
    $limit = posix_getrlimit();

    //
    // Become a session leader to lose controlling TTY.
    //
    $pid = pcntl_fork( );
    if( $pid < 0 )
    {
        die( "Could not fork daemon proces!\n" );
    }
    else if( $pid )
    {
        //
        // This is the parent process here. (the child pid is returned...)
        //
        echo "This is the parent process, exiting....\n";
        exit;
    }

    if( posix_setsid() === -1 )
    {
         die('could not setsid');
    }

    //
    // First child process executing here.  We ensure future
    // opens won't allocate controlling TTY's.
    //
    pcntl_signal(SIGTSTP, SIG_IGN);
    pcntl_signal(SIGTTOU, SIG_IGN);
    pcntl_signal(SIGTTIN, SIG_IGN);
    pcntl_signal(SIGHUP, SIG_IGN);

    // CHILD PROCESS EXECUTION FROM THIS POINT ON
    //*************************************************
    //*************************************************

    //
    // Change the working dir to root to keep from interfering
    // with any filesystem stuff.
    //
    if( chdir("/") != true )
    {
        die( "Failure to chdir to /\n");
    }

    echo "This is the child; closing the fd's...\n";

    return( EOK );
    //
    // Attach fd 0,1,2 to /dev/null to ignore input and output.
    //
    fclose(STDIN);
    fclose(STDOUT);
    fclose(STDERR);

    $stdIn  = fopen('/dev/null', 'r'); // set fd/0
    $stdOut = fopen('/dev/null', 'w'); // set fd/1
    $stdErr = fopen('php://stdout', 'w'); // a hack to duplicate fd/1 to 2
    return( EOK );
}

//
// Parse the arguments
//
ParseArgs( $argv );

//
// Daemonize the process if the global is set
//
if( $gDaemonize == true )
{
    Daemonize();
    echo "Server:  Child returned ok...\n";
}

//
// Create the server.
//
CreateServerInstance( $gServerInstance );

echo "Server:  STARTED!\n";

//
// Start the server task.
//
StartServerAndWaitOnThreads( $gServerInstance );

exit;
