# Sync to Moneybird

A modern WordPress plugin that automatically syncs WooCommerce orders to Moneybird accounting software as external sales invoices.

## Features

- **Easy Setup** - Guided setup wizard to connect your Moneybird account
- **Automatic Sync** - Orders are automatically synced when they reach processing or completed status
- **Flexible Configuration** - Choose which ledger account and tax rate to use
- **Sync History** - View detailed history of all synced orders with success/error status
- **Smart Tax Handling** - Automatically applies the correct tax rate based on order tax amounts
- **Order Notes** - Adds private order notes with direct links to Moneybird invoices
- **Comprehensive Logging** - Track all sync attempts with detailed error messages

## Requirements

- WordPress 5.2 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- Active Moneybird account
- Moneybird API token with appropriate permissions

## Installation

### From GitHub

1. Clone this repository or download the ZIP file
2. Upload to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to Moneybird in the admin menu
5. Follow the setup wizard to connect your Moneybird account

### Creating a Moneybird API Token

1. Log in to your Moneybird account
2. Go to **Administration Settings > External Applications**
3. Click **"Create API Token"**
4. Select the following permissions:
   - Sales invoices
5. Copy the generated token and paste it in the plugin setup

## Usage

### Initial Setup

1. After activation, navigate to **Moneybird** in your WordPress admin menu
2. Enter your Moneybird API token in the setup wizard
3. The plugin will automatically:
   - Validate your token
   - Retrieve your administration ID
   - Verify you have the correct permissions

### Configuration

Once connected, configure the following settings:

- **Ledger Account**: Select which ledger account to use for order line items
- **Default Tax Rate**: Choose the default tax rate to apply (optional)
- **Sync Trigger**: Choose when to sync orders (Processing or Completed status)

### Monitoring

View the **Sync History** page to:
- See all synced orders
- Check sync status (success or error)
- Access direct links to Moneybird invoices
- View detailed error messages for failed syncs

## Development

### Local Development Setup

This plugin uses [@wordpress/env](https://www.npmjs.com/package/@wordpress/env) for local development:

```bash
# Install dependencies
npm install

# Start the development environment
npm start

# Stop the development environment
npm stop
```

The local environment includes:
- Latest WordPress installation
- WooCommerce 10.3.3
- The plugin automatically activated

## FAQ

### Can I sync existing orders?

Currently, the plugin only syncs orders automatically when they transition to the configured status. Manual syncing may be added in a future version.

### What happens if syncing fails?

Failed sync attempts are logged in the sync history, and a private order note is added to the order with error details.

### How are taxes handled?

The plugin applies the configured default tax rate only when the WooCommerce tax amount matches expectations. Otherwise, line items are synced without a tax rate for manual adjustment in Moneybird.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This plugin is licensed under the MIT License. See [LICENSE](LICENSE) for details.

## Author

**Thomas Schoffelen**
- Website: [includable.com](https://includable.com)
- GitHub: [@includable](https://github.com/includable)

## Support

For issues and questions, please use the [GitHub Issues](https://github.com/includable/moneybird-for-woocommerce/issues) page.
