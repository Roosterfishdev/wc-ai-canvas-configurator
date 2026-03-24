<?php
/**
 * Build Model
 *
 * Represents a single canvas build/configuration.
 *
 * @package WC_AICC\Models
 */

namespace WC_AICC\Models;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Build Model class
 */
class Build {

    /**
     * Build statuses
     */
    const STATUS_DRAFT      = 'draft';
    const STATUS_PROCESSING = 'processing';
    const STATUS_READY      = 'ready';
    const STATUS_FAILED     = 'failed';
    const STATUS_ORDERED    = 'ordered';
    const STATUS_EXPIRED    = 'expired';

    /**
     * Build ID
     *
     * @var int
     */
    public $id = 0;

    /**
     * Build UUID
     *
     * @var string
     */
    public $build_uuid = '';

    /**
     * Session key
     *
     * @var string
     */
    public $session_key = '';

    /**
     * User ID (nullable)
     *
     * @var int|null
     */
    public $user_id = null;

    /**
     * Product ID
     *
     * @var int
     */
    public $product_id = 0;

    /**
     * Variation ID
     *
     * @var int
     */
    public $variation_id = 0;

    /**
     * Size label
     *
     * @var string
     */
    public $size_label = '';

    /**
     * Aspect ratio
     *
     * @var string
     */
    public $aspect_ratio = '';

    /**
     * Customization options (JSON) - user selections for prompt building
     *
     * @var array
     */
    public $customization_options = array();

    /**
     * Status
     *
     * @var string
     */
    public $status = self::STATUS_DRAFT;

    /**
     * Regeneration count
     *
     * @var int
     */
    public $regen_count = 0;

    /**
     * Original image R2 key
     *
     * @var string
     */
    public $original_key = '';

    /**
     * Cropped image R2 key
     *
     * @var string
     */
    public $cropped_key = '';

    /**
     * Final art R2 key
     *
     * @var string
     */
    public $final_art_key = '';

    /**
     * Mockup R2 key
     *
     * @var string
     */
    public $mockup_key = '';

    /**
     * Error message
     *
     * @var string
     */
    public $error_message = '';

    /**
     * Created at timestamp
     *
     * @var string
     */
    public $created_at = '';

    /**
     * Updated at timestamp
     *
     * @var string
     */
    public $updated_at = '';

    /**
     * Constructor
     *
     * @param array|object $data Optional data to populate.
     */
    public function __construct( $data = null ) {
        if ( $data ) {
            $this->populate( $data );
        }
    }

    /**
     * Populate model from data
     *
     * @param array|object $data Data to populate from.
     */
    public function populate( $data ) {
        $data = (array) $data;

        $properties = array(
            'id', 'build_uuid', 'session_key', 'user_id', 'product_id',
            'variation_id', 'size_label', 'aspect_ratio', 'customization_options',
            'status', 'regen_count', 'original_key', 'cropped_key',
            'final_art_key', 'mockup_key', 'error_message', 'created_at', 'updated_at',
        );

        foreach ( $properties as $prop ) {
            if ( isset( $data[ $prop ] ) ) {
                $this->$prop = $data[ $prop ];
            }
        }

        // Decode customization_options from JSON
        if ( isset( $data['customization_options'] ) ) {
            $val = $data['customization_options'];
            $this->customization_options = is_array( $val ) ? $val : ( is_string( $val ) ? json_decode( $val, true ) : array() );
            $this->customization_options = is_array( $this->customization_options ) ? $this->customization_options : array();
        }

        // Type casting
        $this->id           = (int) $this->id;
        $this->user_id      = $this->user_id ? (int) $this->user_id : null;
        $this->product_id   = (int) $this->product_id;
        $this->variation_id = (int) $this->variation_id;
        $this->regen_count  = (int) $this->regen_count;
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function to_array() {
        return array(
            'id'                   => $this->id,
            'build_uuid'           => $this->build_uuid,
            'session_key'          => $this->session_key,
            'user_id'              => $this->user_id,
            'product_id'           => $this->product_id,
            'variation_id'         => $this->variation_id,
            'size_label'           => $this->size_label,
            'aspect_ratio'         => $this->aspect_ratio,
            'customization_options'=> $this->customization_options,
            'status'               => $this->status,
            'regen_count'   => $this->regen_count,
            'original_key'  => $this->original_key,
            'cropped_key'   => $this->cropped_key,
            'final_art_key' => $this->final_art_key,
            'mockup_key'    => $this->mockup_key,
            'error_message' => $this->error_message,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        );
    }

    /**
     * Get valid statuses
     *
     * @return array
     */
    public static function get_statuses() {
        return array(
            self::STATUS_DRAFT,
            self::STATUS_PROCESSING,
            self::STATUS_READY,
            self::STATUS_FAILED,
            self::STATUS_ORDERED,
            self::STATUS_EXPIRED,
        );
    }

    /**
     * Check if build is in processing state
     *
     * @return bool
     */
    public function is_processing() {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Check if build is ready
     *
     * @return bool
     */
    public function is_ready() {
        return $this->status === self::STATUS_READY;
    }

    /**
     * Check if build has failed
     *
     * @return bool
     */
    public function has_failed() {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if build can be regenerated
     *
     * @param int $max_regen Maximum regeneration attempts.
     * @return bool
     */
    public function can_regenerate( $max_regen = 3 ) {
        return $this->regen_count < $max_regen 
            && in_array( $this->status, array( self::STATUS_READY, self::STATUS_FAILED ), true );
    }
}
