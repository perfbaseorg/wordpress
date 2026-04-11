<?php
/**
 * Perfbase WordPress Profiler
 *
 * @package Perfbase\WordPress
 */

namespace Perfbase\WordPress;

/**
 * Handles WordPress-specific profiling scenarios
 */
class PerfbaseProfiler {

    /**
     * Plugin instance
     *
     * @var PerfbasePlugin
     */
    private $plugin;

    /**
     * Active profiling context
     *
     * @var array
     */
    private $context = [];

    /**
     * Constructor
     *
     * @param PerfbasePlugin $plugin
     */
    public function __construct(PerfbasePlugin $plugin) {
        $this->plugin = $plugin;
        $this->init();
    }

    /**
     * Initialize profiler
     *
     * @return void
     */
    private function init() {
        // Hook into WordPress lifecycle events
        add_action('wp_head', [$this, 'on_wp_head'], 999);
        add_action('wp_footer', [$this, 'on_wp_footer'], 1);

        add_action('shutdown', [$this, 'add_database_stats'], 1);

        // Theme and plugin profiling
        add_action('after_setup_theme', [$this, 'profile_theme_setup'], 999);
        add_action('plugins_loaded', [$this, 'profile_plugins_loaded'], 999);

        // Comment and post profiling
        add_action('wp_insert_post', [$this, 'profile_post_save'], 10, 3);
        add_action('wp_insert_comment', [$this, 'profile_comment_insert'], 10, 2);

        // User profiling
        add_action('wp_login', [$this, 'profile_user_login'], 10, 2);
        add_action('wp_logout', [$this, 'profile_user_logout']);

        // Note: wp_cache_add/set/get are PHP functions, not WordPress hooks.
        // Cache profiling is handled by the ext-perfbase extension via TrackCaches flag,
        // not by hook registration.

        // REST API profiling
        add_action('rest_api_init', [$this, 'init_rest_profiling']);

        // WooCommerce profiling (if available)
        if (class_exists('WooCommerce')) {
            $this->init_woocommerce_profiling();
        }
    }

    /**
     * Handle wp_head
     *
     * @return void
     */
    public function on_wp_head() {
        $perfbase = $this->plugin->get_perfbase();
        if (!$perfbase) {
            return;
        }

        $perfbase->setAttribute('wordpress.wp_head', 'reached');
    }

    /**
     * Handle wp_footer
     *
     * @return void
     */
    public function on_wp_footer() {
        $perfbase = $this->plugin->get_perfbase();
        if (!$perfbase) {
            return;
        }

        $perfbase->setAttribute('wordpress.wp_footer', 'reached');
    }

    /**
     * Add database statistics
     *
     * @return void
     */
    public function add_database_stats() {
        $perfbase = $this->plugin->get_perfbase();
        if (!$perfbase) {
            return;
        }

        global $wpdb;

        // Add query count
        $perfbase->setAttribute('database.queries.total', (string) get_num_queries());

        // Add slow queries if available
        if (defined('SAVEQUERIES') && SAVEQUERIES && !empty($wpdb->queries)) {
            $slow_queries = 0;
            $total_time = 0;

            foreach ($wpdb->queries as $query) {
                $time = $query[1];
                $total_time += $time;

                if ($time > 0.1) { // 100ms threshold
                    $slow_queries++;
                }
            }

            $perfbase->setAttribute('database.queries.slow', (string) $slow_queries);
            $perfbase->setAttribute('database.queries.total_time', (string) round($total_time, 4));
        }
    }

    /**
     * Profile theme setup
     *
     * @return void
     */
    public function profile_theme_setup() {
        $perfbase = $this->plugin->get_perfbase();
        if (!$perfbase) {
            return;
        }

        $perfbase->setAttribute('wordpress.theme_setup', 'completed');
    }

    /**
     * Profile plugins loaded
     *
     * @return void
     */
    public function profile_plugins_loaded() {
        $perfbase = $this->plugin->get_perfbase();
        if (!$perfbase) {
            return;
        }

        $active_plugins = get_option('active_plugins', []);
        $perfbase->setAttribute('wordpress.plugins.count', (string) count($active_plugins));
        $perfbase->setAttribute('wordpress.plugins_loaded', 'completed');
    }

    /**
     * Profile post save
     *
     * @param int $post_id
     * @param WP_Post $post
     * @param bool $update
     * @return void
     */
    public function profile_post_save($post_id, $post, $update) {
        $perfbase = $this->plugin->get_perfbase();
        if (!$perfbase) {
            return;
        }

        $action = $update ? 'update' : 'insert';
        $perfbase->setAttribute("wordpress.post.{$action}", (string) $post_id);
        $perfbase->setAttribute("wordpress.post.{$action}.type", $post->post_type);
    }

    /**
     * Profile comment insert
     *
     * @param int $comment_id
     * @param array $comment
     * @return void
     */
    public function profile_comment_insert($comment_id, $comment) {
        $perfbase = $this->plugin->get_perfbase();
        if (!$perfbase) {
            return;
        }

        $perfbase->setAttribute('wordpress.comment.insert', (string) $comment_id);
    }

    /**
     * Profile user login
     *
     * @param string $user_login
     * @param WP_User $user
     * @return void
     */
    public function profile_user_login($user_login, $user) {
        $perfbase = $this->plugin->get_perfbase();
        if (!$perfbase) {
            return;
        }

        $perfbase->setAttribute('wordpress.user.login', (string) $user->ID);
    }

    /**
     * Profile user logout
     *
     * @return void
     */
    public function profile_user_logout() {
        $perfbase = $this->plugin->get_perfbase();
        if (!$perfbase) {
            return;
        }

        $perfbase->setAttribute('wordpress.user.logout', 'true');
    }

    /**
     * Initialize REST API profiling
     *
     * @return void
     */
    public function init_rest_profiling() {
        $perfbase = $this->plugin->get_perfbase();
        if (!$perfbase) {
            return;
        }

        $perfbase->setAttribute('wordpress.rest_api', 'initialized');

        // Hook into REST request
        add_action('rest_pre_dispatch', function($result, $server, $request) {
            $perfbase = $this->plugin->get_perfbase();
            if ($perfbase) {
                $perfbase->setAttribute('wordpress.rest.route', $request->get_route());
                $perfbase->setAttribute('wordpress.rest.method', $request->get_method());
            }
            return $result;
        }, 10, 3);
    }

    /**
     * Initialize WooCommerce profiling
     *
     * @return void
     */
    private function init_woocommerce_profiling() {
        $perfbase = $this->plugin->get_perfbase();
        if (!$perfbase) {
            return;
        }

        $perfbase->setAttribute('woocommerce.active', 'true');
        if (function_exists('WC')) {
            $woocommerce = WC();
            if (is_object($woocommerce) && isset($woocommerce->version) && is_string($woocommerce->version)) {
                $perfbase->setAttribute('woocommerce.version', $woocommerce->version);
            }
        }

        // Profile WooCommerce specific pages
        add_action('woocommerce_init', function() {
            $perfbase = $this->plugin->get_perfbase();
            if (!$perfbase) {
                return;
            }

            if (is_shop()) {
                $perfbase->setAttribute('woocommerce.page', 'shop');
            } elseif (is_product()) {
                $perfbase->setAttribute('woocommerce.page', 'product');
                $product = wc_get_product();
                if ($product) {
                    $perfbase->setAttribute('woocommerce.product.id', (string) $product->get_id());
                    $perfbase->setAttribute('woocommerce.product.type', $product->get_type());
                }
            } elseif (is_cart()) {
                $perfbase->setAttribute('woocommerce.page', 'cart');
            } elseif (is_checkout()) {
                $perfbase->setAttribute('woocommerce.page', 'checkout');
            } elseif (is_account_page()) {
                $perfbase->setAttribute('woocommerce.page', 'account');
            }
        });

        // Profile cart operations
        add_action('woocommerce_add_to_cart', function($cart_item_key, $product_id, $quantity) {
            $perfbase = $this->plugin->get_perfbase();
            if ($perfbase) {
                $perfbase->setAttribute('woocommerce.cart.add', (string) $product_id);
                $perfbase->setAttribute('woocommerce.cart.quantity', (string) $quantity);
            }
        }, 10, 3);

        // Profile order operations
        add_action('woocommerce_new_order', function($order_id) {
            $perfbase = $this->plugin->get_perfbase();
            if ($perfbase) {
                $perfbase->setAttribute('woocommerce.order.created', (string) $order_id);
            }
        });
    }
}
