<?php
/**
 * Admin Builds List Template
 *
 * @package WC_AICC\Templates\Admin
 *
 * Variables available:
 * @var array  $builds         Array of Build objects.
 * @var array  $counts         Status counts.
 * @var string $status_filter  Current status filter.
 * @var object $storage        R2_Storage instance.
 * @var string $action_completed Action that was completed.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$admin_controller = \WC_AICC\Admin\Admin_Controller::instance();
?>

<div class="wrap wc-aicc-admin-wrap">
    <div class="wc-aicc-admin-header">
        <h1><?php esc_html_e( 'AI Canvas Builds', 'wc-aicc' ); ?></h1>
    </div>

    <?php if ( $action_completed ) : ?>
        <div class="wc-aicc-notice wc-aicc-notice--success">
            <?php
            switch ( $action_completed ) {
                case 'retry':
                    esc_html_e( 'Build has been re-queued for processing.', 'wc-aicc' );
                    break;
                case 'expire':
                    esc_html_e( 'Build has been deleted.', 'wc-aicc' );
                    break;
                default:
                    esc_html_e( 'Action completed successfully.', 'wc-aicc' );
            }
            ?>
        </div>
    <?php endif; ?>

    <!-- Status Filters -->
    <div class="wc-aicc-status-filters">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-aicc-builds' ) ); ?>" 
           class="<?php echo empty( $status_filter ) ? 'current' : ''; ?>">
            <?php esc_html_e( 'All', 'wc-aicc' ); ?>
            <span class="count">(<?php echo esc_html( $counts['all'] ); ?>)</span>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-aicc-builds&status=draft' ) ); ?>" 
           class="<?php echo $status_filter === 'draft' ? 'current' : ''; ?>">
            <?php esc_html_e( 'Draft', 'wc-aicc' ); ?>
            <span class="count">(<?php echo esc_html( $counts['draft'] ); ?>)</span>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-aicc-builds&status=processing' ) ); ?>" 
           class="<?php echo $status_filter === 'processing' ? 'current' : ''; ?>">
            <?php esc_html_e( 'Processing', 'wc-aicc' ); ?>
            <span class="count">(<?php echo esc_html( $counts['processing'] ); ?>)</span>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-aicc-builds&status=ready' ) ); ?>" 
           class="<?php echo $status_filter === 'ready' ? 'current' : ''; ?>">
            <?php esc_html_e( 'Ready', 'wc-aicc' ); ?>
            <span class="count">(<?php echo esc_html( $counts['ready'] ); ?>)</span>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-aicc-builds&status=failed' ) ); ?>" 
           class="<?php echo $status_filter === 'failed' ? 'current' : ''; ?>">
            <?php esc_html_e( 'Failed', 'wc-aicc' ); ?>
            <span class="count">(<?php echo esc_html( $counts['failed'] ); ?>)</span>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-aicc-builds&status=ordered' ) ); ?>" 
           class="<?php echo $status_filter === 'ordered' ? 'current' : ''; ?>">
            <?php esc_html_e( 'Ordered', 'wc-aicc' ); ?>
            <span class="count">(<?php echo esc_html( $counts['ordered'] ); ?>)</span>
        </a>
    </div>

    <?php if ( empty( $builds ) ) : ?>
        <div class="wc-aicc-empty-state">
            <h3><?php esc_html_e( 'No builds found', 'wc-aicc' ); ?></h3>
            <p><?php esc_html_e( 'Builds will appear here when customers use the canvas configurator.', 'wc-aicc' ); ?></p>
        </div>
    <?php else : ?>
        <table class="wc-aicc-builds-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Build UUID', 'wc-aicc' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'wc-aicc' ); ?></th>
                    <th><?php esc_html_e( 'Product', 'wc-aicc' ); ?></th>
                    <th><?php esc_html_e( 'Options', 'wc-aicc' ); ?></th>
                    <th><?php esc_html_e( 'User', 'wc-aicc' ); ?></th>
                    <th><?php esc_html_e( 'Assets', 'wc-aicc' ); ?></th>
                    <th><?php esc_html_e( 'Created', 'wc-aicc' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'wc-aicc' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $builds as $build ) : ?>
                    <?php
                    $product = wc_get_product( $build->product_id );
                    $user = $build->user_id ? get_user_by( 'id', $build->user_id ) : null;
                    ?>
                    <tr>
                        <td>
                            <code class="wc-aicc-uuid" title="<?php echo esc_attr( $build->build_uuid ); ?>">
                                <?php echo esc_html( substr( $build->build_uuid, 0, 8 ) ); ?>...
                            </code>
                        </td>
                        <td>
                            <span class="wc-aicc-status-badge <?php echo esc_attr( $admin_controller->get_status_class( $build->status ) ); ?>">
                                <?php echo esc_html( $admin_controller->get_status_label( $build->status ) ); ?>
                            </span>
                            <?php if ( $build->error_message ) : ?>
                                <br><small style="color: #c00;"><?php echo esc_html( wp_trim_words( $build->error_message, 5 ) ); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( $product ) : ?>
                                <a href="<?php echo esc_url( get_edit_post_link( $build->product_id ) ); ?>">
                                    <?php echo esc_html( $product->get_name() ); ?>
                                </a>
                                <br><small><?php echo esc_html( $build->size_label ); ?></small>
                            <?php else : ?>
                                <?php esc_html_e( 'Deleted', 'wc-aicc' ); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $opts  = $build->customization_options;
                            $parts = is_array( $opts ) ? \WC_AICC\Config\Prompt_Builder::summarize_option_labels( $opts ) : array();
                            echo esc_html( ! empty( $parts ) ? implode( ', ', $parts ) : '-' );
                            ?>
                        </td>
                        <td>
                            <?php if ( $user ) : ?>
                                <a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>">
                                    <?php echo esc_html( $user->display_name ); ?>
                                </a>
                            <?php else : ?>
                                <?php esc_html_e( 'Guest', 'wc-aicc' ); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="wc-aicc-asset-links">
                                <?php
                                $assets = array(
                                    'original'  => $build->original_key,
                                    'final_art' => $build->final_art_key,
                                    'mockup'    => $build->mockup_key,
                                );
                                foreach ( $assets as $type => $key ) :
                                    if ( $key ) :
                                        $url = $admin_controller->get_asset_url( $key, $storage );
                                        ?>
                                        <a href="<?php echo esc_url( $url ); ?>" target="_blank">
                                            <?php echo esc_html( ucfirst( str_replace( '_', ' ', $type ) ) ); ?>
                                        </a>
                                        <?php
                                    endif;
                                endforeach;
                                ?>
                            </div>
                        </td>
                        <td>
                            <?php echo esc_html( date_i18n( 'M j, Y g:i a', strtotime( $build->created_at ) ) ); ?>
                        </td>
                        <td>
                            <div class="wc-aicc-actions">
                                <?php if ( in_array( $build->status, array( 'failed', 'draft' ), true ) ) : ?>
                                    <a href="<?php echo esc_url( wp_nonce_url( 
                                        admin_url( 'admin.php?page=wc-aicc-builds&action=retry&build_uuid=' . $build->build_uuid ),
                                        'wc_aicc_admin_action'
                                    ) ); ?>" class="retry-action">
                                        <?php esc_html_e( 'Retry', 'wc-aicc' ); ?>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ( $build->status !== 'ordered' ) : ?>
                                    <a href="<?php echo esc_url( wp_nonce_url( 
                                        admin_url( 'admin.php?page=wc-aicc-builds&action=expire&build_uuid=' . $build->build_uuid ),
                                        'wc_aicc_admin_action'
                                    ) ); ?>" 
                                       class="expire-action"
                                       onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this build?', 'wc-aicc' ); ?>');">
                                        <?php esc_html_e( 'Delete', 'wc-aicc' ); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="wc-aicc-footer" style="margin-top: 20px; color: #666; font-size: 12px;">
        <p>
            <?php 
            printf(
                esc_html__( 'Builds older than 72 hours (except ordered) are automatically cleaned up daily. Storage: %s', 'wc-aicc' ),
                $storage->is_configured() ? 'Cloudflare R2' : 'Local (development)'
            );
            ?>
        </p>
    </div>
</div>
