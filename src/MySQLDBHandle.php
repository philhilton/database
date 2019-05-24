<?php

namespace InterFix\Database;

use DateTime;
use Exception;
use PDO;

/**
 * Class MySQLDBHandle
 * @package InterFix\Database
 */
class MySQLDBHandle extends PDOHandle {
	
	/**
	 * MySQLDBHandle constructor.
	 *
	 * @param array $args must contain either credentials (host, user,
	 * pass, database) or a previously established PDO object (dbConnection)
	 *
	 * @throws Exception
	 */
	function __construct( $args = [] ) {
		
		if ( ! isset( $args['database'] ) ) {
			$args['database'] = 'mysql';
		}
		
		parent::__construct( $args );
	}
	
	/**
	 * Returns true if the string passed in is a valid MysSQL timestamp.
	 * Checks for valid dates as well (2018-02-30 would fail, for example)
	 *
	 * @param $timestamp string A YYYY-MM-DD HH:MM:SS formatted timestamp
	 *
	 * @return bool
	 * @throws Exception
	 */
	static public function timestampIsValid( $timestamp ) {
		
		$dateTime = self::DateTimeFromMySQLTimestamp( $timestamp );
		
		if ( ! $dateTime ) {
			return false;
		}
		
		if ( $dateTime->format( 'Y-m-d H:i:s' ) !== $timestamp ) {
			return false;
		}
		
		return true;
	}
	
	/**
	 * @param $mySQLTimestamp
	 *
	 * @return bool|DateTime
	 */
	static public function DateTimeFromMySQLTimestamp( $mySQLTimestamp ) {
		return DateTime::createFromFormat( 'Y-m-d H:i:s', $mySQLTimestamp );
	}
	
	/**
	 * Returns current timestamp in MySQL YYYY-MM-DD HH:MM:SS format
	 *
	 * @return false|string
	 */
	static public function getTimestampForNow() {
		return date( 'Y-m-d H:i:s' );
	}
	
	/**
	 * @param $sql
	 * @param array $opts
	 *
	 * @return array|mixed
	 */
	public function getOneDBRowAsAssocArray( $sql, $opts = [] ) {
		$results = $this->getArrayOfAssocArrays( $sql, $opts );
		
		return isset( $results[0] ) ? $results[0] : array();
		
	}
	
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
	public function getArrayOfAssocArrays( $sql, $opts = [] ) {
		
		$this->resetQueryTimer();
		
		$sth = $this->dbConn->prepare( $sql );
		$this->processQueryBinds( $sth, $opts );
		$sth->execute();
		
		$rtn = $sth->fetchAll( PDO::FETCH_NAMED );
		
		$this->logQuery(
			$sql,
			isset( $opts['binds'] ) ? $opts['binds'] : [],
			$rtn
		);
		
		if ( isset( $opts['keyCol'] ) && is_string( $opts['keyCol'] ) ) {
			
			$newResults = [];
			foreach ( $rtn as $row ) {
				
				if ( isset( $row[ $opts['keyCol'] ] ) ) {
					$newResults[ $row[ $opts['keyCol'] ] ] = $row;
				}
			}
			
			return $newResults;
		}
		
		return $rtn;
	}
	
	
	/**
	 *
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
	public function updateSingleKeyColRecordWithAssocArray( $table, $keyCol, $keyValue, array $arr ) {
		$sql   = "UPDATE $table SET\n";
		$first = 1;
		$binds = [];
		foreach ( $arr as $key => $value ) {
			if ( ! $first ) {
				$sql .= ",\n";
			}
			$sql            .= "\t$key = :$key";
			$binds[":$key"] = $value;
			$first          = 0;
		}
		$sql                  .= "\nWHERE $keyCol = :pk_$keyCol";
		$binds[":pk_$keyCol"] = $keyValue;
		
		return $this->doSQL( $sql, [ 'binds' => $binds ] );
	}
	
	/**
	 * Perform a sql statement which does not return a result (inserts, updates, deletes, etc).
	 * This function returns true on success, false on error
	 *
	 * @param $sql
	 * @param array $opts
	 *
	 * @return bool
	 */
	public function doSQL( $sql, $opts = [] ) {
		
		$this->resetQueryTimer();
		
		$sth = $this->dbConn->prepare( $sql );
		$this->processQueryBinds( $sth, $opts );
		$rtn = $sth->execute();
		
		$this->logQuery(
			$sql,
			isset( $opts['binds'] ) ? $opts['binds'] : [],
			$rtn
		);
		
		return $rtn;
	}
	
	/**
	 * @param $table
	 * @param array $keyPairs
	 * @param array $arr
	 *
	 * @return bool
	 */
	public function updateMultiKeyColRecordWithAssocArray( $table = null, array $keyPairs = null, array $arr = [] ) {
		
		if ( ! $table ) {
			return false;
		}
		
		if ( ! $keyPairs ) {
			return false;
		}
		
		$sql = "UPDATE $table SET\n";
		
		$first = 1;
		$binds = [];
		foreach ( $arr as $key => $value ) {
			if ( ! $first ) {
				$sql .= ",\n";
			}
			$sql            .= "\t$key = :$key";
			$binds[":$key"] = $value;
			$first          = 0;
		}
		
		$sql .= "\nWHERE 1 = 1\n";
		
		foreach ( $keyPairs as $key => $value ) {
			$sql               .= "\tAND $key = :pk_$key\n";
			$binds[":pk_$key"] = $value;
		}
		
		return $this->doSQL( $sql, [ 'binds' => $binds ] );
	}
	
	/**
	 * insert the given array as a row in the given table
	 *
	 * @param $table
	 * @param array $arr
	 *
	 * @return int|string the last autoinsertid
	 */
	public function insertSingleKeyColRecordWithAssocArray( $table, array $arr = array() ) {
		$sql   = /** @lang mysql */
			"INSERT INTO $table (\n";
		$first = 1;
		$cols  = '';
		$vals  = '';
		$binds = [];
		foreach ( $arr as $key => $value ) {
			if ( ! $first ) {
				$cols .= ", ";
				$vals .= ", ";
			}
			$cols             .= $key;
			$vals             .= ":v_$key";
			$binds[":v_$key"] = $value;
			$first            = 0;
		}
		
		$sql .= "\t$cols\n) VALUES (\n\t$vals\n)";
		if ( $this->doSQL( $sql, [ 'binds' => $binds ] ) ) {
			return $this->lastAutoInsertID();
		} else {
			return 0;
		}
	}
	
	/**
	 * returns the last automatically generated primary key
	 *
	 * @return string
	 */
	public function lastAutoInsertID() {
		return $this->dbConn->lastInsertId();
	}
	
	/**
	 * @param string $table
	 * @param string $keyColumn
	 * @param mixed $keyValue
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function deleteRowsWithKeyValue( $table, $keyColumn, $keyValue ) {
		return $this->deleteRowsWithKeyValues( $table, [ $keyColumn => $keyValue ] );
	}
	
	/**
	 * @param $table
	 * @param array $keyValues
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function deleteRowsWithKeyValues( $table, array $keyValues ) {
		
		if ( ! $table ) {
			throw new Exception( 'Missing table name' );
		}
		
		if ( ! $keyValues ) {
			throw new Exception( 'Missing key value pairs. Use deleteAllTableRows to empty delete without conditions.' );
		}
		
		$sql = "DELETE FROM $table WHERE 1 = 1\n";
		
		$binds = [];
		foreach ( $keyValues as $key => $value ) {
			$sql               .= "\tAND $key = :pk_$key\n";
			$binds[":pk_$key"] = $value;
		}
		
		return $this->doSQL( $sql, [ 'binds' => $binds ] );
	}
	
	/**
	 * Remove all rows from the given table
	 *
	 * @param $table
	 *
	 * @return bool
	 */
	public function deleteAllTableRows( $table ) {
		return $this->doSQL( "DELETE FROM $table" );
	}
	
	
	/**
	 * Get the number of would-have-been-found rows in the previous
	 * query that used a limit clause. This will only work if the
	 * previous query used SQL_CALC_FOUND_ROWS
	 *
	 * @return int
	 */
	public function prevQueryFoundDBRows() {
		return $this->getOneDBValue( 'SELECT FOUND_ROWS();' );
	}
	
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
	public function getOneDBValue( $sql, $opts = [] ) {
		$results = $this->getOneDBColAsArray( $sql, $opts );
		
		return isset( $results[0] ) ? $results[0] : null;
	}
	
	/**
	 * Return an entire column as a single array of values
	 *
	 * @param $sql
	 * @param array $opts
	 *
	 * @return array
	 */
	public function getOneDBColAsArray( $sql, $opts = [] ) {
		
		$this->resetQueryTimer();
		
		$sth = $this->dbConn->prepare( $sql );
		$this->processQueryBinds( $sth, $opts );
		$sth->execute();
		
		$rtn = $sth->fetchAll( PDO::FETCH_COLUMN, 0 );
		
		$this->logQuery(
			$sql,
			isset( $opts['binds'] ) ? $opts['binds'] : [],
			$rtn
		);
		
		return $rtn;
	}
	
	/**
	 * @param $tableName
	 * @param $keyPairs
	 *
	 * @return bool|null
	 */
	public function countRowsWithKeyPairs( $tableName, $keyPairs ) {
		
		if ( ! $tableName ) {
			return false;
		}
		
		if ( ! $keyPairs ) {
			return false;
		}
		
		$sql = "
			SELECT COUNT(*)
			FROM $tableName
			WHERE 1 = 1
		";
		
		$binds = [];
		foreach ( $keyPairs as $key => $value ) {
			$sql               .= "\tAND $key = :pk_$key\n";
			$binds[":pk_$key"] = $value;
		}
		
		return $this->getOneDBValue( $sql, [ 'binds' => $binds ] );
	}
	
	/**
	 * @param $columnName
	 * @param $tableName
	 *
	 * @return bool
	 */
	public function columnExistsInTable( $columnName, $tableName ) {
		
		if ( ! $this->tableExists( $tableName ) ) {
			return false;
		}
		
		$cols     = $this->getTableColumns( $tableName );
		$colNames = array_column( $cols, 'Field' );
		
		return in_array( $columnName, $colNames );
	}
	
	/**
	 * @param $tableName
	 *
	 * @return bool
	 */
	public function tableExists( $tableName ) {
		return in_array( $tableName, $this->getTableNames() );
	}
	
	/**
	 * @return array
	 */
	public function getTableNames() {
		return $this->getOneDBColAsArray( 'show tables' );
	}
	
	/**
	 * @param $tableName
	 *
	 * @return array
	 */
	public function getTableColumns( $tableName ) {
		
		if ( ! $this->tableExists( $tableName ) ) {
			return [];
		}
		
		return $this->getArrayOfAssocArrays( "SHOW COLUMNS FROM `$tableName`" );
	}
	
	
}
