<?php
namespace WC_Moneybird\Admin;

use WC_Moneybird\Api_Client;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings page handler
 */
class Settings {
    public function __construct() {
        add_action('admin_init', [$this, 'handle_form_submission']);
    }

    public function render() {
        $api_token = get_option('wc_moneybird_api_token');
        $administration_id = get_option('wc_moneybird_administration_id');

        echo '<div class="wrap wc-moneybird-settings">';
        echo '<h1>' . esc_html__('Moneybird Sync Settings', 'moneybird-for-woocommerce') . '</h1>';

        // Display settings errors/updates
        settings_errors('wc_moneybird');

        if (empty($api_token) || empty($administration_id)) {
            $this->render_setup_wizard();
        } else {
            $this->render_main_settings();
        }

        echo '</div>';
    }

    private function render_setup_wizard() {
        ?>
        <div class="wc-moneybird-setup-wizard">
            <div class="wc-moneybird-card">
                <h2><?php esc_html_e('Welcome to Moneybird Sync', 'moneybird-for-woocommerce'); ?></h2>
                <p><?php esc_html_e('Let\'s get you connected to Moneybird. Follow these steps:', 'moneybird-for-woocommerce'); ?></p>

                <div class="wc-moneybird-steps">
                    <div class="step">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h3><?php esc_html_e('Create a Moneybird API Token', 'moneybird-for-woocommerce'); ?></h3>
                            <ol>
                                <li><?php esc_html_e('Log in to your Moneybird account', 'moneybird-for-woocommerce'); ?></li>
                                <li><?php esc_html_e('Go to Administration Settings > External Applications', 'moneybird-for-woocommerce'); ?></li>
                                <li><?php esc_html_e('Click "Create API Token"', 'moneybird-for-woocommerce'); ?></li>
                                <li>
                                    <?php esc_html_e('Select the following permissions:', 'moneybird-for-woocommerce'); ?>
                                    <?php esc_html_e('Sales invoices', 'moneybird-for-woocommerce'); ?>
                                </li>
                                <li><?php esc_html_e('Copy the generated token', 'moneybird-for-woocommerce'); ?></li>
                            </ol>
                        </div>
                    </div>

                    <div class="step">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h3><?php esc_html_e('Enter Your API Token', 'moneybird-for-woocommerce'); ?></h3>
                            <form method="post" action="">
                                <?php wp_nonce_field('wc_moneybird_setup', 'wc_moneybird_setup_nonce'); ?>

                               <input type="text"
                                                   id="api_token"
                                                   name="api_token"
                                                   class="regular-text"
                                                   placeholder="<?php esc_attr_e('Paste your API token here', 'moneybird-for-woocommerce'); ?>"
                                                   required>
                                            <p class="description">
                                                <?php esc_html_e('Your API token will be securely stored and used to communicate with Moneybird.', 'moneybird-for-woocommerce'); ?>
                                            </p>
                              
                                <p class="submit">
                                    <button type="submit" name="action" value="setup" class="button button-primary button-large">
                                        <?php esc_html_e('Connect to Moneybird', 'moneybird-for-woocommerce'); ?>
                                    </button>
                                </p>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_main_settings() {
        $ledger_account_id = get_option('wc_moneybird_ledger_account_id');
        $tax_rate_id = get_option('wc_moneybird_tax_rate_id');
        $sync_on_status = get_option('wc_moneybird_sync_on_status', 'completed');

        $api = new Api_Client();
        $ledger_accounts = $api->get_ledger_accounts();
        $tax_rates = $api->get_tax_rates();

        ?>
        <div class="wc-moneybird-main-settings">
            <div class="wc-moneybird-card">
                <h2><?php esc_html_e('Sync Settings', 'moneybird-for-woocommerce'); ?></h2>

                <form method="post" action="">
                    <?php wp_nonce_field('wc_moneybird_settings', 'wc_moneybird_settings_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="ledger_account_id"><?php esc_html_e('Ledger Account', 'moneybird-for-woocommerce'); ?></label>
                            </th>
                            <td>
                                <?php if (is_wp_error($ledger_accounts)): ?>
                                    <p class="error"><?php echo esc_html($ledger_accounts->get_error_message()); ?></p>
                                <?php else: ?>
                                    <select id="ledger_account_id" name="ledger_account_id" class="regular-text">
                                        <option value=""><?php esc_html_e('Select a ledger account', 'moneybird-for-woocommerce'); ?></option>
                                        <?php foreach ($ledger_accounts as $account): ?>
                                            <option value="<?php echo esc_attr($account['id']); ?>"
                                                    <?php selected($ledger_account_id, $account['id']); ?>>
                                                <?php echo esc_html($account['name'] . ' (' . $account['account_type'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">
                                        <?php esc_html_e('The ledger account to use for WooCommerce order line items.', 'moneybird-for-woocommerce'); ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="tax_rate_id"><?php esc_html_e('Default Tax Rate', 'moneybird-for-woocommerce'); ?></label>
                            </th>
                            <td>
                                <?php if (is_wp_error($tax_rates)): ?>
                                    <p class="error"><?php echo esc_html($tax_rates->get_error_message()); ?></p>
                                <?php else: ?>
                                    <select id="tax_rate_id" name="tax_rate_id" class="regular-text">
                                        <option value=""><?php esc_html_e('No default tax rate', 'moneybird-for-woocommerce'); ?></option>
                                        <?php foreach ($tax_rates as $rate): ?>
                                            <option value="<?php echo esc_attr($rate['id']); ?>"
                                                    <?php selected($tax_rate_id, $rate['id']); ?>>
                                                <?php echo esc_html($rate['name'] . ' (' . $rate['percentage'] . '%)'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">
                                        <?php esc_html_e('The default tax rate to apply to invoice lines. Will only be applied if it matches the WooCommerce tax amount.', 'moneybird-for-woocommerce'); ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="sync_on_status"><?php esc_html_e('Sync When Order Status', 'moneybird-for-woocommerce'); ?></label>
                            </th>
                            <td>
                                <select id="sync_on_status" name="sync_on_status" class="regular-text">
                                    <option value="processing" <?php selected($sync_on_status, 'processing'); ?>>
                                        <?php esc_html_e('Processing', 'moneybird-for-woocommerce'); ?>
                                    </option>
                                    <option value="completed" <?php selected($sync_on_status, 'completed'); ?>>
                                        <?php esc_html_e('Completed', 'moneybird-for-woocommerce'); ?>
                                    </option>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('Orders will be synced to Moneybird when they transition to this status.', 'moneybird-for-woocommerce'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" name="action" value="save_settings" class="button button-primary">
                            <?php esc_html_e('Save Settings', 'moneybird-for-woocommerce'); ?>
                        </button>
                        <button type="submit" name="action" value="reset" class="button button-secondary"
                                onclick="return confirm('<?php esc_attr_e('Are you sure you want to reset all settings? This will disconnect your Moneybird account.', 'moneybird-for-woocommerce'); ?>');">
                            <?php esc_html_e('Reset Settings', 'moneybird-for-woocommerce'); ?>
                        </button>
                    </p>
                </form>

                <div class="wc-moneybird-connection-status">
                    <h3><?php esc_html_e('Connection Status', 'moneybird-for-woocommerce'); ?></h3>
                    <p>
                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php esc_html_e('Connected to Moneybird', 'moneybird-for-woocommerce'); ?>
                    </p>
                    <p class="description">
                        <?php
                        /* translators: %s: The Moneybird administration ID */
                        printf(
                            esc_html__('Administration ID: %s', 'moneybird-for-woocommerce'),
                            '<code>' . esc_html(get_option('wc_moneybird_administration_id')) . '</code>'
                        );
                        ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    public function handle_form_submission() {
        if (!isset($_POST['action'])) {
            return;
        }

        $action = sanitize_text_field(wp_unslash($_POST['action']));

        if ($action === 'setup') {
            $this->handle_setup();
        } elseif ($action === 'save_settings') {
            $this->handle_save_settings();
        } elseif ($action === 'reset') {
            $this->handle_reset();
        }
    }

    private function handle_setup() {
        if (!isset($_POST['wc_moneybird_setup_nonce']) ||
            !wp_verify_nonce(wp_unslash($_POST['wc_moneybird_setup_nonce']), 'wc_moneybird_setup')) {
            wp_die(esc_html__('Security check failed', 'moneybird-for-woocommerce'));
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page', 'moneybird-for-woocommerce'));
        }

        $api_token = isset($_POST['api_token']) ? sanitize_text_field(wp_unslash($_POST['api_token'])) : '';

        if (empty($api_token)) {
            add_settings_error('wc_moneybird', 'empty_token', __('Please enter an API token', 'moneybird-for-woocommerce'));
            return;
        }

        $api = new Api_Client($api_token);

        // Step 1: Get administrations
        $administrations = $api->get_administrations();

        if (is_wp_error($administrations)) {
            add_settings_error(
                'wc_moneybird',
                'invalid_token',
                sprintf(
                    /* translators: %s: The error message from the API */
                    __('Invalid API token: %s', 'moneybird-for-woocommerce'),
                    $administrations->get_error_message()
                )
            );
            return;
        }

        if (empty($administrations) || !is_array($administrations)) {
            add_settings_error('wc_moneybird', 'no_administrations', __('No administrations found for this API token', 'moneybird-for-woocommerce'));
            return;
        }

        $administration_id = $administrations[0]['id'];

        // Step 2: Verify permissions by listing sales invoices
        $api = new Api_Client($api_token, $administration_id);
        $verify = $api->verify_permissions();

        if (is_wp_error($verify)) {
            add_settings_error(
                'wc_moneybird',
                'insufficient_permissions',
                sprintf(
                    /* translators: %s: The error message describing permission issues */
                    __('API token does not have sufficient permissions: %s', 'moneybird-for-woocommerce'),
                    $verify->get_error_message()
                )
            );
            return;
        }

        // Save settings
        update_option('wc_moneybird_api_token', $api_token);
        update_option('wc_moneybird_administration_id', $administration_id);

        add_settings_error(
            'wc_moneybird',
            'setup_success',
            __('Successfully connected to Moneybird!', 'moneybird-for-woocommerce'),
            'success'
        );

        // Redirect to prevent form resubmission
        wp_safe_redirect(admin_url('admin.php?page=wc-moneybird&setup=success'));
        exit;
    }

    private function handle_save_settings() {
        if (!isset($_POST['wc_moneybird_settings_nonce']) ||
            !wp_verify_nonce(wp_unslash($_POST['wc_moneybird_settings_nonce']), 'wc_moneybird_settings')) {
            wp_die(esc_html__('Security check failed', 'moneybird-for-woocommerce'));
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page', 'moneybird-for-woocommerce'));
        }

        $ledger_account_id = isset($_POST['ledger_account_id']) ? sanitize_text_field(wp_unslash($_POST['ledger_account_id'])) : '';
        $tax_rate_id = isset($_POST['tax_rate_id']) ? sanitize_text_field(wp_unslash($_POST['tax_rate_id'])) : '';
        $sync_on_status = isset($_POST['sync_on_status']) ? sanitize_text_field(wp_unslash($_POST['sync_on_status'])) : 'completed';

        update_option('wc_moneybird_ledger_account_id', $ledger_account_id);
        update_option('wc_moneybird_tax_rate_id', $tax_rate_id);
        update_option('wc_moneybird_sync_on_status', $sync_on_status);

        add_settings_error(
            'wc_moneybird',
            'settings_saved',
            __('Settings saved successfully!', 'moneybird-for-woocommerce'),
            'success'
        );

        wp_safe_redirect(admin_url('admin.php?page=wc-moneybird&updated=1'));
        exit;
    }

    private function handle_reset() {
        if (!isset($_POST['wc_moneybird_settings_nonce']) ||
            !wp_verify_nonce(wp_unslash($_POST['wc_moneybird_settings_nonce']), 'wc_moneybird_settings')) {
            wp_die(esc_html__('Security check failed', 'moneybird-for-woocommerce'));
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page', 'moneybird-for-woocommerce'));
        }

        delete_option('wc_moneybird_api_token');
        delete_option('wc_moneybird_administration_id');
        delete_option('wc_moneybird_ledger_account_id');
        delete_option('wc_moneybird_tax_rate_id');
        delete_option('wc_moneybird_sync_on_status');

        add_settings_error(
            'wc_moneybird',
            'settings_reset',
            __('Settings have been reset. Please reconnect to Moneybird.', 'moneybird-for-woocommerce'),
            'success'
        );

        wp_safe_redirect(admin_url('admin.php?page=wc-moneybird&reset=1'));
        exit;
    }
}
