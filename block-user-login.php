<?php
/**
 * Plugin Name: Block User Login
 * Description: Block User Login is a WordPress plugin that provides administrators with the ability to selectively block and unblock users from logging in.
 * Version: 1.4.3
 * Plugin URI:  https://techcreative.dev/block-user-login
 * Author: TechCreative
 * Author URI:  https://techcreative.dev/
 * License:     GPL-3.0+
 * Text Domain: block-user-login
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Stable tag: 1.4.3
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class User_Access_Blocker {
    
    // Singleton instance
    private static $instance = null;
    
    // Constructor
    private function __construct() {
        // Add hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_block_user', array($this, 'ajax_block_user'));
        add_action('wp_ajax_unblock_user', array($this, 'ajax_unblock_user'));
        add_action('wp_ajax_search_users', array($this, 'ajax_search_users'));
        add_filter('authenticate', array($this, 'check_if_blocked'), 100, 3);
    }
    
    // Get singleton instance
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Add admin menu
    public function add_admin_menu() {
        add_users_page(
            'Block Users',
            'Block Users',
            'manage_options',
            'block-users',
            array($this, 'render_admin_page')
        );
    }
    
    // Enqueue admin scripts
    public function enqueue_admin_scripts($hook) {
        if ('users_page_block-users' !== $hook) {
            return;
        }
        
        wp_enqueue_style('user-blocker-style', plugin_dir_url(__FILE__) . 'assets/css/admin.css');
        wp_enqueue_script('user-blocker-script', plugin_dir_url(__FILE__) . 'assets/js/admin.js', array('jquery'), '1.0', true);
        
        wp_localize_script('user-blocker-script', 'user_blocker', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('user_blocker_nonce'),
        ));
    }
    
    // Render admin page
    public function render_admin_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get blocked users
        $blocked_users = $this->get_blocked_users();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="user-blocker-search">
                <h2>Block a User</h2>
                <p>Search for a user to block:</p>
                <input type="text" id="user-search" placeholder="Search by username or email">
                <div id="search-results"></div>
            </div>
            
            <div class="user-blocker-list">
                <h2>Currently Blocked Users</h2>
                <?php if (empty($blocked_users)) : ?>
                    <p>No users are currently blocked.</p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Blocked On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($blocked_users as $user_id => $blocked_data) : 
                                $user = get_userdata($user_id);
                                if (!$user) continue;
                            ?>
                                <tr>
                                    <td><?php echo esc_html($user->user_login); ?></td>
                                    <td><?php echo esc_html($user->user_email); ?></td>
                                    <td><?php echo esc_html(gmdate('F j, Y, g:i a', $blocked_data['timestamp'])); ?></td>
                                    <td>
                                        <button class="button unblock-user" data-user-id="<?php echo esc_attr($user_id); ?>">Unblock</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Plugin Footer -->
            <div class="user-blocker-footer" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border: 1px solid #e5e5e5; text-align: center; border-radius: 3px;">
                <p style="margin: 0; color: #666; font-size: 14px;">
                    <strong>Block User Login</strong> v1.4.3 | 
                    <a href="https://techcreative.dev/" target="_blank" style="color: #0073aa; text-decoration: none;">TechCreative.dev</a>
                </p>
            </div>
        </div>
        <?php
    }
    
    // Ajax handler for blocking a user
    public function ajax_block_user() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'user_blocker_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Get user ID
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        
        // Validate user ID
        if ($user_id <= 0) {
            wp_send_json_error('Invalid user ID');
        }
        
        // Check if user exists
        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error('User not found');
        }
        
        // Don't allow blocking yourself
        if ($user_id === get_current_user_id()) {
            wp_send_json_error('You cannot block yourself');
        }
        
        // Don't allow blocking administrators
        if (in_array('administrator', $user->roles)) {
            wp_send_json_error('Administrators cannot be blocked');
        }
        
        // Block the user
        $blocked_users = $this->get_blocked_users();
        $blocked_users[$user_id] = array(
            'timestamp' => time(),
        );
        update_option('user_access_blocker_users', $blocked_users);
        
        wp_send_json_success(array(
            'message' => 'User blocked successfully',
            'user' => array(
                'id' => $user_id,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'blocked_on' => gmdate('F j, Y, g:i a', time()),
            ),
        ));
    }
    
    // Ajax handler for unblocking a user
    public function ajax_unblock_user() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'user_blocker_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Get user ID
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        
        // Validate user ID
        if ($user_id <= 0) {
            wp_send_json_error('Invalid user ID');
        }
        
        // Unblock the user
        $blocked_users = $this->get_blocked_users();
        if (isset($blocked_users[$user_id])) {
            unset($blocked_users[$user_id]);
            update_option('user_access_blocker_users', $blocked_users);
            wp_send_json_success(array(
                'message' => 'User unblocked successfully',
                'user_id' => $user_id,
            ));
        } else {
            wp_send_json_error('User is not blocked');
        }
    }
    
    // Ajax handler for searching users
    public function ajax_search_users() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'user_blocker_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Get search term
        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        
        if (empty($search)) {
            wp_send_json_error('Search term is required');
        }
        
        // Get blocked users
        $blocked_users = $this->get_blocked_users();
        
        // Search for users
        $users = new WP_User_Query(array(
            'search' => '*' . $search . '*',
            'search_columns' => array('user_login', 'user_email'),
            'number' => 10,
            'exclude' => array(get_current_user_id()), // Exclude current user
        ));
        
        $results = array();
        foreach ($users->get_results() as $user) {
            $is_admin = in_array('administrator', $user->roles);
            $is_blocked = isset($blocked_users[$user->ID]);
            
            $results[] = array(
                'id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'is_admin' => $is_admin,
                'is_blocked' => $is_blocked,
            );
        }
        
        wp_send_json_success(array(
            'results' => $results,
        ));
    }
    
    // Check if user is blocked during login
    public function check_if_blocked($user, $username, $password) {
        // Skip if already failed or not a user object
        if (!$user instanceof WP_User) {
            return $user;
        }
        
        // Get blocked users
        $blocked_users = $this->get_blocked_users();
        
        // Check if user is blocked
        if (isset($blocked_users[$user->ID])) {
            return new WP_Error(
                'block-user-login',
                __('Your account has been blocked. Please contact the site administrator for assistance.', 'block-user-login')
            );
        }
        
        return $user;
    }
    
    // Get blocked users
    private function get_blocked_users() {
        $blocked_users = get_option('user_access_blocker_users', array());
        return is_array($blocked_users) ? $blocked_users : array();
    }
}

// Initialize the plugin
function user_access_blocker_init() {
    User_Access_Blocker::get_instance();
}
add_action('plugins_loaded', 'user_access_blocker_init');

// Create CSS directory and file on plugin activation
function user_access_blocker_activate() {
    // Initialize WordPress filesystem
    global $wp_filesystem;
    if (empty($wp_filesystem)) {
        require_once(ABSPATH . '/wp-admin/includes/file.php');
        WP_Filesystem();
    }
    
    // Create plugin assets directories
    $plugin_dir = plugin_dir_path(__FILE__);
    $assets_dir = $plugin_dir . 'assets';
    $css_dir = $assets_dir . '/css';
    $js_dir = $assets_dir . '/js';
    
    if (!$wp_filesystem->exists($assets_dir)) {
        $wp_filesystem->mkdir($assets_dir);
    }
    
    if (!$wp_filesystem->exists($css_dir)) {
        $wp_filesystem->mkdir($css_dir);
    }
    
    if (!$wp_filesystem->exists($js_dir)) {
        $wp_filesystem->mkdir($js_dir);
    }
    
    // Create CSS file
    $css_content = "
.user-blocker-search {
    background: #fff;
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.user-blocker-list {
    background: #fff;
    padding: 15px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

#user-search {
    width: 300px;
    margin-right: 10px;
}

#search-results {
    margin-top: 10px;
}

.search-result-item {
    background: #f9f9f9;
    padding: 10px;
    margin-bottom: 5px;
    border: 1px solid #e5e5e5;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.search-result-item .user-info {
    flex: 1;
}

.search-result-item .user-actions {
    text-align: right;
}
";
    file_put_contents($css_dir . '/admin.css', $css_content);
    
    // Create JS file
    $js_content = "
jQuery(document).ready(function($) {
    var searchTimeout;
    
    // User search
    $('#user-search').on('keyup', function() {
        var search = $(this).val();
        
        // Clear previous timeout
        clearTimeout(searchTimeout);
        
        // Set new timeout
        searchTimeout = setTimeout(function() {
            if (search.length < 3) {
                $('#search-results').empty();
                return;
            }
            
            // Search for users
            $.ajax({
                url: user_blocker.ajax_url,
                type: 'POST',
                data: {
                    action: 'search_users',
                    nonce: user_blocker.nonce,
                    search: search
                },
                success: function(response) {
                    if (response.success) {
                        displaySearchResults(response.data.results);
                    }
                }
            });
        }, 500);
    });
    
    // Display search results
    function displaySearchResults(results) {
        var html = '';
        
        if (results.length === 0) {
            html = '<p>No users found.</p>';
        } else {
            for (var i = 0; i < results.length; i++) {
                var user = results[i];
                html += '<div class=\"search-result-item\">';
                html += '<div class=\"user-info\">';
                html += '<strong>' + user.username + '</strong> (' + user.email + ')';
                html += '</div>';
                html += '<div class=\"user-actions\">';
                
                if (user.is_admin) {
                    html += '<span class=\"button disabled\">Cannot Block Administrator</span>';
                } else if (user.is_blocked) {
                    html += '<span class=\"button disabled\">Already Blocked</span>';
                } else {
                    html += '<button class=\"button button-primary block-user\" data-user-id=\"' + user.id + '\">Block</button>';
                }
                
                html += '</div>';
                html += '</div>';
            }
        }
        
        $('#search-results').html(html);
    }
    
    // Block user
    $(document).on('click', '.block-user', function() {
        var userId = $(this).data('user-id');
        
        $.ajax({
            url: user_blocker.ajax_url,
            type: 'POST',
            data: {
                action: 'block_user',
                nonce: user_blocker.nonce,
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    // Update search results
                    $(this).closest('.search-result-item').find('.user-actions').html('<span class=\"button disabled\">Already Blocked</span>');
                    
                    // Update blocked users list
                    var user = response.data.user;
                    var tableBody = $('.user-blocker-list table tbody');
                    
                    // If table doesn't exist yet, create it
                    if (tableBody.length === 0) {
                        $('.user-blocker-list').html(
                            '<h2>Currently Blocked Users</h2>' +
                            '<table class=\"wp-list-table widefat fixed striped\">' +
                            '<thead><tr><th>Username</th><th>Email</th><th>Blocked On</th><th>Actions</th></tr></thead>' +
                            '<tbody></tbody></table>'
                        );
                        tableBody = $('.user-blocker-list table tbody');
                    }
                    
                    // Add new row
                    var newRow = '<tr id=\"blocked-user-' + user.id + '\">' +
                        '<td>' + user.username + '</td>' +
                        '<td>' + user.email + '</td>' +
                        '<td>' + user.blocked_on + '</td>' +
                        '<td><button class=\"button unblock-user\" data-user-id=\"' + user.id + '\">Unblock</button></td>' +
                        '</tr>';
                    
                    tableBody.append(newRow);
                    
                    // Clear search field
                    $('#user-search').val('');
                    $('#search-results').empty();
                }
            }
        });
    });
    
    // Unblock user
    $(document).on('click', '.unblock-user', function() {
        var userId = $(this).data('user-id');
        var row = $(this).closest('tr');
        
        $.ajax({
            url: user_blocker.ajax_url,
            type: 'POST',
            data: {
                action: 'unblock_user',
                nonce: user_blocker.nonce,
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    // Remove row
                    row.remove();
                    
                    // If no more blocked users, show message
                    if ($('.user-blocker-list table tbody tr').length === 0) {
                        $('.user-blocker-list table').remove();
                        $('.user-blocker-list').append('<p>No users are currently blocked.</p>');
                    }
                }
            }
        });
    });
});
";
    file_put_contents($js_dir . '/admin.js', $js_content);
}
register_activation_hook(__FILE__, 'user_access_blocker_activate');
