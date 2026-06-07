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
        add_action('wp_head', array($this, 'output_frontend_inline_styles'), 20);
        add_action('wp_head', array($this, 'output_frontend_inline_bootstrap'), 30);
    }

    /**
     * Default settings.
     *
     * @return array<string, mixed>
     */
    public static function default_settings() {
        return array(
            'motion_preset' => 'gentle',
            'animation_style' => 'fade',
            'duration' => '350ms',
            'timing_function' => 'cubic-bezier(0.2, 0, 0, 1)',
            'delay' => '0ms',
            'transition_mode' => 'cross-document',
            'root_transition' => '1',
            'respect_reduced_motion' => '1',
            'shared_elements' => array(
                'featured_image' => '1',
                'site_logo' => '1',
                'entry_title' => '1',
                'avatar' => '0',
            ),
            'persistent_elements' => array(
                'site_header' => '0',
                'site_footer' => '0',
                'primary_nav' => '0',
                'sidebar' => '0',
            ),
            'name_scope' => 'post',
            'exclude_list' => '',
            'advanced_selectors' => '',
        );
    }

    /**
     * Motion presets.
     *
     * @return array<string, array<string, string>>
     */
    private static function motion_presets() {
        return array(
            'gentle' => array(
                'label' => 'Gentle',
                'duration' => '350ms',
                'timing_function' => 'cubic-bezier(0.2, 0, 0, 1)',
                'delay' => '0ms',
            ),
            'airy' => array(
                'label' => 'Airy',
                'duration' => '250ms',
                'timing_function' => 'ease-out',
                'delay' => '0ms',
            ),
            'dramatic' => array(
                'label' => 'Dramatic',
                'duration' => '650ms',
                'timing_function' => 'cubic-bezier(0.16, 1, 0.3, 1)',
                'delay' => '0ms',
            ),
            'slow' => array(
                'label' => 'Slow',
                'duration' => '900ms',
                'timing_function' => 'cubic-bezier(0.22, 1, 0.36, 1)',
                'delay' => '0ms',
            ),
        );
    }

    /**
     * Animation styles for the root transition.
     *
     * @return array<string, array<string, string>>
     */
    private static function animation_styles() {
        return array(
            'fade' => array(
                'label' => 'Fade',
                'old_keyframes' => 'from { opacity: 1; transform: none; } to { opacity: 0; transform: none; }',
                'new_keyframes' => 'from { opacity: 0; transform: none; } to { opacity: 1; transform: none; }',
            ),
            'slide_up' => array(
                'label' => 'Slide up',
                'old_keyframes' => 'from { opacity: 1; transform: translateY(0); } to { opacity: 0; transform: translateY(-12px); }',
                'new_keyframes' => 'from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); }',
            ),
            'slide_right' => array(
                'label' => 'Slide from right',
                'old_keyframes' => 'from { opacity: 1; transform: translateX(0); } to { opacity: 0; transform: translateX(-16px); }',
                'new_keyframes' => 'from { opacity: 0; transform: translateX(16px); } to { opacity: 1; transform: translateX(0); }',
            ),
            'slide_left' => array(
                'label' => 'Slide from left',
                'old_keyframes' => 'from { opacity: 1; transform: translateX(0); } to { opacity: 0; transform: translateX(16px); }',
                'new_keyframes' => 'from { opacity: 0; transform: translateX(-16px); } to { opacity: 1; transform: translateX(0); }',
            ),
            'zoom' => array(
                'label' => 'Zoom',
                'old_keyframes' => 'from { opacity: 1; transform: scale(1); } to { opacity: 0; transform: scale(0.96); }',
                'new_keyframes' => 'from { opacity: 0; transform: scale(1.04); } to { opacity: 1; transform: scale(1); }',
            ),
            'flip_3d' => array(
                'label' => '3D flip',
                'old_keyframes' => 'from { opacity: 1; transform: perspective(1000px) rotateY(0deg); transform-origin: center center; } to { opacity: 0; transform: perspective(1000px) rotateY(-18deg); transform-origin: center center; }',
                'new_keyframes' => 'from { opacity: 0; transform: perspective(1000px) rotateY(18deg); transform-origin: center center; } to { opacity: 1; transform: perspective(1000px) rotateY(0deg); transform-origin: center center; }',
            ),
            'rotate' => array(
                'label' => 'Rotate',
                'old_keyframes' => 'from { opacity: 1; transform: rotate(0deg) scale(1); } to { opacity: 0; transform: rotate(-4deg) scale(0.98); }',
                'new_keyframes' => 'from { opacity: 0; transform: rotate(4deg) scale(1.02); } to { opacity: 1; transform: rotate(0deg) scale(1); }',
            ),
        );
    }

    /**
     * Shared element presets.
     *
     * @return array<string, array<string, string>>
     */
    private static function shared_element_presets() {
        return array(
            'featured_image' => array(
                'label' => 'Featured image',
                'selector' => '.wp-post-image, .featured-image img, .post-thumbnail img',
                'scope' => 'post',
            ),
            'site_logo' => array(
                'label' => 'Site logo',
                'selector' => '.custom-logo, .site-logo img',
                'scope' => 'site',
            ),
            'entry_title' => array(
                'label' => 'Entry title',
                'selector' => '.entry-title, .wp-block-post-title',
                'scope' => 'post',
            ),
            'avatar' => array(
                'label' => 'Avatar',
                'selector' => '.avatar, .wp-block-avatar img',
                'scope' => 'person',
            ),
        );
    }

    /**
     * Persistent element presets.
     *
     * @return array<string, array<string, string>>
     */
    private static function persistent_element_presets() {
        return array(
            'site_header' => array(
                'label' => 'Site header',
                'selector' => 'header, .site-header, .wp-block-template-part, .wp-block-template-part header',
            ),
            'site_footer' => array(
                'label' => 'Site footer',
                'selector' => 'footer, .site-footer, .wp-block-template-part footer',
            ),
            'primary_nav' => array(
                'label' => 'Primary navigation',
                'selector' => 'nav, .primary-navigation, .wp-block-navigation',
            ),
            'sidebar' => array(
                'label' => 'Sidebar',
                'selector' => 'aside, .sidebar, .widget-area',
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

        $settings = wp_parse_args($stored, self::default_settings());
        $settings['animation_style'] = isset($settings['animation_style']) ? $settings['animation_style'] : self::default_settings()['animation_style'];
        $settings['shared_elements'] = wp_parse_args($settings['shared_elements'], self::default_settings()['shared_elements']);
        $settings['persistent_elements'] = wp_parse_args($settings['persistent_elements'], self::default_settings()['persistent_elements']);

        return $settings;
    }

    /**
     * Register admin menu.
     */
    public function register_admin_menu() {
        add_menu_page(
            __('Animated Continuity Effects', 'animated-continuity-effects'),
            __('Continuity Effects', 'animated-continuity-effects'),
            'manage_options',
            'animated-continuity-effects',
            array($this, 'render_settings_page'),
            'dashicons-superhero',
            65
        );

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
            'motion_preset',
            __('Motion preset', 'animated-continuity-effects'),
            array($this, 'render_motion_preset_field'),
            'animated-continuity-effects',
            'ace_main_section'
        );

        add_settings_field(
            'transition_mode',
            __('Transition mode', 'animated-continuity-effects'),
            array($this, 'render_transition_mode_field'),
            'animated-continuity-effects',
            'ace_main_section'
        );

        add_settings_field(
            'animation_style',
            __('Animation style', 'animated-continuity-effects'),
            array($this, 'render_animation_style_field'),
            'animated-continuity-effects',
            'ace_main_section'
        );

        add_settings_field(
            'root_transition',
            __('Root transition', 'animated-continuity-effects'),
            array($this, 'render_toggle_field'),
            'animated-continuity-effects',
            'ace_main_section',
            array('key' => 'root_transition', 'label' => __('Enable the page-wide transition root', 'animated-continuity-effects'))
        );

        add_settings_field(
            'respect_reduced_motion',
            __('Reduced motion', 'animated-continuity-effects'),
            array($this, 'render_toggle_field'),
            'animated-continuity-effects',
            'ace_main_section',
            array('key' => 'respect_reduced_motion', 'label' => __('Skip transitions when the user prefers reduced motion', 'animated-continuity-effects'))
        );

        add_settings_field(
            'duration',
            __('Duration', 'animated-continuity-effects'),
            array($this, 'render_duration_field'),
            'animated-continuity-effects',
            'ace_main_section'
        );

        add_settings_field(
            'timing_function',
            __('Timing function', 'animated-continuity-effects'),
            array($this, 'render_easing_field'),
            'animated-continuity-effects',
            'ace_main_section'
        );

        add_settings_field(
            'shared_elements',
            __('Shared elements', 'animated-continuity-effects'),
            array($this, 'render_shared_elements_field'),
            'animated-continuity-effects',
            'ace_main_section'
        );

        add_settings_field(
            'persistent_elements',
            __('Persistent regions', 'animated-continuity-effects'),
            array($this, 'render_persistent_elements_field'),
            'animated-continuity-effects',
            'ace_main_section'
        );

        add_settings_field(
            'exclude_list',
            __('Exclusions', 'animated-continuity-effects'),
            array($this, 'render_exclusions_field'),
            'animated-continuity-effects',
            'ace_main_section'
        );

        add_settings_field(
            'advanced_selectors',
            __('Advanced selectors', 'animated-continuity-effects'),
            array($this, 'render_advanced_selectors_field'),
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

        $presets = self::motion_presets();
        $preset_key = isset($input['motion_preset']) ? sanitize_key(wp_unslash($input['motion_preset'])) : 'gentle';
        $output['motion_preset'] = isset($presets[$preset_key]) ? $preset_key : 'gentle';

        $animation_styles = self::animation_styles();
        $animation_style = isset($input['animation_style']) ? sanitize_key(wp_unslash($input['animation_style'])) : 'fade';
        $output['animation_style'] = isset($animation_styles[$animation_style]) ? $animation_style : 'fade';

        $shared_defaults = $defaults['shared_elements'];
        $shared_input = isset($input['shared_elements']) && is_array($input['shared_elements']) ? $input['shared_elements'] : array();
        $output['shared_elements'] = array();
        foreach ($shared_defaults as $key => $value) {
            $output['shared_elements'][$key] = isset($shared_input[$key]) && '1' === (string) wp_unslash($shared_input[$key]) ? '1' : '0';
        }

        $persistent_defaults = $defaults['persistent_elements'];
        $persistent_input = isset($input['persistent_elements']) && is_array($input['persistent_elements']) ? $input['persistent_elements'] : array();
        $output['persistent_elements'] = array();
        foreach ($persistent_defaults as $key => $value) {
            $output['persistent_elements'][$key] = isset($persistent_input[$key]) && '1' === (string) wp_unslash($persistent_input[$key]) ? '1' : '0';
        }

        $output['transition_mode'] = isset($input['transition_mode']) ? sanitize_key(wp_unslash($input['transition_mode'])) : 'cross-document';
        $output['transition_mode'] = in_array($output['transition_mode'], array('cross-document', 'same-document'), true) ? $output['transition_mode'] : 'cross-document';

        $output['root_transition'] = isset($input['root_transition']) && '1' === (string) wp_unslash($input['root_transition']) ? '1' : '0';
        $output['respect_reduced_motion'] = isset($input['respect_reduced_motion']) && '1' === (string) wp_unslash($input['respect_reduced_motion']) ? '1' : '0';

        $duration = isset($input['duration']) ? sanitize_text_field(wp_unslash($input['duration'])) : $defaults['duration'];
        $timing_function = isset($input['timing_function']) ? sanitize_text_field(wp_unslash($input['timing_function'])) : $defaults['timing_function'];
        $delay = isset($input['delay']) ? sanitize_text_field(wp_unslash($input['delay'])) : $defaults['delay'];
        $output['duration'] = $duration;
        $output['timing_function'] = $timing_function;
        $output['delay'] = $delay;

        $output['name_scope'] = sanitize_key($defaults['name_scope']);

        $exclude_list = isset($input['exclude_list']) ? wp_unslash($input['exclude_list']) : '';
        $exclude_items = preg_split('/[,\n]+/', (string) $exclude_list);
        $exclude_items = array_filter(array_map('trim', (array) $exclude_items));
        $exclude_items = array_values(array_unique($exclude_items));
        $output['exclude_list'] = implode(', ', array_map('sanitize_text_field', $exclude_items));

        $output['advanced_selectors'] = isset($input['advanced_selectors']) ? sanitize_textarea_field(wp_unslash($input['advanced_selectors'])) : '';

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
     * Output frontend CSS directly in the head.
     */
    public function output_frontend_inline_styles() {
        if (is_admin()) {
            return;
        }

        $settings = $this->get_settings();
        $animation_styles = self::animation_styles();
        $config = $this->build_frontend_config($settings);
        echo '<style id="ace-view-transition-inline-css">';
        echo '@view-transition { navigation: auto; }';
        echo 'html.ace-view-transitions-enabled { view-transition-name: root; --ace-view-transition-old-animation: ace-root-out-' . esc_html($config['animationStyle']) . '; --ace-view-transition-new-animation: ace-root-in-' . esc_html($config['animationStyle']) . '; }';
        echo '::view-transition { background: transparent; }';
        echo '::view-transition-group(root) { animation-duration: ' . esc_html($config['cssVars']['--ace-view-transition-duration']) . '; animation-timing-function: ' . esc_html($config['cssVars']['--ace-view-transition-timing-function']) . '; }';
        echo '::view-transition-old(root) { animation: var(--ace-view-transition-old-animation, ace-root-out-fade) ' . esc_html($config['cssVars']['--ace-view-transition-duration']) . ' ' . esc_html($config['cssVars']['--ace-view-transition-timing-function']) . ' both; }';
        echo '::view-transition-new(root) { animation: var(--ace-view-transition-new-animation, ace-root-in-fade) ' . esc_html($config['cssVars']['--ace-view-transition-duration']) . ' ' . esc_html($config['cssVars']['--ace-view-transition-timing-function']) . ' both; }';
        foreach ($animation_styles as $style_key => $style) {
            echo '@keyframes ace-root-out-' . esc_html($style_key) . ' { ' . esc_html($style['old_keyframes']) . ' }';
            echo '@keyframes ace-root-in-' . esc_html($style_key) . ' { ' . esc_html($style['new_keyframes']) . ' }';
        }
        echo '</style>';
    }

    /**
     * Output frontend bootstrap directly in the head.
     */
    public function output_frontend_inline_bootstrap() {
        if (is_admin()) {
            return;
        }

        $settings = $this->get_settings();
        $config = $this->build_frontend_config($settings);
        ?>
        <script id="ace-view-transition-inline-js">
        window.ACE_VIEW_TRANSITIONS = <?php echo wp_json_encode($config); ?>;
        (function () {
            'use strict';
            var config = window.ACE_VIEW_TRANSITIONS || {};
            var cssVars = config.cssVars || {};
            var excludeList = Array.isArray(config.excludeList) ? config.excludeList : [];
            var sameElementRules = Array.isArray(config.sameElementRules) ? config.sameElementRules : [];
            var enableRootTransition = config.enableRootTransition !== false;
            var respectReducedMotion = config.respectReducedMotion !== false;
            var supportsViewTransitions = 'startViewTransition' in document;

            function applyCssVariables() {
                var root = document.documentElement;
                Object.keys(cssVars).forEach(function (key) {
                    if (cssVars[key]) {
                        root.style.setProperty(key, String(cssVars[key]));
                    }
                });
            }

            function hasExcludedMatch(root) {
                if (!root || !excludeList.length) {
                    return false;
                }

                return excludeList.some(function (selector) {
                    try {
                        return !!root.querySelector(selector);
                    } catch (error) {
                        return false;
                    }
                });
            }

            function slugify(value) {
                return String(value)
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '')
                    .slice(0, 40);
            }

            function scopeToken(rule, index) {
                return slugify(['post', rule.scope || 'post', rule.label || index].join('-'));
            }

            function uniqueMatchName(base, index) {
                return 'ace-' + base + '-' + index;
            }

            function enableDocumentTransition() {
                if (!supportsViewTransitions || !enableRootTransition) {
                    return;
                }

                document.documentElement.style.viewTransitionName = 'root';
            }

            function applySharedElementNames() {
                var seen = new Set();

                sameElementRules.forEach(function (rule, ruleIndex) {
                    if (!rule || !rule.selector) {
                        return;
                    }

                    var elements = [];
                    try {
                        elements = Array.from(document.querySelectorAll(rule.selector));
                    } catch (error) {
                        return;
                    }

                    elements.forEach(function (element, elementIndex) {
                        var token = uniqueMatchName(scopeToken(rule, ruleIndex), elementIndex);
                        if (seen.has(token)) {
                            return;
                        }

                        seen.add(token);
                        element.style.viewTransitionName = token;
                    });
                });
            }

            function setDocumentHints() {
                document.documentElement.classList.add('ace-view-transitions-enabled');
                document.documentElement.style.setProperty('--ace-view-transition-enabled', '1');
            }

            function setupNavigationGuards() {
                window.addEventListener('pageswap', function (event) {
                    if (!event || !event.viewTransition) {
                        return;
                    }

                    if (hasExcludedMatch(document)) {
                        event.viewTransition.skipTransition();
                    }
                });

                window.addEventListener('pagereveal', function (event) {
                    if (!event || !event.viewTransition) {
                        return;
                    }

                    if (respectReducedMotion && window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                        event.viewTransition.skipTransition();
                    }
                });
            }

            function init() {
                applyCssVariables();
                setDocumentHints();

                if (!supportsViewTransitions) {
                    return;
                }

                enableDocumentTransition();
                applySharedElementNames();
                setupNavigationGuards();
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init, { once: true });
            } else {
                init();
            }
        }());
        </script>
        <?php
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
        );
        $animation_styles = self::animation_styles();
        $animation_style_key = isset($settings['animation_style']) && isset($animation_styles[$settings['animation_style']]) ? $settings['animation_style'] : 'fade';
        $css_vars['--ace-view-transition-old-animation'] = 'ace-root-out-' . $animation_style_key;
        $css_vars['--ace-view-transition-new-animation'] = 'ace-root-in-' . $animation_style_key;

        $shared_elements = self::shared_element_presets();
        $selected_shared = array();
        foreach ($settings['shared_elements'] as $key => $enabled) {
            if ('1' === (string) $enabled && isset($shared_elements[$key])) {
                $selected_shared[] = $shared_elements[$key];
            }
        }

        $persistent_elements = self::persistent_element_presets();
        $selected_persistent = array();
        foreach ($settings['persistent_elements'] as $key => $enabled) {
            if ('1' === (string) $enabled && isset($persistent_elements[$key])) {
                $selected_persistent[] = $persistent_elements[$key];
            }
        }

        $advanced_selectors = $this->parse_advanced_selectors($settings['advanced_selectors']);

        return array(
            'cssVars' => $css_vars,
            'excludeList' => $this->parse_exclude_list($settings['exclude_list']),
            'sameElementRules' => array_values(array_merge($selected_shared, $selected_persistent, $advanced_selectors)),
            'nameScope' => sanitize_key($settings['name_scope']),
            'enableRootTransition' => '1' === (string) $settings['root_transition'],
            'respectReducedMotion' => '1' === (string) $settings['respect_reduced_motion'],
            'transitionMode' => $settings['transition_mode'],
            'animationStyle' => isset($settings['animation_style']) && isset(self::animation_styles()[$settings['animation_style']]) ? $settings['animation_style'] : 'fade',
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
     * Parse advanced selectors from the textarea.
     *
     * @param string $raw Raw text.
     * @return array<int, array<string, string>>
     */
    private function parse_advanced_selectors($raw) {
        $prepared = array();
        $lines = preg_split('/\r\n|\r|\n/', (string) $raw);

        foreach ((array) $lines as $line) {
            $line = trim((string) $line);
            if ('' === $line || false === strpos($line, '|')) {
                continue;
            }

            list($label, $selector) = array_map('trim', explode('|', $line, 2));
            if ('' === $label || '' === $selector) {
                continue;
            }

            $prepared[] = array(
                'label' => sanitize_text_field($label),
                'selector' => sanitize_text_field($selector),
                'scope' => 'advanced',
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
        <div class="wrap ace-settings">
            <h1><?php echo esc_html__('Animated Continuity Effects', 'animated-continuity-effects'); ?></h1>
            <p><?php echo esc_html__('Control the view transition motion, exclusions, and shared element rules used on the frontend.', 'animated-continuity-effects'); ?></p>
            <form action="options.php" method="post">
                <?php
                settings_fields('ace_settings_group');
                do_settings_sections('animated-continuity-effects');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render a motion preset field.
     *
     * @param array<string, mixed> $args Arguments.
     */
    public function render_motion_preset_field() {
        $settings = $this->get_settings();
        $presets = self::motion_presets();
        ?>
        <select name="<?php echo esc_attr(ACE_OPTION_NAME . '[motion_preset]'); ?>">
            <?php foreach ($presets as $key => $preset) : ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['motion_preset'], $key); ?>><?php echo esc_html($preset['label']); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Render a transition mode field.
     */
    public function render_transition_mode_field() {
        $settings = $this->get_settings();
        ?>
        <select name="<?php echo esc_attr(ACE_OPTION_NAME . '[transition_mode]'); ?>">
            <option value="cross-document" <?php selected($settings['transition_mode'], 'cross-document'); ?>><?php echo esc_html__('Cross-document', 'animated-continuity-effects'); ?></option>
            <option value="same-document" <?php selected($settings['transition_mode'], 'same-document'); ?>><?php echo esc_html__('Same-document', 'animated-continuity-effects'); ?></option>
        </select>
        <p class="description"><?php echo esc_html__('Cross-document is the browser navigation path; same-document is useful for intra-page UI transitions if you later add them.', 'animated-continuity-effects'); ?></p>
        <?php
    }

    /**
     * Render animation style field.
     */
    public function render_animation_style_field() {
        $settings = $this->get_settings();
        $styles = self::animation_styles();
        ?>
        <select name="<?php echo esc_attr(ACE_OPTION_NAME . '[animation_style]'); ?>">
            <?php foreach ($styles as $key => $style) : ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected(isset($settings['animation_style']) ? $settings['animation_style'] : 'fade', $key); ?>><?php echo esc_html($style['label']); ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php echo esc_html__('Choose how the page swap enters and exits: subtle fade, slide, zoom, rotate, or a 3D flip.', 'animated-continuity-effects'); ?></p>
        <?php
    }

    /**
     * Render a toggle field.
     *
     * @param array<string, mixed> $args Arguments.
     */
    public function render_toggle_field($args) {
        $settings = $this->get_settings();
        $key = isset($args['key']) ? $args['key'] : '';
        $label = isset($args['label']) ? $args['label'] : '';
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr(ACE_OPTION_NAME . '[' . $key . ']'); ?>" value="1" <?php checked(isset($settings[$key]) ? $settings[$key] : '0', '1'); ?>>
            <?php echo esc_html($label); ?>
        </label>
        <?php
    }

    /**
     * Render the duration field.
     */
    public function render_duration_field() {
        $settings = $this->get_settings();
        ?>
        <select name="<?php echo esc_attr(ACE_OPTION_NAME . '[duration]'); ?>">
            <?php foreach (array('150ms', '250ms', '350ms', '500ms', '700ms', '900ms') as $value) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['duration'], $value); ?>><?php echo esc_html($value); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Render the easing field.
     */
    public function render_easing_field() {
        $settings = $this->get_settings();
        $options = array(
            'ease' => 'Ease',
            'ease-out' => 'Ease out',
            'ease-in-out' => 'Ease in out',
            'cubic-bezier(0.2, 0, 0, 1)' => 'Smooth',
            'cubic-bezier(0.16, 1, 0.3, 1)' => 'Snappy',
            'linear' => 'Linear',
        );
        ?>
        <select name="<?php echo esc_attr(ACE_OPTION_NAME . '[timing_function]'); ?>">
            <?php foreach ($options as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['timing_function'], $value); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Render shared element checkboxes.
     */
    public function render_shared_elements_field() {
        $settings = $this->get_settings();
        $presets = self::shared_element_presets();
        ?>
        <div class="ace-grid">
            <?php foreach ($presets as $key => $preset) : ?>
                <label class="ace-card">
                    <strong><?php echo esc_html($preset['label']); ?></strong>
                    <span><?php echo esc_html($preset['selector']); ?></span>
                    <input type="checkbox" name="<?php echo esc_attr(ACE_OPTION_NAME . '[shared_elements][' . $key . ']'); ?>" value="1" <?php checked(isset($settings['shared_elements'][$key]) ? $settings['shared_elements'][$key] : '0', '1'); ?>>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Render persistent region checkboxes.
     */
    public function render_persistent_elements_field() {
        $settings = $this->get_settings();
        $presets = self::persistent_element_presets();
        ?>
        <div class="ace-grid">
            <?php foreach ($presets as $key => $preset) : ?>
                <label class="ace-card">
                    <strong><?php echo esc_html($preset['label']); ?></strong>
                    <span><?php echo esc_html($preset['selector']); ?></span>
                    <input type="checkbox" name="<?php echo esc_attr(ACE_OPTION_NAME . '[persistent_elements][' . $key . ']'); ?>" value="1" <?php checked(isset($settings['persistent_elements'][$key]) ? $settings['persistent_elements'][$key] : '0', '1'); ?>>
                </label>
            <?php endforeach; ?>
        </div>
        <p class="description"><?php echo esc_html__('Use these for persistent page chrome like header, footer, navigation, or sidebar regions. They are given stable transition names so they remain visually consistent instead of being part of the root page swap.', 'animated-continuity-effects'); ?></p>
        <?php
    }

    /**
     * Render exclusion field.
     */
    public function render_exclusions_field() {
        $settings = $this->get_settings();
        ?>
        <textarea class="large-text" rows="4" name="<?php echo esc_attr(ACE_OPTION_NAME . '[exclude_list]'); ?>"><?php echo esc_textarea($settings['exclude_list']); ?></textarea>
        <p class="description"><?php echo esc_html__('Comma-separated classes or IDs, for example: .no-transition, #cart, .skip-view-transition', 'animated-continuity-effects'); ?></p>
        <?php
    }

    /**
     * Render advanced selectors.
     */
    public function render_advanced_selectors_field() {
        $settings = $this->get_settings();
        ?>
        <textarea class="large-text" rows="4" name="<?php echo esc_attr(ACE_OPTION_NAME . '[advanced_selectors]'); ?>"><?php echo esc_textarea($settings['advanced_selectors']); ?></textarea>
        <p class="description"><?php echo esc_html__('Optional. One selector per line in the form: Label | selector. This is for custom shared elements beyond the presets.', 'animated-continuity-effects'); ?></p>
        <?php
    }
}
