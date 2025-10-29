=== Sync to Moneybird ===
Contributors: schof
Tags: woocommerce, moneybird, accounting, invoices, sync
Requires at least: 5.2
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Automatically sync WooCommerce orders to Moneybird accounting software as external sales invoices.

== Description ==

Sync to Moneybird seamlessly integrates your WooCommerce store with Moneybird accounting software. Automatically create external sales invoices in Moneybird when orders reach a specific status, keeping your bookkeeping up-to-date without manual data entry.

= Features =

* **Easy Setup** - Guided setup wizard to connect your Moneybird account
* **Automatic Sync** - Orders are automatically synced when they reach processing or completed status
* **Flexible Configuration** - Choose which ledger account and tax rate to use
* **Sync History** - View detailed history of all synced orders with success/error status
* **Smart Tax Handling** - Automatically applies the correct tax rate based on order tax amounts
* **Order Notes** - Adds private order notes with direct links to Moneybird invoices
* **Comprehensive Logging** - Track all sync attempts with detailed error messages

= How It Works =

1. Install and activate the plugin
2. Follow the setup wizard to create a Moneybird API token
3. Configure your preferred ledger account, tax rate, and sync trigger
4. Orders will automatically sync to Moneybird when they reach your chosen status
5. View sync history in the admin dashboard

= Requirements =

* WordPress 5.2 or higher
* WooCommerce 5.0 or higher
* PHP 7.4 or higher
* Active Moneybird account
* Moneybird API token with appropriate permissions

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/woocommerce-moneybird`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Moneybird in the admin menu
4. Follow the setup wizard to connect your Moneybird account

= Creating a Moneybird API Token =

1. Log in to your Moneybird account
2. Go to Administration Settings > External Applications
3. Click "Create API Token"
4. Select the following permissions:
   * Sales invoices
5. Copy the generated token and paste it in the plugin setup

== Frequently Asked Questions ==

= What Moneybird permissions are required? =

The plugin requires permissions to read and create sales invoices, as well as read ledger accounts and tax rates.

= Can I sync existing orders? =

Currently, the plugin only syncs orders automatically when they transition to the configured status. Manual syncing of existing orders may be added in a future version.

= What happens if syncing fails? =

Failed sync attempts are logged in the sync history, and a private order note is added to the order with details about the error.

= Can I sync to multiple Moneybird administrations? =

Currently, the plugin supports syncing to a single Moneybird administration. The first administration from your account is automatically selected during setup.

= How are taxes handled? =

The plugin intelligently applies the configured default tax rate only when the WooCommerce tax amount matches what would be expected. If taxes don't match, the line item is synced without a tax rate, allowing you to manually adjust in Moneybird.

== Screenshots ==

1. Setup wizard - Easy step-by-step setup process
2. Settings page - Configure sync preferences
3. Sync history - View all synced orders and their status
4. Order notes - Direct links to Moneybird invoices in WooCommerce orders

== Changelog ==

= 1.0.0 =
* Initial release
* Automatic order syncing to Moneybird
* Setup wizard for easy configuration
* Sync history tracking
* Support for ledger accounts and tax rates
* Configurable sync trigger (processing or completed status)

== Upgrade Notice ==

= 1.0.0 =
Initial release of Sync to Moneybird plugin.
