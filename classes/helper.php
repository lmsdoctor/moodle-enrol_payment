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
 * This is a one-line short description of the file.
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package   enrol_payment
 * @copyright 2020 Andrés Ramos, LMS Doctor <andres.ramos@lmsdoctor.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_payment;

defined('MOODLE_INTERNAL') || die();

use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Forum subscription manager.
 *
 * @package   enrol_payment
 * @copyright 2020 Andrés Ramos, LMS Doctor <andres.ramos@lmsdoctor.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    protected $istaxable = false;
    protected $usertax = 0;

    /**
     * Constructor.
     *
     * @param string $instance
     * @param array  $config
     */
    public function __construct() {
        // Empty constructor.
    }

    /**
     * Get the payment from token.
     *
     * @param  string $prepaytoken
     * @return stdClass
     */
    public static function get_payment_from_token(string $prepaytoken) {
        global $DB;
        return $DB->get_record_sql('SELECT * FROM {enrol_payment_session} WHERE '
            . $DB->sql_compare_text('prepaytoken') . ' = ? ',
            ['prepaytoken' => $prepaytoken]
        );
    }

    /**
     * Normalize percent discount.
     *
     * @param  stdClass $instance
     * @return int
     */
    public static function normalize_percent_discount($amount, $discounttype) {
        if ($discounttype == 1 && $amount > 1) {
            return $amount * 0.01;
        }
        return $amount;
    }

    /**
     * Calculate cost.
     *
     * @param stdClass $instance enrol_payment instance
     * @param stdClass $payment  payment object from enrol_payment_session
     *
     * @return array
     */
    public static function calculate_cost(stdClass $instance, stdClass $payment, bool $addtax = false) {
        $discountthreshold = $instance->customint8;
        $discountcoderequired = $instance->customint7;
        $discountamount = 0.0;

        $cost = $payment->originalcost;
        $subtotal = $cost;

        if (self::is_negative_value($discountamount)) {
            throw new moodle_exception('negativediscount', 'enrol_payment');
        }

        if (self::not_enough_units($payment->units)) {
            throw new moodle_exception('notenoughunits', 'enrol_payment');
        }

        // If conditions have been met for a discount, apply it.
        // This is not the most concise way to write this logic, but it is the most understandable in my opinion.
        // Assuming the discount theshold is met:
        // If a discount code isn't required, apply the discount.
        // If a discount code is required and the user has provided it, apply the discount.
        $discounttype = 0;
        $discountamount = 0;
        if ($payment->units >= $discountthreshold) {
            if (!$discountcoderequired || ($discountcoderequired && $payment->codegiven)) {
                $discounttype = $instance->customint3;
                $discountamount = $instance->customdec1;
            }
        }

        $ocdiscounted = $cost;
        $normalizeddiscount = self::normalize_percent_discount($discountamount, $discounttype);

        switch ($discounttype) {
            case 0:
                $subtotal = $cost * $payment->units;
                break;
            case 1:
                if ($discountamount > 100) {
                    throw new \Exception(get_string("percentdiscountover100error", "enrol_payment"));
                }

                // Percentages over 1 converted to a float between 0 and 1.
                // Per-unit cost is the difference between the full cost and the percent discount.
                $perunitcost = $cost - ($cost * $normalizeddiscount);
                $subtotal = $perunitcost * $payment->units;

                $ocdiscounted = $perunitcost;

                break;
            case 2:
                $ocdiscounted = $cost - $discountamount;
                $subtotal = ($cost - $discountamount) * $payment->units;

                break;
            default:
                throw new \Exception(get_string("discounttypeerror", "enrol_payment"));
                break;
        }

        $taxamount = 0;
        $subtotaltaxed = $subtotal;

        if ($payment->taxpercent && $addtax) {
            $taxamount = $subtotal * $payment->taxpercent;
            $subtotaltaxed = $subtotal + $taxamount;
        }

        $ret = [];
        $ret['subtotal'] = format_float($subtotal, 2, false);
        $ret['subtotallocalised'] = format_float($subtotal, 2, true);
        $ret['subtotaltaxed'] = format_float($subtotaltaxed, 2, true);
        $ret['taxamount'] = format_float($taxamount, 2, true);
        $ret['ocdiscounted'] = format_float($ocdiscounted, 2, true);
        $ret['percentdiscount'] = floor($normalizeddiscount * 100);
        $ret['originalunitprice'] = format_float($cost, 2, true);
        $percentdiscountunit = self::calculate_discount($ret['originalunitprice'], $ret['percentdiscount']);
        $ret['percentdiscountunit'] = format_float($percentdiscountunit, 2, true);
        $ret['discountvalue'] = format_float($discountamount, 2);

        return $ret;
    }

    /**
     * Calculate the total discount of a percentage.
     *
     * @param  int $total
     * @param  int $discount
     *
     * @return int
     */
    protected static function calculate_discount(int $total, int $discount) {
        return $total * $discount / 100;
    }

    /**
     * Returns and object holding the cost values.
     *
     * @param  array  $ret
     * @param  string $symbol
     * @param  string $currency
     * @param  int    $units
     * @param  string $taxstring
     * @return [type]
     */
    public static function get_object_of_costs(array $ret, string $symbol, string $currency, int $units, string $taxstring) {

        $data                   = new stdClass;
        $data->symbol           = $symbol;
        $data->originalcost     = $ret['originalunitprice'];
        $data->unitdiscount     = $ret['percentdiscountunit'];
        $data->percentdiscount  = $ret['percentdiscount'];
        $data->units            = $units;
        $data->subtotaltaxed    = $ret['subtotaltaxed'];
        $data->taxstring        = $taxstring;
        $data->currency         = $currency;
        $data->discountvalue    = $ret['discountvalue'];
        return $data;

    }

    /**
     * Check if the value is negative.
     *
     * @param  float   $number
     * @return boolean
     */
    public static function is_negative_value(float $number) {
        if ($number < 0.00) {
            return true;
        }
        return false;
    }

    /**
     * Check if units are 0.
     *
     * @param  int    $units
     * @throws moodle_exception
     */
    public static function not_enough_units(int $units) {
        if (empty($units)) {
            return true;
        }
        return false;
    }

    /**
     * Returns the percentage calculation string.
     *
     * @param  stdClass $obj
     * @return string
     */
    public static function get_percentage_calculation_string(stdClass $obj, bool $codegiven, bool $hasdiscount) {

        if (!$hasdiscount) {
            return get_string('percentcalculation', 'enrol_payment', $obj);
        }
        return get_string('percentcalculationdiscount', 'enrol_payment', $obj);
    }

    /**
     * Returns the percentage discount string.
     *
     * @param  stdClass $obj
     * @return string
     */
    public static function get_percentage_discount_string(stdClass $obj, bool $codegiven, bool $hasdiscount) {

        if (!$hasdiscount) {
            return '';
        }
        return get_string('percentdiscountstring', 'enrol_payment', $obj);
    }

    /**
     * When switching between Single and Multiple mode, make the necessary
     * adjustments to our payment row in the database.
     *
     * @param  bool $multiple
     * @param  array $users
     * @param  stdClass &$payment
     * @return void
     */
    public static function update_payment_data(bool $multiple, array $users, stdClass &$payment) {
        global $DB;

        $userids = array();
        if ($multiple) {
            foreach ($users as $u) {
                array_push($userids, $u["id"]);
            }
        }

        $payment->multiple = $multiple;
        $payment->multipleuserids = $multiple ? implode(",", $userids) : null;
        $payment->units = $multiple ? count($userids) : 1;
        $DB->update_record('enrol_payment_session', $payment);
    }

    /**
     * Return true if the corresponding transaction is pending.
     *
     * @param  int $paymentid
     * @return bool
     */
    public static function payment_pending(int $paymentid) {
        global $DB;
        $payment = $DB->get_record('enrol_payment_session', array('id' => $paymentid));
        $transaction = $DB->get_record('enrol_payment_transaction', array('txnid' => $payment->paypaltxnid));

        if ($transaction) {
            return ($transaction->payment_status == "Pending");
        }

        return false;

    }

    /**
     * Returns the payment status.
     *
     * @param  int $paymentid
     * @return string
     */
    public static function get_payment_status(int $paymentid) {
        global $DB;
        $payment = $DB->get_record('enrol_payment_session', array('id' => $paymentid));
        $transaction = $DB->get_record('enrol_payment_transaction', array('txnid' => $payment->paypaltxnid));

        if ($transaction) {
            return $transaction->paymentstatus;
        }
        return false;
    }

    /**
     * Look for users using email addresses.
     *
     * @param  array $emails
     * @return array
     */
    public static function get_moodle_users_by_emails(array $emails) {
        global $DB;

        $users = [
            'found' => [],
            'notfound' => [],
        ];
        foreach ($emails as $email) {
            $user = $DB->get_record('user', ['email' => $email], 'id, email, firstname, lastname');
            if ($user) {
                $userdata = [
                    'id'    => $user->id,
                    'email' => $email,
                    'name'  => ($user->firstname . " " . $user->lastname)
                ];
                array_push($users['found'], $userdata);
            } else {
                array_push($users['notfound'], $email);
            }
        }

        return $users;
    }

    /**
     * Pretty print user.
     *
     * @param  array $user
     * @return string
     */
    public static function pretty_print_user(array $user) {
        return $user['name'] . " &lt;" . $user['email'] . "&gt;";
    }

    /**
     * Store payment session in the db.
     *
     * @param  string $prepaytoken
     * @param  int    $userid
     * @param  int    $courseid
     * @param  int    $enrolid
     * @param  int    $originalcost
     * @param  float  $taxpercent
     *
     * @return stdClass
     */
    public static function store_payment_session(string $prepaytoken, int $userid, int $courseid,
        int $enrolid, int $originalcost, float $taxpercent) {

        global $DB;

        return $DB->insert_record('enrol_payment_session', [
            'prepaytoken' => $prepaytoken,
            'userid' => $userid,
            'courseid' => $courseid,
            'instanceid' => $enrolid,
            'multiple' => false,
            'multipleuserids' => null,
            'codegiven' => false,
            'units' => 1,
            'originalcost' => $originalcost,
            'taxpercent' => $taxpercent,
            'paypaltxnid' => null,
        ]);

    }

    /**
     * Returns the stripe logo url.
     *
     * @return string
     */
    public static function get_stripe_logo_url() {
        $stripelogo = get_config('stripelogourl', 'enrol_payment');
        if (empty($stripelogo)) {
            return '';
        }
        $strippedlogo = str_replace('/', '', $stripelogo);
        return (string) moodle_url::make_pluginfile_url(1, 'enrol_payment', 'stripelogo', null, '/', $strippedlogo);
    }

    /**
     * Alerts site admin of potential problems.
     *
     * @param string   $subject email subject
     * @param stdClass $data    PayPal IPN data
     */
    public static function message_paypal_error_to_admin(string $subject, stdClass $data) {
        $admin = get_admin();
        $site = get_site();

        $message = "$site->fullname:  Transaction failed.\n\n$subject\n\n";

        foreach ($data as $key => $value) {
            $message .= "$key => $value\n";
        }

        $eventdata = new \stdClass();
        $eventdata->modulename        = 'moodle';
        $eventdata->component         = 'enrol_payment';
        $eventdata->name              = 'payment_enrolment';
        $eventdata->userfrom          = $admin;
        $eventdata->userto            = $admin;
        $eventdata->subject           = "PAYPAL ERROR: ".$subject;
        $eventdata->fullmessage       = $message;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = '';
        $eventdata->smallmessage      = '';
        message_send($eventdata);
    }

    /**
     * Silent exception handler.
     *
     * @return callable exception handler
     */
    public static function get_exception_handler() {
        return function($ex) {
            $info = get_exception_info($ex);

            $logerrmsg = "enrol_payment IPN exception handler: " . $info->message;
            if (debugging('', DEBUG_NORMAL)) {
                $logerrmsg .= ' Debug: ' . $info->debuginfo . "\n" . format_backtrace($info->backtrace, true);
            }
            echo $logerrmsg;

            if (http_response_code() == 200) {
                http_response_code(500);
            }

            exit(0);
        };
    }

}