<?php
if (!defined('ABSPATH')) exit;

/**
 * Plugin settings: the optional "Prepared by" block shown at the top of
 * exported reports (agency / author name and logo).
 *
 * Saved through the WordPress Settings API, so nonce and capability checks
 * are handled by options.php. Everything is stored in one option.
 */
class CoreSpect_Settings {

    const OPTION = 'corespect_settings';
    const GROUP  = 'corespect_settings_group';

    const NAME_MAX_LENGTH = 100;

    public static function defaults() {
        return [
            'prepared_by_name'    => '',
            'prepared_by_logo_id' => 0
        ];
    }

    public static function register() {
        register_setting(self::GROUP, self::OPTION, [
            'type'              => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize'],
            'default'           => self::defaults(),
            'show_in_rest'      => false
        ]);
    }

    /**
     * Clean up whatever was submitted. options.php has already unslashed it.
     */
    public static function sanitize($input) {

        $input = is_array($input) ? $input : [];
        $clean = self::defaults();

        if (isset($input['prepared_by_name'])) {
            $clean['prepared_by_name'] = mb_substr(
                sanitize_text_field($input['prepared_by_name']),
                0,
                self::NAME_MAX_LENGTH
            );
        }

        if (isset($input['prepared_by_logo_id'])) {

            $logo_id = absint($input['prepared_by_logo_id']);

            if ($logo_id > 0 && !wp_attachment_is_image($logo_id)) {

                add_settings_error(
                    self::OPTION,
                    'corespect_logo_invalid',
                    esc_html__('The selected logo is not an image, so it was not saved.', 'corespect-site-audit-report'),
                    'warning'
                );

                $logo_id = 0;
            }

            $clean['prepared_by_logo_id'] = $logo_id;
        }

        // the report is cached for a few minutes; make the change show at once
        delete_transient('corespect_last_report');

        return $clean;
    }

    public static function get() {
        return wp_parse_args((array) get_option(self::OPTION, []), self::defaults());
    }

    /**
     * The "Prepared by" data for the report header, or an empty array if
     * nothing has been set. Developers can still override it with the
     * 'corespect_report_prepared_by' filter.
     */
    public static function get_prepared_by() {

        $settings = self::get();

        $name = trim((string) $settings['prepared_by_name']);
        $logo = '';

        if (!empty($settings['prepared_by_logo_id'])) {
            $url  = wp_get_attachment_image_url((int) $settings['prepared_by_logo_id'], 'medium');
            $logo = $url ? $url : ''; // the image may have been deleted since
        }

        if ($name === '' && $logo === '') {
            return [];
        }

        return [
            'name'     => $name,
            'logo_url' => $logo
        ];
    }

    /* =================================================
       SETTINGS TAB
    ================================================= */

    public static function render_tab() {

        $settings = self::get();
        $logo_id  = (int) $settings['prepared_by_logo_id'];
        $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';

        settings_errors();
        ?>
        <div class="cs-settings">

            <h2><?php echo esc_html__('Report settings', 'corespect-site-audit-report'); ?></h2>

            <p class="description">
                <?php echo esc_html__('Add your name or agency details to show a "Prepared by" block at the top of every exported report. Leave both fields empty to hide it.', 'corespect-site-audit-report'); ?>
            </p>

            <form method="post" action="options.php">

                <?php settings_fields(self::GROUP); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="corespect-prepared-by-name"><?php echo esc_html__('Name or agency', 'corespect-site-audit-report'); ?></label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="corespect-prepared-by-name"
                                class="regular-text"
                                name="<?php echo esc_attr(self::OPTION); ?>[prepared_by_name]"
                                value="<?php echo esc_attr($settings['prepared_by_name']); ?>"
                                maxlength="<?php echo esc_attr(self::NAME_MAX_LENGTH); ?>"
                                placeholder="<?php echo esc_attr__('For example: Northwind Web Studio', 'corespect-site-audit-report'); ?>">
                            <p class="description"><?php echo esc_html__('Shown after "Prepared by" in the report header.', 'corespect-site-audit-report'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Logo', 'corespect-site-audit-report'); ?></th>
                        <td>
                            <input
                                type="hidden"
                                id="corespect-logo-id"
                                name="<?php echo esc_attr(self::OPTION); ?>[prepared_by_logo_id]"
                                value="<?php echo esc_attr($logo_id); ?>">

                            <img
                                id="corespect-logo-preview"
                                class="cs-logo-preview<?php echo $logo_url ? '' : ' is-empty'; ?>"
                                src="<?php echo esc_url($logo_url); ?>"
                                alt="">

                            <button
                                type="button"
                                class="button"
                                id="corespect-logo-select"
                                data-title="<?php echo esc_attr__('Choose a logo', 'corespect-site-audit-report'); ?>"
                                data-button="<?php echo esc_attr__('Use this logo', 'corespect-site-audit-report'); ?>">
                                <?php echo esc_html__('Choose logo', 'corespect-site-audit-report'); ?>
                            </button>

                            <button
                                type="button"
                                class="button-link button-link-delete"
                                id="corespect-logo-remove"
                                <?php echo $logo_id ? '' : 'hidden'; ?>>
                                <?php echo esc_html__('Remove logo', 'corespect-site-audit-report'); ?>
                            </button>

                            <p class="description"><?php echo esc_html__('Optional. Shown small, so a wide logo on a plain background works best.', 'corespect-site-audit-report'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>

            </form>
        </div>
        <?php
    }
}
