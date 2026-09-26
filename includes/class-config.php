<?php
/**
 * Provider-specific wording used by the shared admin and Connectors code.
 *
 * @package WebberZone\ChatGPT_Account
 */

namespace WebberZone\ChatGPT_Account;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Holds everything the shared files need to know about this provider.
 *
 * The shared files (Admin, Connectors, OAuth_Client, Token_Store, Availability, connectors.js) are
 * identical across the WebberZone account plugins apart from namespace, prefix and text domain;
 * anything that genuinely differs lives here.
 *
 * @since 1.0.0
 */
class Config {


	/**
	 * Name of the service, used in error messages.
	 *
	 * @var string
	 */
	const VENDOR = 'OpenAI';

	/**
	 * Provider label.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function label() {
		return __( 'ChatGPT Account', 'webberzone-chatgpt-account' );
	}

	/**
	 * Sign-in button label and modal title.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function sign_in_label() {
		return __( 'Sign in with ChatGPT', 'webberzone-chatgpt-account' );
	}

	/**
	 * Introduction shown on the settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function intro() {
		return __( 'Use your ChatGPT subscription for text and image generation in the WordPress AI Client, instead of an OpenAI API key. Embeddings and text-to-speech still need the OpenAI API-key provider.', 'webberzone-chatgpt-account' );
	}

	/**
	 * Message shown when the plugin's dependencies are missing.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function requirements() {
		return __( 'The WordPress AI Client and a compatible version of the AI Provider for OpenAI plugin are both required.', 'webberzone-chatgpt-account' );
	}

	/**
	 * Note shown before signing in. Text inside <a></a> is linked to the URL, when there is one.
	 *
	 * @since 1.0.0
	 *
	 * @return array{text: string, url: string}
	 */
	public static function note() {
		return array(
			'text' => __( 'Before signing in, enable device code login for Codex in <a>ChatGPT → Settings → Security</a>.', 'webberzone-chatgpt-account' ),
			'url'  => OAuth::SECURITY_SETTINGS_URL,
		);
	}

	/**
	 * First sign-in step. Text inside <a></a> is linked to the device sign-in page.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function device_step() {
		return __( '1. Open <a>the ChatGPT device sign-in page</a> and sign in.', 'webberzone-chatgpt-account' );
	}

	/**
	 * Account details shown on the settings page.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $tokens Token data.
	 * @return array<string, string> Label => value.
	 */
	public static function account_rows( array $tokens ) {
		return array(
			__( 'Account', 'webberzone-chatgpt-account' ) => (string) ( $tokens['email'] ?? '' ),
			__( 'Plan', 'webberzone-chatgpt-account' )    => ucfirst( (string) ( $tokens['plan'] ?? '' ) ),
		);
	}

	/**
	 * One-line account summary shown on the Connectors card.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $tokens Token data.
	 * @return string
	 */
	public static function account_summary( array $tokens ) {
		$email = (string) ( $tokens['email'] ?? '' );
		$plan  = (string) ( $tokens['plan'] ?? '' );
		if ( '' === $email ) {
			return '';
		}
		return '' !== $plan
		/* translators: 1: account email, 2: plan name. */
		? sprintf( __( 'Signed in as %1$s (%2$s plan).', 'webberzone-chatgpt-account' ), $email, $plan )
		/* translators: %s: account email. */
		: sprintf( __( 'Signed in as %s.', 'webberzone-chatgpt-account' ), $email );
	}
}
