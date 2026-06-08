<?php

if (!defined('ABSPATH')) {
    exit;
}

class ACE_Plugin {
    /** @var ACE_Plugin|null */
    private static $instance = null;

    /** @var string */
    private $hook_suffix = '';

    /** @var array<string, bool> */
    private $server_rendered_transition_names = array();

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
        add_filter('render_block', array($this, 'apply_server_rendered_transition_names'), 20, 2);
        add_filter('post_thumbnail_html', array($this, 'apply_post_thumbnail_transition_name'), 20, 5);
        add_action('update_option_' . ACE_OPTION_NAME, array($this, 'flush_cache_after_settings_update'), 10, 2);
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
            'intercept_internal_links' => '0',
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
                'selector' => 'header, header.site-header, header.wp-block-template-part',
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
     * Check if a shared element preset is enabled.
     *
     * @param string $key Preset key.
     * @return bool
     */
    private function is_shared_element_enabled($key) {
        $settings = $this->get_settings();
        return isset($settings['shared_elements'][$key]) && '1' === (string) $settings['shared_elements'][$key];
    }

    /**
     * Check if a persistent element preset is enabled.
     *
     * @param string $key Preset key.
     * @return bool
     */
    private function is_persistent_element_enabled($key) {
        $settings = $this->get_settings();
        return isset($settings['persistent_elements'][$key]) && '1' === (string) $settings['persistent_elements'][$key];
    }

    /**
     * Reserve a server-rendered transition name for this response.
     *
     * Native view transitions require names to be unique per document. If the
     * same block is rendered in multiple places, only the first instance wins.
     *
     * @param string $name Transition name.
     * @return bool
     */
    private function reserve_server_transition_name($name) {
        $name = sanitize_html_class($name);
        if ('' === $name || isset($this->server_rendered_transition_names[$name])) {
            return false;
        }

        $this->server_rendered_transition_names[$name] = true;
        return true;
    }

    /**
     * Append a view-transition-name style to the first matching HTML tag.
     *
     * @param string $html HTML.
     * @param string $tag Tag name.
     * @param string $name Transition name.
     * @param bool $unique Whether this name must only appear once in the document.
     * @return string
     */
    private function add_view_transition_name_to_first_tag($html, $tag, $name, $unique = true) {
        if ('' === $html || '' === $tag || '' === $name) {
            return $html;
        }

        $name = sanitize_html_class($name);
        if ('' === $name) {
            return $html;
        }

        if (class_exists('WP_HTML_Tag_Processor')) {
            $processor = new WP_HTML_Tag_Processor($html);
            if ($processor->next_tag(array('tag_name' => strtoupper($tag)))) {
                if ($unique && !$this->reserve_server_transition_name($name)) {
                    return $html;
                }

                $style = (string) $processor->get_attribute('style');
                $style = rtrim(trim($style), ';');
                $style = '' === $style ? 'view-transition-name: ' . $name . ';' : $style . '; view-transition-name: ' . $name . ';';
                $processor->set_attribute('style', $style);
                return $processor->get_updated_html();
            }
        }

        return preg_replace_callback(
            '/<' . preg_quote($tag, '/') . '\b([^>]*)>/i',
            function ($matches) use ($tag, $name, $unique) {
                if ($unique && !$this->reserve_server_transition_name($name)) {
                    return $matches[0];
                }

                $attributes = $matches[1];
                if (preg_match('/\sstyle=(["\'])(.*?)\1/i', $attributes, $style_match)) {
                    $new_style = rtrim(trim($style_match[2]), ';') . '; view-transition-name: ' . $name . ';';
                    $attributes = str_replace($style_match[0], ' style=' . $style_match[1] . esc_attr($new_style) . $style_match[1], $attributes);
                    return '<' . $tag . $attributes . '>';
                }

                return '<' . $tag . $attributes . ' style="' . esc_attr('view-transition-name: ' . $name . ';') . '">';
            },
            $html,
            1
        );
    }

    /**
     * Add transition names to post thumbnail HTML rendered outside block callbacks.
     *
     * @param string $html Post thumbnail HTML.
     * @param int $post_id Post ID.
     * @param int $post_thumbnail_id Thumbnail attachment ID.
     * @param string|array<int, int|string> $size Image size.
     * @param string|array<string, mixed> $attr Image attributes.
     * @return string
     */
    public function apply_post_thumbnail_transition_name($html, $post_id, $post_thumbnail_id, $size, $attr) {
        if (is_admin() || !$post_id || !$this->is_shared_element_enabled('featured_image')) {
            return $html;
        }

        return $this->add_view_transition_name_to_first_tag($html, 'img', 'ace-featured-image-' . (int) $post_id, false);
    }

    /**
     * Add transition names to post title anchors rendered by latest-post style blocks.
     *
     * @param string $html Block HTML.
     * @return string
     */
    private function add_transition_names_to_title_links($html) {
        if ('' === $html || !$this->is_shared_element_enabled('entry_title')) {
            return $html;
        }

        $allow_all_links = false !== strpos($html, 'wp-block-post-title');

        return preg_replace_callback(
            '/<a\b([^>]*)>/i',
            function ($matches) use ($allow_all_links) {
                $attributes = $matches[1];
                $is_title_link = $allow_all_links || preg_match('/\bclass=(["\'])(?=[^"\']*wp-block-latest-posts__post-title)[^"\']*\1/i', $attributes);
                if (!$is_title_link) {
                    return $matches[0];
                }

                if (!preg_match('/\shref=(["\'])(.*?)\1/i', $attributes, $href_match)) {
                    return $matches[0];
                }

                $post_id = url_to_postid(html_entity_decode($href_match[2]));
                if (!$post_id) {
                    return $matches[0];
                }

                $transition_name = 'ace-entry-title-' . (int) $post_id;
                if (!$this->reserve_server_transition_name($transition_name)) {
                    return $matches[0];
                }

                if (preg_match('/\sstyle=(["\'])(.*?)\1/i', $attributes, $style_match)) {
                    $new_style = rtrim(trim($style_match[2]), ';') . '; view-transition-name: ' . $transition_name . ';';
                    $attributes = str_replace($style_match[0], ' style=' . $style_match[1] . esc_attr($new_style) . $style_match[1], $attributes);
                    return '<a' . $attributes . '>';
                }

                return '<a' . $attributes . ' style="' . esc_attr('view-transition-name: ' . $transition_name . ';') . '">';
            },
            $html
        );
    }

    /**
     * Add server-rendered transition names before native cross-document capture.
     *
     * @param string $block_content Rendered block HTML.
     * @param array<string, mixed> $block Parsed block.
     * @return string
     */
    public function apply_server_rendered_transition_names($block_content, $block) {
        if (is_admin() || '' === $block_content || !is_array($block) || empty($block['blockName'])) {
            return $block_content;
        }

        $block_name = (string) $block['blockName'];
        $post_id = get_the_ID();

        if ('core/post-featured-image' === $block_name && $this->is_shared_element_enabled('featured_image') && $post_id) {
            return $this->add_view_transition_name_to_first_tag($block_content, 'img', 'ace-featured-image-' . (int) $post_id, false);
        }

        if ('core/post-title' === $block_name && $this->is_shared_element_enabled('entry_title') && $post_id) {
            $transition_name = 'ace-entry-title-' . (int) $post_id;
            $updated = $this->add_view_transition_name_to_first_tag($block_content, 'a', $transition_name);
            if ($updated !== $block_content) {
                return $updated;
            }

            $updated = $this->add_view_transition_name_to_first_tag($block_content, 'h1', $transition_name);
            if ($updated !== $block_content) {
                return $updated;
            }

            $updated = $this->add_view_transition_name_to_first_tag($block_content, 'h2', $transition_name);
            return $updated !== $block_content ? $updated : $this->add_view_transition_name_to_first_tag($block_content, 'h3', $transition_name);
        }

        if ('core/site-logo' === $block_name && $this->is_shared_element_enabled('site_logo')) {
            return $this->add_view_transition_name_to_first_tag($block_content, 'img', 'ace-site-logo');
        }

        if ('core/navigation' === $block_name && $this->is_persistent_element_enabled('primary_nav')) {
            return $this->add_view_transition_name_to_first_tag($block_content, 'nav', 'ace-primary-nav');
        }

        if ('core/template-part' === $block_name) {
            if ($this->is_persistent_element_enabled('site_header')) {
                $block_content = $this->add_view_transition_name_to_first_tag($block_content, 'header', 'ace-site-header');
            }

            if ($this->is_persistent_element_enabled('site_footer')) {
                $block_content = $this->add_view_transition_name_to_first_tag($block_content, 'footer', 'ace-site-footer');
            }
        }

        return $this->add_transition_names_to_title_links($block_content);
    }

    /**
     * Clear page caches when settings change.
     *
     * @param mixed $old_value Old option value.
     * @param mixed $value New option value.
     */
    public function flush_cache_after_settings_update($old_value, $value) {
        if ($old_value === $value) {
            return;
        }

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        try {
            if (function_exists('ace_redis_cache')) {
                $redis_cache = ace_redis_cache();
                if ($redis_cache && method_exists($redis_cache, 'get_cache_manager')) {
                    $cache_manager = $redis_cache->get_cache_manager();
                    if ($cache_manager && method_exists($cache_manager, 'clear_all_cache')) {
                        $cache_manager->clear_all_cache();
                    }
                }
            }
        } catch (Throwable $exception) {
            error_log('ACE: Failed to clear Ace Redis cache after settings update: ' . $exception->getMessage());
        }
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
            'intercept_internal_links',
            __('Intercept internal links', 'animated-continuity-effects'),
            array($this, 'render_toggle_field'),
            'animated-continuity-effects',
            'ace_main_section',
            array('key' => 'intercept_internal_links', 'label' => __('Allow the experimental same-document router to intercept normal internal links', 'animated-continuity-effects'))
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
        $output['intercept_internal_links'] = isset($input['intercept_internal_links']) && '1' === (string) wp_unslash($input['intercept_internal_links']) ? '1' : '0';

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
        $frontend_css = ACE_PLUGIN_DIR . 'assets/css/frontend.css';
        $frontend_js = ACE_PLUGIN_DIR . 'assets/js/frontend.js';

        wp_enqueue_style(
            'ace-view-transitions-frontend',
            ACE_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            file_exists($frontend_css) ? (string) filemtime($frontend_css) : '0.1.0'
        );
        wp_register_script(
            'ace-view-transitions-frontend',
            ACE_PLUGIN_URL . 'assets/js/frontend.js',
            array(),
            file_exists($frontend_js) ? (string) filemtime($frontend_js) : '0.1.0',
            true
        );
        wp_enqueue_script('ace-view-transitions-frontend');
        wp_add_inline_script(
            'ace-view-transitions-frontend',
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
        $config = $this->build_frontend_config($settings);
        echo '<style id="ace-view-transition-inline-css">';
        echo '@view-transition { navigation: auto; }';
        echo 'html.ace-view-transitions-enabled { view-transition-name: root; }';
        echo '::view-transition { background: transparent; }';
        echo '::view-transition-group(root) { animation-duration: ' . esc_html($config['cssVars']['--ace-view-transition-duration']) . '; animation-timing-function: ' . esc_html($config['cssVars']['--ace-view-transition-timing-function']) . '; }';
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
        $router = !empty($config['sameDocumentRouterEnabled']) ? 'same-document' : 'native';
        echo "\n<!-- ACE transition mode: " . esc_html($config['transitionMode']) . '; router: ' . esc_html($router) . " -->\n";
    }

    /**
     * Build frontend config.
     *
     * @param array<string, mixed> $settings Settings.
     * @return array<string, mixed>
     */
    private function build_frontend_config(array $settings) {
        if (defined('ACE_FORCE_CROSS_DOCUMENT') && ACE_FORCE_CROSS_DOCUMENT) {
            $settings['transition_mode'] = 'cross-document';
        }

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
            'activeClickPairing' => false,
            'activeFeaturedImagePairing' => true,
            'debugDisableSharedElements' => false,
            'interceptInternalLinks' => '1' === (string) $settings['intercept_internal_links'],
            'sameDocumentRouterEnabled' => 'same-document' === $settings['transition_mode'] && '1' === (string) $settings['intercept_internal_links'],
            'debug' => current_user_can('manage_options'),
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
            <div class="notice notice-info inline">
                <p><strong><?php echo esc_html__('Current saved transition mode:', 'animated-continuity-effects'); ?></strong> <?php echo esc_html($settings['transition_mode']); ?></p>
            </div>
            <p class="description"><?php echo esc_html__('Changing defaults in code does not change existing saved settings. Save this page to update the live mode.', 'animated-continuity-effects'); ?></p>
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
            <option value="cross-document" <?php selected($settings['transition_mode'], 'cross-document'); ?>><?php echo esc_html__('Native browser navigation, recommended', 'animated-continuity-effects'); ?></option>
            <option value="same-document" <?php selected($settings['transition_mode'], 'same-document'); ?>><?php echo esc_html__('AJAX shell swap, experimental', 'animated-continuity-effects'); ?></option>
        </select>
        <p class="description"><?php echo esc_html__('Cross-document uses the browser navigation path and is the recommended mode for Chrome native View Transitions.', 'animated-continuity-effects'); ?></p>
        <p class="description"><?php echo esc_html__('Same-document mode replaces part of the page using fetch() and may not match native browser history animations on complex WordPress themes.', 'animated-continuity-effects'); ?></p>
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
