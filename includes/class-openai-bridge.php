<?php
/**
 * Bridge to the AI Provider for OpenAI's Responses API mapping.
 *
 * @package WebberZone\ChatGPT_Account
 */

namespace WebberZone\ChatGPT_Account;

use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\OpenAiAiProvider\Models\OpenAiTextGenerationModel;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Exposes the OpenAI provider's message and response mapping.
 *
 * Its generateTextResult() is final and bound to api.openai.com, so the mapping is reused through this subclass instead.
 *
 * @since 1.0.0
 */
class OpenAI_Bridge extends OpenAiTextGenerationModel {


	/**
	 * Builds the Responses API parameters for a prompt.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $prompt List of messages.
	 * @return array
	 */
	public function build_params( array $prompt ) {
		return $this->prepareGenerateTextParams( $prompt );
	}

	/**
	 * Parses a Responses API response object.
	 *
	 * @since 1.0.0
	 *
	 * @param  Response $response JSON response.
	 * @return GenerativeAiResult
	 */
	public function parse_result( Response $response ) {
		return $this->parseResponseToGenerativeAiResult( $response );
	}
}
