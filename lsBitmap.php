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
**  Filename:       lsBitmap.php
**  Project:        EcoSphere
**  Author:         Barry Robertson (barry@logixstorm.com)
**  Created:        01-16-2015
**
**  Abstract:       Simple bitmap class based upon 32-bit words.  I wouldn't
 * have written this but I could find one in the first few google pages...
 * oh well.
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
include_once( "lsErrno.php" );

//**************************************************************************
//* Name Space
//**************************************************************************

//**************************************************************************
//* Public Functions
//**************************************************************************

//**************************************************************************
//************************ Classes *****************************************
//**************************************************************************


/**
 * @abstract Simple 32-bit based bitmap class.  This isn't optimized in any
 * way but it's handy...
 */
class LsBitmap
{
    private $map=array();
    private $numBits = 0;
    private $numWords = 0;

    //*************************************************************************
    //*************************************************************************
    // Public Methods
    //*************************************************************************
    //*************************************************************************
    public function __construct( $numBits=0 )
    {
        if( $numBits!=0 )
        {
            // init the bitmap.
            $this->Init( $numBits );
        }
    }

    public function Init( $numBits )
    {
        if( is_string($numBits) )
        {
            $numBits = intval( $numBits );
        }

        if( $numBits== 0 )
        {
            $this->map = array();
            $this->numBits = 0;
            $this->numWords = 0;
            return;
        }

        $this->numBits = $numBits;
        $this->numWords = LsRoundUp( $numBits/32, 1 );
        $this->map = array();

        for( $w=0; $w<$this->numWords; $w++ )
        {
            $this->map[$w] = 0x00000000;
        }
    }

    public function Reset()
    {
        for( $w=0; $w<$this->numWords; $w++ )
        {
            $this->map[$w] = 0x00000000;
        }
    }

    public function BitIsSet( $bit )
    {
        if( is_string($bit) )
        {
            $bit = intval( $bit );
        }

        $w = $bit / 32;

        if( $w >= $this->numWords )
        {
            return( -1 );
        }

        $wbit = 0x1 << ($bit % 32);

        if( $this->map[$w] & $wbit )
        {
            return true;
        }

        return false;
    }

    public function SetBit( $bit )
    {
        if( is_string($bit) )
        {
            $bit = intval( $bit );
        }

        $w = $bit / 32;

        if( $w >= $this->numWords )
        {
            return( -1 );
        }

        $wbit = 0x1 << ($bit % 32);

        //
        // Set the bit.
        //
        $this->map[$w] |= $wbit;

        return true;
    }


    public function TestAndSet( $bit )
    {
        if( is_string($bit) )
        {
            $bit = intval( $bit );
        }

        $w = $bit / 32;

        if( $w >= $this->numWords )
        {
            return( -1 );
        }

        $wbit = 0x1 << ($bit % 32);

        if( $this->map[$w] & $wbit )
        {
            return false;
        }

        //
        // Set the bit.
        //
        $this->map[$w] |= $wbit;

        return true;
    }

    public function Clear( $bit )
    {
        if( is_string($bit) )
        {
            $bit = intval( $bit );
        }

        $w = $bit / 32;

        if( $w >= $this->numWords )
        {
            return( -1 );
        }

        $wbit = 0x1 << ($bit % 32);

        //
        // Clears the bit and returns true if it's currently set.
        //
        if( $this->map[$w] & $wbit )
        {
            $this->map[$w] &= ~$wbit;
            return true;
        }

        return false;
    }

    public function ClearRange( $start, $numBits )
    {
        for( $b=$start; $b<$start+$numBits; $b++ )
        {
            $this->Clear($b);
        }
    }

    public function GetRange( $numBits )
    {
        $bitsLeft = $numBits;
        $startOfRange = -1;

        for( $w=0; $w<$this->numWords && $bitsLeft!=0; $w++ )
        {
            //
            // This word is completely full... skip it.
            //
            if( $this->map[$w] == 0xFFFFFFFF )
            {
                continue;
            }

            //
            // Check to see if we can find a fit!
            //
            for( $b=0; $b<32 && $bitsLeft!=0; $b++ )
            {
                if( $this->map[$w] & (0x1 << $b) )
                {
                    //
                    // This means the range can't be satisfied.
                    //
                    $startOfRange = -1;
                    $bitsLeft = $numBits;
                    continue;
                }

                //
                // This has possibility...
                //
                if( $startOfRange == -1 )
                {
                    $startOfRange = $w*32 + $b;
                }

                $bitsLeft--;
            }
        }


        if( $bitsLeft != 0 )
        {
            return -1;
        }

        //
        // Claim the bits.
        //
        for( $b=$startOfRange; $b<$startOfRange+$numBits; $b++ )
        {
            $this->SetBit($b);
        }

        return( $startOfRange );
    }
}
