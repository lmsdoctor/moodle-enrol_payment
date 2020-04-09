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
    public static function normalize_percent_discount(float $amount, $discounttype) {
        if ($amount > 1 && $discounttype == 1) {
            return $amount * 0.01;
        }
        return $amount;
    }

    /**
     * Calculate cost.
     *
     * @param  stdClass $instance
     * @param  stdClass $payment
     * @param  bool     $addtax
     *
     * @return array
     */
    public static function calculate_cost(stdClass $instance, stdClass $payment, bool $addtax = false) {

        $cart               = new stdClass;
        $cart->discountamount = $instance->customdec1 ?? 0;
        $cart->discounttype = $instance->customint3 ?? 0;
        $cart->cost         = $payment->originalcost;
        $cart->subtotal     = $cart->cost * $payment->units;
        $cart->coderequired = ($instance->customint7) ?? 0;
        $cart->discount     = 0;
        $cart->taxamount    = 0;

        if (self::not_enough_units($payment->units)) {
            throw new moodle_exception('notenoughunits', 'enrol_payment');
        }

        if (self::has_discount($cart->discounttype)) {
            $cart = self::get_discount($cart, $payment);
        }

        $cart->subtotaltaxed = $cart->subtotal;
        if ($payment->taxpercent && $addtax) {
            $cart->taxamount = $cart->subtotal * $payment->taxpercent;
            $cart->subtotaltaxed = $cart->subtotal + $cart->taxamount;
        }

        return self::get_formatted_values($cart);

    }

    /**
     * Reduce the float values to 2 decimals.
     *
     * @param  stdClass $cart [description]
     *
     * @return array
     */
    private static function get_formatted_values(stdClass $cart) {

        $checkout = [
            'subtotal'        => format_float($cart->subtotal, 2, true),
            'subtotaltaxed'   => format_float($cart->subtotaltaxed, 2, true),
            'taxamount'       => format_float($cart->taxamount, 2, true),
            'percentdiscount' => floor($cart->discount * 100),
            'unitprice'       => format_float($cart->cost, 2, true),
            'discountvalue'   => format_float($cart->discountamount, 2),
        ];

        $percentdiscountunit = self::calculate_discount($checkout['unitprice'], $checkout['percentdiscount']);
        $checkout['percentdiscountunit'] = format_float($percentdiscountunit, 2, true);

        return $checkout;

    }

    /**
     * Check if the discount is enabled.
     *
     * @param  int  $discounttype
     *
     * @return bool
     */
    public static function has_discount($discounttype) {
        return (get_config('enrol_payment', 'enablediscounts') && $discounttype != 0);
    }

    /**
     * Apply the discounts based on the discount type.
     *
     * @param  stdClass $cart
     * @param  stdClass $payment
     *
     * @return stdClass
     */
    private static function get_discount(stdClass $cart, stdClass $payment) {

        if ($cart->coderequired && !$payment->codegiven) {
            return $cart;
        }

        $cart->discount = self::normalize_percent_discount($cart->discountamount, $cart->discounttype);
        switch ($cart->discounttype) {

            case 1:
                // This throws an exception if the discount is over 100.
                self::discount_is_overlimit($cart->discountamount);

                // Per-unit cost is the difference between the full cost and the percent discount.
                $cart->perunitcost = $cart->cost - ($cart->cost * $cart->discount);
                $cart->subtotal    = $cart->perunitcost * $payment->units;
                break;

            case 2:
                $cart->subtotal = ($cart->cost - $cart->discountamount) * $payment->units;
                break;
        }

        return $cart;

    }

    /**
     * Check if the discount is overlimit.
     *
     * @param  float $discount
     * @return void
     */
    private static function discount_is_overlimit($discount) {

        if ($discount > 100) {
            throw new \Exception(get_string("percentdiscountover100error", "enrol_payment"));
        }

    }

    /**
     * Calculate the total discount of a percentage.
     *
     * @param  int $total
     * @param  int $discount
     *
     * @return int
     */
    protected static function calculate_discount($total, $discount) {
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
        $data->originalcost     = $ret['unitprice'];
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
        return null;
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
            // Make sure to remove any extra spaces.
            $email = trim($email);
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
     * @param  float  $originalcost
     * @param  float  $taxpercent
     *
     * @return stdClass
     */
    public static function store_payment_session(string $prepaytoken, int $userid, int $courseid,
        int $enrolid, float $originalcost, float $taxpercent) {

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
        $stripelogo = get_config('enrol_payment', 'stripelogourl');
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
        global $CFG;

        $admin = get_admin();
        $site = get_site();

        $message = "$site->fullname:  Transaction failed.\n\n$subject\n\n";

        foreach ($data as $key => $value) {
            $message .= "$key => $value\n";
        }

        $messagehtml = '<p>' . $message . '</p>';
        $messagetext = html_to_text($messagehtml);

        $fromuser = self::get_fromuser_object();
        return email_to_user($admin, $fromuser, $subject, $messagetext, $messagehtml, ", ", false);

    }

    /**
     * Returns fromuser object to be used in email_to_user function.
     *
     * @return stdClass
     */
    public static function get_fromuser_object() {
        global $CFG;

        $fromuser = new stdClass;
        $fromuser->email = $CFG->supportemail;
        $fromuser->firstname = $CFG->supportname;
        $fromuser->lastname = '';
        $fromuser->maildisplay = true;
        $fromuser->mailformat = 1;
        $fromuser->id = -99;
        $fromuser->firstnamephonetic = '';
        $fromuser->lastnamephonetic = '';
        $fromuser->middlename = '';
        $fromuser->alternatename = '';

        return $fromuser;

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

    /**
     * Outputs transfer instructions.
     *
     * @param  float $cost
     * @param  string $coursefullname
     * @param  string $courseshortname
     * @return string
     */
    public static function get_transfer_instructions(float $cost, string $coursefullname,
            string $courseshortname) {

        if (!get_config('enrol_payment', 'allowbanktransfer')) {
            return '';
        }

        $instructions = get_config('enrol_payment', 'transferinstructions');
        $instructions = str_replace("{{AMOUNT}}", "<span id=\"banktransfer-cost\">$cost</span>", $instructions);
        $instructions = str_replace("{{COURSESHORTNAME}}", $courseshortname, $instructions);
        $instructions = str_replace("{{COURSEFULLNAME}}", $coursefullname, $instructions);

        return $instructions;
    }

}