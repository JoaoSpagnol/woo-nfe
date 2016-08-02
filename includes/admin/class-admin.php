<?php

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists('WC_NFe_Admin') ) :

/**
 * WooCommerce NFe WC_NFe_Admin Class
 *
 * @author   NFe.io
 * @package  WooCommerce_NFe/Class/WC_NFe_Admin
 * @version  1.0.0
 */
class WC_NFe_Admin {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Filters
		add_filter( 'manage_edit-shop_order_columns',               				array( $this, 'order_status_column_header' ), 20 );
		add_filter( 'woocommerce_product_data_tabs',                				array( $this, 'product_data_tab' ) );

		// Actions
		add_action( 'manage_shop_order_posts_custom_column',         				array( $this, 'order_status_column_content' ) );
		add_action( 'woocommerce_product_after_variable_attributes', 				array( $this, 'variation_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation',            				array( $this, 'save_variations_fields' ), 10, 2 );
		add_action( 'woocommerce_product_data_panels',               				array( $this, 'product_data_fields' ) );
		add_action( 'woocommerce_process_product_meta',              				array( $this, 'product_data_fields_save' ) );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', 			array( $this, 'display_order_data_in_admin' ), 20 );
		add_action( 'admin_enqueue_scripts',                 						array( $this, 'register_enqueue_css' ) );

		// Issue triggers
		add_action( 'woocommerce_order_status_pending_to_processing_notification', 	array( $this, 'issue_trigger' ) );
		add_action( 'woocommerce_order_status_pending_to_completed_notification', 	array( $this, 'issue_trigger' ) );
		add_action( 'woocommerce_order_status_completed_notification', 				array( $this, 'issue_trigger' ) );
		
		// WooCommerce Subscriptions Support
		if ( class_exists('WC_Subscriptions') ) {
			add_action( 'processed_subscription_payments_for_order', 				array( $this, 'issue_trigger') );
			add_action( 'woocommerce_renewal_order_payment_complete', 				array( $this, 'issue_trigger') );
		}
	}

	/**
	 * Issue a NFe receipt when WooCommerce does its thing.
	 * 
	 * @param  int $order_id Order ID
	 * @return bool true|false
	 */
	public function issue_trigger( $order_id ) {
		if ( nfe_get_field('emissao') == 'manual' ) {
			return;
		}

		if ( $order_id ) {
			$order    = nfe_wc_get_order( $order_id );
			$order_id = $order->id;
		}

		// Don't issue if there is no order address information
		if ( ! nfe_order_address_filled( $order_id ) ) {
			NFe_Woo()->issue_invoice( array( $order_id ) );
		}
	}

	/**
	 * Adds NFe custom tab
	 * 
	 * @param array $product_data_tabs Array of product tabs
	 * @return array Array with product data tabs
	 */
	public function product_data_tab( $product_data_tabs ) {
		$product_data_tabs['nfe-product-info-tab'] = array(
			'label'     => __( 'WooCommerce NFe', 'woo-nfe' ),
			'target'    => 'nfe_product_info_data',
			'class'     => array( 'hide_if_variable' ),
		);
		return $product_data_tabs;
	}

	/**
	 * Adds NFe product fields (tab content)
	 *
	 * @global int $post Uses to fetch the current product ID
	 * 
	 * @return string
	 */
	public function product_data_fields() {
		global $post;
		?>
		<div id="nfe_product_info_data" class="panel woocommerce_options_panel">
			<?php
			woocommerce_wp_text_input( 
				array( 
					'id'            => '_simple_cityservicecode',
					'label'         => __( 'CityServiceCode', 'woo-nfe' ), 
					'wrapper_class' => 'hide_if_variable', 
					'desc_tip'      => 'true',
					'description'   => __( 'Enter the CityServiceCode.', 'woo-nfe' ),
					'value'         => get_post_meta( $post->ID, '_simple_cityservicecode', true )
				)
			);

			woocommerce_wp_text_input( 
				array( 
					'id'            => '_simple_federalservicecode',
					'label'         => __( 'FederalServiceCode', 'woo-nfe' ), 
					'wrapper_class' => 'hide_if_variable',
					'desc_tip'      => 'true',
					'description'   => __( 'Enter the FederalServiceCode.', 'woo-nfe' ),
					'value'         => get_post_meta( $post->ID, '_simple_federalservicecode', true )
				)
			);

			woocommerce_wp_textarea_input( 
				array( 
					'id'            => '_simple_nfe_product_desc',
					'label'         => __( 'Product Description', 'woo-nfe' ),
					'wrapper_class' => 'hide_if_variable', 
					'desc_tip'      => 'true', 
					'description'   => __( 'Description for this product output in NFe receipt.', 'woo-nfe' ),
					'value'         => get_post_meta( $post->ID, '_simple_nfe_product_desc', true )
				)
			);
			?>
		</div>
		<?php
	}

	/**
	 * Saving product data information.
	 * 
	 * @param  int $post_id Product ID
	 * @return bool true|false
	 */
	public function product_data_fields_save( $post_id ) {
		// Text Field - City Service Code
		$simple_cityservicecode = $_POST['_simple_cityservicecode'];
		update_post_meta( $post_id, '_simple_cityservicecode', esc_attr( $simple_cityservicecode ) );

		// Text Field - Federal Service Code
		$simple_federalservicecode = $_POST['_simple_federalservicecode'];
		update_post_meta( $post_id, '_simple_federalservicecode', esc_attr( $simple_federalservicecode ) );

		// TextArea Field - Product Description
		$simple_product_desc = $_POST['_simple_nfe_product_desc'];
		update_post_meta( $post_id, '_simple_nfe_product_desc', esc_html( $simple_product_desc ) );
	}

   /**
	* Adds the NFe fields for product variations
	* 
	* @param  array $loop
	* @param  string $variation_data
	* @param  string $variation
	* @return array
	*/
	public function variation_fields( $loop, $variation_data, $variation ) {
		woocommerce_wp_text_input( 
			array( 
				'id'            => '_cityservicecode[' . $variation->ID . ']',
				'label'         => __( 'NFe CityServiceCode', 'woo-nfe' ), 
				'desc_tip'      => 'true',
				'description'   => __( 'Enter the CityServiceCode.', 'woo-nfe' ),
				'value'         => get_post_meta( $variation->ID, '_cityservicecode', true )
			)
		);

		woocommerce_wp_text_input( 
			array( 
				'id'            => '_federalservicecode[' . $variation->ID . ']',
				'label'         => __( 'NFe FederalServiceCode', 'woo-nfe' ), 
				'desc_tip'      => 'true',
				'description'   => __( 'Enter the FederalServiceCode.', 'woo-nfe' ),
				'value'         => get_post_meta( $variation->ID, '_federalservicecode', true )
			)
		);

		woocommerce_wp_textarea_input( 
			array( 
				'id'            => '_nfe_product_variation_desc[' . $variation->ID . ']',
				'label'         => __( 'NFe Product Description', 'woo-nfe' ),
				'value'         => get_post_meta( $variation->ID, '_nfe_product_variation_desc', true )
			)
		);
	}
   
   /**
	* Save the NFe fields for product variations
	* 
	* @param  int $post_id Product ID
	* @return bool true|false
	*/
	public function save_variations_fields( $post_id ) {
		// Text Field - City Service Code
		$cityservicecode = $_POST['_cityservicecode'][ $post_id ];
		update_post_meta( $post_id, '_cityservicecode', esc_attr( $cityservicecode ) );

		// Text Field - Federal Service Code
		$_federalservicecode = $_POST['_federalservicecode'][ $post_id ];
		update_post_meta( $post_id, '_federalservicecode', esc_attr( $_federalservicecode ) );

		// TextArea Field - Product Variation Description
		$product_desc = $_POST['_nfe_product_variation_desc'][ $post_id ];
		update_post_meta( $post_id, '_nfe_product_variation_desc', esc_html( $product_desc ) );
	}

	/**
	 * NFe Column Header on Order Status
	 * 
	 * @param  array $columns Array of Columns
	 * @return array          NFe Custom Column
	 */
	public function order_status_column_header( $columns ) {
		$new_columns = array();

		foreach ( $columns as $column_name => $column_info ) {
			$new_columns[ $column_name ] = $column_info;

			if ( 'order_actions' === $column_name ) {
				$new_columns['sales_receipt'] = __( 'Sales Receipt', 'woo-nfe' );
			}
		}
		return $new_columns;
	}

	/**
	 * Column Content on Order Status
	 *
	 * @return string
	 */
	public function order_status_column_content( $column ) {
		global $post;

		$order    = nfe_wc_get_order( $post->ID );
		$order_id = $order->id;
		$nfe      = get_post_meta( $order_id, 'nfe_issued', true );
		$status   = array( 'PullFromCityHall', 'WaitingCalculateTaxes', 'WaitingDefineRpsNumber' );
	   
		if ( 'sales_receipt' == $column ) {
			?><p>
			<?php
			$actions = array();

			if ( nfe_get_field('nfe_enable') == 'yes' && $order->has_status( 'completed' ) ) {
				if ( $nfe && $nfe['status'] == 'Cancelled' || $nfe['status'] == 'Issued' ) {
					if ( $nfe['status'] == 'Cancelled' ) {
						$actions['woo_nfe_cancelled'] = array(
							'name'      => __( 'NFe Cancelled', 'woo-nfe' ),
							'action'    => 'woo_nfe_cancelled'
						);
					}
					else if ( $nfe['status'] == 'Issued' ) {
						$actions['woo_nfe_emitida'] = array(
							'name'      => __( 'Issued', 'woo-nfe' ),
							'action'    => 'woo_nfe_emitida'
						);
					}

					$actions['woo_nfe_download'] = array(
						'url'       => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_nfe_download&order_id=' . $order_id ), 'woo_nfe_download' ),
						'name'      => __( 'Download NFe', 'woo-nfe' ),
						'action'    => 'woo_nfe_download'
					);
				}
				else if ( $nfe && in_array( $nfe['status'], $status ) ) {
					$actions['woo_nfe_issuing'] = array(
						'name'      => __( 'Issuing NFe', 'woo-nfe' ),
						'action'    => 'woo_nfe_issuing'
					);
				}
				else {
					if ( nfe_order_address_filled( $order_id ) ) {
						$actions['woo_nfe_pending_address'] = array(
							'name'      => __( 'Pending Address', 'woo-nfe' ),
							'action'    => 'woo_nfe_pending_address'
						);
					}
					else {
						if ( nfe_get_field('issue_past_notes') == 'yes' ) {
							if ( nfe_issue_past_orders( $order ) && empty( $nfe['id'] ) ) {
								$actions['woo_nfe_issue'] = array(
									'url'       => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_nfe_issue&order_id=' . $order_id ), 'woo_nfe_issue' ),
									'name'      => __( 'Issue NFe', 'woo-nfe' ),
									'action'    => 'woo_nfe_issue'
								);
							}
							else {
								$actions['woo_nfe_expired'] = array(
									'name'      => __( 'Issue Expired', 'woo-nfe' ),
									'action'    => 'woo_nfe_expired'
								);
							}
						}
						else {
							$actions['woo_nfe_issue'] = array(
								'url'       => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_nfe_issue&order_id=' . $order_id ), 'woo_nfe_issue' ),
								'name'      => __( 'Issue NFe', 'woo-nfe' ),
								'action'    => 'woo_nfe_issue'
							);
						}
					}
				}
			}

			if ( nfe_get_field('nfe_enable') == 'no' && current_user_can('manage_woocommerce') ) {
				$actions['woo_nfe_tab'] = array(
					'url'       => WOOCOMMERCE_NFE_SETTINGS_URL,
					'name'      => __( 'Enable NFe', 'woo-nfe' ),
					'action'    => 'woo_nfe_tab'
				);
			}

			foreach ( $actions as $action ) {
				if ( $action['action'] == 'woo_nfe_issue' || $action['action'] == 'woo_nfe_download' ) {
					printf( '<a class="button view %s" href="%s" data-tip="%s">%s</a>', 
						esc_attr( $action['action'] ), 
						esc_url( $action['url'] ), 
						esc_attr( $action['name'] ), 
						esc_attr( $action['name'] ) 
					);
				}
				else {
					printf( '<span class="woo_nfe_actions %s" data-tip="%s">%s</span>', 
						esc_attr( $action['action'] ), 
						esc_attr( $action['name'] ), 
						esc_attr( $action['name'] ) 
					);
				}
			}
			?>
			</p><?php
		}
	}

	/**
	 * Adds NFe information on order page
	 * 
	 * @param  WC_Order $order
	 * @return string
	 */
	public function display_order_data_in_admin( $order ) {
		$nfe = get_post_meta( $order->id, 'nfe_issued', true ); 
		?>
	    <h4><?php echo '<strong>' . __( 'NFe Details', 'woo-nfe' ) . '</strong><br />'; ?></h4>
	    <div class="nfe-details">
	        <?php 
	        	echo '<p>';
	            echo '<strong>' . __( 'Status', 'woo-nfe' ) . ': </strong>' . $nfe['status'] . '<br />';
	            echo '<strong>' . __( 'Number', 'woo-nfe' ) . ': </strong>' . $nfe['number'] . '<br />';
	            echo '<strong>' . __( 'Check Code', 'woo-nfe' ) . ': </strong>' . $nfe['checkCode'] . '<br />';
	            echo '<strong>' . __( 'Issued on', 'woo-nfe' ) . ': </strong>' . date_i18n( get_option( 'date_format' ), strtotime( $nfe['issuedOn'] ) ) . '<br />';
	            echo '<strong>' . __( 'Price', 'woo-nfe' ) . ': </strong>' . wc_price( $nfe['amountNet'] ) . '<br />';
	            echo '</p>';
			?>
	    </div>
	<?php }

	/**
     * Adds the NFe Admin CSS
     */
    public function register_enqueue_css() {
        wp_register_style( 'nfe-woo-admin-css', plugins_url( 'woo-nfe/assets/css/nfe' ) . '.css' );
        wp_enqueue_style( 'nfe-woo-admin-css' );
    }
}

return new WC_NFe_Admin();

endif;

// That's it! =)
