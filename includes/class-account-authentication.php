<?php
/**
 * Request authentication for the ChatGPT backend.
 *
 * @package WebberZone\ChatGPT_Account
 */

namespace WebberZone\ChatGPT_Account;

use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Adds the ChatGPT access token and account headers to requests.
 *
 * Extends the API-key class because the AI Client registry only accepts authentication matching the provider's declared method.
 *
 * @since 1.0.0
 */
class Account_Authentication extends ApiKeyRequestAuthentication {


	/**
	 * Codex CLI version reported to the backend, which gates the model list.
	 *
	 * @var string
	 */
	const CODEX_CLIENT_VERSION = '0.133.0';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct( PROVIDER_ID );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param  Request $request The request.
	 * @return Request
	 */
	public function authenticateRequest( Request $request ): Request {
		return self::apply_headers( $request, OAuth::get_valid_tokens() );
	}

	/**
	 * Adds the authentication and Codex client headers to a request.
	 *
	 * @since 1.0.0
	 *
	 * @param  Request $request The request.
	 * @param  array   $tokens  Token data.
	 * @return Request
	 */
	public static function apply_headers( Request $request, array $tokens ) {
		return $request
			->withHeader( 'Authorization', 'Bearer ' . $tokens['access_token'] )
			->withHeader( 'ChatGPT-Account-ID', $tokens['account_id'] )
			->withHeader( 'originator', 'codex_cli_rs' )
			->withHeader( 'User-Agent', 'codex_cli_rs/' . self::client_version() . ' (WordPress)' )
			->withHeader( 'session_id', wp_generate_uuid4() );
	}

	/**
	 * Codex CLI version reported to the backend.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function client_version() {
		/**
		 * Filters the Codex CLI version reported to the ChatGPT backend.
		 *
		 * @since 1.0.0
		 *
		 * @param string $version Version string.
		 */
		return (string) apply_filters( 'wzcga_codex_client_version', self::CODEX_CLIENT_VERSION );
	}
}
