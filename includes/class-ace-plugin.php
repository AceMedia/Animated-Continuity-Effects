<?php

if (!defined('ABSPATH')) {
    exit;
}

class ACE_Plugin {
    /** @var ACE_Plugin|null */
    private static $instance = null;

    /** @var string */
    private $hook_suffix = '';

    /**
     * Get the singleton instance.
     *
     * @return ACE_Plugin
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Activation callback.
     */
    public static function activate() {
        if (false === get_option(ACE_OPTION_NAME, false)) {
            add_option(ACE_OPTION_NAME, self::default_settings(), '', false);
        }
    }

    /**
     * Deactivation callback.
     */
    public static function deactivate() {
        // Intentionally left blank; settings are preserved on deactivation.
    }

    /**
     * Constructor.
     */
    private function __construct() {
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }

    /**
     * Default settings.
     *
     * @return array<string, mixed>
     */
    public static function default_settings() {
        return array(
            'duration' => '350ms',
            'timing_function' => 'cubic-bezier(0.2, 0, 0, 1)',
            'delay' => '0ms',
            'fade_in' => 'auto',
            'fade_out' => 'auto',
            'name_scope' => 'post',
            'exclude_list' => '',
            'same_element_rules' => self::default_same_element_rules(),
        );
    }

    /**
     * Default shared element rules.
     *
     * @return array<int, array<string, string>>
     */
    private static function default_same_element_rules() {
        return array(
            array(
                'label' => 'Featured image',
                'selector' => '.wp-post-image, .featured-image img, .post-thumbnail img',
                'scope' => 'post',
            ),
            array(
                'label' => 'Site logo',
                'selector' => '.custom-logo, .site-logo img',
                'scope' => 'site',
            ),
            array(
                'label' => 'Entry title',
                'selector' => '.entry-title, .wp-block-post-title',
                'scope' => 'post',
            ),
            array(
                'label' => 'Avatars',
                'selector' => '.avatar, .wp-block-avatar img',
                'scope' => 'person',
            ),
        );
    }

    /**
     * Load settings.
     *
     * @return array<string, mixed>
     */
    private function get_settings() {
        $stored = get_option(ACE_OPTION_NAME, array());
        if (!is_array($stored)) {
            $stored = array();
        }

        return wp_parse_args($stored, self::default_settings());
    }

    /**
     * Register admin menu.
     */
    public function register_admin_menu() {
        $this->hook_suffix = add_options_page(
            __('Animated Continuity Effects', 'animated-continuity-effects'),
            __('Animated Continuity Effects', 'animated-continuity-effects'),
            'manage_options',
            'animated-continuity-effects',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings and fields.
     */
    public function register_settings() {
        register_setting(
            'ace_settings_group',
            ACE_OPTION_NAME,
            array($this, 'sanitize_settings')
        );

        add_settings_section(
            'ace_main_section',
            __('View Transition Settings', 'animated-continuity-effects'),
            function () {
                echo '<p>' . esc_html__('Set the view transition CSS variables and exclusions for the frontend.', 'animated-continuity-effects') . '</p>';
            },
            'animated-continuity-effects'
        );

        add_settings_field(
            'duration',
            __('Duration', 'animated-continuity-effects'),
            array($this, 'render_text_field'),
            'animated-continuity-effects',
            'ace_main_section',
            array('key' => 'duration', 'placeholder' => '350ms')
        );

        add_settings_field(
            'timing_function',
            __('Timing function', 'animated-continuity-effects'),
            array($this, 'render_text_field'),
            'animated-continuity-effects',
            'ace_main_section',
            array('key' => 'timing_function', 'placeholder' => 'cubic-bezier(0.2, 0, 0, 1)')
        );

        add_settings_field(
            'delay',
            __('Delay', 'animated-continuity-effects'),
            array($this, 'render_text_field'),
            'animated-continuity-effects',
            'ace_main_section',
            array('key' => 'delay', 'placeholder' => '0ms')
        );

        add_settings_field(
            'fade_in',
            __('Fade in', 'animated-continuity-effects'),
            array($this, 'render_text_field'),
            'animated-continuity-effects',
            'ace_main_section',
            array('key' => 'fade_in', 'placeholder' => 'auto')
        );

        add_settings_field(
            'fade_out',
            __('Fade out', 'animated-continuity-effects'),
            array($this, 'render_text_field'),
            'animated-continuity-effects',
            'ace_main_section',
            array('key' => 'fade_out', 'placeholder' => 'auto')
        );

        add_settings_field(
            'name_scope',
            __('Name scope', 'animated-continuity-effects'),
            array($this, 'render_text_field'),
            'animated-continuity-effects',
            'ace_main_section',
            array('key' => 'name_scope', 'placeholder' => 'post')
        );

        add_settings_field(
            'exclude_list',
            __('Excluded classes or IDs', 'animated-continuity-effects'),
            array($this, 'render_textarea_field'),
            'animated-continuity-effects',
            'ace_main_section'
        );
    }

    /**
     * Sanitize settings.
     *
     * @param mixed $input Raw input.
     * @return array<string, mixed>
     */
    public function sanitize_settings($input) {
        $defaults = self::default_settings();
        $input = is_array($input) ? $input : array();
        $output = array();

        foreach (array('duration', 'timing_function', 'delay', 'fade_in', 'fade_out', 'name_scope') as $key) {
            $value = isset($input[$key]) ? wp_unslash($input[$key]) : $defaults[$key];
            $output[$key] = sanitize_text_field($value);
        }

        $exclude_list = isset($input['exclude_list']) ? wp_unslash($input['exclude_list']) : '';
        $exclude_items = preg_split('/[,\n]+/', (string) $exclude_list);
        $exclude_items = array_filter(array_map('trim', (array) $exclude_items));
        $exclude_items = array_values(array_unique($exclude_items));
        $output['exclude_list'] = implode(', ', array_map('sanitize_text_field', $exclude_items));

        $output['same_element_rules'] = $defaults['same_element_rules'];

        return $output;
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook_suffix Current hook suffix.
     */
    public function enqueue_admin_assets($hook_suffix) {
        if ('settings_page_animated-continuity-effects' !== $hook_suffix) {
            return;
        }

        wp_enqueue_style(
            'ace-admin',
            ACE_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            '0.1.0'
        );
    }

    /**
     * Enqueue frontend assets and inline configuration.
     */
    public function enqueue_frontend_assets() {
        if (is_admin()) {
            return;
        }

        $settings = $this->get_settings();
        $config = $this->build_frontend_config($settings);

        wp_enqueue_style(
            'ace-frontend',
            ACE_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            '0.1.0'
        );
        wp_register_script(
            'ace-frontend',
            ACE_PLUGIN_URL . 'assets/js/frontend.js',
            array(),
            '0.1.0',
            true
        );
        wp_enqueue_script('ace-frontend');
        wp_add_inline_script(
            'ace-frontend',
            'window.ACE_VIEW_TRANSITIONS = ' . wp_json_encode($config) . ';',
            'before'
        );
    }

    /**
     * Build frontend config.
     *
     * @param array<string, mixed> $settings Settings.
     * @return array<string, mixed>
     */
    private function build_frontend_config(array $settings) {
        $css_vars = array(
            '--ace-view-transition-duration' => $settings['duration'],
            '--ace-view-transition-timing-function' => $settings['timing_function'],
            '--ace-view-transition-delay' => $settings['delay'],
            '--ace-view-transition-fade-in' => $settings['fade_in'],
            '--ace-view-transition-fade-out' => $settings['fade_out'],
        );

        return array(
            'cssVars' => $css_vars,
            'excludeList' => $this->parse_exclude_list($settings['exclude_list']),
            'sameElementRules' => $this->prepare_same_element_rules($settings['same_element_rules']),
            'nameScope' => sanitize_key($settings['name_scope']),
        );
    }

    /**
     * Parse exclusions.
     *
     * @param string $raw Raw CSV.
     * @return array<int, string>
     */
    private function parse_exclude_list($raw) {
        $items = preg_split('/[,\n]+/', (string) $raw);
        $items = array_filter(array_map('trim', (array) $items));
        $items = array_map('sanitize_text_field', $items);
        return array_values(array_unique($items));
    }

    /**
     * Prepare same-element rules.
     *
     * @param array<int, array<string, string>> $rules Rules.
     * @return array<int, array<string, string>>
     */
    private function prepare_same_element_rules(array $rules) {
        $prepared = array();

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $label = isset($rule['label']) ? sanitize_text_field($rule['label']) : '';
            $selector = isset($rule['selector']) ? sanitize_text_field($rule['selector']) : '';
            $scope = isset($rule['scope']) ? sanitize_key($rule['scope']) : 'post';

            if ('' === $label || '' === $selector) {
                continue;
            }

            $prepared[] = array(
                'label' => $label,
                'selector' => $selector,
                'scope' => $scope,
            );
        }

        return $prepared;
    }

    /**
     * Render settings page.
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = $this->get_settings();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Animated Continuity Effects', 'animated-continuity-effects'); ?></h1>
            <p><?php echo esc_html__('Control the view transition motion, exclusions, and shared element rules used on the frontend.', 'animated-continuity-effects'); ?></p>
            <form action="options.php" method="post">
                <?php
                settings_fields('ace_settings_group');
                do_settings_sections('animated-continuity-effects');
                submit_button();
                ?>
            </form>
            <h2><?php echo esc_html__('Shared element presets', 'animated-continuity-effects'); ?></h2>
            <p><?php echo esc_html__('These selectors target elements that can animate across pages when the markup matches on both sides of the transition.', 'animated-continuity-effects'); ?></p>
            <ul>
                <?php foreach ($settings['same_element_rules'] as $rule) : ?>
                    <li><strong><?php echo esc_html($rule['label']); ?></strong>: <code><?php echo esc_html($rule['selector']); ?></code></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    /**
     * Render a text field.
     *
     * @param array<string, mixed> $args Arguments.
     */
    public function render_text_field($args) {
        $settings = $this->get_settings();
        $key = isset($args['key']) ? $args['key'] : '';
        $value = isset($settings[$key]) ? $settings[$key] : '';
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';
        ?>
        <input type="text"
               class="regular-text"
               name="<?php echo esc_attr(ACE_OPTION_NAME . '[' . $key . ']'); ?>"
               value="<?php echo esc_attr($value); ?>"
               placeholder="<?php echo esc_attr($placeholder); ?>">
        <?php
    }

    /**
     * Render a textarea field.
     */
    public function render_textarea_field() {
        $settings = $this->get_settings();
        ?>
        <textarea class="large-text" rows="4" name="<?php echo esc_attr(ACE_OPTION_NAME . '[exclude_list]'); ?>"><?php echo esc_textarea($settings['exclude_list']); ?></textarea>
        <p class="description"><?php echo esc_html__('Comma-separated classes or IDs, for example: .no-transition, #cart, .skip-view-transition', 'animated-continuity-effects'); ?></p>
        <?php
    }
}
