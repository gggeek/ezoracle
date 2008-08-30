#!/usr/bin/env php
<?php
//
// ## BEGIN COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
// SOFTWARE NAME: eZ 0racle
// SOFTWARE RELEASE: 2.0.x
// COPYRIGHT NOTICE: Copyright (C) 1999-2008 eZ Systems AS
// SOFTWARE LICENSE: GNU General Public License v2.0
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0  of the GNU General
//   Public License as published by the Free Software Foundation.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of version 2.0 of the GNU General
//   Public License along with this program; if not, write to the Free
//   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301, USA.
//
//
// ## END COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
//

error_reporting( E_ALL );

$argc = count( $argv );

if ( $argc < 3 )
{
    print( "Usage: $argv[0] <username> <password>\n" );
    exit( 1 );
}

$user = $argv[1];
$password = $argv[2];

//$user = "scott";
//$password = "tiger";
$sql = "CREATE USER $user IDENTIFIED BY $password QUOTA UNLIMITED ON SYSTEM;
GRANT CREATE    SESSION   TO $user;
GRANT CREATE    TABLE     TO $user;
GRANT CREATE    TRIGGER   TO $user;
GRANT CREATE    SEQUENCE  TO $user;
GRANT CREATE    PROCEDURE TO $user;
GRANT ALTER ANY TABLE     TO $user;
GRANT ALTER ANY TRIGGER   TO $user;
GRANT ALTER ANY SEQUENCE  TO $user;
GRANT DROP  ANY TABLE     TO $user;
GRANT DROP  ANY TRIGGER   TO $user;
GRANT DROP  ANY SEQUENCE  TO $user;";

print( $sql . "\n" );

?>