<?php
/**
 * Text generation model.
 *
 * @package WebberZone\ChatGPT_Account
 */

namespace WebberZone\ChatGPT_Account;

use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Generates text through the ChatGPT Codex backend's streaming Responses endpoint.
 *
 * @since 1.0.0
 */
class Text_Generation_Model extends AbstractApiBasedModel implements TextGenerationModelInterface {


	/**
	 * Minimum request timeout in seconds; streamed replies can be slow.
	 *
	 * @var float
	 */
	const MIN_TIMEOUT = 180.0;

	/**
	 * Instructions sent when the prompt has no system instruction; the backend requires one.
	 *
	 * @var string
	 */
	const DEFAULT_INSTRUCTIONS = 'You are a helpful assistant.';

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param array $prompt List of messages.
	 */
	public function generateTextResult( array $prompt ): GenerativeAiResult {
		$bridge = new OpenAI_Bridge( $this->metadata(), $this->providerMetadata() );
		$bridge->setConfig( $this->getConfig() );

		$params   = $this->adapt_params( $bridge->build_params( $prompt ) );
		$response = $this->send( $params, true );

		return $bridge->parse_result( $this->parse_stream( $response ) );
	}

	/**
	 * Adjusts OpenAI Platform parameters for the Codex backend.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $params Responses API parameters.
	 * @return array
	 */
	private function adapt_params( array $params ) {
		unset( $params['temperature'], $params['top_p'], $params['top_logprobs'], $params['max_output_tokens'] );

		$include   = array_diff( (array) ( $params['include'] ?? array() ), array( 'message.output_text.logprobs' ) );
		$include[] = 'reasoning.encrypted_content';

		$params['include']      = array_values( array_unique( $include ) );
		$params['stream']       = true;
		$params['store']        = false;
		$params['instructions'] = ( $params['instructions'] ?? '' ) !== '' ? $params['instructions'] : self::DEFAULT_INSTRUCTIONS;
		$params['tools']        = $params['tools'] ?? array();
		$params['tool_choice']  = $params['tool_choice'] ?? 'auto';

		// With store=false, reasoning items can only be replayed when their encrypted content travels with them.
		$params['input'] = array_values(
			array_filter(
				(array) $params['input'],
				static function ( $item ) {
					return ! is_array( $item ) || 'reasoning' !== ( $item['type'] ?? '' ) || ! empty( $item['encrypted_content'] );
				}
			)
		);

		return $params;
	}

	/**
	 * Sends the request, refreshing the token once on a 401.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $params      Request parameters.
	 * @param  bool  $allow_retry Whether a 401 may trigger a refresh and retry.
	 * @return Response
	 * @throws RuntimeException On HTTP errors.
	 */
	private function send( array $params, $allow_retry ) {
		$request  = new Request(
			HttpMethodEnum::POST(),
			Provider::url( 'responses' ),
			array(
				'Content-Type' => 'application/json',
				'Accept'       => 'text/event-stream',
			),
			$params,
			self::request_options( $this->getRequestOptions() )
		);
		$request  = Account_Authentication::apply_headers( $request, OAuth::get_valid_tokens() );
		$response = $this->getHttpTransporter()->send( $request );

		if ( 401 === $response->getStatusCode() && $allow_retry ) {
			OAuth::refresh( true );
			return $this->send( $params, false );
		}
		if ( ! $response->isSuccessful() ) {
			throw new RuntimeException( esc_html( sprintf( 'ChatGPT returned HTTP %d: %s', $response->getStatusCode(), self::error_detail( (string) $response->getBody() ) ) ) );
		}
		return $response;
	}

	/**
	 * Returns request options with at least the minimum timeout.
	 *
	 * @since 1.0.0
	 *
	 * @param  RequestOptions|null $options Existing options.
	 * @return RequestOptions
	 */
	public static function request_options( $options ) {
		$options = $options ? clone $options : new RequestOptions();
		if ( null === $options->getTimeout() || $options->getTimeout() < self::MIN_TIMEOUT ) {
			$options->setTimeout( self::MIN_TIMEOUT );
		}
		return $options;
	}

	/**
	 * Collapses the server-sent event stream into a single Responses API JSON response.
	 *
	 * @since 1.0.0
	 *
	 * @param  Response $response Streamed response.
	 * @return Response
	 * @throws RuntimeException When the stream reports an error or never completes.
	 */
	private function parse_stream( Response $response ) {
		$final = null;
		$items = array();

		foreach ( (array) preg_split( "/\r?\n\r?\n/", (string) $response->getBody() ) as $event ) {
			$data = array();
			foreach ( (array) preg_split( "/\r?\n/", (string) $event ) as $line ) {
				if ( 0 === strncmp( (string) $line, 'data:', 5 ) ) {
					$data[] = ltrim( substr( (string) $line, 5 ), ' ' );
				}
			}
			$payload = json_decode( implode( "\n", $data ), true );
			if ( ! is_array( $payload ) ) {
				continue;
			}
			$type = $payload['type'] ?? '';

			if ( 'response.output_item.done' === $type && is_array( $payload['item'] ?? null ) ) {
				$items[] = $payload['item'];
			} elseif ( in_array( $type, array( 'response.completed', 'response.incomplete' ), true ) && is_array( $payload['response'] ?? null ) ) {
				$final = $payload['response'];
			} elseif ( in_array( $type, array( 'response.failed', 'error' ), true ) ) {
				$error = $payload['response']['error'] ?? ( $payload['error'] ?? $payload );
				throw new RuntimeException( esc_html( 'ChatGPT error: ' . ( is_array( $error ) ? ( $error['message'] ?? wp_json_encode( $error ) ) : (string) $error ) ) );
			}
		}

		if ( null === $final ) {
			throw new RuntimeException( 'ChatGPT stream ended without a completed response.' );
		}
		// The backend streams output items individually and may leave response.completed's output empty.
		if ( empty( $final['output'] ) && $items ) {
			$final['output'] = $items;
		}

		return new Response( 200, array( 'Content-Type' => array( 'application/json' ) ), (string) wp_json_encode( $final ) );
	}

	/**
	 * Extracts a readable error message from an error response body.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $body Response body.
	 * @return string
	 */
	public static function error_detail( $body ) {
		$data = json_decode( $body, true );
		if ( is_array( $data ) ) {
			$message = $data['detail'] ?? ( $data['error']['message'] ?? ( $data['error'] ?? null ) );
			if ( is_string( $message ) ) {
				return $message;
			}
		}
		return substr( wp_strip_all_tags( $body ), 0, 300 );
	}
}
