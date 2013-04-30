<?php

if ( ! class_exists( 'EtherpadLiteClient' ) ) {
	require PARTICIPAD_PLUGIN_DIR . 'lib/etherpad-lite-client.php';
}

/**
 * Participad Etherpad client
 *
 * Our extension of the EtherpadLiteClient base class. Reworked a bit so as to
 * function as a singleton
 *
 * @since 1.0
 */
class Participad_Client extends EtherpadLiteClient {
	protected static $instance;

	protected static $connected = false;

	/**
	 * Ensures that we've got a singleton
	 */
	public static function instance() {
		if ( empty( self::$instance ) ) {
			// Don't try to initialize with no URL
			if ( participad_api_endpoint() ) {
				self::$instance = new Participad_Client(
					participad_api_key(),
					participad_api_endpoint()
				);
			}
		}

		return self::$instance;
	}

	/**
	 * Overridden here to use WP's HTTP class, and also to provide better
	 * feedback on failed API calls
	 */
	protected function call( $function, array $arguments = array() ) {
		$query = array_merge(
			array( 'apikey' => $this->apiKey ),
			$arguments
		);

		$url = $this->baseUrl . "api/" . self::API_VERSION . "/" . $function . "?" . http_build_query( $query );

		$request = wp_remote_get( $url );

		if ( is_wp_error( $request ) || empty( $request['body'] ) ) {
			$e = new UnexpectedValueException( "Empty or No Response from the server" );
			$e->status = 'no_response';
			throw $e;
		}

		if ( 200 !== $request['response']['code'] ) {
			throw new UnexpectedValueException( "Unknown error: " . $request['response']['code'] );
		}

		$result = json_decode( $request['body'] );

		if ( $result === null ) {
			throw new UnexpectedValueException( "JSON response could not be decoded" );
		}

		return $this->handleResult($result);
	}

	/**
	 * Only have to check connection quality once per load
	 */
	public function is_connected() {
		if ( isset( $this->connected ) && false !== $this->connected ) {
			return (bool) $this->connected;
		}

		try {
			$api_test = $this->getText( 1 );
			if ( $api_test ) {
				$this->connected = 1;
				return true;
			}
		} catch ( Exception $e ) {
			if ( ! isset( $e->status ) || 'no_response' != $e->status ) {
				$this->connected = 1;
				return true;
			} else {
				$this->connected = 0;
				return false;
			}
		}
	}

	////////////////////////////
	// ADDITIONAL API METHODS //
	////////////////////////////

	/**
	 * Get the last edited date for a pad
	 */
	public function getLastEdited( $padID ){
		return $this->call("getLastEdited", array(
			"padID" => $padID
		));
	}

	/**
	 * Get the HTML content of a pad
	 */
	public function getHTML( $padID ){
		return $this->call("getHTML", array(
			"padID" => $padID
		));
	}
}
