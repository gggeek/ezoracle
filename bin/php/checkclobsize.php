#!/usr/bin/env php
<?php
/**
 * Script to query max length of data in CLOB/LONGTEXT fields in the db
 */

require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array(
    'description' => "Lists the maximum size of data in all CLOB/LONGTEXT fields in the database",
    'use-session'    => false,
    'use-modules'    => false,
    'use-extensions' => true )
);
$script->startup();
$options = $script->getOptions(
    "[a]",
    "",
    array( 'a' => 'List all tables with CLOB/LONGTEXT fields, not only the ones with data in them' )
);
$script->initialize();

$cli->output( 'Retrieving database schema definition...' );
// retrieve live db schema, without transforming it to "generic" version
$db = eZDB::instance();
$dbSchema = eZDbSchema::instance( $db );
$liveSchema = $dbSchema->schema( array( 'format' => 'local' ) );
$dbName = $db->databaseName();

$cli->output( 'Retrieving CLOB/LONGTEXT lengths' . ( $dbName == 'oracle' ? '(in characters)' : '' ) . ( $options['a'] ? ' of all columns' : '' ) );

// for every blob col, get the max length of data stored
// NB: results are in chars, NOT in bytes! This means a 4000 chars blob would not fit in a 4000 bytes varchar2
foreach( $liveSchema as $tableName => $tableDef )
{
    if ( isset( $tableDef['fields'] ) )
    {
        foreach( $tableDef['fields'] as $colName => $colDef )
        {
            if ( $colDef['type'] == 'longtext' )
            {
                $max = getMaxColLength( $tableName, $colName, $db );
                if ( $options['a'] || $max )
                {
                    $cli->output( str_pad( "$tableName.$colName:", 62 ) . $max );
                }
            }
        }
    }
}

$script->shutdown();

function getMaxColLength( $tableName, $colName, $db )
{
    switch( $db->databaseName() )
    {
        case 'mysql':
        case 'mysqli':
            // bytes
            $sql = "SELECT MAX( LENGTH( $colName ) ) AS maxsize FROM $tableName";
            break;
        case 'oracle':
            // chars
            /// @todo !important use bind params for better speed
            $sql = "SELECT MAX( DBMS_LOB.GETLENGTH( $colName ) ) AS maxsize FROM $tableName";
        break;
    }
    $out = $db->arrayquery( $sql );
    if ( is_array( $out ) && count( $out ) )
    {
        return $out[0]['maxsize'];
    }
    return null;
}