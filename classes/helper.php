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
 * @package    enrol_payment
 * @copyright  2020 Andrés Ramos, LMS Doctor <andres.ramos@lmsdoctor.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_payment;

defined('MOODLE_INTERNAL') || die();

use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Forum subscription manager.
 *
 * @copyright  2014 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Get the payment from token.
     *
     * @param  string $prepaytoken
     * @return object
     */
    public static function get_payment_from_token($prepaytoken) {
        global $DB;
        return $DB->get_record_sql('
            SELECT * FROM {enrol_payment_session}
            WHERE ' . $DB->sql_compare_text('prepaytoken') . ' = ? ',
            ['prepaytoken' => $prepaytoken]
        );
    }

    /**
     * Normalize percent discount.
     *
     * @param  object $instance
     * @return int
     */
    public static function normalize_percent_discount($instance) {
        $amount = $instance->customdec1;
        if ($instance->customint3 == 1 && $amount > 1.0) {
            return $amount * 0.01;
        } else {
            return $amount;
        }
    }

    /**
     * Calculate cost.
     *
     * @param $instance enrol_payment instance
     * @param $payment payment object from enrol_payment_session
     * @return object with "subtotal" and "subtotallocalised" fields.
     */
    public static function calculate_cost($instance, $payment, $addtax = false) {
        $discountthreshold = $instance->customint8;
        $discountcoderequired = $instance->customint7;
        $discountamount = 0.0;

        $cost = $payment->originalcost;
        $subtotal = $cost;

        if ($discountamount < 0.00) {
            throw new \Exception(get_string("negativediscount", "enrol_payment"));
        }

        if ($payment->units < 1) {
            throw new \Exception(get_string("notenoughunits", "enrol_payment"));
        }

        // If conditions have been met for a discount, apply it.
        // This is not the most concise way to write this logic, but it is the most understandable in my opinion.
        // Assuming the discount theshold is met:
        // If a discount code isn't required, apply the discount.
        // If a discount code is required and the user has provided it, apply the discount.
        $applydiscount = 0;
        if ($payment->units >= $discountthreshold) {
            if (!$discountcoderequired || ($discountcoderequired && $payment->codegiven)) {
                $applydiscount = $instance->customint3;
                $discountamount = $instance->customdec1;
            }
        }

        $ocdiscounted = $cost;
        $normalizeddiscount = self::normalize_percent_discount($instance);

        switch ($applydiscount) {
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

        if ($payment->taxpercent && $addtax) {
            $taxamount = $subtotal * $payment->taxpercent;
            $subtotaltaxed = $subtotal + $taxamount;
        } else {
            $taxamount = 0;
            $subtotaltaxed = $subtotal;
        }

        $ret['subtotal'] = format_float($subtotal, 2, false);
        $ret['subtotallocalised'] = format_float($subtotal, 2, true);
        $ret['subtotaltaxed'] = format_float($subtotaltaxed, 2, true);
        $ret['taxamount'] = format_float($taxamount, 2, true);
        $ret['ocdiscounted'] = format_float($ocdiscounted, 2, true);
        $ret['percentdiscount'] = floor($normalizeddiscount * 100);
        $ret['originalunitprice'] = format_float($cost, 2, true);
        $ret['percentdiscountunit'] = format_float(self::calculate_discount($ret['originalunitprice'], $ret['percentdiscount']), 2, true);
        $ret['discountvalue'] = format_float($discountamount, 2);

        return $ret;
    }

    /**
     * Calculate the total discount of a percentage.
     *
     * @param  int $total
     * @param  int $discount
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

        $data                   = new \stdClass;
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
    private static function is_negative_value(float $number) {
        if ($number < 0.00) {
            throw new moodle_exception('negativediscount', 'enrol_payment');
        }
    }

    /**
     * Check if units are 0.
     *
     * @param  int    $units
     * @throws moodle_exception
     */
    private static function not_enough_units(int $units) {
        if (empty($units)) {
            throw new moodle_exception('notenoughunits', 'enrol_payment');
        }
    }

    /**
     * Returns the precentage calculation string.
     *
     * @param  stdClass $a
     * @return string
     */
    public static function get_percentage_calculation_string(stdClass $a) {
        return "{$a->symbol}{$a->originalcost} - {$a->symbol}{$a->unitdiscount} ({$a->percentdiscount}% discount) × {$a->units} {$a->taxstring}
                = <b>{$a->symbol}{$a->subtotaltaxed}</b> {$a->currency}";
    }

    /**
     * Returns the percentage discount string.
     *
     * @param  stdClass $a
     * @return string
     */
    public static function get_percentage_discount_string(stdClass $a) {
        return "The {$a->symbol}{$a->percentdiscount}% discount has been applied.";
    }

    /**
     * Returns the value calculation string.
     *
     * @param  stdClass $a
     * @return string
     */
    public static function get_value_calculation_string(stdClass $a) {
        return "{$a->symbol}{$a->originalcost} - {$a->symbol}{$a->discountvalue} discount × {$a->units} {$a->taxstring} = <b>{$a->symbol}{$a->subtotaltaxed}</b> {$a->currency}";
    }

    /**
     * Returns the value discount string.
     *
     * @param  stdClass $a
     * @return string
     */
    public static function get_value_discount_string(stdClass $a) {
        return "The {$a->symbol}{$a->discountvalue} discount per-seat has been applied.";
    }

    /**
     * When switching between Single and Multiple mode, make the necessary
     * adjustments to our payment row in the database.
     *
     * @param  bool $multiple
     * @param  array $users
     * @param  object &$payment
     * @return void
     */
    public static function update_payment_data($multiple, $users, &$payment) {
        global $DB;

        $userids = array();
        if ($multiple) {
            foreach ($users as $u) {
                array_push($userids, $u["id"]);
            }
        }

        $payment->multiple = $multiple;
        $payment->multiple_userids = $multiple ? implode(",", $userids) : null;
        $payment->units = $multiple ? count($userids) : 1;
        $DB->update_record('enrol_payment_session', $payment);
    }

    /**
     * Return true if the corresponding transaction is pending.
     *
     * @param  int $paymentid
     * @return bool
     */
    public static function payment_pending($paymentid) {
        global $DB;
        $payment = $DB->get_record('enrol_payment_session', array('id' => $paymentid));
        $transaction = $DB->get_record('enrol_payment_transaction', array('txnid' => $payment->paypaltxnid));

        if ($transaction) {
            return ($transaction->payment_status == "Pending");
        } else {
            return false;
        }
    }

    /**
     * Returns the payment status.
     *
     * @param  int $paymentid
     * @return string
     */
    public static function get_payment_status($paymentid) {
        global $DB;
        $payment = $DB->get_record('enrol_payment_session', array('id' => $paymentid));
        $transaction = $DB->get_record('enrol_payment_transaction', array('txnid' => $payment->paypaltxnid));

        if ($transaction) {
            return $transaction->payment_status;
        } else {
            return false;
        }
    }

    /**
     * Look for users using email addresses.
     *
     * @param  array $emails
     * @return array
     */
    public static function get_moodle_users_by_emails($emails) {
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
     * @param  array $u
     * @return string
     */
    public static function pretty_print_user($u) {
        return $u['name'] . " &lt;" . $u['email'] . "&gt;";
    }

}