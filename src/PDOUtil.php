<?php


namespace InterFix\Database;


use Exception;

/**
 * Class PDOUtil
 * @package InterFix\Database
 */
class PDOUtil {
	
	/**
	 * The point of this method is to translate non-user-friendly PDO/SQL Exception messages
	 * into something users can understand. In cases where the problem is technical in nature
	 * and/or beyond the users control (SQL formatting, database issue), the full Exception
	 * message is returned in the hopes that the end user will pass it on to a developer.
	 *
	 * @param Exception $e
	 *
	 * @return string
	 * @throws Exception
	 */
	public static function getPDOExceptionSimpleErrorMessage( Exception $e ) {
		
		if ( ! $e ) {
			throw new Exception( 'Missing Exception' );
		}
		
		// see https://www.ibm.com/support/knowledgecenter/en/SSEPEK_10.0.0/codes/src/tpc/db2z_sqlstatevalues.html
		$knownErrorCodes = [
			'22001' => 'The data provided is too large.',
			'22003' => 'A number provided is out of range (too high or low) for the space allotted in the database.',
			'22004' => 'A required value is missing.', // A null value is not allowed. (diff than 23502?)
			'23502' => 'A required value is missing.', // null insert/update where not allowed (diff than 22004?)
		];
		
		$code = $e->getCode();
		
		if ( isset( $knownErrorCodes[ $code ] ) ) {
			return $knownErrorCodes[ $code ];
		}
		
		return $e->getMessage();
	}
	
}