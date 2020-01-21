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
     * @return object with "subtotal" and "subtotal_localised" fields.
     */
    public static function calculate_cost($instance, $payment, $addtax = false) {
        $discountthreshold = $instance->customint8;
        $discountcoderequired = $instance->customint7;
        $discountamount = 0.0;

        $cost = $payment->original_cost;
        $subtotal = $cost;

        if($discountamount < 0.00) {
            throw new \Exception(get_string("negativediscount", "enrol_payment"));
        }

        if($payment->units < 1) {
            throw new \Exception(get_string("notenoughunits", "enrol_payment"));
        }

        // If conditions have been met for a discount, apply it.
        // This is not the most concise way to write this logic, but it is the most understandable in my opinion.
        // Assuming the discount theshold is met:
        // If a discount code isn't required, apply the discount.
        // If a discount code is required and the user has provided it, apply the discount.

        $applydiscount = 0;
        if($payment->units >= $discountthreshold) {
            if(!$discountcoderequired || ($discountcoderequired && $payment->code_given)) {
                $applydiscount = $instance->customint3;
                $discountamount = $instance->customdec1;
            }
        }

        $oc_discounted = $cost;
        $normalized_discount = self::normalize_percent_discount($instance);

        switch ($applydiscount) {
            case 0:
                $subtotal = $cost * $payment->units;
                break;
            case 1:
                if($discountamount > 100) {
                    throw new \Exception(get_string("percentdiscountover100error", "enrol_payment"));
                }

                // Percentages over 1 converted to a float between 0 and 1.
                // Per-unit cost is the difference between the full cost and the percent discount.
                $perunitcost = $cost - ($cost * $normalized_discount);
                $subtotal = $perunitcost * $payment->units;

                $oc_discounted = $perunitcost;

                break;
            case 2:
                $oc_discounted = $cost - $discountamount;
                $subtotal = ($cost - $discountamount) * $payment->units;

                break;
            default:
                throw new \Exception(get_string("discounttypeerror", "enrol_payment"));
                break;
        }

        if($payment->tax_percent && $addtax) {
            $tax_amount = $subtotal * $payment->tax_percent;
            $subtotal_taxed = $subtotal + $tax_amount;
        } else {
            $tax_amount = 0;
            $subtotal_taxed = $subtotal;
        }

        $ret['subtotal'] = format_float($subtotal, 2, false);
        $ret['subtotal_localised'] = format_float($subtotal, 2, true);
        $ret['subtotal_taxed'] = format_float($subtotal_taxed, 2, true);
        $ret['tax_amount'] = format_float($tax_amount, 2, true);
        $ret['oc_discounted'] = format_float($oc_discounted, 2, true);
        $ret['percent_discount'] = floor($normalized_discount * 100);

        return $ret;
    }

}