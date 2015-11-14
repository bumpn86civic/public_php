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
**  Filename:       lsServerThread.php
**  Project:        EcoSphere
**  Author:         Barry Robertson (barry@logixstorm.com)
**  Created:        11-03-2014
**
**  Abstract:       Simple sub-class to the PHP 'Thread' construct which gives
** us basic pthread access.  We primarily use this class to run the EcoSphere
** server message processor.  It's not multi-threaded in PHP; we'll thread the
** product of course and probably write the server in C or Python at that point.
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

$globalThreadId = 100;

//****************************************************************************
//************************ Classes *******************************************
//****************************************************************************

/**
 *******************************************************************************
 * Class: LsServerThread
 *
 * @abstract This is a thread abstraction used to process incoming messages
 * to the EcoSphere System.
 *
 ********************************************************************************/
class LsServerThread
{
    //*******************************************************
    //*******************************************************
    // Public Members
    //*******************************************************
    //*******************************************************
    public  $stopThread = false;

    //*******************************************************
    //*******************************************************
    // Private Members
    //*******************************************************
    //*******************************************************
    private $id;
    private $name;
    private $isRunning = false;
    private $pDataRef;
    private $runFunction = array();

    //*******************************************************
    //*******************************************************
    // Constructor & Abstract (Virtual) Methods
    //*******************************************************
    //*******************************************************
    public function __construct( )
    {
        global $globalThreadId;

        //
        // Set the thread ID and bump the global counter.
        //
        $this->id = $globalThreadId;
        $this->name = "default_".$this->id;

        $globalThreadId++;
    }

    //*******************************************************
    //*******************************************************
    // Public Methods
    //*******************************************************
    //*******************************************************
    public function GetId()     { return( $this->id ); }
    public function IsRunning() { return( $this->isRunning ); }

    public function Name( $name=null )
    {
        if( $name != null )
        {
            $this->name = $name;
        }

        return( $this->name );
    }

    public function SetRunFunction( &$obj, $method )
    {
        $this->runFunction = array( $obj, $method );
    }

    public function DataRef( &$pDataRef = null )
    {
        if( $pDataRef != null )
        {
            $this->pDataRef = $pDataRef;
        }
        return( $this->pDataRef );
    }

    public function StartThread()
    {
        if( $this->isRunning === true )
        {
            return( EOK );
        }

        $this->isRunning = true;

        //
        // Call into the start method will create a thread and
        // call our $this->run() method on the new thread.
        //
        $this->start();

        return( EOK );
    }

    public function run( )
    {
        //
        // Route into the run function.
        //
        call_user_func_array( $this->runFunction, array($this) );
    }
}
