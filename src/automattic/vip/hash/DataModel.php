<?php

namespace automattic\vip\hash;

// @TODO: we should use error objects or exceptions here instead of just returning false

class DataModel {

	public function markHash( $hash, $username, $value ) {
		$file = $this->getDBDir().$hash.'-'.$username;
		if ( file_exists( $file ) ) {
			// it already exists! we don't edit/update records, we only add and retrieve them
			return false;
		}
		touch( $file );
		$result = file_put_contents( $file, $value );
		if ( !$result ) {
			return false;
		}
		return true;
	}

	public function hashFile( $file ) {
		$code = php_strip_whitespace( $file );
		if ( empty( $code ) ) {
			return false;
		}
		$hash = sha1( $code );
		return $hash;
	}

	public function getHashStatusByUser( $hash, $username ) {
		$file = $this->getDBDir().$hash.'-'.$username;
		if ( file_exists( $file ) ) {
			return file_get_contents( $file );
		}
		return false;
	}

	public function getHashStatusAllUsers( $hash ) {
		$files = scandir( $this->getDBDir() );

		if ( !$files ) {
			return false;
		}

		$results = array();
		foreach ( $files as $file ) {
			if ( substr( $file, 0, strlen( $hash ) ) == $hash ) {
				$results[] = $file;
			}
		}
		return $results;
	}

	/**
	 * @return string the folder containing hash records with a trailing slash
	 */
	public function getDBDir() {
		return $_SERVER['HOME'].'/.viphash/';
	}
} 