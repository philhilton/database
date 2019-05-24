<?php

namespace InterFix\Database;

use Exception;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Class PDOHandle
 *
 * Base Class for Database Handle Sub Classes
 *
 * @package InterFix\Database
 */
abstract class PDOHandle implements PDOHandleInterface {
	
	/** @var PDO database connection handle */
	protected $dbConn;
	
	/**
	 * @var string debug string containing connection details
	 */
	protected $connectionDetails;
	
	/**
	 * @var bool to display or suppress error messages (including potentially revealing sql code)
	 */
	protected $debug;
	
	/**
	 * @var bool $profilingEnabled
	 * true if profiling enabled, false otherwise
	 */
	protected $profilingEnabled;
	
	/**
	 * @var bool $profilingLogResultsEnabled
	 * true if profiling should log results, false otherwise
	 */
	protected $profilingLogResultsEnabled;
	
	/**
	 * @var float $startTime time when profiling was last enabled
	 * Only populated if profiling is enabled, null otherwise
	 */
	protected $startTime;
	
	/**
	 * @var array $queryLog array of queries and how long they took
	 * Only populated if profiling is enabled
	 */
	protected $queryLog;
	
	/**
	 * @var float $queryTimer
	 * holds the start time of a query being timed
	 */
	protected $queryTimer;
	
	
	/**
	 * PDOHandle constructor.
	 *
	 * @param array $args
	 *
	 * @throws Exception
	 */
	public function __construct( $args = [] ) {
		
		$this->debug             = isset( $args['debug'] ) ? $args['debug'] : false;
		$this->connectionDetails = 'not connected';
		
		if ( isset( $args['profilingEnabled'] ) && $args['profilingEnabled'] ) {
			$this->enableProfiling( true );
		} else {
			$this->enableProfiling( false );
		}
		
		if ( isset( $args['profilingLogResultsEnabled'] ) && $args['profilingLogResultsEnabled'] ) {
			$this->enableProfilingResultLogging( true );
		} else {
			$this->enableProfilingResultLogging( false );
		}
		
		$this->queryTimer = null;
		$this->queryLog   = [];
		
		if ( isset( $args['dbConnection'] ) ) {
			$this->setConn( $args['dbConnection'] );
			$this->connectionDetails = 'Using provided dbConnection PDO object';
			
			return;
		}
		
		$type     = isset( $args['type'] ) ? $args['type'] : 'mysql';
		$host     = isset( $args['host'] ) ? $args['host'] : 'localhost';
		$user     = isset( $args['user'] ) ? $args['user'] : 'anonymous';
		$pass     = isset( $args['pass'] ) ? $args['pass'] : '';
		$database = isset( $args['database'] ) ? $args['database'] : 'mysql';
		
		try {
			$this->connectionDetails = "\$pdoObj = new PDO(\"$type:host=$host;dbname=$database\", $user, ******);";
			$pdoObj                  = new PDO( "$type:host=$host;dbname=$database", $user, $pass );
			$pdoObj->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
			$this->setConn( $pdoObj );
		} catch ( PDOException $e ) {
			
			if ( $this->debug ) {
				print "Error!: " . PDOUtil::getPDOExceptionSimpleErrorMessage( $e );
				die();
			}
			
			throw $e;
		}
		
	}
	
	/**
	 * @param bool $on
	 *
	 * @return bool
	 */
	public function enableProfiling( $on = true ) {
		
		if ( $on ) {
			$this->profilingEnabled = true;
			$this->startTime        = microtime( true );
			
			return true;
		}
		
		$this->profilingEnabled = false;
		$this->startTime        = null;
		
		return false;
	}
	
	/**
	 * @param bool $on
	 *
	 * @return bool
	 */
	public function enableProfilingResultLogging( $on = true ) {
		
		if ( $on ) {
			$this->profilingLogResultsEnabled = true;
			
			return true;
		}
		
		$this->profilingLogResultsEnabled = false;
		
		return false;
	}
	
	/**
	 * Update/set/change PDO connection
	 *
	 * @param $dbConnection
	 */
	public function setConn( $dbConnection ) {
		$this->dbConn = $dbConnection;
	}
	
	/**
	 * return the PDO connection object
	 *
	 * @return null|PDO
	 */
	public function getDBConn() {
		return isset( $this->dbConn ) ? $this->dbConn : null;
	}
	
	/**
	 * return the connection identifier
	 *
	 * @return string
	 */
	public function getConnectionDetails() {
		return $this->connectionDetails;
	}
	
	/**
	 * escape strings, make then safe for sql insertion
	 *
	 * $safePost = $dbh->quote($_POST); // entire post array
	 *
	 * $safeBio = $dbh->quote($_POST['biography']);
	 *
	 * @param array[]|mixed[]|string $var the data to escape/quoted
	 * @param bool $wrap if true then include single quotes around data
	 *
	 * @return array|bool|string data ready for safe database insertion
	 */
	public function quote( $var, $wrap = true ) {
		if ( ! isset( $var ) ) {
			return "NULL";
		}
		if ( is_null( $var ) ) {
			return "NULL";
		}
		# I'm not sure why this is commented out... PH
		#if (is_array($var)) return $this->array_map_recursive(array($this, 'quote'), $var);
		if ( is_array( $var ) ) {
			return array_map( array( $this, 'quote' ), $var );
		}
		if ( $wrap ) {
			return $this->dbConn->quote( $var );
		}
		
		return substr( substr( $this->dbConn->quote( $var ), 0, - 1 ), 1 );
	}
	
	/**
	 * @return int
	 */
	public function getStartTime() {
		return $this->startTime;
	}
	
	/**
	 * @param bool $asPlainText
	 *
	 * @return string
	 */
	public function renderQueryLog( $asPlainText = false ) {
		
		if ( ! $this->profilingIsEnabled() ) {
			return "Profiling is not enabled.";
		}
		
		if ( $asPlainText ) {
			return print_r( $this->queryLog, true );
		}
		
		$incResults       = $this->profilingLogResultsIsEnabled();
		$resultsColHeader = $incResults ? '<th>Results</th>' : '';
		
		$total = 0;
		$table = "
			<table class='dbQueryLogDatatable table table-striped'>
				<thead>
					<tr>
						<th>Start</th>
						<!-- th>End</th -->
						<th>Duration</th>
						<th>SQL</th>
						<th>Binds</th>
						$resultsColHeader
					</tr>
				</thead>
				<tbody>
		";
		
		$i = 0;
		foreach ( $this->queryLog as $q ) {
			
			$i ++;
			
			$total += $q['duration'];
			
			$start    = round( $q['startTime'] - $this->startTime, 5 );
			$end      = round( $q['endTime'] - $this->startTime, 5 );
			$duration = round( $q['duration'], 5 );
			
			// remove blank lines in sql
			$sql = preg_replace( '/^[ \t]*[\r\n]+/m', '', $q['sql'] );
			
			// un-indent sql based on leading whitepsace on first line
			$matches    = [];
			$foundMatch = preg_match( "/^(\s+).*/", $sql, $matches );
			if ( $foundMatch ) {
				// $matches[1]
				$sql = preg_replace( "/^{$matches[1]}/m", '', $sql );
			}
			
			$sql = htmlentities( $sql, ENT_QUOTES );
			
			$binds = '';
			if ( isset( $q['binds'] ) && $q['binds'] ) {
				
				$binds = print_r( $q['binds'], true );
				
				// remove first 2 and last lines of binds
				$binds = preg_replace( '/^.+\n/', '', $binds );
				$binds = preg_replace( '/^.+\n/', '', $binds );
				$binds = preg_replace( '/\n\)\n$/', '', $binds );
				
				// un-indent binds print_r output
				$binds = preg_replace( '/^    /m', '', $binds );
				
				$binds = htmlentities( "$binds", ENT_QUOTES );
				
			}
			
			$results = '[no data logged]';
			if ( $incResults && isset( $q['results'] ) ) {
				
				if ( is_null( $q['results'] ) ) {
					
					$results .= 'NULL';
					
				} elseif ( is_array( $q['results'] ) ) {
					
					if ( ! $q['results'] ) {
						$results = "[empty array]";
					} else {
						
						$divID   = "dbQueryLogDatatable-resultContentDiv-$i";
						$results = "
							<button
								class='btn btn-sm btn btn-outline-info'
								onClick=\"modalContentFromWrapperID('$divID', 'Result Array')\"
							>View Array</button>
							<div id='$divID' style='display: none;'>
								<div class='table-responsive'>
									{$this->arrayToTable(
										$q['results'],
										false,
										'NULL',
										'table-striped table-bordered table-hover table-sm'
									)}
								</div>
							</div>
						";
					}
					
				} else {
					$results = htmlentities( $q['results'], ENT_QUOTES );
				}
			}
			
			$resultCol = $incResults
				? "<td class='dbQueryLogDatatable-resultsTD'>$results</td>"
				: 'no incResults';
			
			$table .= "
				<tr>
					<td>$start</td>
					<!-- td>$end</td -->
					<td>$duration</td>
					<td class='dbQueryLogDatatable-sqlTD'>$sql</td>
					<td class='dbQueryLogDatatable-bindsTD'>$binds</td>
					$resultCol
				</tr>
			";
		}
		
		$table .= "
				</tbody>
			</table>
		";
		
		$total = round( $total, 5 );
		$table .= "
			<br>
			Total Query Time: $total seconds<br>
		";
		
		return $table;
	}
	
	/**
	 * @return bool true if profiling is enabled, false otherwise
	 */
	public function profilingIsEnabled() {
		return $this->profilingEnabled;
	}
	
	/**
	 * @return bool
	 */
	public function profilingLogResultsIsEnabled() {
		return $this->profilingLogResultsEnabled;
	}
	
	/**
	 * Translate a result array into a HTML table
	 *
	 * @author  Aidan Lister <aidan@php.net>
	 * @version 1.3.2
	 * @link    http://aidanlister.com/2004/04/converting-arrays-to-human-readable-tables/
	 *
	 * @param   array $array The result (numericaly keyed, associative inner) array.
	 * @param   bool $recursive Recursively generate tables for multi-dimensional arrays
	 * @param   string $null String to output for blank cells
	 * @param   string $tableClass String for table tag class attribute
	 * @param   string $theadClass String for thead tag class attribute
	 *
	 * @return bool|string
	 */
	private function arrayToTable( $array, $recursive = false, $null = '&nbsp;', $tableClass = '', $theadClass = '' ) {
		// Sanity check
		if ( empty( $array ) || ! is_array( $array ) ) {
			return false;
		}
		
		if ( ! isset( $array[0] ) || ! is_array( $array[0] ) ) {
			$array = array( $array );
		}
		
		// Start the table
		$table = "<table class='$tableClass'>\n";
		
		// The header
		$table .= "\t<thead class='$theadClass'>\n\t\t<tr>\n";
		// Take the keys from the first row as the headings
		foreach ( array_keys( $array[0] ) as $heading ) {
			$table .= "\t\t\t<th>$heading</th>\n";
		}
		$table .= "\t\t</tr>\n\t</thead>\n\t<tbody>\n";
		
		// The body
		foreach ( $array as $row ) {
			$table .= "\t\t<tr>\n";
			foreach ( $row as $cell ) {
				$table .= "\t\t\t<td>";
				
				// Cast objects
				if ( is_object( $cell ) ) {
					$cell = (array) $cell;
				}
				
				if ( $recursive === true && is_array( $cell ) && ! empty( $cell ) ) {
					// Recursive mode
					$table .= "\n" . self::arrayToTable( $cell, true, true ) . "\n";
				} else {
					/** @noinspection PhpParamsInspection */
					$table .= ( strlen( $cell ) > 0 )
						? htmlspecialchars( (string) $cell )
						: $null;
				}
				
				$table .= "</td>\n";
			}
			
			$table .= "\t\t</tr>\n";
		}
		
		$table .= "\t<tbody>\n</table>";
		
		return $table;
	}
	
	/**
	 * @param PDOStatement|null $sth
	 * @param array $opts
	 */
	protected function processQueryBinds( PDOStatement $sth = null, $opts = [] ) {
		
		if ( ! $sth ) {
			return;
		}
		if ( ! isset( $opts['binds'] ) ) {
			return;
		}
		
		foreach ( $opts['binds'] as $alias => $value ) {
			$sth->bindValue( $alias, $value );
		}
		
		return;
	}
	
	/**
	 * @param $sql
	 * @param $binds
	 * @param $startTime
	 * @param $endTime
	 * @param null $results
	 *
	 * @return bool
	 */
	protected function logQuery( $sql, $binds = null, $results = null, $startTime = null, $endTime = null ) {
		
		if ( ! $this->profilingIsEnabled() ) {
			return false;
		}
		
		if ( $startTime === null && $this->queryTimer !== null ) {
			$startTime = $this->queryTimer;
		}
		
		if ( $endTime === null ) {
			$endTime = microtime( true );
		}
		
		if ( empty( $sql ) ) {
			return false;
		}
		
		if ( empty( $sql ) ) {
			return false;
		}
		
		if ( ( ! is_float( $startTime ) ) || ( ! is_float( $endTime ) ) ) {
			return false;
		}
		
		$this->queryLog[] = [
			'sql'       => $sql,
			'binds'     => is_array( $binds ) ? $binds : [],
			'startTime' => $startTime,
			'endTime'   => $endTime,
			'duration'  => $endTime - $startTime,
			'results'   => $this->profilingLogResultsIsEnabled() ? $results : null,
		];
		
		return true;
	}
	
	/**
	 * @return bool
	 */
	protected function resetQueryTimer() {
		
		if ( ! $this->profilingIsEnabled() ) {
			return false;
		}
		
		$this->queryTimer = microtime( true );
		
		return true;
	}
	
	/**
	 * Returns the file name, function name, and line number which called your function
	 * (not this function, then one that called it to begin with)
	 * @return string
	 */

	/** @noinspection PhpUnusedPrivateMethodInspection */
	protected function debugCallingFunction() {
		$file       = 'n/a';
		$func       = 'n/a';
		$line       = 'n/a';
		$debugTrace = debug_backtrace();
		if ( isset( $debugTrace[1] ) ) {
			$file = $debugTrace[1]['file'] ? $debugTrace[1]['file'] : 'n/a';
			$line = $debugTrace[1]['line'] ? $debugTrace[1]['line'] : 'n/a';
		}
		if ( isset( $debugTrace[2] ) ) {
			$func = $debugTrace[2]['function'] ? $debugTrace[2]['function'] : 'n/a';
		}
		
		return "<pre>\n$file, $func, $line\n</pre>";
	}
	
	/**
	 * like array_map, but recurses in to arrays of arrays
	 * http://php.net/manual/en/function.array-map.php, comment by qermey
	 *
	 * @param $fn
	 * @param $arr
	 *
	 * @return array
	 *
	 * /** @noinspection PhpUnusedPrivateMethodInspection
	 */
	private function array_map_recursive( $fn, $arr ) {
		$rarr = array();
		foreach ( $arr as $k => $v ) {
			$rarr[ $k ] = is_array( $v ) ?
				$this->array_map_recursive( $fn, $v ) :
				$fn( $v );
		}
		
		return $rarr;
	}
	
}