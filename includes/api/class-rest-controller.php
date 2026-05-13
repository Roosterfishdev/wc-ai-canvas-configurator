<?php
/**
 * REST API Controller
 *
 * Handles all REST API endpoints for the configurator.
 *
 * @package WC_AICC\API
 */

namespace WC_AICC\API;

use WC_AICC\Logger;
use WC_AICC\Session_Manager;
use WC_AICC\Models\Build;
use WC_AICC\Repository\Build_Repository;
use WC_AICC\Storage\R2_Storage;
use WC_AICC\Providers\AI_Provider_Factory;
use WC_AICC\Config\Prompt_Builder;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST Controller class
 */
class REST_Controller {

    /**
     * Singleton instance
     *
     * @var REST_Controller|null
     */
    private static $instance = null;

    /**
     * API namespace
     *
     * @var string
     */
    const NAMESPACE = 'wc-aicc/v1';

    /**
     * Max upload size (10MB)
     *
     * @var int
     */
    const MAX_UPLOAD_SIZE = 10485760;

    /**
     * Allowed mime types
     *
     * @var array
     */
    const ALLOWED_MIMES = array(
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    );

    /**
     * Get singleton instance
     *
     * @return REST_Controller
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
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST routes
     */
    public function register_routes() {
        // POST /builds - Create new build
        register_rest_route(
            self::NAMESPACE,
            '/builds',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'create_build' ),
                'permission_callback' => array( $this, 'check_nonce_permission' ),
                'args'                => array(
                    'product_id'   => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                    'variation_id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                    'size_label'   => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'aspect_ratio' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        // POST /builds/{uuid}/upload - Upload image
        register_rest_route(
            self::NAMESPACE,
            '/builds/(?P<build_uuid>[a-f0-9-]{36})/upload',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'upload_image' ),
                'permission_callback' => array( $this, 'check_build_access' ),
                'args'                => array(
                    'build_uuid' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        // POST /builds/{uuid}/generate - Start generation
        register_rest_route(
            self::NAMESPACE,
            '/builds/(?P<build_uuid>[a-f0-9-]{36})/generate',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'generate_build' ),
                'permission_callback' => array( $this, 'check_build_access' ),
                'args'                => array(
                    'build_uuid'           => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'customization_options' => array(
                        'required'          => false,
                        'type'              => 'object',
                        'default'           => array(),
                    ),
                ),
            )
        );

        // GET /builds/{uuid} - Get build status
        register_rest_route(
            self::NAMESPACE,
            '/builds/(?P<build_uuid>[a-f0-9-]{36})',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_build' ),
                'permission_callback' => array( $this, 'check_build_access' ),
                'args'                => array(
                    'build_uuid' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        // GET /options - Get customization options config for UI
        register_rest_route(
            self::NAMESPACE,
            '/options',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_options' ),
                'permission_callback' => '__return_true',
            )
        );

        // POST /session - Create/get session
        register_rest_route(
            self::NAMESPACE,
            '/session',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'create_session' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * Check nonce permission
     *
     * @param \WP_REST_Request $request Request object.
     * @return bool|\WP_Error
     */
    public function check_nonce_permission( $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        
        if ( empty( $nonce ) ) {
            $nonce = $request->get_param( '_wpnonce' );
        }

        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'Invalid or missing nonce.', 'wc-aicc' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Check build access permission
     *
     * @param \WP_REST_Request $request Request object.
     * @return bool|\WP_Error
     */
    public function check_build_access( $request ) {
        // First check nonce
        $nonce_check = $this->check_nonce_permission( $request );
        if ( is_wp_error( $nonce_check ) ) {
            return $nonce_check;
        }

        $build_uuid = $request->get_param( 'build_uuid' );
        
        if ( empty( $build_uuid ) ) {
            return new \WP_Error(
                'rest_invalid_param',
                __( 'Invalid build UUID.', 'wc-aicc' ),
                array( 'status' => 400 )
            );
        }

        // Get build
        $repository = Build_Repository::instance();
        $build      = $repository->get_by_uuid( $build_uuid );

        if ( ! $build ) {
            return new \WP_Error(
                'rest_not_found',
                __( 'Build not found.', 'wc-aicc' ),
                array( 'status' => 404 )
            );
        }

        // Check access
        $session_key = Session_Manager::get_session_key_from_header();
        $user_id     = get_current_user_id();

        if ( ! $repository->can_access( $build, $session_key, $user_id ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to access this build.', 'wc-aicc' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Create new build
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function create_build( $request ) {
        $product_id   = $request->get_param( 'product_id' );
        $variation_id = $request->get_param( 'variation_id' );
        $size_label   = $request->get_param( 'size_label' );
        $aspect_ratio = $request->get_param( 'aspect_ratio' );

        // Validate product exists and is enabled
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return new \WP_Error(
                'invalid_product',
                __( 'Invalid product.', 'wc-aicc' ),
                array( 'status' => 400 )
            );
        }

        // Check if configurator is enabled for this product
        if ( get_post_meta( $product_id, '_wc_aicc_enabled', true ) !== 'yes' ) {
            return new \WP_Error(
                'configurator_disabled',
                __( 'Configurator is not enabled for this product.', 'wc-aicc' ),
                array( 'status' => 400 )
            );
        }

        // Validate variation exists
        $variation = wc_get_product( $variation_id );
        if ( ! $variation || $variation->get_parent_id() !== $product_id ) {
            return new \WP_Error(
                'invalid_variation',
                __( 'Invalid variation.', 'wc-aicc' ),
                array( 'status' => 400 )
            );
        }

        // Get session and user
        $session_key = Session_Manager::get_session_key();
        $user_id     = get_current_user_id() ?: null;

        // Create build
        $repository = Build_Repository::instance();
        $build      = $repository->create(
            array(
                'session_key'  => $session_key,
                'user_id'      => $user_id,
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'size_label'   => $size_label,
                'aspect_ratio' => $aspect_ratio,
                'status'       => Build::STATUS_DRAFT,
            )
        );

        if ( ! $build ) {
            return new \WP_Error(
                'create_failed',
                __( 'Failed to create build.', 'wc-aicc' ),
                array( 'status' => 500 )
            );
        }

        return new \WP_REST_Response(
            array(
                'build_uuid'  => $build->build_uuid,
                'status'      => $build->status,
                'session_key' => $session_key,
            ),
            201
        );
    }

    /**
     * Upload image
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function upload_image( $request ) {
        $build_uuid = $request->get_param( 'build_uuid' );

        // Get build
        $repository = Build_Repository::instance();
        $build      = $repository->get_by_uuid( $build_uuid );

        if ( ! $build ) {
            return new \WP_Error(
                'not_found',
                __( 'Build not found.', 'wc-aicc' ),
                array( 'status' => 404 )
            );
        }

        // Check for uploaded file
        $files = $request->get_file_params();
        
        if ( empty( $files['image'] ) ) {
            return new \WP_Error(
                'no_file',
                __( 'No image file provided.', 'wc-aicc' ),
                array( 'status' => 400 )
            );
        }

        $file = $files['image'];

        // Check for upload errors
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            return new \WP_Error(
                'upload_error',
                $this->get_upload_error_message( $file['error'] ),
                array( 'status' => 400 )
            );
        }

        // Validate file size
        if ( $file['size'] > self::MAX_UPLOAD_SIZE ) {
            return new \WP_Error(
                'file_too_large',
                __( 'File size exceeds 10MB limit.', 'wc-aicc' ),
                array( 'status' => 400 )
            );
        }

        // Validate mime type
        $finfo     = finfo_open( FILEINFO_MIME_TYPE );
        $mime_type = finfo_file( $finfo, $file['tmp_name'] );
        finfo_close( $finfo );

        if ( ! isset( self::ALLOWED_MIMES[ $mime_type ] ) ) {
            return new \WP_Error(
                'invalid_mime',
                __( 'Invalid file type. Allowed: JPG, PNG, WebP.', 'wc-aicc' ),
                array( 'status' => 400 )
            );
        }

        $extension = self::ALLOWED_MIMES[ $mime_type ];

        // Read file content
        $binary = file_get_contents( $file['tmp_name'] );
        
        if ( $binary === false ) {
            return new \WP_Error(
                'read_failed',
                __( 'Failed to read uploaded file.', 'wc-aicc' ),
                array( 'status' => 500 )
            );
        }

        // Upload to R2
        $storage = R2_Storage::instance();
        $key     = "builds/{$build_uuid}/original.{$extension}";

        if ( ! $storage->is_configured() ) {
            // For development, store locally in uploads
            $upload_dir  = wp_upload_dir();
            $local_dir   = $upload_dir['basedir'] . '/wc-aicc-builds/' . $build_uuid;
            $local_file  = $local_dir . "/original.{$extension}";

            if ( ! file_exists( $local_dir ) ) {
                wp_mkdir_p( $local_dir );
            }

            if ( ! move_uploaded_file( $file['tmp_name'], $local_file ) ) {
                return new \WP_Error(
                    'save_failed',
                    __( 'Failed to save file.', 'wc-aicc' ),
                    array( 'status' => 500 )
                );
            }

            $original_url = $upload_dir['baseurl'] . '/wc-aicc-builds/' . $build_uuid . "/original.{$extension}";
        } else {
            if ( ! $storage->put_object( $key, $binary, $mime_type ) ) {
                return new \WP_Error(
                    'upload_failed',
                    __( 'Failed to upload file to storage.', 'wc-aicc' ),
                    array( 'status' => 500 )
                );
            }

            $original_url = $storage->get_public_url( $key );
        }

        // Update build
        $repository->update_by_uuid(
            $build_uuid,
            array( 'original_key' => $key )
        );

        // Refresh build
        $build = $repository->get_by_uuid( $build_uuid );

        return new \WP_REST_Response(
            array(
                'build_uuid'   => $build->build_uuid,
                'status'       => $build->status,
                'original_url' => $original_url,
            ),
            200
        );
    }

    /**
     * Generate build (enqueue processing job)
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function generate_build( $request ) {
        $build_uuid           = $request->get_param( 'build_uuid' );
        $customization_options = $request->get_param( 'customization_options' );

        // Get build
        $repository = Build_Repository::instance();
        $build      = $repository->get_by_uuid( $build_uuid );

        if ( ! $build ) {
            return new \WP_Error(
                'not_found',
                __( 'Build not found.', 'wc-aicc' ),
                array( 'status' => 404 )
            );
        }

        // Validate build has original image
        if ( empty( $build->original_key ) ) {
            return new \WP_Error(
                'no_image',
                __( 'Please upload an image first.', 'wc-aicc' ),
                array( 'status' => 400 )
            );
        }

        // Sanitize and validate customization options
        $customization_options = Prompt_Builder::sanitize_options( (array) $customization_options );

        Logger::info(
            'REST',
            'generate_build request',
            array(
                'build_uuid'     => $build_uuid,
                'previous_status'=> $build->status,
                'regen_count'    => (string) $build->regen_count,
            )
        );

        // Check if already processing
        if ( $build->status === Build::STATUS_PROCESSING ) {
            Logger::warning(
                'REST',
                'generate_build rejected: already_processing',
                array( 'build_uuid' => $build_uuid )
            );
            return new \WP_Error(
                'already_processing',
                __( 'Build is already being processed.', 'wc-aicc' ),
                array( 'status' => 400 )
            );
        }

        // Update build with customization options
        $repository->update_by_uuid(
            $build_uuid,
            array(
                'customization_options' => $customization_options,
                'status'                 => Build::STATUS_PROCESSING,
            )
        );

        // Increment regen count if regenerating
        if ( in_array( $build->status, array( Build::STATUS_READY, Build::STATUS_FAILED ), true ) ) {
            $repository->update_by_uuid(
                $build_uuid,
                array( 'regen_count' => $build->regen_count + 1 )
            );
        }

        // Enqueue Action Scheduler job
        if ( function_exists( 'as_enqueue_async_action' ) ) {
            as_enqueue_async_action(
                'wc_aicc_process_build',
                array( 'build_uuid' => $build_uuid ),
                'wc-aicc'
            );
            Logger::info(
                'REST',
                'Queued wc_aicc_process_build (Action Scheduler)',
                array( 'build_uuid' => $build_uuid )
            );
        } else {
            // Fallback: process immediately (not recommended for production)
            Logger::warning(
                'REST',
                'as_enqueue_async_action missing; running wc_aicc_process_build synchronously',
                array( 'build_uuid' => $build_uuid )
            );
            do_action( 'wc_aicc_process_build', $build_uuid );
        }

        return new \WP_REST_Response(
            array(
                'build_uuid' => $build_uuid,
                'status'     => Build::STATUS_PROCESSING,
            ),
            200,
            array(
                'Cache-Control'     => 'no-store, no-cache, must-revalidate, max-age=0',
                'CDN-Cache-Control' => 'no-store',
            )
        );
    }

    /**
     * Get build status and URLs
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_build( $request ) {
        $build_uuid = $request->get_param( 'build_uuid' );

        // Get build
        $repository = Build_Repository::instance();
        $build      = $repository->get_by_uuid( $build_uuid );

        if ( ! $build ) {
            return new \WP_Error(
                'not_found',
                __( 'Build not found.', 'wc-aicc' ),
                array( 'status' => 404 )
            );
        }

        // Build response with URLs
        $storage  = R2_Storage::instance();
        $payload = array(
            'build_uuid'           => $build->build_uuid,
            'product_id'           => $build->product_id,
            'variation_id'         => $build->variation_id,
            'size_label'           => $build->size_label,
            'aspect_ratio'         => $build->aspect_ratio,
            'customization_options'=> $build->customization_options,
            'status'               => $build->status,
            'regen_count'   => $build->regen_count,
            'error_message' => $build->error_message,
            'created_at'    => $build->created_at,
            'updated_at'    => $build->updated_at,
            'urls'          => array(
                'original'  => $this->get_asset_url( $build->original_key, $storage ),
                'cropped'   => $this->get_asset_url( $build->cropped_key, $storage ),
                'final_art' => $this->get_asset_url( $build->final_art_key, $storage ),
                'mockup'    => $this->get_asset_url( $build->mockup_key, $storage ),
            ),
        );

        $rest = new \WP_REST_Response( $payload, 200 );
        // Critical: intermediaries must not cache this (CDN often caches REST GET unless forced).
        $rest->header( 'Cache-Control', 'private, no-cache, no-store, must-revalidate, max-age=0' );
        $rest->header( 'CDN-Cache-Control', 'private, no-store' );
        $rest->header( 'Vary', 'Cookie' );

        return $rest;
    }

    /**
     * Get customization options config for UI
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_options( $request ) {
        return new \WP_REST_Response(
            array( 'options' => Prompt_Builder::get_options_config() ),
            200
        );
    }

    /**
     * Create or get session
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function create_session( $request ) {
        $session_key = Session_Manager::get_session_key();

        return new \WP_REST_Response(
            array(
                'session_key' => $session_key,
                'user_id'     => get_current_user_id() ?: null,
            ),
            200
        );
    }

    /**
     * Get asset URL (public or signed)
     *
     * @param string     $key     R2 object key.
     * @param R2_Storage $storage Storage instance.
     * @return string|null URL or null if no key.
     */
    private function get_asset_url( $key, $storage ) {
        if ( empty( $key ) ) {
            return null;
        }

        // Check if using local storage (development)
        if ( ! $storage->is_configured() ) {
            $upload_dir = wp_upload_dir();
            $local_path = str_replace( 'builds/', 'wc-aicc-builds/', $key );
            return $upload_dir['baseurl'] . '/' . $local_path;
        }

        // Use public URL (CDN)
        return $storage->get_public_url( $key );

        // TODO: Switch to signed URLs for sensitive content
        // return $storage->get_signed_url( $key, 3600 );
    }

    /**
     * Get upload error message
     *
     * @param int $error_code PHP upload error code.
     * @return string Error message.
     */
    private function get_upload_error_message( $error_code ) {
        $messages = array(
            UPLOAD_ERR_INI_SIZE   => __( 'File exceeds server upload limit.', 'wc-aicc' ),
            UPLOAD_ERR_FORM_SIZE  => __( 'File exceeds form upload limit.', 'wc-aicc' ),
            UPLOAD_ERR_PARTIAL    => __( 'File was only partially uploaded.', 'wc-aicc' ),
            UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded.', 'wc-aicc' ),
            UPLOAD_ERR_NO_TMP_DIR => __( 'Missing temporary folder.', 'wc-aicc' ),
            UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk.', 'wc-aicc' ),
            UPLOAD_ERR_EXTENSION  => __( 'File upload stopped by extension.', 'wc-aicc' ),
        );

        return $messages[ $error_code ] ?? __( 'Unknown upload error.', 'wc-aicc' );
    }
}
