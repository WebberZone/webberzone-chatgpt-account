<?php
/**
 * Image generation model.
 *
 * @package WebberZone\ChatGPT_Account
 */

namespace WebberZone\ChatGPT_Account;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\OpenAiAiProvider\Models\OpenAiImageGenerationModel;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Generates images through the ChatGPT Codex backend's Images endpoint, as Codex does with a ChatGPT sign-in.
 *
 * @since 1.0.0
 */
class Image_Generation_Model extends OpenAiImageGenerationModel {


	/**
	 * Parameters the Codex backend accepts for image generation.
	 *
	 * @var string[]
	 */
	const ALLOWED_PARAMS = array( 'prompt', 'model', 'n', 'quality', 'size', 'background' );

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param  array $prompt List of messages.
	 * @throws InvalidArgumentException When the prompt contains an image to edit.
	 */
	public function generateImageResult( array $prompt ): GenerativeAiResult {
		if ( $this->promptContainsImage( $prompt ) ) {
			throw new InvalidArgumentException( esc_html__( 'Image editing is not supported by the ChatGPT Account provider yet.', 'webberzone-chatgpt-account' ) );
		}
		return parent::generateImageResult( $prompt );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param array $prompt List of messages.
	 */
	protected function prepareGenerateImageParams( array $prompt ): array {
		$params  = array_intersect_key( parent::prepareGenerateImageParams( $prompt ), array_flip( self::ALLOWED_PARAMS ) );
		$params += array(
			'quality' => 'auto',
			'size'    => 'auto',
		);
		return $params;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param HttpMethodEnum $method  HTTP method.
	 * @param string         $path    Endpoint path.
	 * @param array          $headers Request headers.
	 * @param mixed          $data    Request data.
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = array(), $data = null ): Request {
		$headers['x-codex-image-turn-id'] = wp_generate_uuid4();
		return new Request(
			$method,
			Provider::url( $path ),
			$headers,
			$data,
			Text_Generation_Model::request_options( $this->getRequestOptions() )
		);
	}
}
