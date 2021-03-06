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
            $payment->codegiven = true;
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

            $ret['users'] = helper::get_moodle_users_by_emails($emaillist);

            // If some users were not found in the db, return an error.
            if (!empty($ret['users']['notfound'])) {
                $notfoundlist = $ret['users']['notfound'];
                $stremails = implode(',', $notfoundlist);
                $ret['success'] = false;
                $ret['failmessage'] = get_string('usersnotfoundwithemail', 'enrol_payment', $stremails);
                return json_encode($ret);
            }

            $ret['users'] = $ret['users']['found'];

            $payment = helper::get_payment_from_token($params['prepaytoken']);
            helper::update_payment_data(true, $ret['users'], $payment);
            $instance = $DB->get_record('enrol', ['id' => $params['enrolid']]);

            // Set the discount threshold to validate when showing the discount text.
            $threshold = $instance->customint8;

            // Tack new subtotals onto return data.
            $ret = array_merge($ret, helper::calculate_cost($instance, $payment, true));

            if ($payment->taxpercent) {
                $taxamount = $ret['taxamount'];
                $taxpercent = floor(100 * floatval($payment->taxpercent));
                $taxstring = ' + ' . $symbol . $taxamount . " (${taxpercent}% tax)";
            } else {
                $taxstring = "";
            }

            $objcosts = helper::get_object_of_costs($ret, $symbol, $instance->currency, $payment->units, $taxstring);
            $stremails = array_map('\enrol_payment\helper::pretty_print_user', $ret['users']);

            // If the setting is percentage discount.
            if ($instance->customint3 == 1) {

                $strings = new \stdClass;

                // This string should only be displayed for the multienrollment and if the code
                // was given, otherwise we don't display the string.
                // If the code is required and given or no code is required, display strings.
                $strings->discount = helper::get_percentage_discount_string($objcosts, $payment->codegiven, false);
                $strings->calculation = helper::get_percentage_calculation_string($objcosts, $payment->codegiven, false);

                if ($instance->customint7 && $payment->codegiven || !$instance->customint7) {
                    $hasdiscount = (count($ret['users']) >= $threshold);
                    $strings->discount = helper::get_percentage_discount_string($objcosts, $payment->codegiven, $hasdiscount);
                    $strings->calculation = helper::get_percentage_calculation_string($objcosts, $payment->codegiven, $hasdiscount);
                }

                // Update the units.
                $ret['units'] = $payment->units;

                $ret['successmessage'] = get_string('multipleregistrationconfirmuserlist', 'enrol_payment')
                . implode('<li>', $stremails)
                . '</ul>'
                . get_string('totalcost', 'enrol_payment', $strings);

            } else if ($instance->customint3 == 2) {

                // ... it is value discount and we want to reflect that.
                $strings                = new \stdClass;
                $strings->discount      = '';
                $strings->calculation   = get_string('nodiscountvaluecalculation', 'enrol_payment', $objcosts);

                // If the code is required and given or no code is required, display strings.
                if ($instance->customint7 && $payment->codegiven || !$instance->customint7) {
                    $strings->discount = get_string('getvaluediscount', 'enrol_payment', $objcosts);
                    $strings->calculation = get_string('getvaluecalculation', 'enrol_payment', $objcosts);
                }

                // Update the units.
                $ret['units']           = $payment->units;
                $ret['successmessage']  = get_string('multipleregistrationconfirmuserlist', 'enrol_payment')
                                            . implode('<li>', $stremails)
                                            . '</ul>'
                                            . get_string('totalcost', 'enrol_payment', $strings);

            } else {

                // ... it is value discount and we want to reflect that.
                $strings                = new \stdClass;
                $strings->discount      = '';
                $strings->calculation   = get_string('nodiscountvaluecalculation', 'enrol_payment', $objcosts);
                $ret['successmessage']  = get_string('multipleregistrationconfirmuserlist', 'enrol_payment')
                                            . implode('<li>', $stremails)
                                            . '</ul>'
                                            . get_string('totalcost', 'enrol_payment', $strings);

            }
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
            helper::update_payment_data(false, [], $payment);
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

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function check_enrol_parameters() {
        return new external_function_parameters(
            [
                'courseid' => new external_value(PARAM_INT, 'Course id'),
                'paymentid' => new external_value(PARAM_INT, 'Payment id'),
            ]
        );
    }

    /**
     * Validate single enrollments.
     *
     * @param  int $courseid
     * @param  string $paymentid
     * @return string
     */
    public static function check_enrol($courseid, $paymentid) {
        global $DB, $USER;

        // Parameters validation.
        $params = self::validate_parameters(self::check_enrol_parameters(),
            [
                'courseid' => $courseid,
                'paymentid' => $paymentid,
            ]
        );

        $context = context_course::instance($params['courseid'], MUST_EXIST);

        if (is_enrolled($context, $USER, '', true)) {
            return json_encode([
                'status' => 'success',
                'result' => true
            ]);

        } else if (helper::payment_pending($params['paymentid'])) {
            return json_encode([
                'status' => 'success',
                'result' => false,
                'reason' => 'Pending'
            ]);
        } else {
            $reason = helper::get_payment_status($params['paymentid']);
            return json_encode([
                'status' => 'success',
                'result' => false,
                'reason' => $reason
            ]);
        }

    }

    /**
     * Returns description of method result value.
     *
     * @return external_description
     */
    public static function check_enrol_returns() {
        return new external_value(PARAM_RAW, 'Returns an array with the check enrol result');
    }

}