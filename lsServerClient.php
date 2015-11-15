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
**  Filename:       lsServerClient.php
**  Project:        EcoSphere
**  Author:         Barry Robertson (barry@logixstorm.com)
**  Created:        11-03-2014
**
**  Abstract:       Provides a basic structure to represent a connection from
**  a remote peer to the LsServer instance.
**
**
**  Change Log:
**  Barry Robertson; 11-11-2014; Initial version.
**  <engr. name>; <date>; <reason>
*****************************************************************************/

//********************************************************
//* Use Modules
//********************************************************

//********************************************************
//* Include/Require Files
//********************************************************
require_once( "lsErrno.php" );

//********************************************************
//* Name Space
//********************************************************

//****************************************************************************
//************************ Classes *******************************************
//****************************************************************************

/**
 *******************************************************************************
 * Class: LsClient
 *
 * @abstract The client class is used to maintain individual connections to
 * the EcoSphere Server.  A simple message buffer is maintained internally to
 * track incoming message fragments and then pass those into the server.
 *
 ********************************************************************************/
class LsClient
{
    //*******************************************************
    //*******************************************************
    // Public Members
    //*******************************************************
    //*******************************************************
    public $readBuffer = "";

    //*******************************************************
    //*******************************************************
    // Private Members
    //*******************************************************
    //*******************************************************
    private $lsServer       = null;
    private $peerHostname   = "unknown";
    private $peerIp         = "0.0.0.0";
    private $peerPortNumber = "0";
    private $conn           = null;
    private $connEstTime    = "LS_NOT_SET";
    private $sysErrMsg      = "";

    private $writeBlockSize = LsClient::WRITE_BLOCK_SIZE;
    private $bytesToWrite   = 0;
    private $bytesWritten   = 0;
    private $writeOpCount   = 0;
    private $writeBuffer    = "";

    private $bBannerDspy    = false;
    private $bMsgReady      = false;
    private $bBlocking      = true;
    private $bInuse         = false;

    //
    // Message transmission members
    //
    private $txid = 1;
    private $rxid = 1;

    private $stats = array(
        LsClient::CMD_COUNT     => 0,
        LsClient::READ_BYTES    => 0,
        LsClient::WRITE_BYTES   => 0
    );

    //*******************************************************
    //*******************************************************
    // Constants
    //*******************************************************
    //*******************************************************
    const CMD_COUNT         = 'CMD_COUNT';
    const READ_BYTES        = 'READ_BYTES';
    const WRITE_BYTES       = 'WRITE_BYTES';

    const WRITE_BLOCK_SIZE  = 4096;

    //*******************************************************
    //*******************************************************
    // Public Methods
    //*******************************************************
    //*******************************************************
    //
    // Getters & Setters
    //
    public function PeerHostname( $v=null )     { if($v!=null){$this->peerHostname=$v;}
                                                    return($this->peerHostname); }
    public function PeerIp( $v=null )           { if($v!=null){$this->peerIp=$v;}
                                                    return($this->peerIp); }
    public function PeerPortNumber( $v=null )   { if($v!=null){$this->peerPortNumber=$v;}
                                                    return($this->peerPortNumber); }
    public function GetLsServer()               { return($this->lsServer); }
    public function Conn( )                     { return($this->conn); }
    public function SetConn( $conn )            { $this->conn = $conn; }
    public function ConnTime( $v=null )         { if($v!=null){$this->connEstTime=$v;}
                                                    return($this->connEstTime); }
    public function SetBlocking( $v )
    {
        $this->bBlocking = $v;
        stream_set_blocking( $this->conn , $v );
    }

    public function GetWriteBlockSize( )        { return( $this->writeBlockSize ); }
    public function SetWriteBlockSize( $blockSize=0 )
    {
        if( $blockSize===0 )
        {
            $this->writeBlockSize = LsClient::WRITE_BLOCK_SIZE;
        }
        else
        {
            $this->writeBlockSize = $blockSize;
        }
        return( $this->writeBlockSize );
    }

    public function BannerDspy( $v=null )       { if($v!=null){$this->bBannerDspy=$v;}
                                                    return($this->bBannerDspy); }
    public function IsInuse( )                  { return( $this->bInuse ); }
    public function IsMsgReady( )               { return($this->bMsgReady); }
    public function ClrMsgReady( )              { $this->bMsgReady = false; }

    public function Msg( $v=null )              { if($v!=null){$this->readBuffer=$v;}
                                                    return($this->readBuffer); }
    public function GetStatsArray( )            { return($this->stats); }
    public function GetStatsValue( $element )   { return( $this->stats[$element] ); }
    public function SetStatsValue( $element, $value ) { $this->stats[$element] = $value; }

    /**
     *
     * @param type $lsServer This is the server that the connection is associated with.
     * @param type $connection Filestream to the client from the server.
     * @param type $peerIp Ip address of the client connecting to the server.
     * @param type $peerPortNumber Port number the client has used to connect from.
     */
    public function SetupNewConnection( $lsServer, $connection, $peerIp, $peerPortNumber )
    {
        $this->bInuse = true;
        $this->lsServer = $lsServer;
        $this->SetConn( $connection );
        $this->PeerIp( $peerIp );
        $this->PeerPortNumber( $peerPortNumber );
        $this->PeerHostname( gethostbyaddr($peerIp) );
        $this->ConnTime( date('n/j/Y g:i a') );

        $this->stats = array(
            LsClient::CMD_COUNT     => 0,
            LsClient::READ_BYTES    => 0,
            LsClient::WRITE_BYTES   => 0
        );
    }

    public function TearDownConnection( )
    {
        $this->lsServer->LsLog(
                    "Tearing down ".$this->peerIp.":".$this->peerPortNumber, KLogger::INFO );
        fclose( $this->conn );

        $this->peerHostname = "unknown";
        $this->peerIp = "0.0.0.0";
        $this->peerPortNumber = "0";
        $this->conn = null;
        $this->connEstTime = "LS_NOT_SET";

        $this->bBannerDspy = false;
        $this->bMsgReady = false;
        $this->bBlocking = true;
        $this->bInuse = false;

        $this->stats = array(
            LsClient::CMD_COUNT     => 0,
            LsClient::READ_BYTES    => 0,
            LsClient::WRITE_BYTES   => 0
        );
    }

    private function CheckSystemError( )
    {
        $errInfo = error_get_last();

        $this->sysErrMsg = "";

        if( isset($errInfo['message']) )
        {
            $this->sysErrMsg = $errInfo['message'];
            return( EIO );
        }

        return( EOK );
    }

    private function WriteInit( $bytesToWrite )
    {
        $this->writeBuffer = "";
        $this->bytesToWrite = $bytesToWrite;
        $this->bytesWritten = 0;
        $this->writeOpCount = 0;
    }

    private function WriteAdvance( $bytesWritten )
    {
        $this->stats['WRITE_BYTES'] += $bytesWritten;
        $this->bytesWritten += $bytesWritten;
        $this->writeOpCount++;
    }

    private function WriteComplete()
    {
        return( $this->bytesWritten == $this->bytesToWrite );
    }

    private function WriteSetForContiuation( $buffer )
    {
        $this->writeBuffer = $buffer;
    }

    public function FlushConnection()
    {
        $retries = 10;

        while( $retries-- && fflush( $this->conn ) === false )
        {
            echo "delay1 for fflush()...\n";
            usleep( 100000 );
        }

        //
        // Store the last system error msg.
        //
        $this->CheckSystemError();

        return( ($retries==0)?EIO:EOK );
    }

    public function ContinueWrite( &$pBytesWritten=null )
    {
        //
        // If there's nothing pending, just return immediately.
        //
        if( $this->WriteComplete() == true )
        {
            return( EOK );
        }

        if( $pBytesWritten !== null )
        {
            $pBytesWritten = 0;
        }

        LsLogDebug( LsSys::LS_LOG_SERVER,
                "Write[".$this->writeOpCount."]=".min($this->writeBlockSize, ($this->bytesToWrite-$this->bytesWritten))." for ".$this->bytesWritten.
                "/".$this->bytesToWrite." bytes to IP=".$this->peerIp );

        $bytesWritten = @fwrite( $this->conn,
                          substr($this->writeBuffer, $this->bytesWritten),
                          min($this->writeBlockSize, ($this->bytesToWrite-$this->bytesWritten)) );
        if( $bytesWritten === false )
        {
            LsLogError( LsSys::LS_LOG_SERVER,
                "Write[".$this->writeOpCount."]=".$bytesWritten." Flush Failed! for ".$this->bytesWritten.
                "/".$this->bytesToWrite." bytes to IP=".$this->peerIp." Returning err=EIO" );
            return( EIO );
        }

        //
        // We need to ensure this buffer is flushed because we can
        // easily overrun the tcp connection and that seems to cause
        // big problems for php socket streams...
        //
        $err = $this->FlushConnection();
        if( $err != EOK )
        {
            LsLogError( LsSys::LS_LOG_SERVER,
                "Write[".$this->writeOpCount."]=".$bytesWritten." Flush Failed! for ".$this->bytesWritten.
                "/".$this->bytesToWrite." bytes to IP=".$this->peerIp." Returning err=ECONTIO" );

            return( EIO );
        }

        //
        // Increment the number of bytes written.
        //
        $this->WriteAdvance( $bytesWritten );

        //
        // Return the number of bytes written in this pass to the
        // caller if they provide a reference.
        //
        if( $pBytesWritten != null )
        {
            $pBytesWritten = $bytesWritten;
        }

        //**************************************************************************
        // If we've written all the bytes to the connection, then return EOK
        //**************************************************************************
        if( $this->WriteComplete() )
        {
            LsLogDebug( LsSys::LS_LOG_SERVER,
                "Write[".$this->writeOpCount."]=".$bytesWritten." Complete   ".$this->bytesWritten.
                "/".$this->bytesToWrite." bytes to IP=".$this->peerIp." Returning err=EOK" );

            return( EOK );
        }

        LsLogDebug( LsSys::LS_LOG_SERVER,
                "Write[".$this->writeOpCount."]=".$bytesWritten." Continuing ".$this->bytesWritten.
                "/".$this->bytesToWrite." bytes to IP=".$this->peerIp." Returning ECONTIO" );

        //
        // Return that we need to continue writing on this connection.
        //
        return( ECONTIO );
    }

    public function WriteBytes( $buffer, $numBytes=0 )
    {
        //
        // Issue the write and override the write block size if the caller
        // sets a non-zero value.
        //
        if( $numBytes===0 )
        {
            $numBytes = min( $this->writeBlockSize, strlen($buffer) );
        }

        $this->WriteInit( strlen($buffer) );

        $bytesWritten = @fwrite( $this->conn, $buffer, $numBytes );
        if( $bytesWritten === false )
        {
            $this->WriteSetForContiuation( $buffer );

            LsLogInfo( LsSys::LS_LOG_SERVER,
                "Write returned false for $numBytes bytes to IP=".$this->peerIp." Returning ECONTIO" );
            return( EIO );
        }

        //
        // We need to ensure this buffer is flushed because we can
        // easily overrun the tcp connection and that seems to cause
        // big problems for php socket streams...
        //
        $err = $this->FlushConnection();
        if( $err != EOK )
        {
            $this->WriteSetForContiuation( $buffer );

            LsLogError( LsSys::LS_LOG_SERVER,
                "Write[".$this->writeOpCount."]=".$bytesWritten." Flush Failed! for ".$this->bytesWritten.
                "/".$this->bytesToWrite." bytes to IP=".$this->peerIp." Returning err=ECONTIO" );
            return( EIO );
        }

        $this->WriteAdvance( $bytesWritten );

        //
        // If we weren't able to write everything, then cache the buffer
        // and return continue i/o status.
        //
        if( $this->WriteComplete() == false )
        {
            $this->WriteSetForContiuation( $buffer, $bytesWritten );

            LsLogDebug( LsSys::LS_LOG_SERVER,
                "Write[".$this->writeOpCount."]=".$bytesWritten." Needs Continuation ".$this->bytesWritten.
                "/".$this->bytesToWrite." bytes to IP=".$this->peerIp." Returning ECONTIO" );

            return( ECONTIO );
        }

        LsLogDebug( LsSys::LS_LOG_SERVER,
                "Write[".$this->writeOpCount."]=".$bytesWritten." Complete   ".$this->bytesWritten.
                "/".$this->bytesToWrite." bytes to IP=".$this->peerIp." Returning err=EOK" );

        return( EOK );
    }

    public function ReadBytes( $numBytes )
    {
        $msg = fread( $this->conn, $numBytes );
        $bytesRead = strlen( $msg );

        if( $msg === false || $bytesRead==0 )
        {
            return( 0 );
        }

        $this->stats[LsClient::READ_BYTES] += $bytesRead;

        //$ordinalVal = ord( $msg );
        //echo "OrdinalValue=".$ordinalVal."\n";

        //
        // Append the message fragment to the client's message buffer.
        //
        $this->readBuffer .= $msg;

        //echo "SERVER:message='".$this->message."\n";

        //
        // This may change in the future to detect EOF-type terminating strings.
        //
        if( strpos($msg,"\n") !== FALSE )
        {
            $this->bMsgReady = true;
        }

        return( $bytesRead );
    }

    public function ReadMessage()
    {
        $this->bMsgReady = false;
        $this->readBuffer = "";

        do
        {
            $bytesRead = $this->ReadBytes( 4096 );
            usleep( LsServer::LS_SERVER_POLL_USECS );

        }while( $this->bMsgReady == false );

        $result = trim( $this->readBuffer );
        $result = rtrim( $result );

        return( $result );
    }

    public function ProgramMessageResponseHeader( &$msg, $statusCode )
    {
        //
        // Advance the RXID
        //
        $this->rxid = ($this->rxid + 1) & 0xFFFFFFFF;

        //
        // We flip-flop the SID and DID for the response.
        //
        $sid = $msg->JduSID();
        $did = $msg->JduDID();

        $msg->JduRspCode( $statusCode );
        $msg->SetCtrlAsRsp();
        $msg->JduSID( $did ); // flip SID and DID
        $msg->JduDID( $sid );
        $msg->JduRxid( $this->rxid );
    }

}
