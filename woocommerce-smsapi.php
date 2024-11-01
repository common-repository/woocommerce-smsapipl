<?php
/*
  Plugin Name: WooCommerce SMSAPI.pl
  Plugin URI: http://www.wpdesk.pl/sklep/smsapi-woocommerce/
  Description: Integracja WooCommerce z <a href="https://ssl.smsapi.pl/rejestracja/4PT2" target="_blank">SMSAPI.pl</a>.
  Version: 1.1
  Author: Inspire Labs
  Author URI: http://www.inspirelabs.pl/
  License: GPLv2 or later
  Domain Path: /lang/
  Text Domain: woocommerce-smsapi

  Copyright 2015 Inspire Labs Sp. z o.o.

  This program is free software; you can redistribute it and/or
  modify it under the terms of the GNU General Public License
  as published by the Free Software Foundation; either version 2
  of the License, or (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

if (!class_exists('inspire_Plugin4')) {
    require_once('class/inspire/plugin4.php');
}

class WooCommerce_SmsApi_Plugin extends inspire_Plugin4
{
    private static $_oInstance = false;

    protected $_pluginNamespace = "woocommerce-smsapi";

    public function __construct()
    {
        parent::__construct();

        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))))
        {
            add_action('woocommerce_integrations_init', array($this, 'initSmsApiIntergrationAction'));
            add_filter('woocommerce_integrations', array($this, 'addIntegrationFilter'));
        }

        if ( is_admin() )
        {
            add_action( 'admin_enqueue_scripts', array($this, 'initAdminCssAction'), 75 );
        }
    }

    public function loadPluginTextDomain()
    {
        parent::loadPluginTextDomain();
        load_plugin_textdomain( 'woocommerce-smsapi', FALSE, basename( dirname( __FILE__ ) ) . '/lang/' );
    }

    /**
     * WordPress action
     *
     * Inits css
     */
    public function initAdminCssAction()
    {
        wp_enqueue_style( 'smsapi_admin_style', $this->getPluginUrl() . '/assets/css/admin.css' );
    }

    public static function getInstance()
    {
        if( self::$_oInstance == false )
        {
            self::$_oInstance = new WooCommerce_SmsApi_Plugin();
        }
        return self::$_oInstance;
    }

    public function initBaseVariables()
    {
        parent::initBaseVariables();
    }

    public function initSmsApiIntergrationAction()
    {
        if (!class_exists('WC_SmsApi_Integration')) {
            require_once('class/wcSmsApiIntegration.php');
        }
    }

    public function addIntegrationFilter($integrations) {
        $integrations[] = 'WC_SmsApi_Integration';
        return $integrations;
    }

    public function linksFilter( $links )
    {
        $plugin_links = array(
            '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=integration&section=woocommerce-smsapi') . '">' . __( 'Settings', 'woocommerce-smsapi' ) . '</a>',
            '<a href="http://www.wpdesk.pl/docs/woocommerce-smsapi-docs/">' . __( 'Docs', 'woocommerce-smsapi' ) . '</a>',
            '<a href="http://www.wpdesk.pl/support/">' . __( 'Support', 'woocommerce-smsapi' ) . '</a>',
        );

        return array_merge( $plugin_links, $links );
    }


}

WooCommerce_SmsApi_Plugin::getInstance();
