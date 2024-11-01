<?php
 /**
 * Plugin Name: Woocommerce Custom Payment
 * Plugin URI: 
 * Description: Custom payment gateway and call your apis after payment successfully.
 * Version: 1.00
 * Author: 
 * Author URI: 
 * Requires at least: 4.4
 * Tested up to: 4.9.1
 * Text Domain: 
 *
 */
//-------------------------------Checkout WCPG Form------------------------------
add_action('plugins_loaded', 'init_wccustompayment_gateway_class');
function init_wccustompayment_gateway_class(){
    class WC_Gateway_WCPG extends WC_Payment_Gateway {
        public $domain;
        /**
         * Constructor for the gateway.
         */
        public function __construct() {
            $this->domain 		   = 'wccustompayment_payment';
            $this->id              = 'wccustompayment';
            $this->icon            = apply_filters('woocommerce_wccustompayment_gateway_icon', '');
            $this->has_fields      = false;
            $this->method_title    = __( 'Woocommerce Custom Payment', $this->domain );
            $this->method_description = __( 'Allows payments with woocommerce custom payment gateway. <span style="color:red;">(Note: This apis call after payment successfully.)<span>', $this->domain);

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions', $this->description );
            $this->order_status = $this->get_option( 'order_status', 'completed' );
			
			$this->api_key      = $this->get_option( 'api_key' );
			$this->user_email   = $this->get_option( 'user_email' );
			$this->doc_type     = $this->get_option( 'doc_type' );
			$this->environment  = $this->get_option( 'environment' );
			$this->clearing_integration = $this->get_option( 
											'clearing_integration' );
			$this->paypal_integration 	= $this->get_option( 
											'paypal_integration' );
			$this->language_list 		= $this->get_option( 'language_list' );
			$this->maxpayments_list 	= $this->get_option( 'maxpayments_list' );
			$this->checkout_window 		= $this->get_option( 'checkout_window' );
			
            // Actions
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_thankyou', array( $this, 'thankyou_page_WCPG' ) );

            // WCPG Emails
            add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions_WCPG' ), 10, 3 );
        }


        /**
         * Initialise Gateway Settings Form Fields.
         */
        public function init_form_fields() {
            $this->form_fields = array(								
                'enabled' => array(
                    'title'   => __( 'Enable/Disable', $this->domain ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable woocommerce custom payment gateway Payment', $this->domain ),
                    'default' => 'yes'
                ),
				'api_url' => array(
					'title'       => __( 'API URL', $this->api_url ),
					'type' 	      =>   'text',
					'description' => __( 'Please insert API URL.', $this-> api_url ),
					'desc_tip'    =>   true,
				),
				'api_key' => array(
					'title'       => __( 'API Key', $this->api_key ),
					'type' 	      =>   'text',
					'description' => __( 'Please insert API Key.', $this-> api_key ),
					'desc_tip'    =>   true,
				),
				'user_email' => array(
					'title'       => __( 'Email Address', $this->user_email ),
					'type' 	      => 'text',
					'description' => __( 'Please insert email address.',$this->user_email),
					'desc_tip'    => true,
				),
				'environment' => array(
					'title'     => __ ( 'Environment', $this->environment ),
					'type' 		=>    'select',
					'options' 	=>    array('1' => 'DEMO', '2'  => 'PRODUCTION'),
					'description'=> __( 'Please select environment.', $this->environment),
					'desc_tip'   => true,
				),

				'section_title'=> array(
					'title'    => __( 'Other Settings', '' ),
					'type'     => 'title',
					'desc'     => '',
            	),
                'title' => array(
                    'title'       => __( 'Title', $this->domain ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title which the user sees
											 during checkout.', $this->domain ),
                    'default'     => __( 'Woocommerce Custom Payment', $this->domain ),
                    'desc_tip'    => true,
                ),
                'order_status' => array(
                    'title'       => __( 'Order Status', $this->domain ),
                    'type'        => 'select',
                    'class'       => 'wc-enhanced-select',
                    'description' => __( 'Choose whether status you wish 
											after checkout.', $this->domain ),
                    'default'     => 'wc-completed',
                    'desc_tip'    => true,
                    'options'     => wc_get_order_statuses()
                ),
                'description' => array(
                    'title'       => __( 'Description', $this->domain ),
                    'type'        => 'textarea',
                    'description' => __( 'Payment method description that the counter 
											will see on your checkout.', $this->domain ),
                    'default'     => __('Payment Information', $this->domain),
                    'desc_tip'    => true,
                ),
                'instructions' => array(
                    'title'       => __( 'Instructions', $this->domain ),
                    'type'        => 'textarea',
                    'description' => __( 'Instructions that will be added to the thank 
											you page and emails.', $this->domain ),
                    'default'     => '',
                    'desc_tip'    => true,
                )				
            );
        }

        /**
         * Output for the order received page.
         */
        public function thankyou_page_WCPG() {
            if ( $this->instructions )				
                echo wpautop( wptexturize( $this->instructions ) );
        }

        /**
         * Add content to the WC emails.
         *
         * @access public
         * @param WC_Order $order
         * @param bool $sent_to_admin
         * @param bool $plain_text
         */
        public function email_instructions_WCPG( $order, $sent_to_admin, $plain_text = false ) {
            if ( $this->instructions && ! $sent_to_admin && 'wccustompayment' === $order->payment_method && $order->has_status( 'on-hold' ) ) {
                echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
            }
        }

        public function payment_fields_WCPG(){
            if ( $description = $this->get_description() ) {
                echo wpautop( wptexturize( $description ) );
            }
        }  

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment( $order_id ) {   

            $order 		= wc_get_order( $order_id ); 
			if (!session_id())
    		session_start();
			$_SESSION['current_order-id'] = $order_id;
			$status = 'pending';
            // Set order status
            $order->update_status( $status, __( 'Pending wccustompayment payment. ', $this->domain ) );

            // Reduce stock levels
            $order->reduce_order_stock();

            // Remove cart
            WC()->cart->empty_cart();

            // Return thankyou redirect
            return array(
                'result'    => 'success',
                'redirect'  => $this->get_return_url( $order )
            );
        }
    }
}

add_filter( 'woocommerce_payment_gateways', 'add_custom_payment_wcpg_gateway_class' );
function add_custom_payment_wcpg_gateway_class( $methods ) {
    $methods[] = 'WC_Gateway_WCPG'; 
    return $methods;
}

add_action( 'template_redirect', 'woo_custom_wcpg_redirect_after_purchase' );
function woo_custom_wcpg_redirect_after_purchase() {

    global $wp;
    $paymentMethod = get_post_meta( $wp->query_vars['order-received'], '_payment_method', true );
        if ( is_checkout() && !empty( $wp->query_vars['order-received'] ) && $paymentMethod=='wccustompayment') {
            //Redirect page
            $wcpg_payment = new WC_Gateway_WCPG();
            $wcpg_payment->init_form_fields();
            $wcpg_payment->init_settings();

            //--------------------------------Call API ---------------------------------------
            $api_url  = $wcpg_payment->get_option('api_url');
            $data = [
                "api_key"   => $wcpg_payment->get_option( 'api_key' ),
                "email"     => $wcpg_payment->get_option( 'user_email' )
            ];

            //get secret url
            $args = array(
                'body'          => $data,
                'timeout'       => '5',
                'redirection'   => '5',
                'httpversion'   => '1.0',
                'blocking'      => true,
                'headers'       => array(),
                'cookies'       => array()
            );
            $response = wp_remote_post( $api_url, $args );
            $result = wp_remote_retrieve_body( $response );
            //--------------------------XXXXXXXXXX--------------------------------------------
        }
} 



add_action('woocommerce_checkout_process', 'process_wcpa_payment');
function process_wcpa_payment(){
    if($_POST['payment_method'] != 'wccustompayment'){ 
        return;    
	}
}

/**
 * Update the order meta with field value
 */
add_action('woocommerce_checkout_update_order_meta', 'wc_wcpg_costom_payment_payment_update_order_meta');
function wc_wcpg_costom_payment_payment_update_order_meta( $order_id ) {
    if($_POST['payment_method'] != 'wccustompayment')
        return;
}

/**
 * Display field value on the order edit page
 */
add_action( 'woocommerce_admin_order_data_after_billing_address', 'wc_wcpg_costom_payment_checkout_field_display_admin_order_meta', 10, 1 );
function wc_wcpg_costom_payment_checkout_field_display_admin_order_meta($order){
    $method = get_post_meta( $order->id, '_payment_method', true );
    if($method != 'wccustompayment')
        return;
}

