#!/usr/bin/env php
<?php
/**
 * Script to query / enable / disable caching of CLOB fields (Oracle does not cache them in buffer cache by default)
 *
 * @todo allow user to specify a list of fields/tables not to operate on
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
    "[a|alwayscache][e|readonlycache][n|nocache]",
    "",
    array( 'nocache' => 'set to no-cache',
           'readonlycache' => 'set to read-only-cache',
           'alwayscache' => 'set to always cache (read + write)' )
);
$script->initialize();

if ( $options['nocache'] )
{
    $mode = 'NOCACHE';
}
elseif ( $options['readonlycache'] )
{
    $mode = 'CACHE READS';
}
elseif ( $options['alwayscache'] )
{
    $mode = 'CACHE';
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


$cli->output( $mode == 'QUERY' ? 'Listing caching mode of CLOB columns' : 'Enabling caching for CLOB columns' );

foreach( $liveSchema as $tableName => $tableDef )
{
    if ( isset( $tableDef['fields'] ) )
    {
        foreach( $tableDef['fields'] as $colName => $colDef )
        {
            if ( $colDef['type'] == 'longtext' )
            {
                if ( $mode == 'QUERY' )
                {
                    $ok = $db->arrayquery( "SELECT cache, logging, in_row, chunk FROM all_lobs WHERE LOWER(table_name) = '$tableName' AND LOWER(column_name) = '$colName'" );
                    $ok = $ok[0];
                    $cli->output( str_pad( "$tableName.$colName:", 62 ) . "cache: {$ok['cache']}, logging: {$ok['logging']}, store_in_row: {$ok['in_row']}, chunk: {$ok['chunk']}" );
                }
                else
                {
                    $ok = $db->query( "ALTER TABLE $tableName MODIFY LOB ($colName) ($mode)" );
                    $cli->output( str_pad( "$tableName.$colName:", 62 ) . $mode );
                }
            }
        }
    }
}

$script->shutdown();
