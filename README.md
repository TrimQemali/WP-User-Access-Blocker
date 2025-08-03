# Block User Login
Plugin Name: Block User Login
Description: Block User Login provides administrators with the ability to selectively block and unblock users from logging into their WordPress site.
Version: 1.4.3
Plugin URI:  https://techcreative.dev/block-user-login
Author: TechCreative
Author URI:  https://techcreative.dev/
License:     GPL-3.0+
Text Domain: block-user-login
Domain Path: /languages
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.4.3



What It Does
- Block Users: Prevent specific users from logging into your WordPress site
- Unblock Users: Restore login access for previously blocked users
- User Search: Search for users by username or email address
- Real-time Management: AJAX-powered interface for instant blocking/unblocking
- Login Prevention: Automatically blocks login attempts from blocked users
- Admin Protection: Prevents administrators from being blocked (including self-blocking)

How It Works
- The plugin uses a singleton class architecture (User_Access_Blocker) that:
- Stores blocked users in the WordPress options table (user_access_blocker_users)
- Hooks into the authentication process to check if a user is blocked during login
- Provides an admin interface under Users > Block Users in the WordPress dashboard
- Uses AJAX for seamless user search and blocking/unblocking operations
- Implements security measures including nonce verification and capability checks
- When a blocked user attempts to log in, they receive an error message: "Your account has been blocked. Please contact the site administrator for assistance."

Installation

- Upload the plugin: Copy the user-access-blocker folder to your /wp-content/plugins/ directory 
- Activate the plugin: Go to Plugins > Installed Plugins in your WordPress admin and activate "User Access Blocker
- Access the interface: Navigate to Users > Block Users in your WordPress admin dashboard


Requirements
- WordPress 4.0 or higher
- Administrator privileges to access blocking functionality
- JavaScript enabled for optimal user experience
- Security Features
- Nonce verification for all AJAX requests
- Capability checks (manage_options) for admin functions
- Prevention of self-blocking
- Administrator role protection
- Sanitized user input handling


This plugin provides a simple yet effective solution for managing user access without complex role systems or permanent user deletion.
