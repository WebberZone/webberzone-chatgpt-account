<?php
/**
 * Model metadata directory.
 *
 * @package WebberZone\ChatGPT_Account
 */

namespace WebberZone\ChatGPT_Account;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Files\Enums\MediaOrientationEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Lists the models available to the connected ChatGPT account.
 *
 * Text models come from the backend's /models endpoint (cached), with a fallback list; the image model is fixed.
 *
 * @since 1.0.0
 */
class Model_Directory implements ModelMetadataDirectoryInterface {


	/**
	 * Transient that caches the live text model list.
	 *
	 * @var string
	 */
	const CACHE_KEY = 'wzcga_models';

	/**
	 * Image model used by Codex with a ChatGPT sign-in.
	 *
	 * @var string
	 */
	const IMAGE_MODEL = 'gpt-image-2';

	/**
	 * Text models used when the live list is unavailable.
	 *
	 * @var array<string, string>
	 */
	const FALLBACK_MODELS = array(
		'gpt-5.2'       => 'GPT-5.2',
		'gpt-5.2-codex' => 'GPT-5.2 Codex',
		'gpt-5.1'       => 'GPT-5.1',
	);

	/**
	 * Model metadata keyed by model ID.
	 *
	 * @var array<string, ModelMetadata>|null
	 */
	private $models = null;

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function listModelMetadata(): array {
		if ( null === $this->models ) {
			$this->models = array();
			foreach ( $this->text_models() as $id => $name ) {
				$this->models[ $id ] = self::text_metadata( $id, $name );
			}
			$this->models[ self::IMAGE_MODEL ] = self::image_metadata();
		}
		return array_values( $this->models );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_id Model ID.
	 */
	public function hasModelMetadata( string $model_id ): bool {
		$this->listModelMetadata();
		return isset( $this->models[ $model_id ] );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param  string $model_id Model ID.
	 * @throws InvalidArgumentException When the model is unknown.
	 */
	public function getModelMetadata( string $model_id ): ModelMetadata {
		if ( ! $this->hasModelMetadata( $model_id ) ) {
			throw new InvalidArgumentException( esc_html( sprintf( 'No ChatGPT Account model with ID %s.', $model_id ) ) );
		}
		return $this->models[ $model_id ];
	}

	/**
	 * Returns the text models as ID => display name.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	private function text_models() {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && $cached ) {
			return $cached;
		}
		$models = $this->fetch_live_models();
		if ( $models ) {
			set_transient( self::CACHE_KEY, $models, 12 * HOUR_IN_SECONDS );
			return $models;
		}

		/**
		 * Filters the text models offered when the live model list cannot be fetched.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string> $models Model ID => display name.
		 */
		return (array) apply_filters( 'wzcga_fallback_models', self::FALLBACK_MODELS );
	}

	/**
	 * Fetches the text models from the backend.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	private function fetch_live_models() {
		if ( ! Token_Store::is_connected() ) {
			return array();
		}
		try {
			$tokens = OAuth::get_valid_tokens();
		} catch ( \Exception $e ) {
			return array();
		}
		$request = Account_Authentication::apply_headers(
			new Request( HttpMethodEnum::GET(), Provider::url( 'models' ) . '?client_version=' . rawurlencode( Account_Authentication::client_version() ) ),
			$tokens
		);
		$headers = array();
		foreach ( $request->getHeaders() as $name => $values ) {
			$headers[ $name ] = implode( ', ', (array) $values );
		}
		$response = wp_remote_get(
			$request->getUri(),
			array(
				'timeout' => 15,
				'headers' => $headers,
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$list = $data['models'] ?? ( $data['data'] ?? array() );

		$models = array();
		foreach ( (array) $list as $entry ) {
			$id = $entry['slug'] ?? ( $entry['id'] ?? '' );
			if ( ! is_string( $id ) || ! self::is_text_model( $id ) || ( isset( $entry['visibility'] ) && 'list' !== $entry['visibility'] ) ) {
				continue;
			}
			$models[ $id ] = is_string( $entry['display_name'] ?? null ) ? $entry['display_name'] : $id;
		}
		return $models;
	}

	/**
	 * Whether a model ID looks like a chat model.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $id Model ID.
	 * @return bool
	 */
	private static function is_text_model( $id ) {
		return (bool) preg_match( '/^(gpt-|o[134]|codex-)/', $id )
		&& ! preg_match( '/-(instruct|realtime|transcribe|audio|tts|image)/', $id );
	}

	/**
	 * Builds metadata for a text model.
	 *
	 * Sampling options are advertised so features calling using_temperature() still match; the model drops them before sending.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $id   Model ID.
	 * @param  string $name Display name.
	 * @return ModelMetadata
	 */
	private static function text_metadata( $id, $name ) {
		$options = array(
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::candidateCount() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::stopSequences() ),
			new SupportedOption( OptionEnum::outputMimeType(), array( 'text/plain', 'application/json' ) ),
			new SupportedOption( OptionEnum::outputSchema() ),
			new SupportedOption( OptionEnum::functionDeclarations() ),
			new SupportedOption( OptionEnum::customOptions() ),
			new SupportedOption(
				OptionEnum::inputModalities(),
				array(
					array( ModalityEnum::text() ),
					array( ModalityEnum::text(), ModalityEnum::image() ),
				)
			),
			new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) ),
		);
		return new ModelMetadata( $id, $name, array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ), $options );
	}

	/**
	 * Builds metadata for the image model.
	 *
	 * @since 1.0.0
	 *
	 * @return ModelMetadata
	 */
	private static function image_metadata() {
		$options = array(
			new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
			new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::image() ) ) ),
			new SupportedOption( OptionEnum::candidateCount() ),
			new SupportedOption( OptionEnum::outputMimeType(), array( 'image/png' ) ),
			new SupportedOption( OptionEnum::outputFileType(), array( FileTypeEnum::inline() ) ),
			new SupportedOption(
				OptionEnum::outputMediaOrientation(),
				array(
					MediaOrientationEnum::square(),
					MediaOrientationEnum::landscape(),
					MediaOrientationEnum::portrait(),
				)
			),
			new SupportedOption( OptionEnum::outputMediaAspectRatio(), array( '1:1', '3:2', '2:3' ) ),
			new SupportedOption( OptionEnum::customOptions() ),
		);
		return new ModelMetadata( self::IMAGE_MODEL, 'GPT Image 2', array( CapabilityEnum::imageGeneration() ), $options );
	}
}
