#!/usr/bin/env php
<?php
/**
 * NB: NEEDS ORACLE 10+
 */

require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array(
    'description' => "Reclaims unused space that Oracle allocated to CLOBs",
    'use-session'    => false,
    'use-modules'    => false,
    'use-extensions' => true )
);
$script->startup();
$options = $script->getOptions(
    "",
    "",
    array( /*'n' => 'set to no-cache'*/ )
);
$script->initialize();

$cli->output( 'Retrieving database schema definition...' );
// retrieve live db schema, without transforming it to "generic" version
$db = eZDB::instance();
$dbSchema = eZDbSchema::instance( $db );
$liveSchema = $dbSchema->schema( array( 'format' => 'local' ) );

$cli->output( 'Reclaiming unused space for CLOB columns' );
foreach( $liveSchema as $tableName => $tableDef )
{
    if ( isset( $tableDef['fields'] ) )
    {
        foreach( $tableDef['fields'] as $colName => $colDef )
        {
            if ( $colDef['type'] == 'longtext' )
            {
                $ok = $db->query( "ALTER TABLE $tableName MODIFY ($colName) (SHRINK SPACE)" );
                $cli->output( str_pad( "$tableName.$colName:", 62 ) . "OK" );
            }
        }
    }
}

$script->shutdown();
