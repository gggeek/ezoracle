#!/usr/bin/env php
<?php
/**
 * Script to convert all CLOB/LONGTEXT cols in the db to VARCHAR(4000)
 * By default it only converts those cols which have no data longer than 4000 chars
 *
 * @todo allow user to specify a list of tables not to operate on
 * @todo give user 10-secs grace period before executing: this is a dangerous operation
 */

require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array(
    'description' => "Coverts all CLO/BLONGTEXT fields in the database to VARCHAR",
    'use-session'    => false,
    'use-modules'    => false,
    'use-extensions' => true )
);
$script->startup();
$options = $script->getOptions(
    "[f|force][l|list]", //"[f|force][r|reverse]",
    "",
    array(
        'force' => 'Forces conversion of columns even when their data is longer than 4000 chars',
        'list' => 'Lists CLOB/LONGTEXT with their data length and migrabilit status'
        /*'reverse' => 'Moves converted varchar2 columns back to clob'*/ )
);
$script->initialize();

$cli->output( 'Retrieving database schema definition...' );
// retrieve live db schema, without transforming it to "generic" version
$db = eZDB::instance();
$dbSchema = eZDbSchema::instance( $db );
$liveSchema = $dbSchema->schema( array( 'format' => 'local' ) );
$dbName = $db->databaseName();

if ( $options['list'] )
    $cli->output( 'Retrieving CLOB/LONGTEXT lengths' . ( $dbName == 'oracle' ? '(in characters)' : '' ) );
else
    $cli->output( 'Converting CLOB/LONGTEXT cols to VARCHAR(4000)' );

// for every blob/longtext col, get the max length of data stored
// NB: for Oracle, results are in chars, NOT in bytes! This means a 4000 chars blob would not fit in a 4000 bytes varchar2...
foreach( $liveSchema as $tableName => $tableDef )
{
    if ( isset( $tableDef['fields'] ) )
    {
        foreach( $tableDef['fields'] as $colName => $colDef )
        {
            if ( $colDef['type'] == 'longtext' )
            {
                $max = getMaxColLength( $tableName, $colName, $db );
                if ( $options['list'] )
                {
                    $cli->output( str_pad( "$tableName.$colName:", 62 ) . $max . ( $max <= 4000 ? ' - can be migrated' : ' - can NOT be migrated' ) );
                }
                else
                {
                    if ( $max <= 4000 || $options['force'] )
                    {
                        $ok = alterBlobColToVarchar( $tableName, $colName, $colDef, $db );
                        if ( $ok === true )
                        {
                            $cli->output( str_pad( "$tableName.$colName:", 62 ) . 'migrated' );
                        }
                        else
                        {
                            $cli->output( str_pad( "$tableName.$colName:", 62 ) . 'NOT migrated: ' . $ok );
                        }
                    }
                    else
                    {
                        if ( $max > 4000 )
                        {
                            $cli->output( str_pad( "$tableName.$colName:", 62 ) . "NOT migrated: data too long ($max chars)" );
                        }
                    }
                }
            }
        }
    }
}

$script->shutdown();

/**
* @return integer|null
*/
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

/**
* @return mixed true | error msg
*/
function alterBlobColToVarchar( $tableName, $colName, $colDef, $db )
{
    switch( $db->databaseName() )
    {
        case 'mysql':
        case 'mysqli':
            /// @todo...
            $newColDef = "VARCHAR(4000)";
            if ( @$colDef['not_null'] )
            {
                $newColDef .= ' NOT NULL';
            }
            // improbable: according to http://dev.mysql.com/doc/refman/5.5/en/blob.html
            // TEXT cols can not have default on mysql
            if ( @$colDef['default'] !== null && @$colDef['default'] !== ''  && @$colDef['default'] !== false )
            {
                $newColDef .= " DEFAULT '" . str_replace( "'", "\'", $colDef['default'] ) . "'";
            }
            if ( ! $db->query( "ALTER TABLE $tableName MODIFY $colName $newColDef" ) )
            {
                var_dump( "ALTER TABLE $tableName MODIFY $colName $newColDef" );
                return "could not alter col";
            }
            return true;

        case 'oracle':
            $newColName = substr( $colName, 0, 26 ) . "_tmp";
            $newColDef = "VARCHAR2(4000)";

            // improbable: according to http://dev.mysql.com/doc/refman/5.5/en/blob.html
            // TEXT cols can not have default on mysql
            if ( @$colDef['default'] !== null && @$colDef['default'] !== '' )
            {
                $newColDef .= " DEFAULT '" . str_replace( "'", "''", $colDef['default'] ) . "'";
            }

            // we do our best not to hurt the db whatever happens
            if ( ! $db->query( "ALTER TABLE $tableName ADD $newColName $newColDef" ) )
            {
                return "could not create temp col";
            }
            if ( ! $db->query( "UPDATE $tableName SET $newColName = dbms_lob.substr( $colName, 4000, 1 )" ) )
            {
                if ( ! $db->query( "ALTER TABLE $tableName DROP COLUMN $newColName" ) )
                {
                    $cli->error( "$tableName.$colName left in inconsistent state" );
                }
                return "could not copy data into temp col";
            }
            if ( @$colDef['not_null'] )
            {
                // nb: this has to be done after data copy, or it will fail if table has rows in it and no default value
                if ( ! $db->query( "ALTER TABLE $tableName MODIFY( $newColName NOT NULL )" ) )
                {
                    if ( ! $db->query( "ALTER TABLE $tableName DROP COLUMN $newColName" ) )
                    {
                        $cli->error( "$tableName.$colName left in inconsistent state" );
                    }
                    return "could not enforce NOT NULL";
                }
            }
            if ( ! $db->query( "ALTER TABLE $tableName DROP COLUMN $colName" ) )
            {
                /// NB: here we risk loosing both old and new col... (eg. if orig col was already dropped)
                /// is it a good idea? to preserve data is our duty! so we skip dropping new col
                //if ( ! $db->query( "ALTER TABLE $tableName DROP COLUMN $newColName" ) )
                //{
                    $cli->error( "$tableName.$colName left in inconsistent state" );
                //}
                return "could not drop original col";
            }
            if ( ! $db->query( "ALTER TABLE $tableName RENAME COLUMN $newColName TO $colName" ) )
            {
                $cli->error( "$tableName.$colName left in inconsistent state" );
                return "could not rename temp col";
            }

            return true;

        default:
            return "unsupported db type: " . $db->databaseName();
    }
}