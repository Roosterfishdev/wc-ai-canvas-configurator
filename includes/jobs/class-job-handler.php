<?php
/**
 * Job Handler
 *
 * Handles Action Scheduler jobs for build processing.
 *
 * @package WC_AICC\Jobs
 */

namespace WC_AICC\Jobs;

use WC_AICC\Logger;
use WC_AICC\Models\Build;
use WC_AICC\Repository\Build_Repository;
use WC_AICC\Storage\R2_Storage;
use WC_AICC\Providers\AI_Provider_Factory;
use WC_AICC\Mockup\Mockup_Generator_Factory;
use WC_AICC\Config\Prompt_Builder;
use WC_AICC\Config\Size_Aspect_Map;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Job Handler class
 */
class Job_Handler {

    /**
     * Singleton instance
     *
     * @var Job_Handler|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Job_Handler
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Register job handler
        add_action( 'wc_aicc_process_build', array( $this, 'process_build' ), 10, 1 );
    }

    /**
     * Process a build
     *
     * @param string $build_uuid Build UUID.
     */
    public function process_build( $build_uuid ) {
        Logger::info( 'Job', 'process_build started', array( 'build_uuid' => $build_uuid ) );

        $repository = Build_Repository::instance();
        $build      = $repository->get_by_uuid( $build_uuid );

        if ( ! $build ) {
            Logger::error( 'Job', 'Build not found', array( 'build_uuid' => $build_uuid ) );
            return;
        }

        // Ensure build is in processing state
        if ( $build->status !== Build::STATUS_PROCESSING ) {
            Logger::warning(
                'Job',
                'Skipped: build not in processing state (another job may have finished or duplicate queue)',
                array(
                    'build_uuid' => $build_uuid,
                    'status'     => $build->status,
                )
            );
            return;
        }

        // Replicate polling + downloading the output can exceed default max_execution_time on shared hosts
        // (often 30s). A fatal stop here never runs catch, so the build stays "processing" with null final_art.
        if ( function_exists( 'wc_set_time_limit' ) ) {
            wc_set_time_limit( 600 );
        } else {
            @set_time_limit( 600 );
        }
        if ( function_exists( 'wp_raise_memory_limit' ) ) {
            wp_raise_memory_limit( 'admin' );
        }

        // If PHP fatals during AI/download, catch never runs — build stays "processing" forever.
        $job_completed = false;
        register_shutdown_function(
            function () use ( $build_uuid, &$job_completed ) {
                if ( $job_completed ) {
                    return;
                }
                $err = error_get_last();
                if ( ! is_array( $err ) ) {
                    return;
                }
                $fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR );
                if ( ! in_array( (int) $err['type'], $fatal_types, true ) ) {
                    return;
                }
                $repo = Build_Repository::instance();
                $b    = $repo->get_by_uuid( $build_uuid );
                if ( ! $b || $b->status !== Build::STATUS_PROCESSING ) {
                    return;
                }
                $repo->update_by_uuid(
                    $build_uuid,
                    array(
                        'status'        => Build::STATUS_FAILED,
                        'error_message' => sprintf(
                            /* translators: %s: PHP error message from the server */
                            __( 'Server stopped during processing: %s', 'wc-aicc' ),
                            $err['message']
                        ),
                    )
                );
                Logger::error(
                    'Job',
                    'Shutdown after PHP fatal',
                    array(
                        'build_uuid' => $build_uuid,
                        'php_error'  => $err['message'],
                    )
                );
            }
        );

        try {
            // Step 1: Crop (placeholder - just copy original)
            $cropped_key = $this->step_crop( $build );
            
            if ( ! $cropped_key ) {
                throw new \Exception( __( 'Cropping failed.', 'wc-aicc' ) );
            }

            // Update build with cropped key
            $repository->update_by_uuid( $build_uuid, array( 'cropped_key' => $cropped_key ) );
            $build->cropped_key = $cropped_key;

            // Step 2: AI generation
            $final_art_key = $this->step_ai_generate( $build );
            
            if ( ! $final_art_key ) {
                throw new \Exception( __( 'AI generation failed.', 'wc-aicc' ) );
            }

            // Persist final artwork and mark READY so the frontend can advance as soon as Replicate succeeds.
            // Mockup runs afterward; failures there must not block the customer preview (fixes "stuck on processing").
            $repository->update_by_uuid(
                $build_uuid,
                array(
                    'final_art_key' => $final_art_key,
                    'status'        => Build::STATUS_READY,
                    'error_message' => '',
                )
            );
            $build->final_art_key = $final_art_key;

            Logger::info(
                'Job',
                'Build marked ready after AI generation',
                array( 'build_uuid' => $build_uuid )
            );

            try {
                $mockup_key = $this->step_mockup_generate( $build );
                $repository->update_by_uuid(
                    $build_uuid,
                    array( 'mockup_key' => $mockup_key )
                );
                Logger::info( 'Job', 'Mockup completed', array( 'build_uuid' => $build_uuid, 'mockup_key' => $mockup_key ) );
            } catch ( \Exception $mockup_ex ) {
                Logger::warning(
                    'Job',
                    'Mockup failed; build stays ready without mockup',
                    array(
                        'build_uuid' => $build_uuid,
                        'message'    => $mockup_ex->getMessage(),
                    )
                );
            }

            Logger::info( 'Job', 'Build completed successfully', array( 'build_uuid' => $build_uuid ) );

            $job_completed = true;

        } catch ( \Exception $e ) {
            Logger::error(
                'Job',
                'Build failed',
                array(
                    'build_uuid' => $build_uuid,
                    'message'    => $e->getMessage(),
                )
            );

            $repository->update_by_uuid(
                $build_uuid,
                array(
                    'status'        => Build::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                )
            );
            $job_completed = true;
        }
    }

    /**
     * Step: Crop image
     *
     * @param Build $build Build object.
     * @return string|false Cropped key or false on failure.
     */
    private function step_crop( $build ) {
        Logger::info( 'Job', 'Step: crop', array( 'build_uuid' => $build->build_uuid ) );

        // REST may already set cropped_key (= original stub) before the worker runs; skip duplicate work.
        if ( ! empty( $build->cropped_key ) ) {
            Logger::info(
                'Job',
                'Crop skipped (already set)',
                array( 'build_uuid' => $build->build_uuid )
            );
            return $build->cropped_key;
        }

        // TODO: Implement actual cropping based on aspect_ratio
        // For now, just return the original key as cropped
        
        $cropped_key = $build->original_key;

        // In a real implementation:
        // 1. Download original from R2
        // 2. Crop to aspect_ratio
        // 3. Upload cropped to R2 as builds/{uuid}/cropped.webp
        // 4. Return new key

        Logger::info( 'Job', 'Crop completed (stub)', array( 'build_uuid' => $build->build_uuid ) );

        return $cropped_key;
    }

    /**
     * Step: AI generation
     *
     * @param Build $build Build object.
     * @return string|false Final art key or false on failure.
     */
    private function step_ai_generate( $build ) {
        $storage  = R2_Storage::instance();
        $provider = AI_Provider_Factory::get_provider();
        $pid      = method_exists( $provider, 'get_id' ) ? $provider->get_id() : 'unknown';

        Logger::info(
            'Job',
            'Step: AI generate',
            array(
                'build_uuid' => $build->build_uuid,
                'provider'   => $pid,
                'aspect'     => $build->aspect_ratio,
            )
        );

        $aspect_ratio = $build->aspect_ratio;
        if ( empty( $aspect_ratio ) && ! empty( $build->size_label ) ) {
            $aspect_ratio = Size_Aspect_Map::resolve_for_label( (string) $build->size_label );
        }

        // Get cropped image URL
        $cropped_url = $this->get_asset_url( $build->cropped_key, $storage );

        if ( empty( $cropped_url ) ) {
            Logger::error( 'Job', 'No cropped URL available', array( 'build_uuid' => $build->build_uuid ) );
            return false;
        }

        // Build prompt from customization options
        $options  = $build->customization_options;
        $built    = Prompt_Builder::build(
            is_array( $options ) ? $options : array(),
            array( 'aspect_ratio' => $aspect_ratio )
        );
        $prompt   = $built['prompt'];
        $negative = $built['negative_prompt'] ?? '';

        Logger::info(
            'Job',
            'Prompt built',
            array(
                'build_uuid'       => $build->build_uuid,
                'source_url'       => Logger::summarize_url( $cropped_url ),
                'prompt_length'    => strlen( $prompt ),
                'negative_length'  => strlen( (string) $negative ),
            )
        );

        // Call AI provider
        $result = $provider->generate(
            $cropped_url,
            $prompt,
            $aspect_ratio,
            $negative
        );

        if ( ! $result['success'] ) {
            Logger::error(
                'Job',
                'AI provider returned failure',
                array(
                    'build_uuid' => $build->build_uuid,
                    'provider'   => $pid,
                    'error'      => $result['error'] ?? '',
                )
            );
            throw new \Exception(
                ! empty( $result['error'] )
                    ? $result['error']
                    : __( 'AI generation failed.', 'wc-aicc' )
            );
        }

        // Get the generated image
        $image_data = null;

        if ( ! empty( $result['image_data'] ) ) {
            // Base64 encoded data
            $image_data = base64_decode( $result['image_data'] );
        } elseif ( ! empty( $result['image_url'] ) ) {
            Logger::info(
                'Job',
                'Downloading generated image',
                array(
                    'build_uuid' => $build->build_uuid,
                    'url_hint'   => Logger::summarize_url( $result['image_url'] ),
                )
            );

            $image_data = $this->fetch_generated_image_bytes( $result['image_url'], $build->build_uuid );

            if ( $image_data === false || $image_data === '' ) {
                Logger::error(
                    'Job',
                    'Generated image bytes empty after download attempts',
                    array(
                        'build_uuid' => $build->build_uuid,
                        'url_hint'   => Logger::summarize_url( $result['image_url'] ),
                    )
                );
                throw new \Exception(
                    __(
                        'Could not download the generated image from Replicate (replicate.delivery). Check WC_AICC logs — your host may block outbound HTTPS or truncate long requests.',
                        'wc-aicc'
                    )
                );
            }
        }

        if ( empty( $image_data ) ) {
            Logger::error( 'Job', 'No image data from AI provider', array( 'build_uuid' => $build->build_uuid ) );
            return false;
        }

        // Upload to R2
        $final_art_key = "builds/{$build->build_uuid}/final-art.webp";

        if ( ! $storage->is_configured() ) {
            // Local storage for development
            $upload_dir = wp_upload_dir();
            $local_dir  = $upload_dir['basedir'] . '/wc-aicc-builds/' . $build->build_uuid;
            $local_file = $local_dir . '/final-art.webp';

            if ( ! file_exists( $local_dir ) ) {
                wp_mkdir_p( $local_dir );
            }

            if ( file_put_contents( $local_file, $image_data ) === false ) {
                Logger::error( 'Job', 'Failed to save final art locally', array( 'build_uuid' => $build->build_uuid ) );
                return false;
            }
        } else {
            if ( ! $storage->put_object( $final_art_key, $image_data, 'image/webp' ) ) {
                Logger::error( 'Job', 'Failed to upload final art to R2', array( 'build_uuid' => $build->build_uuid ) );
                return false;
            }
        }

        Logger::info( 'Job', 'AI generation completed', array( 'build_uuid' => $build->build_uuid, 'key' => $final_art_key ) );

        return $final_art_key;
    }

    /**
     * Step: Mockup generation
     *
     * @param Build $build Build object.
     * @return string Mockup object key.
     * @throws \Exception On failure.
     */
    private function step_mockup_generate( $build ) {
        Logger::info( 'Job', 'Step: mockup', array( 'build_uuid' => $build->build_uuid ) );

        $storage   = R2_Storage::instance();
        $generator = Mockup_Generator_Factory::get_generator();

        // Get final art URL
        $final_art_url = $this->get_asset_url( $build->final_art_key, $storage );

        if ( empty( $final_art_url ) ) {
            Logger::error( 'Job', 'No final art URL for mockup', array( 'build_uuid' => $build->build_uuid ) );
            throw new \Exception( __( 'Mockup: could not resolve final artwork URL.', 'wc-aicc' ) );
        }

        // Generate mockup
        $result = $generator->generate(
            $final_art_url,
            $build->size_label,
            $build->aspect_ratio,
            array()
        );

        if ( empty( $result['success'] ) ) {
            $detail = isset( $result['error'] ) ? trim( (string) $result['error'] ) : '';
            Logger::error(
                'Job',
                'Mockup generator error',
                array(
                    'build_uuid' => $build->build_uuid,
                    'error'      => $detail,
                )
            );
            throw new \Exception(
                $detail !== ''
                    ? sprintf(
                        /* translators: %s: reason from image library or network */
                        __( 'Mockup generation failed: %s', 'wc-aicc' ),
                        $detail
                    )
                    : __( 'Mockup generation failed.', 'wc-aicc' )
            );
        }

        $image_data = $result['image_data'];

        if ( empty( $image_data ) ) {
            Logger::error( 'Job', 'No mockup image data', array( 'build_uuid' => $build->build_uuid ) );
            throw new \Exception( __( 'Mockup generation produced empty image data.', 'wc-aicc' ) );
        }

        // Upload to R2 (use JPEG for mockups as they're composited from JPEG templates)
        $mockup_key = "builds/{$build->build_uuid}/mockup-room-1.jpg";

        if ( ! $storage->is_configured() ) {
            // Local storage for development
            $upload_dir = wp_upload_dir();
            $local_dir  = $upload_dir['basedir'] . '/wc-aicc-builds/' . $build->build_uuid;
            $local_file = $local_dir . '/mockup-room-1.jpg';

            if ( ! file_exists( $local_dir ) ) {
                wp_mkdir_p( $local_dir );
            }

            if ( file_put_contents( $local_file, $image_data ) === false ) {
                Logger::error( 'Job', 'Failed to save mockup locally', array( 'build_uuid' => $build->build_uuid ) );
                throw new \Exception( __( 'Mockup: could not save file to uploads (check directory permissions).', 'wc-aicc' ) );
            }
        } else {
            if ( ! $storage->put_object( $mockup_key, $image_data, 'image/jpeg' ) ) {
                Logger::error( 'Job', 'Failed to upload mockup to R2', array( 'build_uuid' => $build->build_uuid ) );
                throw new \Exception( __( 'Mockup: could not upload to cloud storage.', 'wc-aicc' ) );
            }
        }

        Logger::info( 'Job', 'Mockup completed', array( 'build_uuid' => $build->build_uuid, 'key' => $mockup_key ) );

        return $mockup_key;
    }

    /**
     * Allow outbound HTTP to Replicate hosts when WP_HTTP_BLOCK_EXTERNAL is enabled.
     *
     * @param mixed       $external Prior filter value.
     * @param string|null $host     Host name.
     * @return bool|mixed
     */
    private function filter_allow_replicate_hosts( $external, $host ) {
        if ( ! is_string( $host ) ) {
            return $external;
        }
        $h = strtolower( $host );
        if ( strpos( $h, 'replicate.delivery' ) !== false || strpos( $h, 'replicate.com' ) !== false ) {
            return true;
        }
        return $external;
    }

    /**
     * Download generated image bytes from Replicate output URL (separate CDN from api.replicate.com).
     *
     * @param string $url        Image URL.
     * @param string $build_uuid Build UUID for logging and filters.
     * @return string|false Binary image data or false.
     */
    private function fetch_generated_image_bytes( $url, $build_uuid ) {
        $url = esc_url_raw( $url );
        if ( $url === '' ) {
            Logger::error( 'Job', 'Empty image download URL', array( 'build_uuid' => $build_uuid ) );
            return false;
        }

        $timeout = (int) apply_filters( 'wc_aicc_download_generated_image_timeout', 180, $build_uuid, $url );

        add_filter( 'http_request_host_is_external', array( $this, 'filter_allow_replicate_hosts' ), 10, 2 );

        if ( ! function_exists( 'download_url' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $tmp = null;
        try {
            $tmp = download_url( $url, $timeout );
        } finally {
            remove_filter( 'http_request_host_is_external', array( $this, 'filter_allow_replicate_hosts' ), 10 );
        }

        if ( ! is_wp_error( $tmp ) && is_string( $tmp ) && $tmp !== '' ) {
            $data = file_get_contents( $tmp );
            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlinkunlink
            @unlink( $tmp );

            if ( $data !== false && $data !== '' ) {
                Logger::info(
                    'Job',
                    'Generated image fetched via download_url',
                    array(
                        'build_uuid' => $build_uuid,
                        'bytes'      => strlen( $data ),
                    )
                );
                return $data;
            }
        }

        if ( is_wp_error( $tmp ) ) {
            Logger::error(
                'Job',
                'download_url failed',
                array(
                    'build_uuid' => $build_uuid,
                    'error'      => $tmp->get_error_message(),
                )
            );
        } elseif ( is_string( $tmp ) && $tmp !== '' && file_exists( $tmp ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlinkunlink
            @unlink( $tmp );
        }

        return $this->fetch_generated_image_bytes_fallback( $url, $build_uuid, $timeout );
    }

    /**
     * Second attempt: wp_remote_get with explicit headers (some hosts behave differently than download_url).
     *
     * @param string $url        Image URL.
     * @param string $build_uuid Build UUID.
     * @param int    $timeout    Request timeout in seconds.
     * @return string|false
     */
    private function fetch_generated_image_bytes_fallback( $url, $build_uuid, $timeout ) {
        $ver = defined( 'WC_AICC_VERSION' ) ? \WC_AICC_VERSION : '1';

        $args = apply_filters(
            'wc_aicc_download_generated_image_args',
            array(
                'timeout'     => max( 60, $timeout ),
                'redirection' => 10,
                'headers'     => array(
                    'Accept' => 'image/webp,image/apng,image/png,image/jpeg,image/*,*/*;q=0.8',
                ),
                'user-agent'  => sprintf( 'WC-AICC/%s; %s', $ver, home_url( '/' ) ),
                'sslverify'   => true,
            ),
            $build_uuid,
            $url
        );

        add_filter( 'http_request_host_is_external', array( $this, 'filter_allow_replicate_hosts' ), 10, 2 );

        $response = wp_remote_get( $url, $args );

        remove_filter( 'http_request_host_is_external', array( $this, 'filter_allow_replicate_hosts' ), 10 );

        if ( is_wp_error( $response ) ) {
            Logger::error(
                'Job',
                'wp_remote_get fallback failed',
                array(
                    'build_uuid' => $build_uuid,
                    'wp_error'   => $response->get_error_message(),
                )
            );
            return false;
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );
        $body      = wp_remote_retrieve_body( $response );

        if ( $http_code !== 200 || $body === '' ) {
            Logger::error(
                'Job',
                'wp_remote_get fallback bad response',
                array(
                    'build_uuid' => $build_uuid,
                    'http_code'  => $http_code,
                    'body_len'   => strlen( (string) $body ),
                    'snippet'    => Logger::truncate( substr( (string) $body, 0, 400 ), 600 ),
                )
            );
            return false;
        }

        Logger::info(
            'Job',
            'Generated image fetched via wp_remote_get',
            array(
                'build_uuid' => $build_uuid,
                'bytes'      => strlen( $body ),
            )
        );

        return $body;
    }

    /**
     * Get asset URL
     *
     * @param string     $key     R2 object key.
     * @param R2_Storage $storage Storage instance.
     * @return string|null URL or null.
     */
    private function get_asset_url( $key, $storage ) {
        if ( empty( $key ) ) {
            return null;
        }

        if ( ! $storage->is_configured() ) {
            $upload_dir = wp_upload_dir();
            $local_path = str_replace( 'builds/', 'wc-aicc-builds/', $key );
            return $upload_dir['baseurl'] . '/' . $local_path;
        }

        return $storage->get_public_url( $key );
    }
}
