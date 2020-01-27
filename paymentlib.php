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
 * Library for handling payment tokens for the enrol_payment plugin.
 *
 * @package enrol_payment
 * @copyright 2018 Seth Yoder
 * @author Seth Yoder <seth.a.yoder@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paymentlib;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/moodlelib.php");

global $DB;

/**
 * Get the payment from token.
 *
 * @param  string $prepaytoken
 * @return object
 */
function enrol_payment_get_payment_from_token($prepaytoken) {
    global $DB;
    return $DB->get_record_sql('SELECT * FROM {enrol_payment_session}
                                  WHERE ' .$DB->sql_compare_text('prepaytoken') . ' = ? ',
                              ['prepaytoken' => $prepaytoken]);
}

/**
 * Normalize percent discount.
 *
 * @param  object $instance
 * @return int
 */
function enrol_payment_normalize_percent_discount($instance) {
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
function enrol_payment_calculate_cost($instance, $payment, $addtax=false) {
    $discountthreshold = $instance->customint8;
    $discountcoderequired = $instance->customint7;
    $discountamount = 0.0;

    $cost = $payment->originalcost;
    $subtotal = $cost;

    if ($discountamount < 0.00) {
        throw new Exception(get_string("negativediscount", "enrol_payment"));
    }

    if ($payment->units < 1) {
        throw new Exception(get_string("notenoughunits", "enrol_payment"));
    }

    // If conditions have been met for a discount, apply it.
    // This is not the most concise way to write this logic, but it is the most understandable in my opinion.
    // Assuming the discount theshold is met:
    // If a discount code isn't required, apply the discount.
    // If a discount code is required and the user has provided it, apply the discount.

    $applydiscount = 0;
    if ($payment->units >= $discountthreshold) {
        if (!$discountcoderequired || ($discountcoderequired && $payment->code_given)) {
            $applydiscount = $instance->customint3;
            $discountamount = $instance->customdec1;
        }
    }

    $ocdiscounted = $cost;
    $normalizeddiscount = enrol_payment_normalize_percent_discount($instance);

    switch ($applydiscount) {
        case 0:
            $subtotal = $cost * $payment->units;
            break;
        case 1:
            if ($discountamount > 100) {
                throw new Exception(get_string("percentdiscountover100error", "enrol_payment"));
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
            throw new Exception(get_string("discounttypeerror", "enrol_payment"));
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

    return $ret;
}
