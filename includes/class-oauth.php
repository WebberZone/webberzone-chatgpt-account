<?php
/**
 * ChatGPT device-code sign-in and token refresh.
 *
 * @package WebberZone\ChatGPT_Account
 */

namespace WebberZone\ChatGPT_Account;

use RuntimeException;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Implements the OpenAI device-code flow used by `codex login --device-auth`, and token refresh.
 *
 * @since 1.0.0
 */
class OAuth {


	/**
	 * OpenAI auth issuer.
	 *
	 * @var string
	 */
	const ISSUER = 'https://auth.openai.com';

	/**
	 * Public OAuth client ID of the Codex CLI.
	 *
	 * @var string
	 */
	const CLIENT_ID = 'app_EMoamEEZ73f0CkXaXp7hrann';

	/**
	 * ChatGPT settings screen where device code login is enabled.
	 *
	 * @var string
	 */
	const SECURITY_SETTINGS_URL = 'https://chatgpt.com/#settings/Security';

	/**
	 * Lifetime of a device code, in seconds.
	 *
	 * @var int
	 */
	const FLOW_TTL = 900;

	/**
	 * Option used as a refresh lock.
	 *
	 * @var string
	 */
	const LOCK_OPTION = 'wzcga_refresh_lock';

	/**
	 * Refresh access tokens this many seconds before they expire.
	 *
	 * @var int
	 */
	const REFRESH_MARGIN = 300;

	/**
	 * Requests a device code and stores the pending flow for the current user.
	 *
	 * @since 1.0.0
	 *
	 * @return array User code, verification URL, polling interval and expiry timestamp.
	 * @throws RuntimeException When OpenAI rejects the request.
	 */
	public static function start_device_flow() {
		$response = self::post_json( self::ISSUER . '/api/accounts/deviceauth/usercode', array( 'client_id' => self::CLIENT_ID ) );
		$status   = (int) wp_remote_retrieve_response_code( $response );
		if ( 404 === $status ) {
			throw new RuntimeException(
				esc_html(
					sprintf(
					/* translators: %s: ChatGPT security settings URL. */
						__( 'Device code login is turned off for this ChatGPT account. Enable it under ChatGPT Settings → Security (%s), then try again.', 'webberzone-chatgpt-account' ),
						self::SECURITY_SETTINGS_URL
					)
				)
			);
		}
		$body = self::decode( $response, $status );

		$user_code = $body['user_code'] ?? ( $body['usercode'] ?? '' );
		if ( empty( $body['device_auth_id'] ) || '' === $user_code ) {
			throw new RuntimeException( esc_html__( 'Unexpected response when requesting a device code.', 'webberzone-chatgpt-account' ) );
		}

		$flow = array(
			'device_auth_id' => (string) $body['device_auth_id'],
			'user_code'      => (string) $user_code,
			'interval'       => max( 3, (int) ( $body['interval'] ?? 5 ) ),
			'expires_at'     => time() + self::FLOW_TTL,
			'last_poll'      => 0,
		);
		set_transient( self::flow_key(), $flow, self::FLOW_TTL );

		return array(
			'user_code'        => $flow['user_code'],
			'verification_url' => self::ISSUER . '/codex/device',
			'interval'         => $flow['interval'],
			'expires_at'       => $flow['expires_at'],
		);
	}

	/**
	 * Checks once whether the user approved the pending device code, and stores the tokens if so.
	 *
	 * @since 1.0.0
	 *
	 * @return string 'pending' or 'connected'.
	 * @throws RuntimeException When the flow expired or OpenAI returned an error.
	 */
	public static function poll_device_flow() {
		$flow = get_transient( self::flow_key() );
		if ( ! is_array( $flow ) || $flow['expires_at'] < time() ) {
			delete_transient( self::flow_key() );
			throw new RuntimeException( esc_html__( 'The sign-in code expired. Start again.', 'webberzone-chatgpt-account' ) );
		}
		if ( time() - $flow['last_poll'] < $flow['interval'] ) {
			return 'pending';
		}
		$flow['last_poll'] = time();
		set_transient( self::flow_key(), $flow, max( 1, $flow['expires_at'] - time() ) );

		$response = self::post_json(
			self::ISSUER . '/api/accounts/deviceauth/token',
			array(
				'device_auth_id' => $flow['device_auth_id'],
				'user_code'      => $flow['user_code'],
			)
		);
		$status   = (int) wp_remote_retrieve_response_code( $response );
		if ( 403 === $status || 404 === $status ) {
			return 'pending';
		}
		$body = self::decode( $response, $status );
		if ( empty( $body['authorization_code'] ) || empty( $body['code_verifier'] ) ) {
			throw new RuntimeException( esc_html__( 'Unexpected response while waiting for sign-in.', 'webberzone-chatgpt-account' ) );
		}

		// The authorization code is single-use, so drop the flow before exchanging it.
		delete_transient( self::flow_key() );

		$response = wp_remote_post(
			self::ISSUER . '/oauth/token',
			array(
				'timeout' => 20,
				'headers' => array( 'Accept' => 'application/json' ),
				'body'    => array(
					'grant_type'    => 'authorization_code',
					'code'          => $body['authorization_code'],
					'redirect_uri'  => self::ISSUER . '/deviceauth/callback',
					'client_id'     => self::CLIENT_ID,
					'code_verifier' => $body['code_verifier'],
				),
			)
		);
		self::store_tokens( self::decode( $response, (int) wp_remote_retrieve_response_code( $response ) ) );

		return 'connected';
	}

	/**
	 * Abandons the current user's pending device code.
	 *
	 * @since 1.0.0
	 */
	public static function cancel_device_flow() {
		delete_transient( self::flow_key() );
	}

	/**
	 * Returns tokens that are valid for at least a few more minutes, refreshing if needed.
	 *
	 * @since 1.0.0
	 *
	 * @return array Token data.
	 * @throws RuntimeException When not connected or the refresh fails.
	 */
	public static function get_valid_tokens() {
		$tokens = Token_Store::get();
		if ( null === $tokens ) {
			throw new RuntimeException( esc_html__( 'ChatGPT Account is not connected. Sign in under Settings → Connectors.', 'webberzone-chatgpt-account' ) );
		}
		if ( self::is_expiring( $tokens ) ) {
			$tokens = self::refresh( false );
		}
		return $tokens;
	}

	/**
	 * Refreshes the access token.
	 *
	 * Refresh tokens rotate, so only one request may refresh at a time; others wait for its result.
	 *
	 * @since 1.0.0
	 *
	 * @param  bool $force Refresh even if the current token is not about to expire.
	 * @return array Token data.
	 * @throws RuntimeException When not connected or the refresh fails.
	 */
	public static function refresh( $force ) {
		$before = Token_Store::get();
		if ( null === $before ) {
			throw new RuntimeException( esc_html__( 'ChatGPT Account is not connected.', 'webberzone-chatgpt-account' ) );
		}

		if ( ! self::acquire_lock() ) {
			for ( $i = 0; $i < 40; $i++ ) {
				usleep( 250000 );
				$current = Token_Store::get();
				if ( null !== $current && $current['access_token'] !== $before['access_token'] ) {
					return $current;
				}
				if ( ! get_option( self::LOCK_OPTION ) ) {
					break;
				}
			}
			if ( ! self::acquire_lock() ) {
				throw new RuntimeException( esc_html__( 'Timed out waiting for a ChatGPT token refresh.', 'webberzone-chatgpt-account' ) );
			}
		}

		try {
			$tokens = Token_Store::get();
			if ( null === $tokens ) {
				throw new RuntimeException( esc_html__( 'ChatGPT Account is not connected.', 'webberzone-chatgpt-account' ) );
			}
			if ( $tokens['access_token'] !== $before['access_token'] || ( ! $force && ! self::is_expiring( $tokens ) ) ) {
				return $tokens;
			}

			$response = self::post_json(
				self::ISSUER . '/oauth/token',
				array(
					'client_id'     => self::CLIENT_ID,
					'grant_type'    => 'refresh_token',
					'refresh_token' => $tokens['refresh_token'],
				)
			);
			$status   = (int) wp_remote_retrieve_response_code( $response );
			if ( 400 === $status || 401 === $status ) {
				throw new RuntimeException( esc_html__( 'Your ChatGPT session has expired or was revoked. Sign in again under Settings → Connectors.', 'webberzone-chatgpt-account' ) );
			}
			return self::store_tokens( self::decode( $response, $status ), $tokens );
		} finally {
			delete_option( self::LOCK_OPTION );
		}
	}

	/**
	 * Decodes the claims of a JWT without verifying it.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $jwt JSON Web Token.
	 * @return array Claims.
	 */
	public static function jwt_claims( $jwt ) {
		$parts = explode( '.', (string) $jwt );
		if ( count( $parts ) < 2 ) {
			return array();
		}
		$json   = base64_decode( strtr( $parts[1], '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $parts[1] ) % 4 ) % 4 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- JWT payload.
		$claims = json_decode( (string) $json, true );
		return is_array( $claims ) ? $claims : array();
	}

	/**
	 * Extracts the account details from a token response and saves them.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $body     Token endpoint response.
	 * @param  array $previous Previously stored tokens, used for fields the response omits.
	 * @return array Stored token data.
	 * @throws RuntimeException When required fields are missing.
	 */
	private static function store_tokens( array $body, array $previous = array() ) {
		$id_token = $body['id_token'] ?? ( $previous['id_token'] ?? '' );
		if ( empty( $body['access_token'] ) || '' === $id_token ) {
			throw new RuntimeException( esc_html__( 'The token response was missing required fields.', 'webberzone-chatgpt-account' ) );
		}
		$claims = self::jwt_claims( $id_token );
		$auth   = $claims['https://api.openai.com/auth'] ?? array();

		$tokens = array(
			'access_token'  => $body['access_token'],
			'refresh_token' => $body['refresh_token'] ?? ( $previous['refresh_token'] ?? '' ),
			'id_token'      => $id_token,
			'account_id'    => $auth['chatgpt_account_id'] ?? ( $previous['account_id'] ?? '' ),
			'email'         => $claims['email'] ?? ( $previous['email'] ?? '' ),
			'plan'          => $auth['chatgpt_plan_type'] ?? ( $previous['plan'] ?? '' ),
		);
		if ( '' === $tokens['refresh_token'] || '' === $tokens['account_id'] ) {
			throw new RuntimeException( esc_html__( 'The token response did not include a refresh token or ChatGPT account ID.', 'webberzone-chatgpt-account' ) );
		}
		Token_Store::save( $tokens );
		return Token_Store::get() ?? $tokens;
	}

	/**
	 * Whether the access token expires within the refresh margin.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $tokens Token data.
	 * @return bool
	 */
	private static function is_expiring( array $tokens ) {
		$exp = (int) ( self::jwt_claims( $tokens['access_token'] )['exp'] ?? 0 );
		return $exp > 0 && $exp - self::REFRESH_MARGIN <= time();
	}

	/**
	 * Takes the refresh lock. add_option() is atomic because option_name is unique.
	 *
	 * @since 1.0.0
	 *
	 * @phpstan-impure
	 *
	 * @return bool Whether the lock was acquired.
	 */
	private static function acquire_lock() {
		if ( add_option( self::LOCK_OPTION, time() + 30, '', false ) ) {
			return true;
		}
		if ( (int) get_option( self::LOCK_OPTION ) < time() ) {
			delete_option( self::LOCK_OPTION );
			return add_option( self::LOCK_OPTION, time() + 30, '', false );
		}
		return false;
	}

	/**
	 * Transient key for the current user's pending device code.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private static function flow_key() {
		return 'wzcga_flow_' . get_current_user_id();
	}

	/**
	 * Sends a JSON POST request.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $url  URL.
	 * @param  array  $data Request body.
	 * @return array HTTP response.
	 * @throws RuntimeException On transport errors.
	 */
	private static function post_json( $url, array $data ) {
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode( $data ),
			)
		);
		if ( is_wp_error( $response ) ) {
			throw new RuntimeException( esc_html( $response->get_error_message() ) );
		}
		return $response;
	}

	/**
	 * Decodes a JSON response, turning HTTP errors into exceptions.
	 *
	 * @since 1.0.0
	 *
	 * @param  array|\WP_Error $response HTTP response.
	 * @param  int             $status   HTTP status code.
	 * @return array Decoded body.
	 * @throws RuntimeException On HTTP or decoding errors.
	 */
	private static function decode( $response, $status ) {
		if ( is_wp_error( $response ) ) {
			throw new RuntimeException( esc_html( $response->get_error_message() ) );
		}
		$raw  = (string) wp_remote_retrieve_body( $response );
		$body = json_decode( $raw, true );
		if ( $status < 200 || $status >= 300 ) {
			$code   = is_array( $body ) && is_string( $body['error'] ?? null ) ? $body['error'] : '';
			$desc   = is_array( $body ) ? (string) ( $body['error_description'] ?? ( $body['error']['message'] ?? '' ) ) : '';
			$detail = trim( $code . ' ' . $desc, " \n\r\t\v\0" );
			throw new RuntimeException(
				esc_html(
					sprintf(
					/* translators: 1: HTTP status code, 2: error detail. */
						__( 'OpenAI returned HTTP %1$d. %2$s', 'webberzone-chatgpt-account' ),
						$status,
						'' !== $detail ? $detail : substr( wp_strip_all_tags( $raw ), 0, 200 )
					)
				)
			);
		}
		if ( ! is_array( $body ) ) {
			throw new RuntimeException( esc_html__( 'OpenAI returned an invalid response.', 'webberzone-chatgpt-account' ) );
		}
		return $body;
	}
}
