#!/usr/bin/env php
<?php
/**
 * Script to query / enable / disable forced caching of tables in the query results cache
 * Needs Oracle 11g R2 or later
 *
 * @todo allow user to specify a list of tables not to operate on
 * @todo faster operation: do a select on USER_TABLES instead of uding ezdbschema
 */

require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array(
    'description' => "...",
    'use-session'    => false,
    'use-modules'    => false,
    'use-extensions' => true )
);
$script->startup();
$options = $script->getOptions(
    "[f|forcecache][n|nocache]",
    "",
    array( 'nocache' => 'set to no-cache',
           'forcecache' => 'set to force cache' )
);
$script->initialize();

if ( $options['nocache'] )
{
    $mode = 'MANUAL';
}
elseif ( $options['forcecache'] )
{
    $mode = 'FORCE';
}
else
{
    $mode = 'QUERY';
}

$cli->output( 'Retrieving database schema definition...' );
// retrieve live db schema, without transforming it to "generic" version
$db = eZDB::instance();
$dbSchema = eZDbSchema::instance( $db );
$liveSchema = $dbSchema->schema( array( 'format' => 'local' ) );

$cli->output( $mode == 'QUERY' ? 'Listing caching mode of tables' : 'Enabling caching for tables' );

foreach( $liveSchema as $tableName => $tableDef )
{
    if ( isset( $tableDef['fields'] ) )
    {
        if ( $mode == 'QUERY' )
        {
            $ok = $db->arrayquery( "SELECT result_cache, num_rows FROM user_tables WHERE LOWER(table_name) = '$tableName'" );
            $ok = $ok[0];
            $cli->output( str_pad( "$tableName:", 31 ) . "cache: {$ok['result_cache']}, rows: {$ok['num_rows']}" );
        }
        else
        {
            $ok = $db->query( "ALTER TABLE $tableName result_cache(MODE $mode)" );
            $cli->output( str_pad( "$tableName:", 31 ) . $mode );
        }
    }
}

$script->shutdown();
