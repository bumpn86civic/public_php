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
**  Filename:       lsServer.php
**  Project:        EcoSphere
**  Author:         Barry Robertson (barry@logixstorm.com)
**  Created:        11-03-2014
**
**  Abstract:       Processes incoming messages on our cloud server service port.
**  These messages are formatted as JSON fragments and used primarily to
**  communicate from the IoT Gateway/Router and produce updates to the device
**  state structures and log samples/events to the user's database.
**
**
**  Change Log:
**  Barry Robertson; 11-03-2014; Initial version.
**  <engr. name>; <date>; <reason>
*****************************************************************************/


//********************************************************
//* Include/Require Files
//********************************************************
require_once( "lsErrno.php" );
require_once( "lsServerThread.php" );
require_once( "lsServerClient.php" );

//********************************************************
//* Use Modules
//********************************************************

//********************************************************
//* Name Space
//********************************************************

//****************************************************************************
//************************ Classes *******************************************
//****************************************************************************

/**
 *******************************************************************************
 * Class: LsServer
 *
 * @abstract Represents a simple php stream-socket server that reads
 * messages from the client connections and processes them into commands.
 * See lsMessage.php for valid message definitions.
 *
 ********************************************************************************/
abstract class LsServer
{
    //*******************************************************
    //*******************************************************
    // Private Members
    //*******************************************************
    //*******************************************************
    private $hostname;
    private $hostIpAddr;
    private $portNumber;
    private $operatingSystem = "unknown";
    private $hostUrlString;
    private $workerThreads = array();
    private $clients = array();
    private $activeClients = array();
    private $socket;
    private $mutex;
    private $runState = LsServer::LS_SERVER_STATE_HALTED;
    protected $usecCounter = 0;
    protected $secondsCounter = 0;
    protected $prompt = "<CmdPrompt> ";

    private $mode = LsServer::DEBUG;

    //*******************************************************
    //*******************************************************
    // Protected Members
    //*******************************************************
    //*******************************************************
    protected $listenThread;
    protected $name = "SERVER";

    //
    // Log levels
    //
    protected $msgLogLevel = KLogger::INFO;
    protected $connLogLevel = KLogger::INFO;
    protected $ctrlLogLevel = KLogger::INFO;

    //*******************************************************
    //*******************************************************
    // Constants
    //*******************************************************
    //*******************************************************
    const LS_SERVER_MAX_CONNS   = 3;        /* Set to the maximum number of */
                                            /* connections the server will allow. */

    const LS_SERVER_POLL_USECS  = 50000;    /* 50ms polling period. */

    //
    // Server Run States
    //
    const LS_SERVER_STATE_HALTED            = 0;
    const LS_SERVER_STATE_RUNNING           = 1;
    const LS_SERVER_STATE_CLOSING_CONNS     = 2;
    const LS_SERVER_STATE_HALTING_THREADS   = 3;
    const LS_SERVER_STATE_CRITICAL          = 4;

    //
    // Run Modes
    //
    const DEBUG = 'DEBUG';
    const ALPHA = 'ALPHA';
    const PRODUCTION = 'PROD';

    //*******************************************************
    //*******************************************************
    // Public Members
    //*******************************************************
    //*******************************************************
    public function AddThread( $bStartImmediately=false )
    {
        $myNewThread = new LsServerThread;

        //
        // Add the thread to our array of threads.
        //
        array_push( $this->workerThreads, $myNewThread );

        //
        // Set the default entrypoint to our server processing loop.
        //
        $myNewThread->SetRunFunction( $this, 'ThreadEntryPoint' );

        //
        // Start the thread if the caller wants it started up.
        // (They could also call the SetRunFunction() method and set their
        // own entrypoint.  This is for future flexibility at this time.)
        //
        if( $bStartImmediately === true )
        {
            $myNewThread->StartThread();
        }

        return( $myNewThread );
    }

    public function GetMode()
    {
        return( $this->mode );
    }

    public function SetMode( $mode )
    {
        $this->mode = $mode;
    }

    public function GetHostIp() { return($this->hostIpAddr); }
    public function GetHostname() { return($this->hostname); }
    public function GetPortNumber() { return($this->portNumber); }

    public function GetActiveClients() { return($this->activeClients); }

    public function StartServer( $bRunOnThisThread=false )
    {
        //
        // For debugging we want to be able to single-thread the server.
        //
        if( $bRunOnThisThread == true )
        {
            $myFakeThread = new LsServerThread;

            $this->ThreadEntryPoint( $myFakeThread );
            return( EOK );
        }

        //
        // Start any threads that aren't already running.
        //
        foreach( $this->workerThreads as $thread )
        {
            if( $thread->IsRunning() === false )
            {
                $thread->StartThread();
            }
        }

        $this->SetRunState( LsServer::LS_SERVER_STATE_RUNNING );

        return( EOK );
    }

    public function ShutdownServer()
    {
        foreach( $this->workerThreads as $thread )
        {
            if( $thread->IsRunning() === true )
            {
                $thread->stopThread = true;
            }

            //
            // wait for it to exit.
            //
            $this->LsLog( "Terminated thread '".$thread->Name(),
                            $this->ctrlLogLevel );

            $thread->join();

            $this->LsLog( "Joined thread '".$thread->Name(),
                            $this->ctrlLogLevel );
        }

        //
        // Reset the active client array just to be sure it's empty.
        //
        $this->activeClients = array();
    }

    public function SleepWaitForServerShutdown()
    {
        if( $this->listenThread->IsRunning() == true )
        {
            $this->listenThread->join();

            $this->LsLog( "Thread '".$this->listenThread->Name()."' has exited; shutdown server...",
                    $this->ctrlLogLevel );

            $this->ShutdownServer();
            return( EOK );
        }

        return( ENOTREADY );
    }


    //*******************************************************
    //*******************************************************
    // Static Public Methods
    //*******************************************************
    //*******************************************************




    //*******************************************************
    //*******************************************************
    // Constructor & Abstract (Virtual) Methods
    //*******************************************************
    //*******************************************************
    public function __construct()
    {
        //
        // Set the server name and ip address.
        //
        $this->hostname = gethostname();
        $this->hostIpAddr = gethostbyname( $this->hostname );
        $this->portNumber = $this->GetMainListeningPortNumber();

        //
        // Set the operating system
        //
        $this->operatingSystem = LsSys::GetOS();

        //
        // Create the listening and worker threads.
        //
        $this->listenThread = $this->AddThread();
        $this->listenThread->Name( "ListeningThread" );
        $this->listenThread->SetRunFunction( $this, 'ThreadEntryPoint' );
    }

    //*******************************************************
    //*******************************************************
    // Public Methods
    //*******************************************************
    //*******************************************************
    public function OS()
    {
        return( $this->operatingSystem );
    }

    public function ThreadEntryPoint( $thread )
    {
        //
        // Configure the socket for the thread.
        //
        $this->SocketConfigure();

        while( 1 )
        {
            //echo "Polling...\n";

            //***********************************************************
            // Sleep for the polling period.
            //***********************************************************
            usleep( LsServer::LS_SERVER_POLL_USECS );

            $this->usecCounter += LsServer::LS_SERVER_POLL_USECS;

            //***********************************************************
            // Check for a shutdown command.
            //***********************************************************
            if( $thread->stopThread == true )
            {
                echo "THREAD: Instructed to stop.\n";
                //
                // Teardown any client connections.
                //
                foreach( $this->activeClients as $client )
                {
                    $client->TearDownConnection();
                }

                $this->activeClients = array();

                fclose( $this->socket );

                return;
            }

            //
            // Check for a new connection.
            //
            $this->CheckForNewConnections();

            //
            // Monitor and update/cancel any existing connections.
            //
            $this->MonitorConnections();

            //
            // Check the client connections for incoming messages.
            //
            $this->ReadIncomingClientMessages();

            //
            // Dispatch the messages to the handlers.
            //
            $this->DispatchClientMessages();

            //
            // Continue response i/o write operations (if any...)
            //
            $this->ContinueResponseTransmit();

            //
            // Dispatch the 1sec periodic function (if we're at a 1s boundary)
            //
            $this->ServicePeriodicTasks();
        }
    }


    //*******************************************************
    //*******************************************************
    // Protected Methods
    //*******************************************************
    //*******************************************************
    abstract protected function ServerSpecificProcessIncomingMessage( &$client );
    abstract protected function GetMainListeningPortNumber( );
    abstract protected function PeriodicTask( $serverUpSeconds );
    abstract protected function TimeSlice( );

    protected function TransmitResponse( &$client, &$serverThread, &$rspMsg, $rspCode )
    {
        //
        // Build the response.
        //
        $client->ProgramMessageResponseHeader( $rspMsg, $rspCode );

        $jdu = $rspMsg->CreateJdu();

        $this->LsLog( "TX::RSPJDU=".$jdu, $this->msgLogLevel );

        //
        // Transmit the response JDU &
        // Write the message response back through the client into the wire.
        //
        $client->WriteBytes( $jdu."\r".PHP_EOL );

        return( EOK );
    }

    public function LsLog( $message, $priority )
    {
        LsLog( LsSys::LS_LOG_SERVER, $this->name.": ".$message, $priority );
    }

    public function LsLogError( $message )
    {
        LsLog( LsSys::LS_LOG_SERVER, $this->name.": ".$message, KLogger::ERROR );
    }



    //*******************************************************
    //*******************************************************
    // Private Methods
    //*******************************************************
    //*******************************************************

    //
    // Startup banner to the console connection.
    //
    private function Banner()
    {
        $banner = "";

        $banner .= "********************************************************************************\r".PHP_EOL;
        $banner .= "*                               Simple Terminal Server                         *\r".PHP_EOL;
        $banner .= "********************************************************************************\r".PHP_EOL;
        $banner .= "*                      Copyright C 2015-2015, WhateverUWant Inc.               *\r".PHP_EOL;
        $banner .= "*                                All rights reserved.                          *\r".PHP_EOL;
        $banner .= "*                                                                              *\r".PHP_EOL;
        $banner .= "* THIS IS A PRIVATE COMPUTER SYSTEM.                                           *\r".PHP_EOL;
        $banner .= "* The information accessed is proprietary, confidential and for the exclusive. *\r".PHP_EOL;
        $banner .= "* internal use of WhateverUWant employees and authorized personnel.            *\r".PHP_EOL;
        $banner .= "* Dissemination or communication of confidential information is strictly       *\r".PHP_EOL;
        $banner .= "* prohibited.  Use of this system constitutes consent to monitoring of this    *\r".PHP_EOL;
        $banner .= "* system.  Unauthorized use will subject you to administrative, criminal or    *\r".PHP_EOL;
        $banner .= "* other adverse action.                                                        *\r".PHP_EOL;
        $banner .= "********************************************************************************\r".PHP_EOL;
        $banner .= "\r".PHP_EOL;
        $banner .= $this->prompt."%> ";

        return( $banner );
    }

    private function DispatchClientMessages( )
    {
        foreach( $this->activeClients as $client )
        {

            if( $client->IsMsgReady() )
            {
                //echo "Message on:".$client->PeerIp().":".$client->PeerPortNumber()."\n";
                $client->ClrMsgReady( );

                //***************************************************
                // Strip off trialing white-space chars.
                //***************************************************
                $bIsConsoleMsg = true;

                $client->readBuffer = rtrim( $client->readBuffer );
                if( strlen($client->readBuffer) != 0 )
                {
                    //
                    // Process the incoming message!
                    //
                    $this->ProcessMessage( $client, $bIsConsoleMsg );
                    $client->readBuffer = "";
                }
                else
                {
                    //
                    // This just means someone hit a return key...
                    //
                    $bIsConsoleMsg = true;
                }

                if( $bIsConsoleMsg === true )
                {
                    //
                    // Display the banner if it hasn't been.
                    //
                    if( $client->BannerDspy() == false )
                    {
                        $client->BannerDspy( true );
                        $client->WriteBytes( $this->Banner() );
                    }
                    else
                    {
                        $client->WriteBytes( $this->prompt."%> " );
                    }
                }
            }
        }
    }

    private function ServicePeriodicTasks( )
    {
        //
        // Timeslice calls into the sub-class every server tick.
        //
        $this->TimeSlice( );

        //
        // Every second we reset the counter and call into the periodic
        // sub-class handler.
        //
        if( $this->usecCounter >= 1000000 )
        {
            $this->usecCounter = 0;

            $this->PeriodicTask( $this->secondsCounter );
            $this->secondsCounter++;
        }
    }

    private function ContinueResponseTransmit( )
    {
        foreach( $this->activeClients as $client )
        {
            $client->ContinueWrite( );
        }
    }


    private function ReadIncomingClientMessages( )
    {
        //
        // Iterate through the client connections and construct the messages.
        //
        foreach( $this->activeClients as $client )
        {
            $bytesRead = $client->ReadBytes( 1048076 );
        }
    }

    private function MonitorConnections( )
    {
        $bUpdateClients = false;

        //
        // Check for any closed connections.
        //
        foreach( $this->activeClients as $client )
        {
            $mData = stream_get_meta_data( $client->Conn() );

            if( $mData['eof'] == 1 )
            {
                $bUpdateClients = true;
                $client->TearDownConnection();
            }
        }

        //
        // If we had closed connections, then rebuild the active client list.
        //
        if( $bUpdateClients == true )
        {
            $this->activeClients = array();

            for( $i=0; $i<LsServer::LS_SERVER_MAX_CONNS; $i++ )
            {
                if( $this->clients[$i]->IsInuse() == true )
                {
                    array_push( $this->activeClients, $this->clients[$i] );
                }
            }
        }

    }


    private function GetRunState(){ return($this->runState); }

    private function SetRunState( $state )
    {
        switch( $this->runState )
        {
            case LsServer::LS_SERVER_STATE_HALTED:
            {

            }
        }

        //
        // Set the runstate.
        //
        $this->runState = $state;
    }

    private function GetClient( $conn )
    {
        $aIdx = -1;

        for( $i=0; $i<LsServer::LS_SERVER_MAX_CONNS; $i++ )
        {
            if( $this->clients[$i]->Conn() == $conn )
            {
                return( $this->clients[$i] );
            }

            if( $this->clients[$i]->Conn() == null )
            {
                $aIdx = $i;
            }
        }

        //
        // New connection.
        //
        if( $aIdx == -1 )
        {
            return( null );
        }

        //
        // Push the client into the list of active connections.
        //
        array_push( $this->activeClients, $this->clients[$aIdx] );

        //
        // Setting the 'want-peer' param to TRUE gets us the remote
        // end of the connection.
        //
        $peerInfo = stream_socket_get_name( $conn, TRUE );

        //
        // Split the address on the ':' to get the port number.
        // ('2' is maximum number of array elements...)
        //
        $connInfo = explode( ":", $peerInfo, 2 );

        $this->clients[$aIdx]->SetupNewConnection( $this, $conn, $connInfo[0], $connInfo[1] );
        $this->clients[$aIdx]->SetBlocking( 0 );

        return( $this->clients[$aIdx] );
    }

    private function SocketConfigure()
    {
        //
        // Build the stream URL that we're going to listen for connections on.
        //
        $this->hostUrlString = "tcp://".$this->hostIpAddr.":".$this->portNumber;

        //echo "URL=$this->hostUrlString\n";

        $errno = 0;
        $errstr = "";

        $this->socket = stream_socket_server( $this->hostUrlString, $errno, $errstr);
        if( !$this->socket )
        {
            echo "$errstr ($errno)\n";
            return( $errno );
        }

        //
        // Create our client connections array.
        //
        for( $i=0; $i<LsServer::LS_SERVER_MAX_CONNS; $i++ )
        {
            $this->clients[$i] = new LsClient;
        }

        //
        // Set to non-blocking.
        //
        stream_set_blocking( $this->socket , 0 );

        return( EOK );
    }

    private function CheckForNewConnections()
    {
        $arrRead=array( $this->socket );
        $arrWrite=array( $this->socket);
        $arrExcept = array();

        $client = null;

        //echo "CheckForNewConnections() entered...\n";

        if( stream_select($arrRead,$arrWrite,$arrExcept,0) )
        {
            //echo "CheckForNewConnections() stream_select...\n";

            $conn = stream_socket_accept( $this->socket, 0 );
            if( $conn != FALSE )
            {
                //echo "CheckForNewConnections() stream_socket_accept...\n";

                //
                // Get the client structure for this connection.
                //
                $client = $this->GetClient( $conn );
                if( $client == null )
                {
                    echo "ERROR! No more connections available!\n";
                    return null;
                }

                $this->LsLog(
                    "New connection=".$client->PeerHostname().
                    " Ip=".$client->PeerIp()." Port=".$client->PeerPortNumber(),
                    $this->connLogLevel );
            }
        }

        return( $client );

    }

    private function ProcessMessage( &$client, &$bIsConsoleMsg )
    {
        $bIsConsoleMsg = true;
        //
        // Process any backspaces out of the string.
        //
        $finalStrPos = 0;
        for( $i=0; $i<strlen($client->readBuffer); $i++ )
        {
            //echo"STR[$i] =".$client->readBuffer[$i]." ORD=".ord($client->readBuffer[$i])."\n";

            if( $client->readBuffer[$i] === chr(8) )
            {
                //
                // If it's at the beginning of the string, just delete it by advancing
                // the 'i' position and not the 'newString' position.
                //
                if( $finalStrPos == 0 )
                {
                    continue;
                }

                //
                // If it's mid-string, we want to remove the backspace and whatever
                // is ahead of the backspace character.
                //
                $finalStrPos--;
            }
            else
            {
                //
                // If this isn't a delete character, then just copy it down
                // to the new string we're processing in-place.
                //
                $client->readBuffer[$finalStrPos] = $client->readBuffer[$i];
                $finalStrPos++;
            }
        }
        //
        // Set the string to the new, modified length.
        //
        $client->readBuffer = substr( $client->readBuffer, 0, $finalStrPos );

        //**************************************************************************
        // Route into the command processing module.
        //**************************************************************************
        $this->LsLog( "RX::RAW=".$client->readBuffer, $this->msgLogLevel );

        $err = $this->ServerSpecificProcessIncomingMessage(
                        $client,
                        ($bIsConsoleMsg===true)?true:false );

        return( $err );
    }

} /* End LsServer Class */
