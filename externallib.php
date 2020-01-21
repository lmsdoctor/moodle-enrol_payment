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
 * Payment external file
 *
 * @package    enrol_payment
 * @copyright  2020 Andrés Ramos, LMS Doctor
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->libdir . "/externallib.php");
use enrol_payment\helper;

class enrol_payment_external extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function check_discount_parameters() {
        // The external_function_parameters constructor expects an array of external_description.
        return new external_function_parameters(
            [
                'enrolid' => new external_value(PARAM_INT, 'Enrol id from the enrol table'),
                'prepaytoken' => new external_value(PARAM_NOTAGS, 'The prepay token'),
                'discountcode' => new external_value(PARAM_NOTAGS, 'The discount code'),
            ]
        );
    }

    /**
     * Check discount.
     *
     * @param  int $enrolid      [description]
     * @param  string $prepaytoken  [description]
     * @param  string $discountcode [description]
     * @return array
     */
    public static function check_discount($enrolid, $prepaytoken, $discountcode) {
        global $DB;

        // Parameters validation.
        $params = self::validate_parameters(self::check_discount_parameters(),
            [
                'enrolid' => $enrolid,
                'prepaytoken' => $prepaytoken,
                'discountcode' => $discountcode,
            ]
        );

        // NEEDS IMPROVEMENT AS A STUDENT SHOULD HAVE ACCESS TO THIS CONTEXT.
        // Validate the context to make sure the user can do it.
        // $context = context_system::instance();
        // self::validate_context($context);

        // Get enrollment record from the table.
        $enrol = $DB->get_record('enrol', ['id' => $enrolid]);

        if (trim($params['discountcode']) == trim($enrol->customtext2)) {
            $payment = helper::get_payment_from_token($params['prepaytoken']);
            $payment->code_given = true;
            $DB->update_record('enrol_payment_session', $payment);
            $returnedvalue = helper::calculate_cost($enrol, $payment);
        } else {
            $returnedvalue = [
                'error' => true,
                'errormsg' => get_string('incorrectdiscountcode_desc', 'enrol_payment'),
            ];
        }

        return json_encode($returnedvalue);
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function check_discount_returns() {
        return new external_value(PARAM_NOTAGS, 'Returns an array with the calculated costs');
    }



}