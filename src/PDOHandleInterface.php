<?php

namespace InterFix\Database;


/**
 * Interface PDOHandleInterface
 *
 * Defined methods which must be declared for database specific PDOHandle subclasses.
 *
 * @package InterFix\Database
 */
interface PDOHandleInterface {
	
	/**
	 * @param $sql
	 * @param array $opts
	 *
	 * @return array|mixed
	 */
	public function getOneDBRowAsAssocArray( $sql, $opts = [] );
	
	/**
	 * Return an array of associative arrays of all data
	 *
	 * $person = $dbh->getOneDBRowAsAssocArray('SELECT fname, lname FROM people WHERE id = 12345');
	 *
	 * echo "{$person['fname']} {$person['lname']} rocks!";
	 *
	 * @param $sql
	 * @param array $opts
	 *
	 * @return array
	 */
	public function getArrayOfAssocArrays( $sql, $opts = [] );
	
	/**
	 * Given a table name, key column, key value, and a hash reference
	 * this will update every record in the table matching the key
	 * value in the key column with every key/value pair in the hash.
	 * Sensitive values MUST be quoted for SQL injection BEFORE use.
	 * (or use binds)
	 * usage:
	 * $arr = ['fullname' => $dbh->quote("Bobby Fisher"), 'age' => 25];
	 * updateSingleKeyColRecordWithAssocArray('users', 'userid', 622, $arr);
	 *
	 * @param $table
	 * @param $keyCol
	 * @param $keyValue
	 * @param array $arr
	 *
	 * @return bool
	 */
	public function updateSingleKeyColRecordWithAssocArray( $table, $keyCol, $keyValue, array $arr );
	
	/**
	 * Perform a sql statement which does not return a result (inserts, updates, deletes, etc).
	 * This function returns true on success, false on error
	 *
	 * @param $sql
	 * @param array $opts
	 *
	 * @return bool
	 */
	public function doSQL( $sql, $opts = [] );
	
	/**
	 * @param $table
	 * @param array $keyPairs
	 * @param array $arr
	 *
	 * @return bool
	 */
	public function updateMultiKeyColRecordWithAssocArray( $table = null, array $keyPairs = null, array $arr = [] );
	
	/**
	 * insert the given array as a row in the given table
	 *
	 * @param $table
	 * @param array $arr
	 *
	 * @return int|string the last autoinsertid
	 */
	public function insertSingleKeyColRecordWithAssocArray( $table, array $arr = array() );
	
	/**
	 * @param string $table
	 * @param string $keyColumn
	 * @param mixed $keyValue
	 *
	 * @return bool
	 */
	public function deleteRowsWithKeyValue( $table, $keyColumn, $keyValue );
	
	/**
	 * @param $table
	 * @param array $keyValues
	 *
	 * @return bool
	 */
	public function deleteRowsWithKeyValues( $table, array $keyValues );
	
	/**
	 * Remove all rows from the given table
	 *
	 * @param $table
	 *
	 * @return bool
	 */
	public function deleteAllTableRows( $table );
	
	/**
	 * Return a single value from a query
	 *
	 * $rowCount = $dbh->getOneDBValue("SELECT COUNT(*) FROM table");
	 *
	 * @param $sql
	 * @param array $opts
	 *
	 * @return null
	 */
	public function getOneDBValue( $sql, $opts = [] );
	
	/**
	 * Return an entire column as a single array of values
	 *
	 * @param $sql
	 * @param array $opts
	 *
	 * @return array
	 */
	public function getOneDBColAsArray( $sql, $opts = [] );
	
	/**
	 * @param $tableName
	 * @param $keyPairs
	 *
	 * @return bool|null
	 */
	public function countRowsWithKeyPairs( $tableName, $keyPairs );
	
	/**
	 * @param $columnName
	 * @param $tableName
	 *
	 * @return bool
	 */
	public function columnExistsInTable( $columnName, $tableName );
	
	/**
	 * @param $tableName
	 *
	 * @return bool
	 */
	public function tableExists( $tableName );
	
	/**
	 * @return array
	 */
	public function getTableNames();
	
	/**
	 * @param $tableName
	 *
	 * @return array
	 */
	public function getTableColumns( $tableName );
}