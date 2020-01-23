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

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/externallib.php');
use enrol_payment\helper;

/**
 * Payment external file
 *
 * @package    enrol_payment
 * @copyright  2020 Andrés Ramos, LMS Doctor
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_payment_external extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function check_discount_parameters() {
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
     * @param  int $enrolid
     * @param  string $prepaytoken
     * @param  string $discountcode
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
     * Returns description of method result value.
     *
     * @return external_description
     */
    public static function check_discount_returns() {
        return new external_value(PARAM_NOTAGS, 'Returns an array with the calculated costs');
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function multiple_enrollment_parameters() {
        return new external_function_parameters(
            [
                'enrolid' => new external_value(PARAM_INT, 'Enrol id from the enrol table'),
                'prepaytoken' => new external_value(PARAM_NOTAGS, 'The prepay token'),
                'emails' => new external_value(PARAM_NOTAGS, 'Emails'),
                'ipnid' => new external_value(PARAM_RAW, 'IPN ID'),
                'symbol' => new external_value(PARAM_NOTAGS, 'Symbol'),
            ]
        );
    }

    /**
     * Validate multiple enrollments.
     *
     * @param  int $enrolid
     * @param  string $prepaytoken
     * @param  string $emails
     * @param  int $ipnid
     * @param  string $symbol
     * @return string
     */
    public static function multiple_enrollment($enrolid, $prepaytoken, $emails, $ipnid, $symbol) {
        global $DB, $CFG;

        // Parameters validation.
        $params = self::validate_parameters(self::multiple_enrollment_parameters(),
            [
                'enrolid' => $enrolid,
                'prepaytoken' => $prepaytoken,
                'emails' => $emails,
                'ipnid' => $ipnid,
                'symbol' => $symbol,
            ]
        );

        $ret = ['success' => true];
        $emaillist = json_decode(stripslashes($params['emails']));

        if ($CFG->allowaccountssameemail) {
            $ret['success']     = false;
            $ret['failreason']  = 'allowaccountssameemail';
            $ret['failmessage'] = get_string('sameemailaccountsallowed', 'enrol_payment');
        } else if (count($emaillist) != count(array_unique($emaillist))) {
            $ret['success']     = false;
            $ret['failreason']  = 'duplicateemail';
            $ret['failmessage'] = get_string('duplicateemail', 'enrol_payment');
        } else {

            if (!$ret['success']) {
                return json_encode($ret);
            }

            $ret['users'] = helper::get_moodle_users_by_emails($emaillist);

            // TODO: Improve response or message to identify what emails were not found.
            // Validate if the users were found.
            if (empty($ret['users'])) {
                $ret['success'] = false;
                $ret['failmessage'] = get_string("usersnotfoundwithemail", "enrol_payment");
                return json_encode($ret);
            }

            $payment = helper::get_payment_from_token($params['prepaytoken']);
            helper::update_payment_data(true, $ret['users'], $payment);
            $instance = $DB->get_record('enrol', ['id' => $params['enrolid']]);

            // Tack new subtotals onto return data.
            $ret = array_merge($ret, helper::calculate_cost($instance, $payment, true));

            if ($payment->tax_percent) {
                $taxamount = $ret['tax_amount'];
                $taxpercent = floor(100 * floatval($payment->tax_percent));
                $taxstring = ' + ' . $symbol . $taxamount . " (${taxpercent}% tax)";
            } else {
                $taxstring = "";
            }

            $ret['successmessage'] = get_string('multipleregistrationconfirmuserlist', 'enrol_payment')
                . implode('<li>', array_map('pretty_print_user', $ret['users']))
                . '</ul>'
                . get_string('totalcost', 'enrol_payment')
                . $symbol . $ret['oc_discounted'] . ' × ' . $payment->units
                . $taxstring . ' = <b>' . $symbol . $ret['subtotal_taxed']
                . '</b> ' . $instance->currency;

        }

        return json_encode($ret);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_description
     */
    public static function multiple_enrollment_returns() {
        return new external_value(PARAM_RAW, 'Returns an array with the multiple enrollment information');
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function single_enrollment_parameters() {
        return new external_function_parameters(
            [
                'enrolid' => new external_value(PARAM_INT, 'Enrol id from the enrol table'),
                'prepaytoken' => new external_value(PARAM_NOTAGS, 'The prepay token'),
            ]
        );
    }

    /**
     * Validate single enrollments.
     *
     * @param  int $enrolid
     * @param  string $prepaytoken
     * @return string
     */
    public static function single_enrollment($enrolid, $prepaytoken) {
        global $DB;

        // Parameters validation.
        $params = self::validate_parameters(self::single_enrollment_parameters(),
            [
                'enrolid' => $enrolid,
                'prepaytoken' => $prepaytoken,
            ]
        );

        try {
            $instance = $DB->get_record('enrol', ['id' => $params['enrolid']]);
            $payment = helper::get_payment_from_token($params['prepaytoken']);
            update_payment_data(false, null, $payment);
            $ret = helper::calculate_cost($instance, $payment, true);
            $ret['success'] = true;
        } catch (Exception $e) {
            $ret = ['success' => false];
            $ret['failmessage'] = 'Payment UUID ' . $params['prepaytoken'] . ' not found in database.';
        }

        return json_encode($ret);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_description
     */
    public static function single_enrollment_returns() {
        return new external_value(PARAM_RAW, 'Returns an array with the payment enrollment information');
    }

}