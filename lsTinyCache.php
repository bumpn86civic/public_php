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
**  Filename:       lsTinyCache.php
**  Project:        EcoSphere
**  Author:         Barry Robertson (barry@logixstorm.com)
**  Created:        01-16-2015
**
**  Abstract:       This is a simple little web cache we use for storing
** any persistent data we want to access from the EcoSphere web server.
** This is NOT designed for high-performance production server deployment!!
** We'll use AWS ElastiCache or some other web cache implementation and
** almost assuredly not use these little PHP modules for the real heavy
** lifting in the true, production implementation.  But PHP is so simple
** and just molds like silly putty in a matter of minutes! ha!
** ...go f--- yourself if you don't agree! have a nice day
**
** The way this works is by using php's shared memory operations.  They
** don't work to talk from Apache to some other process (sadly...) but
** they *do* persist across client access on the server!
**
** The basic design is a page-based linear memory allocator that stores
** a catalog memory segment in one memory key which is read and written
** entirely each time a cache entry is added or removed from the system.
**
** In a separate file, we store the data associated with the cache keys.
** This is just a raw, offset/length sort of organization and is split
** up into 1k 'pages' controlled by the catalog object referenced above.
**
** Instancing a cache is simple:
**
**      $cache = LsTinyCache::Factory( $key, $size );
**       if( $cache === null )
**       {
**           if( $size == 0 )
**           {
**               return(ENOENT);
**           }
**           else
**           {
**               return( EFAULT );
**           }
**       }
**
**
**  Change Log:
**  Barry Robertson; 01-16-2015; Initial version.
**  <engr. name>; <date>; <reason>
*****************************************************************************/

//**************************************************************************
//* Use Modules
//**************************************************************************

//**************************************************************************
//* Include/Require Files
//**************************************************************************
require_once( "lsUtil.php" );
require_once( "lsErrno.php" );

//**************************************************************************
//* Name Space
//**************************************************************************

//**************************************************************************
//* Public Functions
//**************************************************************************

//**************************************************************************
//************************ Classes *****************************************
//**************************************************************************

class LsCacheEntry
{
    public $key = -1;
    public $dcacheOffset = -1;
    public $pageCount = 0;  // Total length of the original allocation.
    public $valueLen=0; // current length of the string stored in the segment.
}

/**
 * @abstract This is the catalog object used to control what memory segments
 * are available in the data file.
 */
class LsTinyCache
{
    //*************************************************************************
    //*************************************************************************
    // Private Members
    //*************************************************************************
    //*************************************************************************
    public $cacheBitmap = null;
    public $cacheSize = 0;
    public $toc = array();
    private $pageSize = LsTinyCache::PAGE_SIZE;

    //
    // These aren't stored as part of the in-memory catalog
    //
    private $numPages = 0;
    private $lockFileName = "";
    private $lockFd = -1;

    //
    // Shared memory resources
    //
    private $catalogShmKey = -1;
    private $catalogShmHandle = -1;

    private $cacheShmKey = -1;
    private $cacheShmHandle = -1;

    //
    // If this flag is set, then we were simply passed a key for the cache
    // and we're going to discover how big it is and allocate the shared
    // memory objects accordingly.
    //
    private $discoveryMode = LsTinyCache::MANUAL;

    //
    // This is the current size of the serialized catalog.
    // (Gets updated everytime the catalog gets written to shmem...)
    //
    private $catalogSerSize = 0;

    //
    // This is the current size of the allocated catalog memory segments.
    //
    private $catalogMemSize = 0;

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
    const PAGE_SIZE = 1024;
    const MAGIC = 'FEEDBEEF';
    const CATALOG_MIN_SIZE = 32768;
    const ZERO_BLK_SIZE = 65536;
    const HEADER_SIZE = 16;
    const CATALOG_OFFSET = 16;
    const MAGIC_OFFSET = 0;
    const CATALOG_SIZE_OFFSET = 8;

    const MANUAL = 'manual';
    const AUTO  = 'auto';

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
    public function __construct( $keyId, $cacheSize=0 )
    {
        //
        // This means we're just instancing the basics to communicate with
        // the lock file and the catalog.  The actual size of the objects
        // will be discovered.
        //
        $this->pageSize = LsTinyCache::PAGE_SIZE;

        if( $cacheSize == 0 )
        {
            $this->discoveryMode = LsTinyCache::AUTO;
            $this->cacheSize = 0;
            $this->numPages = 0;
        }
        else
        {
            $this->cacheSize = LsRoundUp( $cacheSize, $this->pageSize );
            $this->numPages = $this->cacheSize / $this->pageSize;
        }

        $this->catalogShmKey = $keyId;
        $this->cacheShmKey = $keyId + 4096;

        $this->lockFileName = LsSys::GetEcosphereIpcLockFile($keyId);

    }

    //*************************************************************************
    //*************************************************************************
    // Public Methods
    //*************************************************************************
    //*************************************************************************

    public static function Factory( $keyId, $cacheSize=0 )
    {
        $cache = new LsTinyCache( $keyId, $cacheSize );

        //
        // Open the lock file.
        //
        $err = $cache->OpenLockFile();
        if( $err != EOK )
        {
            return( $err );
        }

        //
        // See if it's in memory already.
        //
        $err = $cache->LoadFromMemory();
        if( $err == EOK )
        {
            return( $cache );
        }

        //***************************************************************
        // See if we get ENOENT && we're in auto-discovery mode.
        // In this case, we can't actually do this..
        //***************************************************************
        if( $err==ENOENT )
        {
            if( $cache->GetDiscoveryMode() == LsTinyCache::AUTO )
            {
                return( null );
            }

            $err = $cache->WriteLock();
            if( $err != EOK )
            {
                return( null );
            }

                //
                // We can create a new cache object.
                //
                $err = $cache->AllocateInternalResources();
                if( $err != EOK )
                {
                    $cache->Unlock();
                    return( null );
                }

                $err = $cache->WriteCatalog();
                if( $err != EOK )
                {
                    $cache->Unlock();
                    return( null );
                }

            $cache->Unlock();

            return( $cache );
        }
        else
        {
            return( null );
        }
    }

    public function GetSizeOfDataCache(){ return( $this->numPages * $this->pageSize ); }
    public function GetPageSize() { return( $this->pageSize ); }
    public function OffsetToPage( $offset ){ return( $offset / $this->pageSize ); }
    public function PageToOffset( $page ){ return( $page * $this->pageSize ); }

    public function ClearTheCache( $bZeroData=false, $bLockHeld=false )
    {
        if( $bLockHeld == false )
        {
            $err = $this->WriteLock();
            if( $err != EOK )
            {
                return( $err );
            }
        }

        $this->toc = array();
        $this->cacheBitmap->Reset();

        //
        // Update the catalog.
        //
        $err = $this->WriteCatalog();

        if( $err == EOK && $bZeroData == true )
        {
            $err = $this->ZeroCacheData();
        }

        if( $bLockHeld == false ){ $this->Unlock(); }

        return( $err );
    }

    public function AllocateKey( $key, $maxSize )
    {
        $err = $this->WriteLock();
        if( $err != EOK )
        {
            return( $err );
        }

        if( isset($this->toc[$key]) )
        {
            //
            // Make sure the size is ok if it already exists.
            //
            if( $maxSize != ($this->toc[$key]->pageCount * $this->pageSize) )
            {
                $this->Unlock();
                return( ENOSPC );
            }

            $this->Unlock();
            return( EOK );
        }

        $entry = $this->AllocateCacheEntry( $key, $maxSize );
        if( $entry == null )
        {
            $this->Unlock();
            return( ENOMEM );
        }

        //
        // Add the key to the catalog.
        //
        $this->toc[$key] = $entry;

        //
        // Update the catalog.
        //
        $err = $this->WriteCatalog();

        $this->Unlock();

        return( $err );
    }



    public function PutKey( $key, $value )
    {
        $err = $this->WriteLock();
        if( $err != EOK )
        {
            return( $err );
        }

        $len = strlen($value);

        if( isset($this->toc[$key]) )
        {
            //
            // Make sure the size is ok.
            //
            $entry = $this->toc[$key];

            if( $len > ($entry->pageCount * $this->pageSize) )
            {
                $this->Unlock();
                return( ENOSPC );
            }
        }
        else
        {
            //
            // Try to get a catalog entry for this size.
            //
            $entry = $this->AllocateCacheEntry( $key, $len );
            if( $entry == null )
            {
                $this->Unlock();
                return( ENOMEM );
            }

            //
            // Add the key to the catalog.
            //
            $this->toc[$key] = $entry;
        }

        //
        // Update the current value in the cache memory block.
        // Write the cache entry.
        //
        $entry->valueLen = $len;

        $err = $this->WriteCacheEntry( $entry, $value );
        if( $err != EOK )
        {
            $this->Unlock();
            return( $err );
        }

        //
        // Update the catalog.
        //
        $err = $this->WriteCatalog();

        $this->Unlock();

        return( $err );
    }

    public function ReadKeyValue( $key, &$data )
    {
        $err = $this->ReadLock();
        if( $err != EOK )
        {
            return( $err );
        }

        if( !isset($this->toc[$key]) )
        {
            $this->Unlock();
            return( ENOENT );
        }

        //
        // Read the data segments for this cache entry.
        //
        $ce = $this->toc[$key];

        $data = shmop_read( $this->cacheShmHandle, $ce->dcacheOffset, $ce->valueLen );
        if( $data === false )
        {
            $this->Unlock();
            return( EIO );
        }

        $this->Unlock();

        return( EOK );
    }

    public function DeleteCache()
    {
        //
        // If the thing hasn't been initialized, we can't say the cache has been 'deleted'.
        //
        if( $this->lockFd == -1 )
        {
            return( EPERM );
        }


        $this->WriteLock();

        //
        // Delete all the data.
        // (true=zeroData, true=lockHeld)
        //
        $err = $this->ClearTheCache( true, true );
        if( $err != EOK )
        {
            $this->Unlock();
            return( $err );
        }

        //
        // Blow away the header so it won't be confused as an existing cache.
        //
        if( $this->catalogShmHandle != -1 )
        {
            $catalogBytesToWrite = LsRoundUp(
                                    $this->catalogSerSize, LsTinyCache::CATALOG_MIN_SIZE );
            $result = shmop_write(
                        $this->catalogShmHandle,
                        str_repeat(' ', LsTinyCache::HEADER_SIZE + $catalogBytesToWrite),
                        0 ); // Offset into the memory.
            if( $result === false )
            {
                $this->Unlock();
                return( EIO );
            }
        }


        //
        // Delete the catalog and data cache objects.
        //
        LsTinyCache::DeleteShmObject( $this->catalogShmHandle );
        LsTinyCache::DeleteShmObject( $this->cacheShmHandle );

        //
        // (true=lock held)
        //
        $this->Close( true );

        //
        // Close out the cache.
        //
        $this->Unlock();

        return( EOK );
    }

    public function Close( $bLockHeld=false )
    {
        if( $bLockHeld == false )
        {
            $this->WriteLock();
        }


        if( $this->cacheShmHandle != -1 )
        {
            shmop_close( $this->cacheShmHandle );
            $this->cacheShmHandle = -1;
        }

        if( $this->catalogShmHandle != -1 )
        {
            shmop_close( $this->catalogShmHandle );
            $this->catalogShmHandle = -1;
        }

        if( $bLockHeld == false )
        {
            $this->Unlock();
        }

        fclose( $this->lockFd );
        $this->lockFd = -1;
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


    private function GetDiscoveryMode()
    {
        return( $this->discoveryMode );
    }

    private  function OpenLockFile( )
    {
        $lockFile = $this->lockFileName;

        if( $this->lockFd != -1 )
        {
            return( EPERM );
        }

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

        return( EOK );
    }

    private function AllocateInternalResources()
    {
        if( $this->numPages == 0 ||
            $this->cacheShmHandle != -1 ||
            $this->catalogShmHandle != -1 )
        {
            return( EPERM );
        }

        //
        // Create the bitmap to control the page allocations.
        //
        $this->cacheBitmap = new LsBitmap( $this->numPages );

        //
        // Create the shared memory objects.
        //
        $err = $this->CreateSharedMemoryRegions();
        if( $err != EOK )
        {
            return( $err );
        }

        return( EOK );
    }


    //
    // Catalog must be write locked.
    //
    private function WriteCatalog( )
    {
        if( $this->catalogShmHandle == -1 )
        {
            return( EPERM );
        }

        $s = serialize( $this );
        $this->catalogSerSize = strlen( $s );

        $data = LsTinyCache::CreateCatalogHeader($this->catalogSerSize) . $s;

        //
        // Read the first 16 bytes.
        //
        $result = shmop_write( $this->catalogShmHandle, $data, 0 );
        if( $result === false )
        {
            return( EIO );
        }

        return( EOK );
    }



    private function LoadFromMemory()
    {
        $err = $this->WriteLock();
        if( $err != EOK )
        {
            return( $err );
        }

        //
        // Check to see if this has already been done.
        //
        if( $this->catalogShmHandle != -1 || $this->cacheShmHandle != -1)
        {
            $this->Unlock();
            return( EPERM );
        }

        $catalog = LsTinyCache::OpenCatalogFromShm( $this->catalogShmKey, $shmHandle );
        if( $catalog === null )
        {
            //
            // Doesn't exist in memory.
            //
            $this->Unlock();
            return( ENOENT );
        }

        //**********************************************************************
        // Check to make sure that we're in auto-discovery mode.
        // If we have a valid catalog onfile, we have to verify that they're
        // the same size...
        //**********************************************************************
        if( $this->discoveryMode != LsTinyCache::AUTO )
        {
            if( $this->GetSizeOfDataCache() != $catalog->GetSizeOfDataCache() ||
                $this->pageSize != $catalog->GetPageSize() )
            {
                $this->Unlock();
                return( ESIZEMISMATCH );
            }
        }

        //**********************************************************************
        // Set the shared memory handle used to read in the stored catalog.
        //**********************************************************************
        $this->catalogShmHandle = $shmHandle;

        $this->cacheSize    = $catalog->cacheSize;
        $this->pageSize     = $catalog->GetPageSize();
        $this->numPages     = $this->cacheSize / $this->pageSize;
        $this->cacheBitmap  = clone $catalog->cacheBitmap;
        $this->toc          = $catalog->toc;

        //
        // Now open up the data cache resources.
        //
        $this->cacheShmHandle = LsTinyCache::CreateShmObject(
                                    $this->cacheShmKey, $this->numPages*$this->pageSize );
        if( $this->cacheShmHandle === -1 )
        {
            $this->Unlock();
            return( EIO );
        }

        $this->Unlock();
        return( EOK );
    }


    //
    // Catalog must be write locked.
    //
    private function WriteCacheEntry( &$ce, $data )
    {
        if( $this->cacheShmHandle == -1 )
        {
            return( EPERM );
        }

        $result = shmop_write( $this->cacheShmHandle, $data, $ce->dcacheOffset );
        if( $result === false )
        {
            return( EIO );
        }

        return( EOK );
    }


    //
    // Object must be write locked.
    //
    private function AllocateCacheEntry( $cacheKey, $size )
    {
        $cacheLen = LsRoundUp( $size, $this->pageSize );
        if( $cacheLen > $this->cacheSize )
        {
            return( null );
        }

        $pageCount = $cacheLen / $this->pageSize;

        //
        // Get a range of pages that are clear.
        //
        $startBit = $this->cacheBitmap->GetRange( $pageCount );
        if( $startBit == -1 )
        {
            return( null );
        }

        $ce = new LsCacheEntry;
        $ce->key = $cacheKey;
        $ce->dcacheOffset = $this->PageToOffset($startBit);
        $ce->pageCount = $pageCount;
        $ce->valueLen = 0;

        return( $ce );
    }



    private function CreateSharedMemoryRegions( )
    {
        if( $this->catalogShmHandle != -1 ||
            $this->cacheShmHandle != -1 )
        {
            return( EPERM );
        }

        //
        // Create the catalog shm file.
        //
        $this->catalogShmHandle = LsTinyCache::CreateShmObject(
                                    $this->catalogShmKey,
                                    LsTinyCache::HEADER_SIZE + LsTinyCache::CATALOG_MIN_SIZE );

        //*********************************************************************
        // Create the cache memory shm file.
        //*********************************************************************
        $this->cacheShmHandle = LsTinyCache::CreateShmObject(
                                    $this->cacheShmKey,
                                    $this->numPages*$this->pageSize );

        return( EOK );
    }

    //
    // Write/read lock must be held.
    //
    private static function ReadCatalogHeader( $shmHandle, &$magic, &$size )
    {
        if( $shmHandle == -1 )
        {
            return( EPERM );
        }

        //
        // Read the first 16 bytes.
        //
        $header = shmop_read( $shmHandle, 0, LsTinyCache::HEADER_SIZE );
        if( $header === false )
        {
            return( EIO );
        }

        $magic = substr( $header, 0, 8 );
        $size = intval( hexdec( substr( $header, 8, 8 ) ) );

        return( EOK );
    }

    private static function CreateCatalogHeader( $size )
    {
        $sizeStr = sprintf( "%08X", $size );

        return( LsTinyCache::MAGIC . $sizeStr );
    }

    private static function OpenCatalogSharedMemory( $key, $size )
    {
        //
        // Round up the size to our minimum size.
        //
        $size = LsRoundUp( $size, LsTinyCache::CATALOG_MIN_SIZE );

        return( LsTinyCache::CreateShmObject($key,$size+LsTinyCache::HEADER_SIZE) );
    }

    //
    // Write lock must be held
    //
    private static function OpenCatalogFromShm( $key, &$returnedFileHandle )
    {
        $returnedFileHandle = null;

        //
        // Open the shared memory object for just the header.
        //
        $headerHandle = LsTinyCache::CreateShmObject( $key, LsTinyCache::CATALOG_MIN_SIZE );
        if( $headerHandle === -1 )
        {
            //
            // This object doesn't exist...
            //
            return( null );
        }

        //
        // Read the header.
        //
        $err = LsTinyCache::ReadCatalogHeader( $headerHandle, $magic, $sizeOfSerializedCatalog );
        if( $err != EOK )
        {
            shmop_close( $headerHandle );
            return( null );
        }

        //
        // See if the object exists in shared memory.
        //
        if( $magic != LsTinyCache::MAGIC )
        {
            //
            // The object doesn't exist!
            //
            shmop_close( $headerHandle );
            return( null );
        }

        //
        // Check to see if the catalog is larger than one, base size block.
        // If it's currently smaller than the base unit, then we don't need
        // to reopen it.
        //
        if( $sizeOfSerializedCatalog > LsTinyCache::CATALOG_MIN_SIZE )
        {
            //
            // Close the shm handle and reopen at the correct size.
            //
            shmop_close( $headerHandle );

            //********************************************************************
            // Now create the catalog file at the correct size.
            //********************************************************************
            $returnedFileHandle = LsTinyCache::OpenCatalogSharedMemory(
                                                    $key, $sizeOfSerializedCatalog );
            if( $returnedFileHandle === -1 )
            {
                return( null );
            }
        }
        else
        {
            //
            // We can just reuse this file descriptor because it's small enough
            // to fit into one unit.
            //
            $returnedFileHandle = $headerHandle;
        }

        //
        // Read in the raw, serialized data and unserialize it.
        //
        $s = shmop_read( $returnedFileHandle,
                         LsTinyCache::CATALOG_OFFSET, $sizeOfSerializedCatalog );
        if( $s === false )
        {
            return( null );
        }

        //
        // Now unserialize the catalog and copy off the fields we want.
        //
        $catalog = unserialize( $s );
        if( $catalog === null )
        {
            return( null );
        }

        return( $catalog );
    }


    private static function DeleteShmObject( $handle )
    {
        if( $handle == -1 )
        {
            return( EINVAL );
        }

        $result = shmop_delete( $handle );
        if( $result === true )
        {
            return( EOK );
        }

        return( EIO );
    }

    private static function CloseShmObject( $handle )
    {
        if( $handle == -1 )
        {
            return( EINVAL );
        }

        $result = shmop_close( $handle );
        if( $result === true )
        {
            return( EOK );
        }

        return( EIO );
    }


    private static function OpenShmObject( $key, $size, $mode='a', $permissions=0666 )
    {
        $handle = @shmop_open( $key, $mode, $permissions, $size );
        if( $handle === false )
        {
            return( -1 );
        }

        return( $handle );
    }

    private static function CreateShmObject( $key, $size, $permissions=0666 )
    {
        $handle = @shmop_open( $key, 'n', $permissions, $size );
        if( $handle === false )
        {
            //
            // Attempt to open it with write permissions if it already exists.
            //
            $handle = @shmop_open( $key, 'w', $permissions, $size );
            if( $handle === false )
            {
                return( -1 );
            }
        }

        return( $handle );
    }

    //
    // Write lock must be held.
    //
    private function ZeroCacheData()
    {
        if( $this->cacheShmHandle == -1 )
        {
            return( EPERM );
        }

        $bytesRemaining = $this->numPages * $this->pageSize;
        $offset = 0;

        while( $bytesRemaining != 0 )
        {
            $bytesToWrite = ($bytesRemaining < LsTinyCache::ZERO_BLK_SIZE) ?
                            $bytesRemaining :
                            LsTinyCache::ZERO_BLK_SIZE;

            $data = str_repeat( '0', $bytesToWrite );

            $result = shmop_write( $this->cacheShmHandle, $data, $offset );
            if( $result === false )
            {
                return( EIO );
            }

            $offset += $bytesToWrite;
            $bytesRemaining -= $bytesToWrite;
        }

        return( EOK );
    }


    private function ReadLock()
    {
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
