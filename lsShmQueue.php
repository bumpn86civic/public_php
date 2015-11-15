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
**  Filename:       lsShmQueue.php
**  Project:        EcoSphere
**  Author:         Barry Robertson (barry@logixstorm.com)
**  Created:        01-12-2015
**
**  Abstract:       Simple queue that uses flock() for coordination in shared
**  memory segments.
**
**
**  Change Log:
**  Barry Robertson; 01-12-2015; Initial version.
**  <engr. name>; <date>; <reason>
*****************************************************************************/

//**************************************************************************
//* Use Modules
//**************************************************************************

//**************************************************************************
//* Include/Require Files
//**************************************************************************
require_once( "lsUtil.php" );

//**************************************************************************
//* Name Space
//**************************************************************************

//**************************************************************************
//* Public Functions
//**************************************************************************

//**************************************************************************
//************************ Classes *****************************************
//**************************************************************************

class LsIpcMsgQueue
{
    private $mQ;
    private $msgQid;
    private $queueDepth;
    private $maxMsgSize     = LsIpcMsgQueue::DFLT_MSG_SIZE;
    private $lockFile       = "";
    private $bIsShmQ        = false;

    const DFLT_MSG_SIZE     = 4096;
    const DFLT_QUEUE_DEPTH  = 32;

    const QFULL_BACK_OFF_US = 100000;
    const RECV_WAIT_US      = 50000;

    const MAY_BLOCK         = true;
    const NO_BLOCKING       = false;

    public function __construct( $msgQid, $queueDepth, $maxMsgSize, $bUseShmQ=false )
    {
        $this->msgQid = $msgQid;
        $lockFile = LsSys::GetEcosphereIpcLockFile(LsSys::ECO_IPC_QUEUE_ID);

        //
        // Just use SVR4 IPC message queues if it's defined.
        //
        if( @function_exists(msg_get_queue) && $bUseShmQ==false )
        {
            $this->mQ = msg_get_queue( $msgQid );
        }
        else
        {
            $this->bIsShmQ = true;

            $this->mQ = LsShmQueue::Factory(
                            $msgQid,
                            $queueDepth,
                            $queueDepth*$maxMsgSize,
                            $lockFile );
        }
    }

    public function SendRcv( &$awsSendArray, &$awsRecvArray )
    {
        $err = $this->Send( $awsSendArray );
        if( $err != EOK )
        {
            return( $err );
        }

        usleep(200000);

        $err = $this->Receive( $awsRecvArray );

        return( $err );
    }

    public function Send( &$awsMsgArray, $bMayBlock=true )
    {
        $msgString = LsDb::AwsToJson( $awsMsgArray );
        if( strlen($msgString) == 0 )
        {
            return( EBADFORM );
        }

        if( $this->bIsShmQ == true )
        {
            while( true )
            {
                $err = $this->mQ->Enqueue( $msgString );
                if( $err == EOK )
                {
                    return( $err );
                }

                if( $bMayBlock && $err == EQFULL )
                {
                    echo "QFULL... blocking.\n";
                    usleep( LsIpcMsgQueue::QFULL_BACK_OFF_US );
                    continue;
                }
            }

            return( $err );
        }
        else
        {
            $result = msg_send( $this->mQ,
                                12808,
                                $msgString,
                                false,
                                $bMayBlock );
            if( $result != true )
            {
                return( EIO );
            }
        }
    }

    public function Receive( &$awsMsgArray, $bMayBlock=true )
    {
        $awsMsgArray = array();

        if( $this->bIsShmQ == true )
        {
            //
            // Just poll until we get a message.
            // (unless we've been told not to 'block'...)
            //
            while( true )
            {
                if( $this->mQ->IsEmpty() == false
                    ||
                    $this->mQ->GenerationChanged( true ) === true )
                {
                    $qe = $this->mQ->Dequeue();
                    if( $qe === null )
                    {
                        return( EFAULT );
                    }

                    $awsMsgArray = LsDb::JsonToAws( $qe->msg );
                    if( $awsMsgArray === null )
                    {
                        return( EBADFORM );
                    }

                    return( EOK );
                }

                if( $bMayBlock == false )
                {
                    return( ENOENT );
                }

                usleep( LsIpcMsgQueue::RECV_WAIT_US );
            }
        }
        else
        {
            $result = msg_receive( $this->mQ,
                            0,          // zero indicates that we just take the next off the queue.
                            $msgtype,
                            $this->maxMsgSize,
                            $ipcMsgString,
                            false,      // false indicates don't unserialize the message payload.
                            ($bMayBlock==false)?MSG_IPC_NOWAIT:0 );
            if( $result != true )
            {
                return( EIO );
            }

            $awsMsgArray = LsDb::JsonToAws( $ipcMsgString );
            if( $awsMsgArray === null )
            {
                return( EBADFORM );
            }

        }

        return( EOK );
    }
}





class LsQE
{
    public $offset = 0;
    public $qidx = 0;
    public $msgOffset = 0;
    public $msgLen = 0;
    public $msg = "";

    public function ToJson()
    {
        $jsonArray = array(
                'qidx'      => sprintf( "%08X", $this->qidx ),
                'msgOffset' => sprintf( "%08X", $this->msgOffset ),
                'msgLen'    => sprintf( "%08X", $this->msgLen ),
                'offset'    => sprintf( "%08X", $this->offset )
                );

        return( LsJson::CreateList( $jsonArray ) );
    }

    public function FromJson( $json )
    {
        $a = json_decode( $json, true );
        if( $a === false )
        {
            return( EBADFORM );
        }

        if( !isset($a['qidx']) ||
            !isset($a['offset']) ||
            !isset($a['msgOffset']) ||
            !isset($a['msgLen']) )
        {
            return( EBADFORM );
        }

        $this->qidx      = hexdec( $a['qidx'] );
        $this->offset    = hexdec( $a['offset'] );
        $this->msgOffset = hexdec( $a['msgOffset'] );
        $this->msgLen    = hexdec( $a['msgLen'] );

        return( EOK );
    }

    static public function GetSize()
    {
        $qe = new LsQE;
        return( strlen( $qe->ToJson() ) );
    }
}

/**
 * @abstract Creates a shared memory queue using the provided path to a lock file.
 */
class LsShmQueue
{
    //*************************************************************************
    //*************************************************************************
    // Private Members
    //*************************************************************************
    //*************************************************************************
    private $generation = 0;

    //
    // These fields make up the stored portion of the queue.
    // This is referred to as the 'header'.
    //
    private $version = "1.0";
    private $inPtr = 0;
    private $outPtr = 0;
    private $queueDepth = 0;
    private $shmSize = -1;
    private $shmKey = -1;
    private $qeSize = -1;
    private $lockFilename = "";
    private $bIsLocking = false;
    private $crc32 = "00000000";

    private $msgMemSize = 4096;
    private $msgMemInPtr = 0;
    private $msgMemOutPtr = 0;

    //
    // These are runtime dynamic members.
    //
    private $lockFd = -1;
    private $shmHandle = -1;
    private $bIsLoaded = false;
    private $msgMemOffset = 0;
    private $qMemOffset = LsShmQueue::HEADER_SIZE;




    //*************************************************************************
    //*************************************************************************
    // Protected Members
    //*************************************************************************
    //*************************************************************************

    //*************************************************************************
    //*************************************************************************
    // Public Members
    //*************************************************************************
    //*************************************************************************

    //*************************************************************************
    //*************************************************************************
    // Constants
    //*************************************************************************
    //*************************************************************************
    const GENERATION_OFFSET = 0;
    const GENERATION_SIZE = 8;
    const HEADER_OFFSET = 8;
    const HEADER_SIZE = 1024;
    const BLANK_CRC = '00000000';

    //*************************************************************************
    //*************************************************************************
    // Static Public Methods
    //*************************************************************************
    //*************************************************************************

    //*************************************************************************
    //*************************************************************************
    // Constructor & Abstract (Virtual) Methods
    //*************************************************************************
    //*************************************************************************

    //*************************************************************************
    //*************************************************************************
    // Public Methods
    //*************************************************************************
    //*************************************************************************
    public static function Factory( $shmKey, $qDepth, $msgMemSize, $lockFile="" )
    {
        $p = new LsShmQueue;

        $err = $p->SetLockFile( $lockFile );
        if( $err != EOK )
        {
            echo "Failure to open lock file!\n";
            return( false );
        }

        $p->SetQueueDepth($qDepth, $msgMemSize);

        $err = $p->OpenShm( $shmKey );
        if( $err != EOK )
        {
            $p->Close();
            echo "Failure to open shmem!\n";
            return( false );
        }

        //
        // Attempt to load the object from memory.
        //
        $p->LoadFromMemory();

        return $p;
    }

    public function ResetLocalGeneration()
    {
        $this->generation = 0;
    }

    public function GenerationChanged( $bLockHeld=false )
    {
        if( $bLockHeld == false )
        {
            $err = $this->ReadLock();
            if( $err != EOK )
            {
                return( -1 );
            }
        }

        $generation = shmop_read( $this->shmHandle, LsShmQueue::GENERATION_OFFSET, LsShmQueue::GENERATION_SIZE );
        if( $generation === false )
        {
            if( $bLockHeld == false ){ $this->Unlock(); }
            return( -1 );
        }

        if( $bLockHeld == false ){ $this->Unlock(); }

        if( $this->generation != hexdec($generation) )
        {
            if( $bLockHeld == false ){ $this->Unlock(); }
            return true;
        }

        return false;
    }

    public function Enqueue( $msg )
    {
        $err = $this->WriteLock();
        if( $err != EOK )
        {
            return( $err );
        }

        //***********************************************
        // Check the generation tag for a change.
        //***********************************************
        if( $this->GenerationChanged(true) == true )
        {
            //
            // Load the shared object queue header.
            //
            $err = $this->ReadQueueHeader( true );
            if( $err != EOK )
            {
                $this->Unlock();
                return null;
            }
        }

        if( $this->IsFull() )
        {
            $this->Unlock();
            return( EQFULL );
        }

        $msgIndex = $this->GetFreeMsgMemIndex( strlen($msg) );
        if( $msgIndex == -1 )
        {
            $this->Unlock();
            return( EQFULL );
        }

        $qidx = $this->inPtr;
        $this->inPtr = ($this->inPtr + 1) % $this->queueDepth;

        $qe = new LsQE;
        $qe->msgLen = strlen( $msg );
        $qe->msgOffset = $this->MsgMemIdxToOffset($msgIndex);
        $qe->offset = $this->QueueEntryIdxToOffset( $qidx );
        $qe->qidx = $qidx;

        //*******************************************************
        // Write the queue entry descriptor.
        //*******************************************************
        $err = $this->WriteQueueEntry($qe, true);
        if( $err != EOK )
        {
            $this->SetMsgMemIndex($msgIndex);
            $this->inPtr = $qidx;
            $this->Unlock();
            return( $err );
        }

        //
        // Write the message memory.
        //
        $err = $this->WriteMsg( $msg, $qe->msgOffset );
        if( $err != EOK )
        {
            $this->SetMsgMemIndex($msgIndex);
            $this->inPtr = $qidx;
            $this->Unlock();
            return( $err );
        }

        //*******************************************************
        // Update the queue header.
        //*******************************************************
        $err = $this->WriteQueueHeader( true );
        if( $err != EOK )
        {
            $this->SetMsgMemIndex($msgIndex);
            $this->inPtr = $qidx;
            $this->Unlock();
            return( $err );
        }

        $this->Unlock();
        return( EOK );
    }

    public function IsEmpty()
    {
        return( ($this->inPtr==$this->outPtr)?true:false );
    }

    public function IsFull()
    {
        return( (($this->inPtr + 2) % $this->queueDepth == $this->outPtr)?true:false );
    }

    public function Dequeue()
    {
        $this->WriteLock();

        //
        // Check the generation tag for a change.
        //
        if( $this->GenerationChanged(true) == true )
        {
            //
            // Load the shared object queue header.
            //
            $err = $this->ReadQueueHeader( true );
            if( $err != EOK )
            {
                $this->Unlock();
                return null;
            }
        }

        //
        // Check to see if the queue is empty.
        //
        if( $this->IsEmpty() )
        {
            $this->Unlock();
            return null;
        }

        $qe = $this->ReadQueueEntry( $this->outPtr, true );
        if( $qe === null )
        {
            $this->Unlock();
            return null;
        }

        $outPtr = $this->outPtr;

        $this->outPtr = ($this->outPtr + 1) % $this->queueDepth;

        //*******************************************************
        // Update the queue header.
        //*******************************************************
        $err = $this->WriteQueueHeader( true );
        if( $err != EOK )
        {
            $this->outPtr = $outPtr;
            $this->Unlock();
            return null;
        }

        $this->Unlock();
        return $qe;
    }



    public function SetLockFile( $lockFile="" )
    {
        if( $lockFile === "" )
        {
            $this->bIsLocking = false;
            return( EOK );
        }

        $this->bIsLocking = true;

        //
        // Open the lockfile as w+ and immediately close it and reopen as 'r'.
        //
        $fd = fopen( $lockFile, 'w+' );
        if( $fd === false )
        {
            return( EIO );
        }

        //
        // Close it and reopen and read-only.
        //
        fclose( $fd );

        $this->lockFd = fopen( $lockFile, 'r' );
        if( $this->lockFd === false )
        {
            $this->lockFd = -1;
            return( EIO );
        }

        $this->lockFilename = $lockFile;

        return( EOK );
    }

    public function SetQueueDepth( $qDepth, $msgMemSize )
    {
        if( is_string($qDepth) )
        {
            $qDepth = intval($qDepth);
        }

        if( is_string($msgMemSize) )
        {
            $msgMemSize = intval($msgMemSize);
        }

        $this->queueDepth = $qDepth;
        $this->msgMemSize = $msgMemSize;

        //
        // Set the queue item entry size.
        //
        $this->qeSize = LsQE::GetSize();

        $this->msgMemOffset = LsRoundUp( LsShmQueue::HEADER_SIZE + $this->qeSize * $qDepth, 1024 );

        $this->shmSize = LsRoundUp( $this->msgMemOffset + $this->msgMemSize, 1024 );
    }

    public function OpenShm( $key )
    {
        //
        // First attempt to create a new shm key.
        //
        $this->shmHandle = @shmop_open( $key, 'n', 0666, $this->shmSize );
        if( $this->shmHandle === false )
        {
            //
            // Attempt to open it with write permissions if it already exists.
            //
            $this->shmHandle = @shmop_open( $key, 'w', 0666, $this->shmSize );
            if( $this->shmHandle === false )
            {
                $this->shmHandle = -1;
                return( EIO );
            }
        }

        $this->shmKey = $key;

        return( EOK );
    }

    public function LoadFromMemory()
    {
        echo "Loading from memory!!\n";

        if( $this->bIsLoaded == true )
        {
            return( EOK );
        }

        if( $this->lockFd==-1 || $this->shmHandle==-1 )
        {
            return( EPERM );
        }

        if( $this->qeSize == -1 )
        {
            return( EPERM );
        }

        //
        // Read in the queue header structure.
        //
        $err = $this->WriteLock();

        $err = $this->ReadQueueHeader( true );
        if( $err != EOK )
        {
            //
            // Check for no-entry or bad crc and if
            // we get those errors, rewrite the blank header.
            //
            if( $err==ENOENT || $err==EBADCRC )
            {
                $err = $this->WriteQueueHeader( true );
                if( $err != EOK )
                {
                    LsLogError( 'Failure to write new queue header' );
                }
            }

            if( $err == EOK )
            {
                $this->bIsLoaded = true;
            }

            $this->Unlock();

            return( $err );
        }

        //
        // Unlock and flag as loaded.
        //
        $this->Unlock();
        $this->bIsLoaded = true;

        //
        // If the record exists in memory, we want to force our generation
        // to be out of sync with the current generation to make any
        // work get picked up if in polling mode.
        //
        $this->generation = ($this->generation-1) % hexdec(0xFFFFFF);

        print_r( $this );

        return( EOK );
    }

    public function Close()
    {
        if( $this->lockFd != -1 )
        {
            fclose( $this->lockFd );
            $this->lockFd = -1;
        }

        if( $this->shmHandle != -1 )
        {
            shm_close( $this->shmHandle );
            $this->shmHandle = -1;
        }
    }


    //*************************************************************************
    //*************************************************************************
    // Protected Methods
    //*************************************************************************
    //*************************************************************************

    //*************************************************************************
    //*************************************************************************
    // Private Methods
    //*************************************************************************
    //*************************************************************************

    private function SetMsgMemIndex( $idx )
    {
        $this->msgMemInPtr = $idx;
    }

    private function GetFreeMsgMemIndex( $size )
    {
        if( is_string($size) )
        {
            $size = intval($size);
        }

        if( $this->msgMemInPtr + $size > $this->msgMemSize )
        {
            //
            // Check to see if there's room at the beginning of the queue.
            //
            if( $size > $this->msgMemOutPtr )
            {
                return( -1 );
            }

            //
            // There's room at the beginning!
            //
            $this->msgMemInPtr = $size;
            return 0;
        }

        //
        // There's room in the queue.
        //
        $position = $this->msgMemInPtr;

        $this->msgMemInPtr += $size;

        return $position;
    }

    private function MsgMemIdxToOffset( $idx )
    {
        return( $this->msgMemOffset + $idx );
    }

    private function MsgMemOffsetToIdx( $offset )
    {
        return( $offset - $this->msgMemOffset );
    }

    private function QueueEntryIdxToOffset( $idx )
    {
        return( $this->qMemOffset + ($idx * $this->qeSize) );
    }


    private function ReadQueueEntry( $idx, $bLockHeld=false )
    {
        if( $bLockHeld == false )
        {
            $err = $this->ReadLock();
            if( $err != EOK )
            {
                return( null );
            }
        }

        //
        // Compute the offset.
        //
        $offset = $this->qMemOffset + ($idx * $this->qeSize);

        $json = shmop_read( $this->shmHandle, $offset, $this->qeSize );
        if( $json === false )
        {
            if( $bLockHeld == false ){ $this->Unlock(); }
            return( null );
        }

        $qe = new LsQE;
        $err = $qe->FromJson( $json );
        if( $err != EOK )
        {
            if( $bLockHeld == false ){ $this->Unlock(); }
            return null;
        }

        //
        // Read in the message memory.
        //
        $qe->msg = $this->ReadMsg( $qe->msgOffset, $qe->msgLen );
        if( $qe->msg === null )
        {
            if( $bLockHeld == false ){ $this->Unlock(); }
            return null;
        }

        //
        // Update the message index.
        //
        $this->msgMemOutPtr = $this->MsgMemOffsetToIdx($qe->msgOffset) + $qe->msgLen;

        if( $bLockHeld == false ){ $this->Unlock(); }

        return $qe;
    }

    private function WriteQueueEntry( $qe, $bLockHeld=false )
    {
        if( $bLockHeld == false )
        {
            $err = $this->WriteLock();
            if( $err != EOK )
            {
                return( $err );
            }
        }

        $offset = $this->QueueEntryIdxToOffset( $qe->qidx );

        $json = $qe->ToJson();

        $result = shmop_write( $this->shmHandle, $json, $offset );
        if( $result === false )
        {
            if( $bLockHeld == false ){ $this->Unlock(); }
            return( EIO );
        }

        if( $bLockHeld == false ){ $this->Unlock(); }

        return( EOK );
    }

    private function WriteMsg( $msg, $offset )
    {
        $result = shmop_write( $this->shmHandle, $msg, $offset );
        if( $result === false )
        {
            return( EIO );
        }
        return( EOK );
    }

    private function ReadMsg( $offset, $size )
    {
        $msg = shmop_read( $this->shmHandle, $offset, $size );
        if( $msg === false )
        {
            return null;
        }

        return $msg;
    }



    private function WriteQueueHeader( $bLockHeld=false )
    {
        if( $this->shmHandle == -1 )
        {
            return( EPERM );
        }

        if( $bLockHeld == false )
        {
            $err = $this->WriteLock();
            if( $err != EOK )
            {
                return( $err );
            }
        }

        $jsonArray = array(
            'version' => $this->version,
            'inPtr' => $this->inPtr,
            'outPtr' => $this->outPtr,
            'queueDepth' => $this->queueDepth,
            'shmSize' => $this->shmSize,
            'qeSize' => $this->qeSize,
            'lockFilename' => $this->lockFilename,
            'bIsLocking' => $this->bIsLocking,
            'msgMemSize' => $this->msgMemSize,
            'msgMemInPtr' => $this->msgMemInPtr,
            'msgMemOutPtr' => $this->msgMemOutPtr,
            'crc32' => LsShmQueue::BLANK_CRC,
        );

        $json = LsJson::CreateList( $jsonArray );

        //
        // Create a json string with a blank crc.
        // Then we compute crc aross that string and store it.
        //
        $crc32 = sprintf( "%08X", crc32($json) );


        $jsonArray = array(
            'version' => $this->version,
            'inPtr' => $this->inPtr,
            'outPtr' => $this->outPtr,
            'queueDepth' => $this->queueDepth,
            'shmSize' => $this->shmSize,
            'qeSize' => $this->qeSize,
            'lockFilename' => $this->lockFilename,
            'bIsLocking' => $this->bIsLocking,
            'msgMemSize' => $this->msgMemSize,
            'msgMemInPtr' => $this->msgMemInPtr,
            'msgMemOutPtr' => $this->msgMemOutPtr,
            'crc32' => $crc32,
        );

        //
        // prepend the generation to json output so we have a faster
        // polling operation for queue changes if we need it.
        //
        $this->generation = ($this->generation + 1) % hexdec(0xFFFFFF);
        $generation = sprintf( "%08X", $this->generation );

        $json = $generation.LsJson::CreateList( $jsonArray );
        //echo "  generation=$this->generation\n";

        $json .= str_repeat( ' ', (LsShmQueue::HEADER_SIZE-strlen($json)) - 1 );

        //
        // Write it to the shared memory.
        //
        $result = shmop_write( $this->shmHandle, $json, 0 );
        if( $result === false )
        {
            echo "Failure to write!!\n";
            $err = EIO;
        }
        else
        {
            $this->bIsLoaded = true;
            $err = EOK;
        }

        if( $bLockHeld == false )
        {
            $this->Unlock();
        }

        echo "Updated the object!!\n";

        return( $err );
    }



    private function ReadQueueHeader( $bLockHeld=false )
    {
        if( $bLockHeld == false )
        {
            $err = $this->ReadLock();
            if( $err != EOK )
            {
                return( $err );
            }
        }

        //
        // Load the header bytes.
        //
        $headerJson = shmop_read( $this->shmHandle, 0, LsShmQueue::HEADER_SIZE );
        if( $headerJson === false )
        {
            if( $bLockHeld == false ){ $this->Unlock(); }
            return( EIO );
        }

        //
        // Trim any trailing whitespace since we're reading in a larger block
        // than the structure actually takes up.
        //
        $headerJson = rtrim( $headerJson );

        //
        // The first 8 characters are the generation counter.
        //
        $generation = substr( $headerJson,
                              LsShmQueue::GENERATION_OFFSET,
                              LsShmQueue::GENERATION_SIZE );
        $this->generation = hexdec( $generation );

        //
        // Pull out the record portion of the storage.
        //
        $headerJson = substr( $headerJson, LsShmQueue::HEADER_OFFSET );

        //
        // Decode the json structure.
        //
        $headerArray = json_decode( $headerJson, true );
        if( $headerArray === null )
        {
            //
            // This structure isn't valid in shared memory, return ENOENT.
            //
            if( $bLockHeld == false ){ $this->Unlock(); }
            return( ENOENT );
        }

        //
        // Make sure all the fields exist.
        //
        if( !isset($headerArray['version']) ||
            !isset($headerArray['inPtr']) ||
            !isset($headerArray['outPtr']) ||
            !isset($headerArray['queueDepth']) ||
            !isset($headerArray['shmSize']) ||
            !isset($headerArray['qeSize']) ||
            !isset($headerArray['lockFilename']) ||
            !isset($headerArray['bIsLocking']) ||
            !isset($headerArray['msgMemSize']) ||
            !isset($headerArray['msgMemInPtr']) ||
            !isset($headerArray['msgMemOutPtr']) ||
            !isset($headerArray['crc32']) )
        {
            if( $bLockHeld == false ){ $this->Unlock(); }
            return( ENOENT );
        }

        //
        // Compute crc and validate.
        //
        $crcInMemory = $headerArray['crc32'];

        $headerArray['crc32'] = LsShmQueue::BLANK_CRC;

        $json = LsJson::CreateList($headerArray);
        $crcComputed = sprintf( "%08X", crc32($json) );

        if( $crcInMemory !== $crcComputed )
        {
            if( $bLockHeld == false ){ $this->Unlock(); }
            return( EBADCRC );
        }

        //
        // Load the values into the object.
        //
        $this->version =        $headerArray['version'];
        $this->inPtr =          $headerArray['inPtr'];
        $this->outPtr =         $headerArray['outPtr'];
        $this->queueDepth =     $headerArray['queueDepth'];
        $this->shmSize =        $headerArray['shmSize'];
        $this->qeSize =         $headerArray['qeSize'];
        $this->lockFilename =   $headerArray['lockFilename'];
        $this->bIsLocking =     $headerArray['bIsLocking'];
        $this->msgMemSize =     $headerArray['msgMemSize'];
        $this->msgMemInPtr =    $headerArray['msgMemInPtr'];
        $this->msgMemOutPtr =   $headerArray['msgMemOutPtr'];

        if( $bLockHeld == false )
        {
            $this->Unlock();
        }

        return( EOK );
    }


    private function ReadLock()
    {
        if( $this->bIsLocking == false )
        {
            return( EOK );
        }

        if( $this->lockFd == -1 )
        {
            return( EPERM );
        }

        $err = flock( $this->lockFd, LOCK_SH );
        if( $err === false )
        {
            return( EIO );
        }

        return( EOK );
    }

    private function WriteLock()
    {
        if( $this->bIsLocking == false )
        {
            return( EOK );
        }

        if( $this->lockFd == -1 )
        {
            return( EPERM );
        }

        $err = flock( $this->lockFd, LOCK_EX );
        if( $err === false )
        {
            return( EIO );
        }

        return( EOK );
    }

    private function Unlock()
    {
        if( $this->bIsLocking == false )
        {
            return( EOK );
        }

        if( $this->lockFd == -1 )
        {
            return( EPERM );
        }

        $err = flock( $this->lockFd, LOCK_UN );
        if( $err === false )
        {
            return( EIO );
        }

        return( EOK );
    }

} /* End Class Definition */
