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
class OAuth extends OAuth_Client {


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
	 * Refresh access tokens this many seconds before they expire.
	 *
	 * @var int
	 */
	const REFRESH_MARGIN = 300;

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @throws RuntimeException When OpenAI rejects the request.
	 */
	public static function start_device_flow() {
		$response = static::post_json( self::ISSUER . '/api/accounts/deviceauth/usercode', array( 'client_id' => self::CLIENT_ID ) );
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
		$body = static::decode( $response, $status );

		$user_code = $body['user_code'] ?? ( $body['usercode'] ?? '' );
		if ( empty( $body['device_auth_id'] ) || '' === $user_code ) {
			throw new RuntimeException( esc_html__( 'Unexpected response when requesting a device code.', 'webberzone-chatgpt-account' ) );
		}

		$flow = array(
			'device_auth_id' => (string) $body['device_auth_id'],
			'user_code'      => (string) $user_code,
			'interval'       => max( 3, (int) ( $body['interval'] ?? 5 ) ),
			'expires_at'     => time() + static::FLOW_TTL,
			'last_poll'      => 0,
		);
		set_transient( static::flow_key(), $flow, static::FLOW_TTL );

		return array(
			'user_code'        => $flow['user_code'],
			'verification_url' => self::ISSUER . '/codex/device',
			'interval'         => $flow['interval'],
			'expires_at'       => $flow['expires_at'],
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @throws RuntimeException When the flow expired or OpenAI returned an error.
	 */
	public static function poll_device_flow() {
		$flow = get_transient( static::flow_key() );
		if ( ! is_array( $flow ) || $flow['expires_at'] < time() ) {
			delete_transient( static::flow_key() );
			throw new RuntimeException( esc_html__( 'The sign-in code expired. Start again.', 'webberzone-chatgpt-account' ) );
		}
		if ( time() - $flow['last_poll'] < $flow['interval'] ) {
			return 'pending';
		}
		$flow['last_poll'] = time();
		set_transient( static::flow_key(), $flow, max( 1, $flow['expires_at'] - time() ) );

		$response = static::post_json(
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
		$body = static::decode( $response, $status );
		if ( empty( $body['authorization_code'] ) || empty( $body['code_verifier'] ) ) {
			throw new RuntimeException( esc_html__( 'Unexpected response while waiting for sign-in.', 'webberzone-chatgpt-account' ) );
		}

		// The authorization code is single-use, so drop the flow before exchanging it.
		delete_transient( static::flow_key() );

		$response = static::post_form(
			self::ISSUER . '/oauth/token',
			array(
				'grant_type'    => 'authorization_code',
				'code'          => $body['authorization_code'],
				'redirect_uri'  => self::ISSUER . '/deviceauth/callback',
				'client_id'     => self::CLIENT_ID,
				'code_verifier' => $body['code_verifier'],
			)
		);
		static::store_tokens( static::decode( $response ) );

		return 'connected';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param array $tokens Current token data.
	 */
	protected static function request_refresh( array $tokens ) {
		return static::post_json(
			self::ISSUER . '/oauth/token',
			array(
				'client_id'     => self::CLIENT_ID,
				'grant_type'    => 'refresh_token',
				'refresh_token' => $tokens['refresh_token'],
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param  array $body     Token endpoint response.
	 * @param  array $previous Previously stored tokens, used for fields the response omits.
	 * @throws RuntimeException When required fields are missing.
	 */
	protected static function store_tokens( array $body, array $previous = array() ) {
		$id_token = $body['id_token'] ?? ( $previous['id_token'] ?? '' );
		if ( empty( $body['access_token'] ) || '' === $id_token ) {
			throw new RuntimeException( esc_html__( 'The token response was missing required fields.', 'webberzone-chatgpt-account' ) );
		}
		$claims = static::jwt_claims( $id_token );
		$auth   = $claims['https://api.openai.com/auth'] ?? array();

		$tokens = array(
			'access_token'  => $body['access_token'],
			'refresh_token' => $body['refresh_token'] ?? ( $previous['refresh_token'] ?? '' ),
			'id_token'      => $id_token,
			'expires_at'    => isset( $body['expires_in'] ) ? time() + (int) $body['expires_in'] : (int) ( static::jwt_claims( $body['access_token'] )['exp'] ?? 0 ),
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
}
