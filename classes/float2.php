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
 * Float2 type form element
 *
 * Contains HTML class for a text type element
 *
 * @package   enrol_payment
 * @copyright 2018 Seth Yoder <seth.a.yoder@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once("HTML/QuickForm/text.php");
require_once($CFG->libdir . "/form/templatable_form_element.php");

/**
 * Text type form element
 *
 * HTML class for a text type element
 *
 * @package   core_form
 * @category  form
 * @copyright 2006 Jamie Pratt <me@jamiep.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class MoodleQuickForm_float2 extends HTML_QuickForm_text implements templatable {
    use templatable_form_element;

    public $_helpbutton = '';
    public $_hiddenlabel = false;
    protected $forceltr = false;

    /**
     * Constructor.
     *
     * @param string $elementname (optional) name of the text field
     * @param string $elementlabel (optional) text field label
     * @param string $attributes (optional) Either a typical HTML attribute string or an associative array
     */
    public function __construct($elementname = null, $elementlabel = null, $attributes = null) {
        parent::__construct($elementname, $elementlabel, $attributes);
    }

    /**
     * Sets label to be hidden
     *
     * @param bool $hiddenLabel sets if label should be hidden
     */
    public function setHiddenLabel($hiddenLabel) {
        $this->_hiddenlabel = $hiddenLabel;
    }

    /**
     * Coerce value to two-decimal-place float.
     */
    public function setValue($value) {
        $this->updateAttributes(array("value" => number_format(floatval($value), 2)));
    }

    /**
     * Freeze the element so that only its value is returned and set persistantfreeze to false
     *
     * @since     Moodle 2.4
     * @access    public
     * @return    void
     */
    public function freeze() {
        $this->_flagFrozen = true;
        // No hidden element is needed refer MDL-30845.
        $this->setPersistantFreeze(false);
    }

    /**
     * Returns the html to be used when the element is frozen
     *
     * @since     Moodle 2.4
     * @return    string Frozen html
     */
    public function getFrozenHtml() {
        $attributes = array('readonly' => 'readonly');
        $this->updateAttributes($attributes);
        return $this->_getTabs() . '<input' . $this->_getAttrString($this->_attributes) . ' />' . $this->_getPersistantData();
    }

    /**
     * Returns HTML for this form element.
     *
     * @return string
     */
    public function toHtml() {

        // Add the class at the last minute.
        if ($this->get_force_ltr()) {
            if (!isset($this->_attributes['class'])) {
                $this->_attributes['class'] = 'text-ltr';
            } else {
                $this->_attributes['class'] .= ' text-ltr';
            }
        }

        $this->_generateId();
        if ($this->_flagFrozen) {
            return $this->getFrozenHtml();
        }
        $html = $this->_getTabs() . '<input' . $this->_getAttrString($this->_attributes) . ' />';

        if ($this->_hiddenlabel) {
            return '<label class="accesshide" for="'.$this->getAttribute('id').'" >'.
                        $this->getLabel() . '</label>' . $html;
        } else {
             return $html;
        }
    }

    /**
     * get html for help button
     *
     * @return string html for help button
     */
    public function getHelpButton() {
        return $this->_helpbutton;
    }

    /**
     * Get force LTR option.
     *
     * @return bool
     */
    public function get_force_ltr() {
        return $this->forceltr;
    }

    /**
     * Force the field to flow left-to-right.
     *
     * This is useful for fields such as URLs, passwords, settings, etc...
     *
     * @param bool $value The value to set the option to.
     */
    public function set_force_ltr($value) {
        $this->forceltr = (bool) $value;
    }
}
