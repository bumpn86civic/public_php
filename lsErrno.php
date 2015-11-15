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
**  Filename:       lsErrno.php
**  Project:        EcoSphere
**  Author:         Barry Robertson (barry@logixstorm.com)
**  Created:        11-02-2014
**
**  Abstract:       This file contains our internal errno representations of
**                  the unix-style errno.h error codes.
**
**
**  Change Log:
**  Barry Robertson; 11-02-2014; Initial version.
**  <engr. name>; <date>; <reason>
*****************************************************************************/

//********************************************************
//* Use Modules
//********************************************************

//********************************************************
//* Include/Require Files
//********************************************************

//**************************************************************
// Definitions & Constants
//**************************************************************
//
// Handy Company Specific Error Codes
//
define( "EBADVER",          8001 );  /* Version Mismatch or Invalid Format */
define( "EBADPASSWD",       8002 );  /* Password Mismatch or Invalid */
define( "EBADCRC",          8003 );  /* CRC Mismatch or Invalid */
define( "EBADFORM",         8004 );  /* Structural or message formatting is incorrect */
define( "ECONTIO",          8005 );  /* Success; additional bytes still need i/o */
define( "ENOTREADY",        8006 );  /* Component not ready yet. */
define( "ENOTSUPP",         8007 );  /* Not supported message. */
define( "EBADTOKEN",        8008 );  /* Message security token is invalid or expired. */
define( "EBADPARAM",        8008 );  /* Bad message parameter. */
define( "EQFULL",           8009 );  /* The queue has no space for the item. */
define( "ESIZEMISMATCH",    8010 );  /* Some operation that requires sizes to be the same has failed. */
define( "EPRECONDERR",      8011 );  /* Precondition Not Met. */
define( "EHTTP",            8012 );  /* HTTP Transport Problem. */
define( "EACCOUNTFUNDING",  8013 );  /* Account funding problem. */
define( "EHTTPCONNREFUSED", 8014 );  /* Can't attach to the web server port-80. */

//
// UNIX Errno Codes
//
define( "EOK",      0 );        /* Ok, no error */
define( "EPERM",    1 );        /* Operation not permitted */
define( "ENOENT",   2 );        /* No such file or directory */
define( "ESRCH",    3 );        /* No such process */
define( "EINTR",    4 );        /* Interrupted system call */
define( "EIO",      5 );        /* I/O error */
define( "ENXIO",    6 );        /* No such device or address */
define( "E2BIG",    7 );        /* Argument list too long */
define( "ENOEXEC",  8 );        /* Exec format error */
define( "EBADF",    9 );        /* Bad file number */
define( "ECHILD",   10 );       /* No child processes */
define( "EAGAIN",   11 );       /* Try again */
define( "ENOMEM",   12 );       /* Out of memory */
define( "EACCES",   13 );       /* Permission denied */
define( "EFAULT",   14 );       /* Bad address */
define( "ENOTBLK",  15 );       /* Block device required */
define( "EBUSY",    16 );       /* Device or resource busy */
define( "EEXIST",   17 );       /* File exists */
define( "EXDEV",    18 );       /* Cross-device link */
define( "ENODEV",   19 );       /* No such device */
define( "ENOTDIR",  20 );       /* Not a directory */
define( "EISDIR",   21 );       /* Is a directory */
define( "EINVAL",   22 );       /* Invalid argument */
define( "ENFILE",   23 );       /* File table overflow */
define( "EMFILE",   24 );       /* Too many open files */
define( "ENOTTY",   25 );       /* Not a typewriter */
define( "ETXTBSY",  26 );       /* Text file busy */
define( "EFBIG",    27 );       /* File too large */
define( "ENOSPC",   28 );       /* No space left on device */
define( "ESPIPE",   29 );       /* Illegal seek */
define( "EROFS",    30 );       /* Read-only file system */
define( "EMLINK",   31 );       /* Too many links */
define( "EPIPE",    32 );       /* Broken pipe */
define( "EDOM",     33 );       /* Math argument out of domain of func */
define( "ERANGE",   34 );       /* Math result not representable */

//
// Web Server Error Codes
//
define(  "STATUS_OK",                200 );
define(  "STATUS_REDIRECT_MULTIPLE", 300 );
define(  "STATUS_REDIRECT_PERM",     301 );
define(  "STATUS_REDIRECT_FOUND",    302 );
define(  "STATUS_REDIRECT_POST",     307 );
define(  "STATUS_BAD_REQUEST",       400 );
define(  "STATUS_UNAUTHORIZED",      401 );
define(  "STATUS_FORBIDDEN",         403 );
define(  "STATUS_NOT_FOUND" ,        404 );
define(  "STATUS_TIMEOUT",           408 );
define(  "STATUS_RSRC_CONFLICT",     409 );
define(  "STATUS_RSRC_GONE",         410 );
define(  "STATUS_LENGTH_REQUIRED",   411 );
define(  "STATUS_PRE_COND_ERROR",    412 );
define(  "STATUS_RQST_TOO_LARGE",    413 );
define(  "STATUS_URI_TOO_LONG",      414 );
define(  "STATUS_INTERNAL_ERROR",    500 );
define(  "STATUS_NOT_IMPLEMENTED",   501 );
define(  "STATUS_SERVICE_NA",        503 );


$gErrToStr = array(
    //
    // Company Specific Error Codes
    //
    EBADVER =>          "Version Mismatch or Invalid Format",
    EBADPASSWD =>       "Password Mismatch or Invalid",
    EBADCRC =>          "CRC Mismatch or Invalid",
    EBADFORM =>         "Structural or Message Formatting is Incorrect",
    ECONTIO =>          "Success but Additional Bytes Require Transfer",
    ENOTREADY =>        "Component Not Ready Yet",
    ENOTSUPP =>         "Message Not Supported",
    EBADTOKEN =>        "Security Token is Invalid or has Expired",
    EBADPARAM =>        "Bad Message Parameter",
    EQFULL =>           "Queue Has No Space Left",
    ESIZEMISMATCH =>    "Operation Requires Objects of the Same Size",
    EPRECONDERR =>      "Precondition Not Met",
    EHTTP =>            "HTTP Transport Error",
    EACCOUNTFUNDING =>  "Account funding problems",
    EHTTPCONNREFUSED => "Unable to connect to the web server port",

    //
    // UNIX Errno Codes
    //
    EOK =>              "Ok",
    EPERM =>            "Operation not permitted",
    ENOENT =>           "No such file or directory",
    ESRCH =>            "No such process",
    EINTR =>            "Interrupted system call",
    EIO =>              "I/O error",
    ENXIO =>            "No such device or address",
    E2BIG =>            "Argument list too long",
    ENOEXEC =>          "Exec format error",
    EBADF =>            "Bad file number",
    ECHILD =>           "No child processes",
    EAGAIN =>           "Try again",
    ENOMEM =>           "Out of memory",
    EACCES =>           "Permission denied",
    EFAULT =>           "Bad address",
    ENOTBLK =>          "Block device required",
    EBUSY =>            "Device or resource busy",
    EEXIST =>           "File exists",
    EXDEV =>            "Cross-device link",
    ENODEV =>           "No such device",
    ENOTDIR =>          "Not a directory",
    EISDIR =>           "Is a directory",
    EINVAL =>           "Invalid argument",
    ENFILE =>           "File table overflow",
    EMFILE =>           "Too many open files",
    ENOTTY =>           "Not a typewriter",
    ETXTBSY =>          "Text file busy",
    EFBIG =>            "File too large",
    ENOSPC =>           "No space left on device",
    ESPIPE =>           "Illegal seek",
    EROFS =>            "Read-only file system",
    EMLINK =>           "Too many links",
    EPIPE =>            "Broken pipe",
    EDOM =>             "Math argument out of domain of func",
    ERANGE =>           "Math result not representable ",

    //
    // Web Server Status Codes
    //
    STATUS_OK               => 'OK',
    STATUS_REDIRECT_MULTIPLE=> 'Redirect Multiple Choices',
    STATUS_REDIRECT_PERM    => 'Redirect Permanently Moved',
    STATUS_REDIRECT_FOUND   => 'Redirect Temporarily Moved',
    STATUS_BAD_REQUEST      => 'Bad Request',
    STATUS_UNAUTHORIZED     => 'Unauthorized',
    STATUS_FORBIDDEN        => 'Forbidden',
    STATUS_NOT_FOUND        => 'Not Found',
    STATUS_TIMEOUT          => 'Timeout',
    STATUS_RSRC_CONFLICT    => 'Resource Conflict',
    STATUS_RSRC_GONE        => 'Resource Gone',
    STATUS_LENGTH_REQUIRED  => 'Length Required',
    STATUS_PRE_COND_ERROR   => 'Precondition Error',
    STATUS_RQST_TOO_LARGE   => 'Request Too Large',
    STATUS_URI_TOO_LONG     => 'URI Too Long',
    STATUS_INTERNAL_ERROR   => 'Internal Server Error',
    STATUS_NOT_IMPLEMENTED  => 'Not Implemented',
    STATUS_SERVICE_NA       => 'Service Not Available',
);


class LsErrno
{
    public static function Str( $err )
    {
        global $gErrToStr;

        if( isset($gErrToStr[$err]) )
        {
            return( $gErrToStr[$err] );
        }
        else
        {
            return( "Unknown Error" );
        }
    }

    public static function StdDspy( $err )
    {
        return( 'err='.$err.' ('.LsErrno::Str($err).')' );
    }
}
