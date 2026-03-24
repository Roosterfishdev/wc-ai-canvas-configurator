<?php
/**
 * Replicate AI Provider
 *
 * AI provider implementation using Replicate's API with OpenAI GPT Image 1.5 model.
 *
 * Configuration via wp-config.php or environment variable:
 * - REPLICATE_API_TOKEN (required)
 * - OPENAI_API_KEY (optional, uses Replicate proxy if not set)
 *
 * @package WC_AICC\Providers
 */

namespace WC_AICC\Providers;

use WC_AICC\Config\Replicate_Gpt_Image_Aspect;
use WC_AICC\Logger;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Replicate AI Provider class
 */
class Replicate_AI_Provider implements AI_Provider_Interface {

    /**
     * Replicate API base URL
     *
     * @var string
     */
    const API_BASE_URL = 'https://api.replicate.com/v1';

    /**
     * Model version for OpenAI GPT Image 1.5 (image-to-image style transfer)
     *
     * @var string
     */
    const MODEL_VERSION = 'openai/gpt-image-1.5:02e2952e9b0844a6390b0b8094c8e3b3b6049ab0c7addd95cabe672c9f30b37f';

    /**
     * Quality preset for preview generation (low = ~$0.009/img)
     *
     * @var string
     */
    const DEFAULT_QUALITY = 'low';

    /**
     * Input fidelity: how much to match input image (low = more style transformation)
     *
     * @var string
     */
    const DEFAULT_INPUT_FIDELITY = 'low';

    /**
     * Maximum polling attempts (5 minutes with 3s interval)
     *
     * @var int
     */
    const MAX_POLL_ATTEMPTS = 100;

    /**
     * Polling interval in seconds
     *
     * @var int
     */
    const POLL_INTERVAL = 3;

    /**
     * Replicate API token
     *
     * @var string
     */
    private $api_token;

    /**
     * Last error from create_prediction (HTTP/transport), for user-visible messages
     *
     * @var string
     */
    private $last_create_prediction_error = '';

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_token = $this->get_api_token();
    }

    /**
     * Build a short message from a failed Replicate API response
     *
     * @param int    $http_code HTTP status.
     * @param mixed  $data      Decoded JSON or null.
     * @param string $raw_body  Raw response body.
     * @return string
     */
    private function format_replicate_api_failure_message( $http_code, $data, $raw_body ) {
        $code = (int) $http_code;
        if ( is_array( $data ) ) {
            if ( ! empty( $data['detail'] ) ) {
                $detail = $data['detail'];
                if ( is_string( $detail ) ) {
                    return sprintf( 'Replicate HTTP %d: %s', $code, $detail );
                }
                return sprintf( 'Replicate HTTP %d: %s', $code, Logger::truncate( wp_json_encode( $detail ), 900 ) );
            }
            if ( ! empty( $data['title'] ) ) {
                $extra = '';
                if ( ! empty( $data['detail'] ) ) {
                    $d = $data['detail'];
                    $extra = ' — ' . ( is_string( $d ) ? $d : Logger::truncate( wp_json_encode( $d ), 400 ) );
                }
                return sprintf( 'Replicate HTTP %d: %s%s', $code, (string) $data['title'], $extra );
            }
            if ( ! empty( $data['message'] ) && is_string( $data['message'] ) ) {
                return sprintf( 'Replicate HTTP %d: %s', $code, $data['message'] );
            }
        }
        return sprintf( 'Replicate HTTP %d: %s', $code, Logger::truncate( (string) $raw_body, 500 ) );
    }

    /**
     * Get API token from constant or environment
     *
     * @return string API token or empty string.
     */
    private function get_api_token() {
        // Check wp-config constant first
        if ( defined( 'REPLICATE_API_TOKEN' ) && ! empty( REPLICATE_API_TOKEN ) ) {
            return REPLICATE_API_TOKEN;
        }

        // Check environment variable
        $env_token = getenv( 'REPLICATE_API_TOKEN' );
        if ( ! empty( $env_token ) ) {
            return $env_token;
        }

        return '';
    }

    /**
     * Get OpenAI API key (optional for GPT Image 1.5 - Replicate may use proxy if not set)
     *
     * @return string OpenAI API key or empty string.
     */
    private function get_openai_api_key() {
        if ( defined( 'OPENAI_API_KEY' ) && ! empty( OPENAI_API_KEY ) ) {
            return OPENAI_API_KEY;
        }

        $env_key = getenv( 'OPENAI_API_KEY' );
        if ( ! empty( $env_key ) ) {
            return $env_key;
        }

        return '';
    }

    /**
     * Get provider identifier
     *
     * @return string
     */
    public function get_id() {
        return 'replicate';
    }

    /**
     * Get provider display name
     *
     * @return string
     */
    public function get_name() {
        return __( 'Replicate (GPT Image 1.5)', 'wc-aicc' );
    }

    /**
     * Check if provider is configured and available
     *
     * @return bool True if API token is set.
     */
    public function is_available() {
        return ! empty( $this->api_token );
    }

    /**
     * Generate stylized artwork from source image
     *
     * @param string $source_url      URL of the source image (must be publicly accessible).
     * @param string $prompt          Full prompt (built by Prompt_Builder).
     * @param string $aspect_ratio    Aspect ratio (e.g., '1:1', '3:4', '4:3').
     * @param string $negative_prompt Optional constraints (may be appended if API supports).
     * @return array {
     *     @type bool   $success    Whether generation succeeded.
     *     @type string $image_url  URL to download the generated image.
     *     @type string $error      Error message (if failed).
     * }
     */
    public function generate( $source_url, $prompt, $aspect_ratio = '1:1', $negative_prompt = '' ) {
        if ( ! $this->is_available() ) {
            return array(
                'success' => false,
                'error'   => __( 'Replicate API token not configured.', 'wc-aicc' ),
            );
        }

        $model_aspect = Replicate_Gpt_Image_Aspect::to_model_input( $aspect_ratio );

        Logger::info(
            'Replicate',
            'generate() started',
            array(
                'print_aspect_ratio'   => $aspect_ratio,
                'replicate_aspect_ratio' => $model_aspect,
                'source_url'           => Logger::summarize_url( $source_url ),
            )
        );

        try {
            // Create prediction (with Prefer: wait for possible synchronous response)
            $prediction = $this->create_prediction( $source_url, $prompt, $model_aspect, $negative_prompt );

            if ( ! $prediction ) {
                $detail = $this->last_create_prediction_error;
                throw new \Exception(
                    $detail !== ''
                        ? $detail
                        : __( 'Failed to create prediction.', 'wc-aicc' )
                );
            }

            // If we got a synchronous result (Prefer: wait), use it directly
            if ( ! empty( $prediction['status'] ) && $prediction['status'] === 'succeeded' ) {
                $result = $prediction;
            } else {
                $prediction_id = $prediction['id'] ?? null;
                if ( empty( $prediction_id ) ) {
                    $detail = $this->last_create_prediction_error;
                    throw new \Exception(
                        $detail !== ''
                            ? $detail
                            : __( 'Failed to create prediction (no prediction id in response).', 'wc-aicc' )
                    );
                }
                Logger::info( 'Replicate', 'Prediction created, polling', array( 'prediction_id' => $prediction_id ) );
                $result = $this->poll_prediction( $prediction_id );
            }

            if ( $result['status'] === 'succeeded' ) {
                // Get output URL (GPT Image 1.5 returns array of image URLs)
                $output_url = $this->extract_output_url( $result );

                if ( empty( $output_url ) ) {
                    throw new \Exception( __( 'No output image URL in prediction result.', 'wc-aicc' ) );
                }

                Logger::info(
                    'Replicate',
                    'Generation succeeded',
                    array( 'output_url' => Logger::summarize_url( $output_url ) )
                );

                return array(
                    'success'   => true,
                    'image_url' => $output_url,
                    'error'     => '',
                );
            } else {
                $error_msg = $result['error'] ?? __( 'Generation failed with unknown error.', 'wc-aicc' );
                throw new \Exception( $error_msg );
            }

        } catch ( \Exception $e ) {
            Logger::error( 'Replicate', 'generate() failed', array( 'message' => $e->getMessage() ) );

            return array(
                'success' => false,
                'error'   => $e->getMessage(),
            );
        }
    }

    /**
     * Create prediction via Replicate API (GPT Image 1.5)
     *
     * @param string $image_url       Source image URL (must be publicly accessible).
     * @param string $prompt          Generation prompt.
     * @param string $aspect_ratio    Model input ratio; must be 1:1, 3:2, or 2:3 (see Replicate_Gpt_Image_Aspect).
     * @param string $negative_prompt Optional negative prompt (if API supports).
     * @return array|null Prediction response or null on failure.
     */
    private function create_prediction( $image_url, $prompt, $aspect_ratio, $negative_prompt = '' ) {
        $this->last_create_prediction_error = '';

        $url = self::API_BASE_URL . '/predictions';

        Logger::info(
            'Replicate',
            'create_prediction request',
            array(
                'aspect_ratio'   => $aspect_ratio,
                'quality'        => self::DEFAULT_QUALITY,
                'input_fidelity' => self::DEFAULT_INPUT_FIDELITY,
                'image_url'      => Logger::summarize_url( $image_url ),
                'prompt_len'     => strlen( $prompt ),
            )
        );

        $input = array(
            'prompt'           => $prompt,
            'input_images'     => array( $image_url ),
            'aspect_ratio'     => $aspect_ratio,
            'quality'          => self::DEFAULT_QUALITY,
            'input_fidelity'   => self::DEFAULT_INPUT_FIDELITY,
            'number_of_images' => 1,
        );

        // Add negative prompt if API supports it (GPT Image 1.5 may support negative_prompt)
        if ( ! empty( $negative_prompt ) ) {
            $input['negative_prompt'] = $negative_prompt;
        }

        $openai_key = $this->get_openai_api_key();
        if ( ! empty( $openai_key ) ) {
            $input['openai_api_key'] = $openai_key;
        }

        $body = array(
            'version' => $this->get_model_version(),
            'input'   => $input,
        );

        $headers = $this->get_headers();
        $headers['Prefer'] = 'wait';

        $response = wp_remote_post(
            $url,
            array(
                'headers' => $headers,
                'body'    => wp_json_encode( $body ),
                'timeout' => 120,
            )
        );

        if ( is_wp_error( $response ) ) {
            $this->last_create_prediction_error = 'Could not reach Replicate API: ' . $response->get_error_message();
            Logger::error(
                'Replicate',
                'create_prediction wp_remote_post failed',
                array( 'error' => $response->get_error_message() )
            );
            return null;
        }

        $code     = wp_remote_retrieve_response_code( $response );
        $raw_body = wp_remote_retrieve_body( $response );
        $data     = json_decode( $raw_body, true );

        if ( $code !== 201 && $code !== 200 ) {
            $this->last_create_prediction_error = $this->format_replicate_api_failure_message( $code, $data, $raw_body );
            $detail                               = is_array( $data ) && isset( $data['detail'] ) ? $data['detail'] : $raw_body;
            if ( is_array( $detail ) ) {
                $detail = wp_json_encode( $detail );
            }
            Logger::error(
                'Replicate',
                'create_prediction API error',
                array(
                    'http_code' => $code,
                    'body'      => Logger::truncate( (string) $detail ),
                )
            );
            return null;
        }

        if ( ! is_array( $data ) ) {
            $this->last_create_prediction_error = sprintf(
                'Replicate HTTP %d returned invalid JSON.',
                $code
            );
            Logger::error( 'Replicate', 'create_prediction invalid JSON', array( 'http_code' => $code ) );
            return null;
        }

        $pred_id = $data['id'] ?? '';
        $pstatus = $data['status'] ?? '';
        Logger::info(
            'Replicate',
            'create_prediction OK',
            array(
                'prediction_id' => $pred_id,
                'status'        => $pstatus,
            )
        );

        return $data;
    }

    /**
     * Poll prediction status until complete or failed
     *
     * @param string $prediction_id Prediction ID.
     * @return array Final prediction status.
     * @throws \Exception On timeout or failure.
     */
    private function poll_prediction( $prediction_id ) {
        $url = self::API_BASE_URL . '/predictions/' . $prediction_id;

        for ( $attempt = 0; $attempt < self::MAX_POLL_ATTEMPTS; $attempt++ ) {
            $response = wp_remote_get(
                $url,
                array(
                    'headers' => $this->get_headers(),
                    'timeout' => 15,
                )
            );

            if ( is_wp_error( $response ) ) {
                Logger::error(
                    'Replicate',
                    'poll request failed',
                    array(
                        'prediction_id' => $prediction_id,
                        'attempt'       => $attempt,
                        'error'         => $response->get_error_message(),
                    )
                );
                sleep( self::POLL_INTERVAL );
                continue;
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( empty( $data['status'] ) ) {
                Logger::warning(
                    'Replicate',
                    'poll invalid response',
                    array(
                        'prediction_id' => $prediction_id,
                        'attempt'       => $attempt,
                        'body_preview'  => Logger::truncate( $body, 200 ),
                    )
                );
                sleep( self::POLL_INTERVAL );
                continue;
            }

            $status = $data['status'];
            if ( 0 === $attempt || ( 'processing' !== $status && 'starting' !== $status ) || 0 === ( $attempt % 10 ) ) {
                Logger::info(
                    'Replicate',
                    'poll status',
                    array(
                        'prediction_id' => $prediction_id,
                        'attempt'       => $attempt,
                        'status'        => $status,
                    )
                );
            }

            switch ( $status ) {
                case 'succeeded':
                    return $data;

                case 'failed':
                case 'canceled':
                    $error = $data['error'] ?? __( 'Prediction failed.', 'wc-aicc' );
                    throw new \Exception( $error );

                case 'starting':
                case 'processing':
                    // Continue polling
                    sleep( self::POLL_INTERVAL );
                    break;

                default:
                    // Unknown status, continue
                    sleep( self::POLL_INTERVAL );
            }
        }

        throw new \Exception( __( 'Generation timed out. Please try again.', 'wc-aicc' ) );
    }

    /**
     * Extract output URL from prediction result
     *
     * @param array $result Prediction result.
     * @return string|null Output URL or null.
     */
    private function extract_output_url( $result ) {
        $output = $result['output'] ?? null;

        if ( is_array( $output ) && ! empty( $output ) ) {
            // GPT Image 1.5 returns array of image URLs (or objects with url key)
            $first = $output[0];
            if ( is_string( $first ) ) {
                return $first;
            }
            if ( is_array( $first ) && isset( $first['url'] ) ) {
                return $first['url'];
            }
            return null;
        }

        if ( is_string( $output ) ) {
            return $output;
        }

        return null;
    }

    /**
     * Get model version for Replicate API
     *
     * @return string Model version (full owner/model:hash string).
     */
    private function get_model_version() {
        return self::MODEL_VERSION;
    }

    /**
     * Get API request headers
     *
     * @return array Headers.
     */
    private function get_headers() {
        return array(
            'Authorization' => 'Token ' . $this->api_token,
            'Content-Type'  => 'application/json',
        );
    }
}
