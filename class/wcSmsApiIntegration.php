<?php

    use SMSApi\Client;
    use SMSApi\Api\UserFactory;
    use SMSApi\Api\SmsFactory;
    use SMSApi\Exception\ClientException;
    use SMSApi\Exception\SmsapiException;

    require_once 'vendor/autoload.php';


    class WC_SmsApi_Integration extends WC_Integration {

                const LOW_FUNDS_LEVEL = 2.0;
                const PARTNER_ID = '4PT2';

                private $rest = false;

                private $_smsapi_client;

                public function __construct() {

                    $this->id = 'woocommerce-smsapi';

                    $this->method_title = __('SMSAPI', 'woocommerce-smsapi');
                    $this->method_description = sprintf( wp_kses( __( 'WooCommerce integration with SMSAPI. <a href="%s" target="_blank">Check out the docs &rarr;</a>', 'woocommerce-smsapi' ), array(  'a' => array( 'href' => array(), 'target' => '_blank' ) ) ), esc_url( 'http://www.wpdesk.pl/docs/woocommerce-smsapi-docs/' ) );

                    $this->enabled = $this->get_option('enabled');
                    $this->api_login = $this->get_option('api_login');
                    $this->api_password = $this->get_option('api_password');
                    $this->sms_type = $this->get_option('sms_type');
                    $this->marketing_sms_user_consent = $this->get_option('marketing_sms_user_consent');
                    
                    $this->checkbox_text = $this->get_option('checkbox_text');
                    $this->checkbox_position = $this->get_option('checkbox_position');
                    $this->processing_order_sms_enabled = $this->get_option('processing_order_sms_enabled');
                    $this->processing_order_sms_text = $this->get_option('processing_order_sms_text');
                    $this->completed_order_sms_enabled = $this->get_option('completed_order_sms_enabled');
                    $this->completed_order_sms_text = $this->get_option('completed_order_sms_text');
                    $this->customer_note_sms_enabled = $this->get_option('customer_note_sms_enabled');

                    add_action('admin_footer', array($this, 'loadIntegrationScriptAction'));

                    // Load the settings.
                    $this->init_form_fields();
                    $this->init_settings(); // /wp-admin/admin.php?page=wc-settings&tab=integration&section=woocommerce-smsapi

                    add_action('woocommerce_update_options_integration_' . $this->id, array($this, 'process_admin_options'));

                    if ($this->enabled == 'yes') {


                        add_action( 'admin_notices', array($this, 'lowFundsNotificationAction' ));

                        add_action('woocommerce_new_customer_note_notification', array($this, 'newCustomerNoteAction'));
                        add_action( 'woocommerce_order_status_changed', array($this, 'orderStatusChangedSmsAction'), 1, 3 );

                        if ($this->marketing_sms_user_consent == 'yes') {
                            if ($this->checkbox_position == 'before') {
                                add_action('woocommerce_review_order_before_submit', array($this, 'addSmsMarketingCheckboxAction'));
                            } else {
                                add_action('woocommerce_review_order_after_submit', array($this, 'addSmsMarketingCheckboxAction'));
                            }

                            add_action('woocommerce_checkout_update_order_meta', array($this, 'checkoutUpdateOrderMetaAction'), 1, 1);

                            add_action( 'show_user_profile',  array($this, 'userProfileMarketingAction'), 100, 1 );
                            add_action( 'edit_user_profile',  array($this, 'userProfileMarketingAction'), 100, 1 );

                            add_action( 'personal_options_update', array($this, 'saveUserProfileMarketingAction') );
                            add_action( 'edit_user_profile_update', array($this, 'saveUserProfileMarketingAction') );

                        }

                    }

                }

                public function saveUserProfileMarketingAction($user_id) {
                    update_user_meta( $user_id,'woocommerce_sms_marketing_consent', (isset($_POST['woocommerce_sms_marketing_consent'])) ? 1 : 0 );
                }

                protected function getSmsMarketingUserConsent($user_id) {
                    $sms_marketing_consent = get_the_author_meta( 'woocommerce_sms_marketing_consent', $user_id );
                    if ($sms_marketing_consent === "")
                        $sms_marketing_consent = 0;
                    return intval($sms_marketing_consent);
                }

                public function userProfileMarketingAction( $user )
                {
                    ?>
                        <table class="form-table">
                            <tr>
                                <th><label for="woocommerce_sms_marketing_consent"><?php _e('SMS Marketing', 'woocommerce-smsapi'); ?></label></th>
                                <td>
                                        <input name="woocommerce_sms_marketing_consent" id="woocommerce_sms_marketing_consent" value="1" type="checkbox" <?php if ($this->getSmsMarketingUserConsent($user->ID)) echo 'checked'; ?> >
                                        <label for="woocommerce_sms_marketing_consent"><?php echo $this->checkbox_text; ?></label>
                                </td>
                            </tr>
                        </table>
                    <?php
                }

                protected function refreshSmsApiFields()
                {
                    $this->api_login = $this->get_option('api_login');
                    $this->api_password = $this->get_option('api_password');
                    $this->_smsapi_client = null;
                }

                protected function setSmsClient($login, $password) {
                    $this->_smsapi_client = new Client($login);
                    $this->_smsapi_client->setPasswordRaw($password);
                }

                public function getSmsClient() {
                    if (!$this->_smsapi_client)
                        $this->setSmsClient($this->api_login, $this->api_password);
                    return $this->_smsapi_client;
                }

                protected function sendSms($to, $text) {
                    try {
                        $smsapi = new SmsFactory;
                        $smsapi->setClient($this->getSmsClient());

                        $actionSend = $smsapi->actionSend();

                        $actionSend->setPartner(self::PARTNER_ID);
                        $actionSend->setTo($to);
                        $actionSend->setText($text);

                        $sender = $this->sms_type;

                        if ($sender == 'ECO')
                            $actionSend->setSender($sender); //Pole nadawcy, lub typ wiadomości: 'ECO', '2Way'

                        $response = $actionSend->execute();

                        return $response->getList();

                        /*foreach ($response->getList() as $status) {
                            echo $status->getNumber() . ' ' . $status->getPoints() . ' ' . $status->getStatus();
                        }*/
                    } catch (SmsapiException $exception) {
                        echo 'ERROR: ' . $exception->getMessage();
                        return false;
                    } catch (ClientException $exception) {
                        echo 'ERROR: ' . $exception->getMessage();
                        $this->sms_type = 'ECO';
                        return false;
                    }

                }

                public function checkoutUpdateOrderMetaAction($order_id) {
                    $order = new WC_Order( $order_id );
                    $user_id = $order->get_user_id();
                    if ($user_id) {
                            update_user_meta( $user_id,'woocommerce_sms_marketing_consent', (isset($_POST['sms_marketing'])) ? 1 : 0 );
                    }
                }

                public function orderStatusChangedSmsAction( $order_id, $old_status, $new_status ) {
                    $order = new WC_Order( $order_id );

                    if ($new_status == 'processing' && $this->processing_order_sms_enabled == 'yes' ) {
                        $this->sendSms( $order->billing_phone, $this->processing_order_sms_text);
                    }

                    if ($new_status == 'completed' && $this->completed_order_sms_enabled == 'yes' ) {
                        $this->sendSms( $order->billing_phone, $this->completed_order_sms_text);
                    }

                }

                public function newCustomerNoteAction($note) {
                    if ($note) {
                        $order_id = $note['order_id'];
                        $customer_note = $note['customer_note'];

                        $order = new WC_Order( $order_id );
                        if ($this->customer_note_sms_enabled == 'yes' ) {
                            $this->sendSms( $order->billing_phone, $customer_note);
                        }
                    }

                }

                public function addSmsMarketingCheckboxAction() {
                        $user_id = get_current_user_id(); ?>
                        <p class="form-row smsapi">
                            <label for="sms_marketing" class="sms_marketing"><input type="checkbox" id="sms_marketing" name="sms_marketing" value="1" <?php if ($this->getSmsMarketingUserConsent($user_id)) echo 'checked'; ?> /> <?php echo $this->checkbox_text; ?></label>
                        </p>
                        <?php
                }

                public function admin_options() {
    	        ?>
                    <div class="wrap">
                    	<div class="inspire-settings">
                    		<div class="inspire-main-content">
                                <?php
                                    parent::admin_options();

                                    // refresh fields after save
                                    $this->enabled = $this->get_option('enabled');

                                    $this->refreshSmsApiFields();

                                    if ($this->enabled == 'yes')
                                    {
                                        $status = __('Error. Please fill in valid API login and password.', 'woocommerce-smsapi');
                                        if ($this->api_login != "" && $this->api_password != "") {
                                            if ($this->checkSmsConnection()) {
                                                $status = __('OK', 'woocommerce-smsapi');
                                            }
                                        }
                                        ?>
                                        <p><?php _e('Connection status:', 'woocommerce-smsapi'); ?> <?php echo $status; ?></p>
                                    <?php
                                    }
                                ?>
                           </div>

                    		<div class="inspire-sidebar">
                    			<a href="http://www.wpdesk.pl/?utm_source=smsapi-settings&utm_medium=banner&utm_campaign=woocommerce-plugins" target="_blank"><img src="<?php echo $this->plugin_url(); ?>/assets/images/wpdesk-woocommerce-plugins.png" alt="Wtyczki do WooCommerce" height="250" width="250" /></a>
                    		</div>
                    	</div>
                    </div>
                <?php
                }


                    public function init_form_fields() {

                        $this->form_fields = array(
                            'enabled' => array(
                                'title' => __('Enable', 'woocommerce-smsapi'),
                                'label' => __('Enable SMSAPI integration', 'woocommerce-smsapi'),
                                'type' => 'checkbox',
                                'default' => 'yes'
                            ),
                            'api_login' => array(
                                'title' => __('API Login', 'woocommerce-smsapi'),
                                'type' => 'text',
                                'default' => '',
                                'description' => __('Username or main email address of your SMSAPI account.', 'woocommerce-smsapi'),
                                'desc_tip'    => true
                            ),
                            'api_password' => array(
                                'title' => __('API Password', 'woocommerce-smsapi'),
                                'type' => 'password',
                                'default' => '',
                                'description' => __('SMSAPI account password. You can set separate API password in your account settings.', 'woocommerce-smsapi'),
                                'desc_tip'    => true
                            ),
                            'sms_type' => array(
                                'title' => __('SMS Type', 'woocommerce-smsapi'),
                                'type' => 'select',
                                'options' => array( 'Info' => 'Pro',
                                                    'ECO' => 'Eco'
                                                   ),
                            ),
                            'marketing_sms_user_consent' => array(
                                'label' => __('Enable user consent to SMS Marketing', 'woocommerce-smsapi'),
                                'title' => __('SMS Marketing', 'woocommerce-smsapi'),
                                'type' => 'checkbox',
                                'default' => 'no',
                                'description' => __('Checkbox will be added to checkout page allowing users to agree to SMS marketing.', 'woocommerce-smsapi')
                            ),
                            'checkbox_text' => array(
                                'title' => __('Checkbox label', 'woocommerce-smsapi'),
                                'type' => 'text',
                                'default' => __('I agree to receiving marketing content via SMS', 'woocommerce-smsapi')
                            ),
                            'checkbox_position' => array(
                                'title' => __('Checkbox position', 'woocommerce-smsapi'),
                                'type' => 'select',
                                'options' => array(
                                    'before' => __('Above Place order button', 'woocommerce-smsapi'),
                                    'after' => __('Below Place order button', 'woocommerce-smsapi'),
                                ),
                                'default' => 'before',
                            ),
                            'processing_order_sms_enabled' => array(
                                'title' => __('Processing order SMS', 'woocommerce-smsapi'),
                                'label' => __('Enable', 'woocommerce-smsapi'),
                                'type' => 'checkbox',
                                'default' => 'yes',
                                'description' => __('SMS will be sent when order status is changed to processing.', 'woocommerce-smsapi'),
                                'desc_tip'    => true
                            ),
                            'processing_order_sms_text' => array(
                                'title' => __('Processing order SMS text', 'woocommerce-smsapi'),
                                'type' => 'text',
                                'default' => sprintf( __( 'Your order status at %s changed to processing.', 'woocommerce-smsapi' ), get_bloginfo('name') ),
                                'description' => __('Max 160 characters. Please note that if you use special characters the limit is 70 characters.', 'woocommerce-smsapi')
                            ),
                            'completed_order_sms_enabled' => array(
                                'title' => __('Completed order SMS', 'woocommerce-smsapi'),
                                'label' => __('Enable', 'woocommerce-smsapi'),
                                'type' => 'checkbox',
                                'default' => 'yes',
                                'description' => __('SMS will be sent when order status is changed to completed.', 'woocommerce-smsapi'),
                                'desc_tip'    => true
                            ),
                            'completed_order_sms_text' => array(
                                'title' => __('Completed order SMS text', 'woocommerce-smsapi'),
                                'type' => 'text',
                                'default' => sprintf( __( 'Your order status at %s changed to completed.', 'woocommerce-smsapi' ), get_bloginfo('name') ),
                                'description' => __('Max 160 characters. Please note that if you use special characters the limit is 70 characters.', 'woocommerce-smsapi')
                            ),
                            'customer_note_sms_enabled' => array(
                                'title' => __('Customer note SMS', 'woocommerce-smsapi'),
                                'label' => __('Enable', 'woocommerce-smsapi'),
                                'type' => 'checkbox',
                                'default' => 'no',
                                'description' => __('Text will be taken from the customer note.', 'woocommerce-smsapi')
                            )
                        );
                        if ($this->marketing_sms_user_consent == "no") {
                            $this->form_fields['checkbox_position']['disabled'] = 'disabled';
                        }
                        if ($this->processing_order_sms_enabled == "no") {
                            $this->form_fields['processing_order_sms_text']['disabled'] = 'disabled';
                        }
                        if ($this->completed_order_sms_enabled == "no") {
                            $this->form_fields['completed_order_sms_text']['disabled'] = 'disabled';
                        }
                    }

                    /**
                     * Get the plugin URL
                     *
                     * @since 1.0.0
                     */
                    public function plugin_url() {
                        if( isset( $this->plugin_url ) ) return $this->plugin_url;

                        if ( is_ssl() ) {
                        	return $this->plugin_url = str_replace( 'http://', 'https://', WP_PLUGIN_URL ) . '/' . plugin_basename( dirname( dirname( __FILE__ ) ) );
                        } else {
                        	return $this->plugin_url = WP_PLUGIN_URL . '/' . plugin_basename( dirname( dirname( __FILE__ ) ) );
                        }
                    } // End plugin_url()

                    public function isIntegration() {
                        global $woocommerce;

                        if (version_compare($woocommerce->version, '2.1.0', '>=')) // WC 2.1
                        {
                            $isIntegration = isset($_GET['page']) && $_GET['page'] == 'wc-settings' && isset($_GET['tab']) && $_GET['tab'] == "integration";
                        } else {
                            $isIntegration = isset($_GET['page']) && $_GET['page'] == 'woocommerce_settings' && isset($_GET['tab']) && $_GET['tab'] == "integration";
                        }
                        return $isIntegration;
                    }

                    public function loadIntegrationScriptAction()
                    {
                        if ($this->isIntegration()) {
                            ?>
                            <script type="text/javascript">
                                jQuery(document).ready(function($) {
                                    $("#woocommerce_woocommerce-smsapi_marketing_sms_user_consent").change(function() {
                                        if ($(this).is(':checked')) {
                                            $("#woocommerce_woocommerce-smsapi_checkbox_text, #woocommerce_woocommerce-smsapi_checkbox_position").removeAttr('disabled').closest('tr').show();
                                        } else {
                                            $("#woocommerce_woocommerce-smsapi_checkbox_text, #woocommerce_woocommerce-smsapi_checkbox_position").attr('disabled', 'disabled').closest('tr').hide();
                                        }
                                    });

                                    $("#woocommerce_woocommerce-smsapi_processing_order_sms_enabled").change(function() {
                                        if ($(this).is(':checked')) {
                                            $("#woocommerce_woocommerce-smsapi_processing_order_sms_text").removeAttr('disabled').closest('tr').show();
                                        } else {
                                            $("#woocommerce_woocommerce-smsapi_processing_order_sms_text").attr('disabled', 'disabled').closest('tr').hide();
                                        }
                                    });

                                    $("#woocommerce_woocommerce-smsapi_completed_order_sms_enabled").change(function() {
                                        if ($(this).is(':checked')) {
                                            $("#woocommerce_woocommerce-smsapi_completed_order_sms_text").removeAttr('disabled').closest('tr').show();
                                        } else {
                                            $("#woocommerce_woocommerce-smsapi_completed_order_sms_text").attr('disabled', 'disabled').closest('tr').hide();
                                        }
                                    });

                                    $("#woocommerce_woocommerce-smsapi_marketing_sms_user_consent, #woocommerce_woocommerce-smsapi_processing_order_sms_enabled, #woocommerce_woocommerce-smsapi_completed_order_sms_enabled").trigger("change");

                                });
                            </script>

                            <?php
                        }
                        ?>

                        <?php /* <script type='text/javascript' src='<?php echo plugins_url("../js/sms.js",__FILE__) ?>'></script>
                        <script type="text/javascript">
                            jQuery(document).ready(function($) {

                                var smsCounterElements = '#woocommerce_woocommerce-smsapi_processing_order_sms_text, #woocommerce_woocommerce-smsapi_completed_order_sms_text, #add_order_note';
                                var smsCounterElementsArray = smsCounterElements.split(',');
                                $('<div class="sms_count textarea-information"> <div class="pull-left"> <div class="sms_not_gsm" data-sms-counter="sms_not_gsm" style="display: none;"> <?php _e("Message contains special characters.", "woocommerce-smsapi") ?></div> </div> <div class="pull-right"><?php _e('Number of parts: ', 'woocommerce-smsapi') ?><b data-sms-counter="count">1</b> <?php _e('Characters left: ', 'woocommerce-smsapi') ?><b data-sms-counter="left">918</b> </div> <div class="clear"></div> </div>').insertAfter(smsCounterElements);

                                $.each(smsCounterElementsArray, function( index, value ) {
                                    sms_counter(value, $(value).parent());
                                });

                            });
                        </script> */ ?>

                        <?php
                    }


                    public function lowFundsNotificationAction()
                    {
                        $this->refreshSmsApiFields();

                        if( !is_admin() || !$this->isIntegration() || !$this->checkSmsConnection() || $_SERVER['REQUEST_METHOD'] !== 'GET') return;

                        if ($this->getSmsPoints() < self::LOW_FUNDS_LEVEL)
                            echo '<div class="notice notice-warning"><p>'
                                . sprintf( wp_kses( __( 'You are running out of funds. In order to keep sending SMS messages, <a href="%s" target="_blank">log in</a> to you SMSAPI account and buy more points.', 'woocommerce-smsapi' ), array(  'a' => array( 'href' => array(), 'target' => '_blank' ) ) ), esc_url( 'https://ssl.smsapi.pl/' ) ) .
                                '</p></div>';

                    }

                    public function getSmsPoints() {
                        try {
                            $api = new UserFactory;
                            $api->setClient($this->getSmsClient());

                            $action = $api->actionGetPoints();

                            $response = $action->execute();
                            return floatval($response->getPoints());

                        } catch (Exception $exception) {
                            return false;
                        }
                    }

                    public function checkSmsConnection() {
                        return $this->getSmsPoints() !== false;
                    }
    }
