<?php

add_action('plugins_loaded', 'cpg_cryptanil_gateway_class');
function cpg_cryptanil_gateway_class()
{

    if (class_exists('WC_Payment_Gateway')) {
        class CPG_WC_Cryptanil_Gateway extends WC_Payment_Gateway
        {
            private $pluginDirUrl;
            public $apiUrl = 'https://api.cryptanil.com';

            public $statuses = [
                '',
                'pending',
                'pending',
                'failed',
                'processing',
                'completed',
                'failed',
                'processing',
                'refunded',
                'failed'
            ];

            /**
             * CPG_WC_Cryptanil_Gateway constructor.
             */
            public function __construct()
            {
                global $pluginDirUrlCryptanil;
                global $pluginDomainCryptanil;

                $this->pluginDirUrl = $pluginDirUrlCryptanil;
                $this->pluginDirUrl = $pluginDirUrlCryptanil;
                $this->pluginDomain = $pluginDomainCryptanil;

                $this->id = $this->pluginDomain;
                $this->has_fields = false;
                $this->method_title = 'Payment Gateway for Cryptanil';
                $this->method_description = 'Pay with crypto. We support BTC, ETH, USDT, USDC, SOL, TON, TRX, BUSD, BNB and many more.';

                $this->init_form_fields();
                $this->init_settings();

                $this->title = sanitize_text_field($this->get_option('title'));
                $this->description = sanitize_text_field($this->get_option('description'));
                $this->enabled = $this->get_option('enabled');
                $this->testmode = 'yes' === $this->get_option('testmode');
                $this->mode = $this->get_option('mode');

                $this->cryptanil_key = sanitize_text_field($this->get_option($this->testmode ? 'your_test_cryptanil_key' : 'your_cryptanil_key'));

                $this->icon = ($this->mode === 'white') ? $this->pluginDirUrl . 'assets/images/cryptanil_white.png' :  $this->pluginDirUrl . 'assets/images/cryptanil_dark.png';

                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

                /**
                 * Complete callback url for cryptanil api
                 */
                add_action('woocommerce_api_cryptanil_complete', array($this, 'webhook_cryptanil_complete'));

                /**
                 * Fail callback url for cryptanil api
                 */
                add_action('woocommerce_api_cryptanil_fail', array($this, 'webhook_cryptanil_fail'));

                /**
                 * Result callback url for cryptanil api
                 */
                add_action('woocommerce_api_cryptanil_result', array($this, 'webhook_cryptanil_result'));

                add_action('admin_print_styles', array($this, 'enqueue_stylesheets'));

                if(is_checkout()){
                    wp_enqueue_script('cryptanil-front-checkout-js', $this->pluginDirUrl . "assets/js/checkout.js", array('jquery'), '', true);
                }
            }

            public function getOrderInfo($order_id)
            {
                $headers = array(
                    'auth' => $this->cryptanil_key,
                    'Content-Type' => 'application/json'
                );

                $response = wp_remote_get($this->apiUrl . '/getOrderInfo?orderId=' . $order_id, [
                        'headers' => $headers,
                ]);

                if (is_wp_error($response)) {
                  return null;
                }

                $response_body = wp_remote_retrieve_body($response);
                $decoded_response = json_decode($response_body, true);

                if(isset($decoded_response['error']) || !isset($decoded_response['result']['data'])) {
                    return null;
                }

                return $decoded_response['result']['data'];
            }

            public function init_form_fields()
            {

                $this->form_fields = array(
                    'enabled' => array(
                        'title' => 'Enable/Disable',
                        'label' => 'Enable payment gateway',
                        'type' => 'checkbox',
                        'description' => '',
                        'default' => 'no'
                    ),
                    'title' => array(
                        'title' => 'Title',
                        'type' => 'text',
                        'description' => 'User (website visitor) sees this title on order registry page as a title for purchase option.',
                        'default' => 'Pay via Crypto',
                        'desc_tip' => true,
                        'placeholder' => 'Type the title',
                    ),
                    'description' => array(
                        'title' => 'Description',
                        'type' => 'textarea',
                        'description' => 'User (website visitor) sees this description on order registry page in bank purchase option.',
                        'default' =>'Pay with crypto. We support BTC, ETH, USDT, USDC, SOL, TON, TRX, BUSD, BNB and many more.',
                        'desc_tip' => true,
                        'placeholder' => 'Type the description',
                    ),
                    'testmode' => array(
                        'title' => 'Test Mode',
                        'label' => 'Enable Test Mode',
                        'type' => 'checkbox',
                        'description' => '',
                        'default' => 'yes'
                    ),
                    'your_test_cryptanil_key' => array(
                        'title' => 'Test Cryptanil Key',
                        'type' => 'text',
                        'placeholder' =>'Your Test Cryptanil Key'
                    ),
                    'your_cryptanil_key' => array(
                        'title' => 'Live Cryptanil Key',
                        'type' => 'text',
                        'placeholder' =>'Your Live Cryptanil Key'
                    ),
                );

            }

            public function process_payment($order_id)
            {

                global $woocommerce;
                $order = wc_get_order($order_id);
                $amount = $order->get_total();
                $order->update_status('pending');
                $user = wp_get_current_user();
                wc_reduce_stock_levels($order_id);

                $headers = array(
                    'auth' => $this->cryptanil_key,
                    'Content-Type' => 'application/json'
                );

                $data = array(
                    'orderId' => (string)$order_id,
                    'redirectUrl' =>  get_site_url().'/wc-api/cryptanil_complete?order_id=' . $order_id,
                    'callbackUrl' => get_site_url().'/wc-api/cryptanil_result?order_id=' . $order_id,
                    'clientId' => $user->ID,
                    'auth' => $this->cryptanil_key,
                    'currency' => get_woocommerce_currency(),
                    "requiredAmount"=> $amount
                );

                $args = array(
                    'headers' => $headers,
                    'body' => json_encode($data)
                );

                $response = wp_remote_post( $this->apiUrl . '/createOrder',  $args );

                if (is_wp_error($response)) {
                    $error_message = $response->get_error_message();
                    echo "Request failed: $error_message";
                } else {

                    $response_body = wp_remote_retrieve_body($response);

                    $decoded_response = json_decode($response_body, true);

                    if(isset($decoded_response['error'])) {
                        wc_add_notice( isset($decoded_response['error']['localizedMessage']) ? $decoded_response['error']['localizedMessage'] : 'Ինչ որ սխալ է տեղի ունեցել․', 'error' );
                        return array(
                            'result'   => 'failure',
                        );
                    }

                    return [
                        'result' => 'success',
                        'redirect' => add_query_arg(array(
                            'action' => 'redirect_cryptanil_form',
                            'redirect_url' => $decoded_response['result']['data']['orderUrl'],
                            'amount' => $amount,
                            'order_id' => $order_id,
                        ))
                    ];


                }
            }

            public function enqueue_stylesheets()
            {
                $plugin_url = $this->pluginDirUrl;
                wp_enqueue_script('cryptanil-front-admin-js', $plugin_url . "assets/js/script.js");
                wp_enqueue_style('cryptanil-style', $plugin_url . "assets/css/style.css");
                wp_enqueue_style('cryptanil-style-awesome', $plugin_url . "assets/css/font_awesome.css");
            }

            public function admin_options()
            {
                ?>
                <div class="wrap-content wrap-content-cryptanil"
                     style="width: 45%;display: inline-block;vertical-align: text-bottom;">
                    <h3><?php echo __('Payment Gateway for Cryptanil', $this->pluginDomain); ?></h3>
                    <table class="form-table">
                        <?php $this->generate_settings_html();?>
                    </table>
                </div>
                <div class="wrap-content wrap-content-cryptanil"
                     style="width: 29%;display: inline-block;position: absolute; padding-top: 75px;">
                <div class="wrap-content-cryptanil-400px">

                </div>
                <div class="wrap-content-cryptanil-400px">
                    <img width="200" height="256"
                         src="<?php echo $this->pluginDirUrl ?>assets/images/cryptanil.png" style="width: 256px">
                    <div class="wrap-content-cryptanil-info">
                        <div class="wrap-content-info">
                            <div class="phone-icon-2 icon"><i class="fa fa-phone"></i>
                            </div>
                            <p><a href="tel:+374 94 653 111">+374 94 653 111</a></p>
                            <div class="mail-icon-2 icon"><i class="fa fa-envelope"></i></div>
                            <p><a href="mailto:info@cryptanil.com">info@cryptanil.com</a></p>
                        </div>
                    </div>
                </div>
                </div><?php
            }

            /*
            * WebHook cryptanil Success Request
            */
            public function webhook_cryptanil_complete()
            {
                $order = wc_get_order(sanitize_text_field( wp_unslash($_REQUEST['order_id'])));
                wp_redirect($this->get_return_url($order));
            }

            /*
             * WebHook cryptanil result Request
             */
            public function webhook_cryptanil_result()
            {
                $response = file_get_contents('php://input');
                $data = json_decode($response, true);

                if(isset($data['status'])){
                    if (wc_get_order($data['orderId']) !== false) {
                        $order = wc_get_order($data['orderId']);
                        if(isset($this->statuses[$data['status']])){
                            $order->update_status($this->statuses[$data['status']]);
                        }else{
                            $order->update_status('canceled');
                        }

                    }

                    foreach ($data as $key => $value){
                        add_post_meta($data['orderId'], $key, $value);
                    }
                }
            }
        }
    }
}
