=== When Last Login ===
Contributors: andrewza, yoohooplugins, travislima
Tags: last login, user login, user login time, last logged in, last seen
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=4GC4JEZH7KSKL
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 1.2.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Show a users last login date by creating a sortable column in your WordPress users list.

== Description ==
A lightweight plugin that allows you to see active users according to their last login time/date. No need to configure, simply activate When Last and you're ready to go! This adds a custom column to your WordPress users list of "Last Login" and a timestamp linked to that user.

= Features =
* Show when last a user has logged into your site.
* Sorts users according to last login time stamp (Ascending/Descending) in the WordPress user list.
* Administrator widget for top 3 users according to login count.
* Integrates with Paid Memberships Pro  1.8+ - Add's a 'Last Logged In' column to the 'Members List'.
* Generates a login history table under 'When Last Login' > 'All Login Records'. 
* Hooks and filters for developers.
* Record the user's last IP address when logging into your WordPress website (Optional Setting).
* A variety of [Premium](https://yoohooplugins.com/plugins/?utm_source=plugin&utm_medium=wordpress&utm_campaign=premium_addons) and Free add-ons available. 

= Free Add-ons =
* [When Last Login - Welcome Email](https://wordpress.org/plugins/when-last-login-welcome-email-add-on/)
* [When Last Login - Export User Records](https://wordpress.org/plugins/when-last-login-export-user-records/)

= Premium Add-ons =
* [When Last Login - Slack Notifications](https://yoohooplugins.com/plugins/when-last-login-slack-notifications/?utm_source=plugin&utm_medium=wordpress&utm_campaign=slack_notifications)
* [When Last Login - User Statistics](https://yoohooplugins.com/plugins/when-last-login-user-statistics/?utm_source=plugin&utm_medium=wordpress&utm_campaign=user_statistics)
* [When Last Login - Zapier Integration](https://yoohooplugins.com/plugins/zapier-integration/?utm_source=plugin&utm_medium=wordpress&utm_campaign=zapier_integration)

= When Last Login in your Language =
We need your help to translate When Last Login into your locale. To translate When Last Login, simply visit [https://translate.wordpress.org/projects/wp-plugins/when-last-login](https://translate.wordpress.org/projects/wp-plugins/when-last-login)

= Track Your Members Better =
You are able to track which members login to your site by simply sorting your default user's list according to when last the user was seen in easily readable text such as "X Min/Hours/Days/Weeks/Months/Years".

= Plugins that When Last Login integrates with =
Here is a list of plugins we currently support:

* [Paid Memberships Pro](https://www.paidmembershipspro.com/)
* [Two Factor Authentication](https://wordpress.org/plugins/two-factor/)

If you have a plugin and would like to integrate with When Last Login, please open a support thread.

= Need Help =
Something not working as intended or have a question about functionality of When Last Login. [Open a support thread](https://wordpress.org/support/plugin/when-last-login)

== Installation ==
1. Upload the plugin files to the '/wp-content/plugins' directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to 'All Users' and data will be showing under 'Last Seen'
4. Users that have not yet logged in with 'When Last' active will show Never.

== Frequently Asked Questions ==

= What does When Last Login do exactly? =
When Last Login allows you to see when last users have logged into your WordPress website. This is great for big sites that require user management. 

= Is When Last Login GDPR ready? =
Since version 1.0, we have made it easier to be GDPR compliant. With this being said, you need to take the necessary steps in order to become GDPR compliant - we are not a law firm!

= Does this work with 2FA? =
Yes, this integrates with the [Two Factor Authentication](https://wordpress.org/plugins/two-factor/) plugin.

= Is this plugin free? =
Yes, When Last Login is a free plugin for WordPress. We are looking into possibilities of creating a Pro version with a lot more features around the user data of WordPress users. We rely heavily on donations to keep all of our plugins free. If you wish to donate, please click on the donation link on the WordPress repository.

= Where is the When Last Login settings page? =
The settings page is found under the menu item 'When Last Login' within the WordPress dashboard.

= How can I remove data I no longer need? =
Inside your WordPress dashboard, navigate to 'When Last Login' -> 'Settings'. Under the 'Tools' heading, you will have a couple of actions that you can run, such as clear old login records, clear all login records and clear IP Addresses.

= I have installed When Last but users are showing "Never" under "Last Login"? =
This is because users have not logged in since you have activated "When Last Login". By default we set the "Last Login" to "Never" but this can be easily changed. Once a user logs into your site, their profile will be updated from "Never" to a timestamp.

= I have updated and lost my 'Login Records' link in the WordPress dashboard =
As of version 0.6 the 'Login Records' has been moved under the 'Users' link in the admin dashboard. 
Version 0.7 introduced a settings page menu item. The 'Login Records' can now be found under the 'When Last Login' menu item. 

= How can I hide the 'All Login Records'? =
Inside your WordPress dashboard navigate to 'When Last Login' -> 'Settings' and deselect this option.

= How can I hide the 'Top 3 Users' widget? =
Add the following snippet of code to your theme's functions.php or custom plugin - add_filter( 'when_last_login_show_admin_widget', '__return_false' );

== Screenshots ==
1. When Last Login - User's list custom last login field with sorting according to "Last Login" time.
2. When Last Login - Show top 3 user login (includes 'administrators' in free version)

== Changelog ==
= 1.2.3 - 2025-07-21 =
* ENHANCEMENT: Added support for 2FA plugin.

= 1.2.2 - 2023-02-28 =
* SECURITY: Added in nonce for hiding the admin notice shown on the settings page.
* BUG FIX: Fixed minor issue where admins weren’t being correctly excluded from the user query for the login widget.

= 1.2.1 - 2021-09-21 =
* Enhancement: Removed admin users from being tracked in WLL dashboard statistics.
* Bug Fix: Issue with order by not correctly set in the dashboard statistics.
= 1.2 =

* Security: Added nonces to settings page.
* Enhancement: Moved notice from all dashboard pages to only show on dashboard page.
* Enhancement: All login records functionality improvement to support PHP 7.4 - Thanks [@walle98](https://github.com/walle89)

= 1.1 =
* Enhancement: Support Privacy Changes coming to WordPress 4.9.6
* Enhancement: Additional Hooks added.

= 1.0 =
* Enhancement: Changed admin icon for menu.
* Enhancement: Added in tools to delete existing data from WordPress dashboard.
* Enhancement: Added in IP Anonymization for IP address tracking to support GDPR.
* Bug Fix: Removed IP address column from users table, if not recording IP's.
* Enhancement/Bug Fix: General speed improvements where possible.

= 0.9 =
* Enhancement: Multisite Support - Dashboard widget
* Enhancement: Multisite Support - User activity is now visible in the network admin's 'Users' page
* Bug Fix: Fixed an undefined variable when logging in

= 0.8 =
* Enhancement: If enabled, user's IP address is availableon the 'Users' profile page
* Enhancement: If enabled, user's IP address is recorded on registration
* Improvements to add-ons page
* Enhancement: User IP address is now visible for each login record if enabled

= 0.7 =
* New Feature: Settings page introduced
* New Feature: Ability to record a user's IP address when logging in
* Enhancement: Login Records moved under the 'When Last Login' menu item
* New Hook Added: 'wll_settings_admin_menu_item'
* New Hook Added: 'wll_logged_in_action'

= 0.6 =
* Enhancement: Moved 'All Login Records' underneath 'Users' link in dashboard.
* Filter: 'when_last_login_show_records_table'. Accepts boolean (default: true).
* Filter: 'when_last_login_show_admin_widget'. Accepts boolean (default: true).

= 0.5 =
* Enhancement: Ability to see which users have logged in and at what times ( Custom Post Type ) - @jarrydlong 
* Bug Fix: return default value for column data if no data is found - @seagyn
* Enhancement: Improved code readability

= 0.4 =
* Enhancement: Dashboard widget added to display top users with user count.

= 0.3 =
* Enhancement: Implemented multi language support and a couple of language files.
* Language Support: French, Spanish, German and Italian

= 0.2 =
* Bug Fixes: fixed missing 'static' on function 'sort_by_login_date'
* Error Handling: Check if 'Paid Memberships Pro' is installed, if not return from the function

= 0.1 =
* First Release

== Upgrade Notice ==
= 1.2.3 =
* Upgrade to support 2FA plugin.

= 1.2.2 =
* Upgrade for minor security and bug improvements.

= 1.2.1 =
* Upgrade to get bug fixes and new improvements to When Last Login.

= 1.2 =
* Version 1.2 includes security improvements and minor code improvements.

= 1.1 =
* Please update When Last Login to ensure you are running the latest version which includes changes for GDPR compliances and WordPress 4.9.6

= 0.6 =
* Please update When Last Login to receive new features. Love When Last Login? Buy the developer a cup of coffee! $5.

= 0.5 =
* Please update When Last Login to receive our latest feature. Please report any bugs you find on the support forum!

= 0.4 =
* Please update When Last Login to receive our latest feature. Please report any bugs you find on the support forum!

= 0.3 =
* Please update When Last Login for language support - please see readme.txt for languages supported

= 0.2 =
* Please update When Last Login

= 0.1 =
* First Release
