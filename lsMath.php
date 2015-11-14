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
**  Filename:       lsMath.php
**  Project:        EcoSphere
**  Author:         Barry Robertson (barry@logixstorm.com)
**  Created:        11-09-2015
**
**  Abstract:       Collection of routines for vector, planes, points, etc...
**
**
**  Change Log:
**  Barry Robertson; 11-09-2015; Initial version.
**  <engr. name>; <date>; <reason>
*****************************************************************************/

//**************************************************************************
//* Use Modules
//**************************************************************************

//**************************************************************************
//* Include/Require Files
//**************************************************************************

//**************************************************************************
//* Name Space
//**************************************************************************

//**************************************************************************
//* Public Functions
//**************************************************************************

function LsDistance( $x, $y, $z )
{
    $r = sqrt( doubleval($x)*doubleval($x) +
                 doubleval($y)*doubleval($y) +
                   doubleval($z)*doubleval($z) );
}

//**************************************************************************
//************************ Classes *****************************************
//**************************************************************************


class LsPoint
{
    //*************************************************************************
    //*************************************************************************
    // Public Members
    //*************************************************************************
    //*************************************************************************
    public $x = 0.0;
    public $y = 0.0;
    public $z = 0.0;
}


/**
 * @abstract Simple vector class
 */
class LsVector
{
    //*************************************************************************
    //*************************************************************************
    // Public Members
    //*************************************************************************
    //*************************************************************************
    public $i = 1.0;
    public $j = 1.0;
    public $k = 1.0;

    //*************************************************************************
    //*************************************************************************
    // Constants
    //*************************************************************************
    //*************************************************************************

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
    public function Mag()
    {
        return sqrt( doubleval($this->i) * doubleval($this->i) +
                     doubleval($this->j) * doubleval($this->j) +
                     doubleval($this->k) * doubleval($this->k) );
    }

    public function Normalize()
    {
        $mag = doubleval($this->Mag());

        $this->i = doubleval($this->i) / $mag;
        $this->j = doubleval($this->j) / $mag;
        $this->k = doubleval($this->k) / $mag;
    }

    public function Scale( $s )
    {
        $this->i *= doubleval( $s );
        $this->j *= doubleval( $s );
        $this->k *= doubleval( $s );
    }

    public function Add( $a )
    {
        $r = new LsVector;

        $r->i = $this->i + $a->i;
        $r->j = $this->j + $a->j;
        $r->k = $this->k + $a->k;

        return $r;
    }

    public function Subtract( $a )
    {
        $r = new LsVector;

        $r->i = $this->i - $a->i;
        $r->j = $this->j - $a->j;
        $r->k = $this->k - $a->k;

        return $r;
    }

    /**
     *
     * @param LsPoint $p1 Starting point of the vector.
     * @param LsPoint $p2 Ending point of the vector.
     * @abstract Creates a vector from p1->p2 which is the result
     *            of p2 - p1.
     */
    public function PointsToVector( $p1, $p2 )
    {
        $this->i = $p2->x - $p1->x;
        $this->j = $p2->y - $p1->y;
        $this->k = $p2->z - $p1->z;
    }

    public function DotProduct( $a )
    {
        return( $this->i*$a->i + $this->j*$a->j + $this->k*$a->k );
    }

    public function ThisCrossArg( $a )
    {
        $r = new LsVector;

        $r->i = (doubleval($this->j) * doubleval($a->k)) - (doubleval($this->k) * doubleval($a->j));
        $r->j = (doubleval($this->k) * doubleval($a->i)) - (doubleval($this->i) * doubleval($a->k));
        $r->k = (doubleval($this->i) * doubleval($a->j)) - (doubleval($this->j) * doubleval($a->i));

        return $r;
    }

    public function AngleBetween( $a )
    {
        return( acos( $this->DotProduct($a) / ($this->Mag() * $a->Mag()) ) );
    }

    /**
     *
     * @param LsVector $a
     * @param bool $bReversed Returns true if the vector is reversed.
     *                         Returns false if in the same direction as 'this'
     */
    public function ProjectArgOntoThis( $a, &$bReversed )
    {
        $r = clone $this;

        $scale = $this->DotProduct($a) / $this->DotProduct($this);

        if( $scale < doubleval(0.0) )
        {
            $bReversed = true;
        }
        else
        {
            $bReversed = false;
        }

        $r->Scale( $scale );

        return $r;
    }

    public function Dspy( $bReturnString=false )
    {
        $str = "i=".$this->i." j=".$this->j." k=".$this->k;

        if( $bReturnString === true )
        {
            return $str;
        }

        echo $str."\n";
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

} /* End Class Definition */


/**
 * @abstract Plane object from the eq: Ax + By + Cz = D
 *            We store the useful sqrt(A^2 + B^2 + C^2) stored for performance.
 */
class LsPlane
{
    //*************************************************************************
    //*************************************************************************
    // Public Members
    //*************************************************************************
    //*************************************************************************
    public $A = 0.0;
    public $B = 0.0;
    public $C = 0.0;
    public $D = 0.0;
    public $S = 0.0; /* the most useful Square of the Sums or sqrt(A^2 + B^2 + C^2) */
                     /* stored for performance... */

    /**
     *
     * @param LsPoint $p This is a point in the plane.
     * @param LsVector $n This is the normal to the point 'p'.
     * @abstract This creates a plane which intersect the point 'p' and is normal to
     *            the vector 'n'.
     */
    public function PointNormal( LsPoint $p, LsVector $n )
    {
        $this->A = doubleval( $n->i );
        $this->B = doubleval( $n->j );
        $this->C = doubleval( $n->k );
        $this->D = doubleval( -( $n->i*$p->x + $n->j*$p->y + $n->k*$p->z ) );
        $this->S = doubleval( 0.0 );
    }

    /**
     *
     * @param LsPoint $p0
     * @param LsPoint $p1
     * @param LsPoint $p2
     * @abstract Converts 3 points in a polygon into a planar object.
     */
    public function PolygonToPlane( LsPoint $p0, LsPoint $p1, LsPoint $p2 )
    {
        $n  = new LsVector();
        $v1 = new LsVector();
        $v2 = new LsVector();

        $v1->PointsToVector( $p0, $p1 );
        $v2->PointsToVector( $p0, $p2 );

        //
        // Left-hand cross from v2 into v1 (effectively 'unscrews' v1 towards v2)
        //
        $n = $v2->ThisCrossArg( $v1 );

        $this->PointNormal( $p0, $n );
    }

    public function UpdateSquareOfTheSums()
    {
        $this->S = LsDistance( $this->A, $this->B, $this->C );
    }

    public function PointDistanceToPlane( LsPoint $p )
    {
        return( fabs( doubleval($this->A) * doubleval($p->x) +
                      doubleval($this->B) * doubleval($p->y) +
                      doubleval($this->C) * doubleval($p->z) +
                      doubleval($this->D) / doubleval($this->S) ) );
    }
}
