<?php
    /**
     * @package biblio
     */

    namespace Inc\covers\Api\Callbacks;
    use Inc\Covers\Base\BaseController;

    class AdminCallbacks extends BaseController {

    public function adminDashboard() {
        return require_once("{$this->plugin_templates_path}/adminCoversDashboard.php");
    }

    public function adminDilveLogs() {
        return require_once("{$this->plugin_templates_path}/adminDilveLogs.php");
    }

    public function adminDilveLines() {
        return require_once("{$this->plugin_templates_path}/adminDilveLines.php");
    }

    public function adminDilveLogger() {
        return require_once("{$this->plugin_templates_path}/adminDilveLogger.php");
    }

    public function textSanitize( $input ) {
        $output = get_option('covers_settings');
        $output['dilve_user'] = sanitize_text_field( $input['dilve_user'] );
        $output['dilve_pass'] = sanitize_text_field( $input['dilve_pass'] );
        $output['cegal_user'] = sanitize_text_field( $input['cegal_user'] );
        $output['cegal_pass'] = sanitize_text_field( $input['cegal_pass'] );
        return $output;
    }
    public function adminSectionManager() {
        echo 'manage the Sections and Features of this plugin by activating the checkboxes in the list below';
      }
    public function textField( $args ){
        //return the input
        $name = $args['label_for'];
        $option_name = $args['option_name'];
        $options = get_option($option_name);
        $value = isset($options[$name]) ? $options[$name] : '';
        echo '<input
            type="text"
            class="'.$args['class'].'"
            id="'.$name.'"
            name="' . $option_name . '[' . $name . ']"
            value="' . esc_attr($value) . '"
            placeholder="Add a value">';
    }
}