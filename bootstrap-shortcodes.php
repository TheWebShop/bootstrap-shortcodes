<?php
/*
Plugin Name: Bootstrap Shortcodes
Plugin URI: https://github.com/TheWebShop/bootstrap-shortcodes
Description: A simple shortcode generator. Add buttons, columns, toggles and alerts to your theme.
Version: 3.4.0
Author: Kevin Attfield
Author URI: https://github.com/Sinetheta

Forked from DW Shortcodes Bootstrap http://wordpress.org/plugins/dw-shortcodes-bootstrap/
*/

require_once( 'inc/bs_grid.php' );
require_once( 'inc/bs_tabs.php' );
require_once( 'inc/bs_collapse.php' );
require_once( 'inc/bs_alert.php' );
require_once( 'inc/bs_well.php' );
require_once( 'inc/bs_buttons.php' );
require_once( 'inc/bs_labels.php' );
require_once( 'inc/bs_icons.php' );
require_once( 'inc/bs_lead.php' );
require_once( 'inc/bs_tooltip.php' );

class BootstrapShortcodes{

    public $shortcodes = array(
        'grid',
        'tabs',
        'collapse',
        'alerts',
        'wells',
        'buttons',
        'labels',
        'icons',
        'lead',
        'tooltip'
    );

    public function __construct() {
        add_action( 'init', array( &$this, 'init' ) );
        register_activation_hook( __FILE__, array( &$this, 'add_options_defaults' ) );
        add_action( 'admin_init', array( &$this, 'register_settings' ) );
        add_action( 'admin_menu', array( &$this, 'register_settings_page' ) );
    }

    function init() {
        $options = get_option( 'bs_options' );
        if( !is_admin() ) {
            if( isset( $options[ 'chk_default_options_css' ] ) && $options[ 'chk_default_options_css' ] ) {
                wp_enqueue_style( 'bs_bootstrap', plugins_url( 'css/bootstrap.css', __FILE__ ) );
                wp_enqueue_style( 'bs_shortcodes', plugins_url( 'css/shortcodes.css', __FILE__ ) );
            }
            if( isset( $options[ 'chk_default_options_js' ]) && $options[ 'chk_default_options_js' ] ) {
                wp_enqueue_script( 'bs_bootstrap', plugins_url( 'js/bootstrap.js', __FILE__ ) , array( 'jquery' ) );
            }
            wp_enqueue_script('bs_init', plugins_url('js/init.js', __FILE__ ) , array('bs_bootstrap'));
        } else {
            wp_enqueue_style( 'bs_admin_style', plugins_url( 'css/admin.css', __FILE__ ) );
        }
        if ( !current_user_can( 'edit_posts' ) && !current_user_can( 'edit_pages' ) ) {
            return;
        }
        if ( get_user_option( 'rich_editing' ) == 'true' ) {
            add_filter( 'mce_external_plugins', array( &$this, 'regplugins' ) );
            add_filter( 'mce_buttons_3', array( &$this, 'regbtns' ) );
        }
    }

    function regbtns( $buttons ) {
        $options = get_option( 'bs_options' );
        foreach ( $this->shortcodes as &$shortcode ) {
            if ( isset( $options[ 'chk_default_options_' . $shortcode ] ) ) {
                array_push( $buttons, 'bs_' . $shortcode );
            }
        }
        return $buttons;
    }

    function regplugins( $plgs) {
        foreach ( $this->shortcodes as &$shortcode ) {
            $plgs[ 'bs_' . $shortcode ] = plugins_url( 'js/plugins/' . $shortcode . '.js', __FILE__ );
        }
        return $plgs;
    }

    function register_settings_page() {
        add_options_page( __( 'BS Shortcodes', 'bsshortcodes' ), __( 'BS Shortcodes', 'bsshortcodes' ), 'manage_options', __FILE__, array( &$this, 'dw_render_form') );
    }

    function add_options_defaults() {
            $arr = array(
                'chk_default_options_css'       => '1',
                'chk_default_options_js'        => '1',
                'chk_default_options_grid'      => '1',
                'chk_default_options_tabs'      => '1',
                'chk_default_options_collapse'  => '1',
                'chk_default_options_alerts'    => '1',
                'chk_default_options_wells'     => '1',
                'chk_default_options_buttons'   => '1',
                'chk_default_options_labels'    => '1',
                'chk_default_options_icons'     => '1',
                'chk_default_options_lead'      => '1',
                'chk_default_options_tooltip'   => '1'
            );
            update_option( 'bs_options', $arr );
    }

    function register_settings() {
        register_setting( 'bs_plugin_options', 'bs_options' );
    }

    function dw_render_form() {
        ?>
        <div class="wrap">
            <div class="icon32" id="icon-options-general"><br></div>
            <h2>Bootstrap Shortcodes Options</h2>
            <form method="post" action="options.php">
                <?php settings_fields( 'bs_plugin_options' ); ?>
                <?php $options = get_option( 'bs_options'); ?>
                <table class="form-table">

                    <tr><td colspan="2"><div style="margin-top:10px;"></div></td></tr>

                    <tr valign="top" style="border-top:#dddddd 1px solid;">
                        <th scope="row">Twitter Bootstrap CSS</th>
                        <td>
                            <label><input name="bs_options[chk_default_options_css]" type="checkbox" value="1" <?php if ( isset( $options[ 'chk_default_options_css' ] ) ) { checked( '1', $options[ 'chk_default_options_css' ] ); } ?> /> Load Twitter Bootstrap css file</label><br /><span style="color:#666666;margin-left:2px;">Uncheck this if you already include Bootstrap css on your template</span>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Twitter Bootstrap JS</th>
                        <td>
                            <label><input name="bs_options[chk_default_options_js]" type="checkbox" value="1" <?php if ( isset( $options[ 'chk_default_options_js' ] ) ) { checked( '1', $options[ 'chk_default_options_js' ] ); } ?> /> Load Twitter Bootstrap javascript file</label><br /><span style="color:#666666;margin-left:2px;">Uncheck this if you already include Bootstrap javascript on your template</span>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Twitter Bootstrap Shortcodes</th>
                        <td>

                            <?php foreach ( $this->shortcodes as &$shortcode ): ?>
                            <label>
                                <input
                                    name="bs_options[chk_default_options_<?php echo $shortcode; ?>]"
                                    type="checkbox"
                                    value=1
                                    <?php if ( isset( $options[ 'chk_default_options_' . $shortcode ] ) ) { checked( '1', $options[ 'chk_default_options_' . $shortcode ] ); } ?>
                                /> <?php echo $shortcode; ?>
                            </label>
                            <br />
                            <?php endforeach; ?>

                            <span style="color:#666666;margin-left:2px;">Uncheck to remove button from TinyMCE editor</span>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
                </p>
            </form>

        </div><?php
    }
}

$bscodes = new BootstrapShortcodes();
