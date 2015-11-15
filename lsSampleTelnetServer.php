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
**  Filename:       lsSampleTelnetServer.php
**  Project:        EcoSphere
**  Author:         Barry Robertson (barry@logixstorm.com)
**  Created:        11-14-2014
**
**  Abstract:       Processes incoming messages on a port we're listening to.
**  These messages are formatted as simple strings and passed into a switch
**  table.  You could enhance this model to use a dispatch table into functions
**  or whatever you'd like.  I used the switch table for clarity and the simplest
**  form of processing an incoming 'command' string.
**
**
**  Change Log:
**  Barry Robertson; 11-14-2014; Initial version.
**  <engr. name>; <date>; <reason>
*****************************************************************************/
require_once( "lsErrno.php" );
require_once( "lsUtil.php" );
require_once( "lsServer.php" );


/**
 * @class LsSampleTelnetServer
 * @abstract This server is designed to accept simple text commands (usually over telnet).
 */
class LsSampleTelnetServer extends LsServer
{
    //**************************************************************************************
    //**************************************************************************************
    // Private Members
    //**************************************************************************************
    //**************************************************************************************
    private $pingCountdown = LsSampleTelnetServer::PING_SOMETHING_INTERVAL;

    //**************************************************************************************
    //**************************************************************************************
    // Constants
    //**************************************************************************************
    //**************************************************************************************
    const TELNET_LISTEN_PORT        = 12909;
    const PING_SOMETHING_INTERVAL   = 10;   /* Value in seconds */

    //**************************************************************************************
    //**************************************************************************************
    // Constructor & Abstract (Virtual) Methods
    //**************************************************************************************
    //**************************************************************************************
    function __construct( )
    {
        parent::__construct();

        $this->name = 'SampleTelnetServer';
    }

    /**
     ***************************************************************************************
     * @param LsServerClient $client This is the the client the message came in on.
     * <p>The message is held in the $client->Msg() buffer.</p>
     **************************************************************************************/
    protected function ServerSpecificProcessIncomingMessage( &$client )
    {
        $err = $this->ProcessMetaCmd( $client, $this->listenThread );
        return( $err );
    }

    protected function GetMainListeningPortNumber( )
    {
        return( LsSampleTelnetServer::TELNET_LISTEN_PORT );
    }

    protected function TimeSlice( )
    {

    }

    //
    // This function is called approximately every second by the server main thread.
    // It's not guaranteed to be *every* second and the clock can get skewed because
    // we're using the counters in the server to dispatch this function whenever
    // we cross a second boundary on the polling period.
    //
    protected function PeriodicTask( $serverUpSeconds )
    {
        //echo "Server Clock = $serverUpSeconds\n";
        //
        // Update the ping interval countdown timer
        //
        $this->pingCountdown--;
        if( $this->pingCountdown == 0 )
        {
            $this->pingCountdown = LsSampleTelnetServer::PING_SOMETHING_INTERVAL;

            //
            // Do something periodic if you like!
            //
        }

    }

    //**************************************************************************************
    //**************************************************************************************
    // Public Methods
    //**************************************************************************************
    //**************************************************************************************


    //**************************************************************************************
    //**************************************************************************************
    // Private Methods
    //**************************************************************************************
    //**************************************************************************************
    /**
    ***************************************************************************************
    * @param LsServerClient $client This is the the client the message came in on.
    * <p>The message is held in the $client->Msg() buffer.</p>
    * @param LsServerThread $serverThread This is the thread the message is processed upon.
    * @return void No return is needed from this function
    * @abstract Process incoming command strings.
    **************************************************************************************/
    function ProcessMetaCmd( &$client, &$serverThread )
    {
        $cmdLineArgs = explode( " ", $client->readBuffer );
        $cmdLineArgc = count( $cmdLineArgs );
        $err = EOK;

        switch( $cmdLineArgs[0] )
        {
            //************************************************************
            // Display the current date on the Gateway Sever.
            //************************************************************
            case "date":
            {
                $uptimeDays = intval( $this->secondsCounter/(3600*24) );
                $uptimeHours = sprintf( "%02u", intval( ($this->secondsCounter % (3600*24)) / 3600 ) );
                $uptimeMinutes = sprintf( "%02u", intval( ($this->secondsCounter % (3600)) / 60 ) );
                $uptimeSeconds = sprintf( "%02u", intval( $this->secondsCounter % 60 ) );

                $client->WriteBytes( date( "m-d-Y G:i:s" ) .
                                     " UpTime= ".$uptimeDays." Days, ".
                                     $uptimeHours.":".$uptimeMinutes.":".$uptimeSeconds." (Hrs:Min:Sec)".PHP_EOL );
                $err = EOK;
                break;
            }

            case "shutdown":
            {
                $serverThread->stopThread = true;
                $err = EOK;
                break;
            }

            case "help":
            default:
            {
                //
                // If the command isn't recognized, then display the help screen.
                //
                $helpString = $this->BuildHelpString();

                $client->WriteBytes( $helpString );
                $err = EOK;
                break;
            }


        }

        $client->WriteBytes( PHP_EOL );

        return( $err );
    }

    private function BuildHelpString()
    {
        $helpString =
         "============================================================================".PHP_EOL.
         " Simple Server Commands".PHP_EOL.
         "============================================================================".PHP_EOL.
         " date                   -- Displays current server time and up-time in seconds.".PHP_EOL.
         " help                   -- Displays help information.".PHP_EOL.
         " shutdown               -- Shuts the server down; waits for threads.".PHP_EOL.
         "============================================================================".PHP_EOL.
        PHP_EOL.PHP_EOL;

        return( $helpString );
    }


}
