<?php
/*
Plugin Name: Bootstrap Shortcodes
Plugin URI: https://github.com/TheWebShop/bootstrap-shortcodes
Description: A simple shortcode generator. Add buttons, columns, toggles and alerts to your theme.
Version: 3.5.0
Requires at least: 5.0
Requires PHP: 7.4
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

    const OPTION = 'bs_options';

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

    /**
     * Every shortcode tag this plugin registers (used for asset detection and the scanner).
     */
    public static $tags = array(
        'bs_row', 'bs_col',
        'bs_tabs', 'bs_thead', 'bs_tab', 'bs_dropdown', 'bs_tcontents', 'bs_tcontent',
        'bs_collapse', 'bs_citem',
        'bs_notification', 'bs_well', 'bs_button', 'bs_label', 'bs_icon', 'bs_lead', 'bs_tooltip'
    );

    private $assets_enqueued = false;

    public function __construct() {
        add_action( 'init', array( $this, 'init' ) );
        register_activation_hook( __FILE__, array( $this, 'add_options_defaults' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_filter( 'pre_do_shortcode_tag', array( $this, 'enqueue_on_render' ), 10, 2 );
    }

    function init() {
        if ( !current_user_can( 'edit_posts' ) && !current_user_can( 'edit_pages' ) ) {
            return;
        }
        if ( get_user_option( 'rich_editing' ) == 'true' ) {
            add_filter( 'mce_external_plugins', array( $this, 'regplugins' ) );
            add_filter( 'mce_buttons_3', array( $this, 'regbtns' ) );
        }
    }

    public static function default_options() {
        return array(
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
    }

    /**
     * Keys the settings form may store. Unchecked boxes are simply absent.
     */
    public static function option_keys() {
        return array_merge( array_keys( self::default_options() ), array( 'chk_conditional_assets' ) );
    }

    private function options() {
        $options = get_option( self::OPTION );
        return is_array( $options ) ? $options : array();
    }

    private function option_on( $key ) {
        $options = $this->options();
        return ! empty( $options[ $key ] );
    }

    /**
     * Should the frontend assets go on this request?
     *
     * Default: always (the behaviour every existing site relies on). With the
     * "conditional" setting on, only when a post in the main query contains one
     * of our shortcodes; enqueue_on_render() is the safety net for anything
     * rendered outside the main query (widgets, templates, custom fields).
     */
    public function assets_wanted() {
        $wanted = true;
        if ( $this->option_on( 'chk_conditional_assets' ) ) {
            $wanted = false;
            global $wp_query;
            if ( $wp_query instanceof WP_Query && ! empty( $wp_query->posts ) ) {
                foreach ( $wp_query->posts as $post ) {
                    if ( isset( $post->post_content ) && self::content_has_shortcodes( $post->post_content ) ) {
                        $wanted = true;
                        break;
                    }
                }
            }
        }
        /**
         * Filters whether the plugin's Bootstrap CSS/JS and init script are loaded on this request.
         *
         * @param bool $wanted True to load (the default).
         */
        return (bool) apply_filters( 'bs_shortcodes_load_assets', $wanted );
    }

    public static function content_has_shortcodes( $content ) {
        if ( ! is_string( $content ) || false === strpos( $content, '[bs_' ) ) {
            return false;
        }
        foreach ( self::$tags as $tag ) {
            if ( has_shortcode( $content, $tag ) ) {
                return true;
            }
        }
        return false;
    }

    function enqueue_frontend_assets() {
        if ( $this->assets_wanted() ) {
            $this->enqueue_assets();
        }
    }

    /**
     * Fallback for conditional loading: a shortcode is about to render, so make
     * sure the assets are queued (late styles print in the footer).
     */
    function enqueue_on_render( $return, $tag ) {
        if ( ! $this->assets_enqueued && ! is_admin() && in_array( $tag, self::$tags, true )
            && apply_filters( 'bs_shortcodes_load_assets', true ) ) {
            $this->enqueue_assets();
        }
        return $return;
    }

    function enqueue_assets() {
        if ( $this->assets_enqueued ) {
            return;
        }
        $this->assets_enqueued = true;
        $init_deps = array( 'jquery' );
        if ( $this->option_on( 'chk_default_options_css' ) ) {
            wp_enqueue_style( 'bs_bootstrap', plugins_url( 'css/bootstrap.css', __FILE__ ) );
            wp_enqueue_style( 'bs_shortcodes', plugins_url( 'css/shortcodes.css', __FILE__ ) );
        }
        if ( $this->option_on( 'chk_default_options_js' ) ) {
            wp_enqueue_script( 'bs_bootstrap', plugins_url( 'js/bootstrap.js', __FILE__ ), array( 'jquery' ) );
            $init_deps[] = 'bs_bootstrap';
        }
        wp_enqueue_script( 'bs_init', plugins_url( 'js/init.js', __FILE__ ), $init_deps );
    }

    function enqueue_admin_assets() {
        wp_enqueue_style( 'bs_admin_style', plugins_url( 'css/admin.css', __FILE__ ) );
    }

    function regbtns( $buttons ) {
        $options = $this->options();
        foreach ( $this->shortcodes as $shortcode ) {
            if ( isset( $options[ 'chk_default_options_' . $shortcode ] ) ) {
                array_push( $buttons, 'bs_' . $shortcode );
            }
        }
        return $buttons;
    }

    function regplugins( $plgs) {
        foreach ( $this->shortcodes as $shortcode ) {
            $plgs[ 'bs_' . $shortcode ] = plugins_url( 'js/plugins/' . $shortcode . '.js', __FILE__ );
        }
        return $plgs;
    }

    function register_settings_page() {
        add_options_page( __( 'BS Shortcodes', 'bsshortcodes' ), __( 'BS Shortcodes', 'bsshortcodes' ), 'manage_options', __FILE__, array( $this, 'dw_render_form') );
    }

    /**
     * Activation: seed defaults only on a fresh install. Reactivating keeps whatever
     * the site had configured.
     */
    function add_options_defaults() {
        if ( false === get_option( self::OPTION ) ) {
            add_option( self::OPTION, self::default_options() );
        }
    }

    function register_settings() {
        register_setting( 'bs_plugin_options', self::OPTION, array( 'sanitize_callback' => array( $this, 'sanitize_options' ) ) );
    }

    function sanitize_options( $input ) {
        $clean = array();
        if ( ! is_array( $input ) ) {
            return $clean;
        }
        foreach ( self::option_keys() as $key ) {
            if ( ! empty( $input[ $key ] ) ) {
                $clean[ $key ] = '1';
            }
        }
        return $clean;
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
                        <th scope="row">Conditional loading</th>
                        <td>
                            <label><input name="bs_options[chk_conditional_assets]" type="checkbox" value="1" <?php if ( isset( $options[ 'chk_conditional_assets' ] ) ) { checked( '1', $options[ 'chk_conditional_assets' ] ); } ?> /> Only load the files above on pages whose content uses these shortcodes</label><br /><span style="color:#666666;margin-left:2px;">Off by default. Leave it off if your theme or other content relies on this plugin loading Bootstrap everywhere. Shortcodes rendered from widgets, templates or custom fields still load the files, but late in the page.</span>
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
