<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Payment enrolment plugin utility class.
 *
 * @package    enrol_payment
 * @copyright  2018 Seth Yoder <seth.a.yoder@gmail.com> - based on enrol_paypal code by Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_payment;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/editorlib.php');
require_once($CFG->libdir . '/adminlib.php');

/**
 * General text area with html editor. No default info displayed.
 */
class admin_setting_confightmleditor_nodefaultinfo extends \admin_setting_configtextarea {
    private $cols;
    private $rows;

    /**
     * @param string $name
     * @param string $visiblename
     * @param string $description
     * @param mixed $defaultsetting string or array
     * @param mixed $paramtype
     */
    public function __construct($name, $visiblename, $description, $defaultsetting, $paramtype=PARAM_RAW, $cols='60', $rows='8') {
        parent::__construct($name, $visiblename, $description, $defaultsetting, $paramtype, $cols, $rows);
        $this->cols = $cols;
        $this->rows = $rows;
        $this->set_force_ltr(false);
        editors_head_setup();
    }

    /**
     * Returns an XHTML string for the editor
     *
     * @param string $data
     * @param string $query
     * @return string XHTML string for the editor
     */
    public function output_html($data, $query = '') {
        global $OUTPUT;
        $editor = editors_get_preferred_editor(FORMAT_HTML);
        $editor->set_text($data);
        $editor->use_editor($this->get_id(), array('noclean' => true));

        $default = $this->get_defaultsetting();
        $defaultinfo = '';
        if (!is_null($default) and $default !== '') {
            $defaultinfo = "\n".$default;
        }

        $context = (object) [
            'cols' => $this->cols,
            'rows' => $this->rows,
            'id' => $this->get_id(),
            'name' => $this->get_full_name(),
            'value' => $data,
            'forceltr' => $this->get_force_ltr(),
        ];
        $element = $OUTPUT->render_from_template('core_admin/setting_configtextarea', $context);

        return format_admin_setting_nodefaultinfo($this, $this->visiblename,
            $element, $this->description, true, '', $defaultinfo, $query);
    }
}

/**
 * Format admin setting with no default info.
 *
 * @param  strClass  $setting
 * @param  string  $title
 * @param  string  $form
 * @param  string  $description
 * @param  boolean $label
 * @param  string  $warning
 * @param  string  $defaultinfo
 * @param  string  $query
 *
 * @return string
 */
function format_admin_setting_nodefaultinfo($setting, $title='', $form='', $description='',
        $label=true, $warning='', $defaultinfo=null, $query='') {
    global $CFG, $OUTPUT;

    $context = (object) [
        'name' => empty($setting->plugin) ? $setting->name : "$setting->plugin | $setting->name",
        'fullname' => $setting->get_full_name(),
    ];

    // Sometimes the id is not id_s_name, but id_s_name_m or something, and this does not validate.
    if ($label === true) {
        $context->labelfor = $setting->get_id();
    } else if ($label === false) {
        $context->labelfor = '';
    } else {
        $context->labelfor = $label;
    }

    $form .= $setting->output_setting_flags();

    $context->warning = $warning;
    $context->override = '';
    if (empty($setting->plugin)) {
        if (array_key_exists($setting->name, $CFG->config_php_settings)) {
            $context->override = get_string('configoverride', 'admin');
        }
    } else {
        if (array_key_exists($setting->plugin, $CFG->forced_plugin_settings) &&
            array_key_exists($setting->name, $CFG->forced_plugin_settings[$setting->plugin])) {
            $context->override = get_string('configoverride', 'admin');
        }
    }

    $defaults = array();
    if (!is_null($defaultinfo)) {
        if ($defaultinfo === '') {
            $defaultinfo = get_string('emptysettingvalue', 'admin');
        }
        $defaults[] = $defaultinfo;
    }

    $context->default = null;
    $setting->get_setting_flag_defaults($defaults);

    $context->error = '';
    $adminroot = admin_get_root();
    if (array_key_exists($context->fullname, $adminroot->errors)) {
        $context->error = $adminroot->errors[$context->fullname]->error;
    }

    $context->id = 'admin-' . $setting->name;
    $context->title = highlightfast($query, $title);
    $context->name = highlightfast($query, $context->name);
    $context->description = highlight($query, markdown_to_html($description));
    $context->element = $form;
    $context->forceltr = $setting->get_force_ltr();

    return $OUTPUT->render_from_template('core_admin/setting', $context);
}
