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
 * Privacy Subsystem implementation for enrol_payment.
 *
 * @package    enrol_payment
 * @category   privacy
 * @copyright  2018 Shamim Rezaie <shamim@moodle.com> (originally for enrol_paypal)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_payment\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for enrol_payment.
 *
 * @copyright  2018 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider {

    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_external_location_link(
            'paypal.com',
            [
                'os0'        => 'privacy:metadata:enrol_payment:paypal_com:os0',
                'custom'     => 'privacy:metadata:enrol_payment:paypal_com:custom',
                'first_name' => 'privacy:metadata:enrol_payment:paypal_com:first_name',
                'last_name'  => 'privacy:metadata:enrol_payment:paypal_com:last_name',
                'address'    => 'privacy:metadata:enrol_payment:paypal_com:address',
                'city'       => 'privacy:metadata:enrol_payment:paypal_com:city',
                'email'      => 'privacy:metadata:enrol_payment:paypal_com:email',
                'country'    => 'privacy:metadata:enrol_payment:paypal_com:country',
            ],
            'privacy:metadata:enrol_payment:paypal_com'
        );

        // The enrol_payment has a DB table that contains user data.
        $collection->add_database_table(
                'enrol_payment_transaction',
                [
                    'business'            => 'privacy:metadata:enrol_payment:enrol_payment_transaction:business',
                    'receiveremail'      => 'privacy:metadata:enrol_payment:enrol_payment_transaction:receiveremail',
                    'receiverid'         => 'privacy:metadata:enrol_payment:enrol_payment_transaction:receiverid',
                    'itemname'           => 'privacy:metadata:enrol_payment:enrol_payment_transaction:itemname',
                    'courseid'            => 'privacy:metadata:enrol_payment:enrol_payment_transaction:courseid',
                    'userid'              => 'privacy:metadata:enrol_payment:enrol_payment_transaction:userid',
                    'instanceid'          => 'privacy:metadata:enrol_payment:enrol_payment_transaction:instanceid',
                    'memo'                => 'privacy:metadata:enrol_payment:enrol_payment_transaction:memo',
                    'tax'                 => 'privacy:metadata:enrol_payment:enrol_payment_transaction:tax',
                    'optionselection1x' => 'privacy:metadata:enrol_payment:enrol_payment_transaction:optionselection1x',
                    'paymentstatus'      => 'privacy:metadata:enrol_payment:enrol_payment_transaction:paymentstatus',
                    'pendingreason'      => 'privacy:metadata:enrol_payment:enrol_payment_transaction:pendingreason',
                    'reasoncode'         => 'privacy:metadata:enrol_payment:enrol_payment_transaction:reasoncode',
                    'txnid'              => 'privacy:metadata:enrol_payment:enrol_payment_transaction:txnid',
                    'parenttxnid'       => 'privacy:metadata:enrol_payment:enrol_payment_transaction:parenttxnid',
                    'paymenttype'        => 'privacy:metadata:enrol_payment:enrol_payment_transaction:paymenttype',
                    'timeupdated'         => 'privacy:metadata:enrol_payment:enrol_payment_transaction:timeupdated'
                ],
                'privacy:metadata:enrol_payment:enrol_payment_transaction'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        $contextlist = new contextlist();

        // Values of ep.receiveremail and ep.business are already normalised to lowercase characters by PayPal,
        // therefore there is no need to use LOWER() on them in the following query.
        $sql = "SELECT ctx.id
                  FROM {enrol_payment_transaction} ep
                  JOIN {enrol} e ON ep.instanceid = e.id
                  JOIN {context} ctx ON e.courseid = ctx.instanceid AND ctx.contextlevel = :contextcourse
             LEFT JOIN {user} u ON u.id = :emailuserid AND (
                    LOWER(u.email) = ep.receiveremail
                        OR
                    LOWER(u.email) = ep.business
                )
                 WHERE ep.userid = :userid
                       OR u.id IS NOT NULL";
        $params = [
            'contextcourse' => CONTEXT_COURSE,
            'userid'        => $userid,
            'emailuserid'   => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        // Values of ep.receiveremail and ep.business are already normalised to lowercase characters by PayPal,
        // therefore there is no need to use LOWER() on them in the following query.
        $sql = "SELECT ep.*
                  FROM {enrol_payment_transaction} ep
                  JOIN {enrol} e ON ep.instanceid = e.id
                  JOIN {context} ctx ON e.courseid = ctx.instanceid AND ctx.contextlevel = :contextcourse
             LEFT JOIN {user} u ON u.id = :emailuserid AND (
                    LOWER(u.email) = ep.receiveremail
                        OR
                    LOWER(u.email) = ep.business
                )
                 WHERE ctx.id {$contextsql}
                       AND (ep.userid = :userid
                        OR u.id IS NOT NULL)
              ORDER BY e.courseid";

        $params = [
            'contextcourse' => CONTEXT_COURSE,
            'userid'        => $user->id,
            'emailuserid'   => $user->id,
        ];
        $params += $contextparams;

        // Reference to the course seen in the last iteration of the loop. By comparing this with the current record, and
        // because we know the results are ordered, we know when we've moved to the PayPal transactions for a new course
        // and therefore when we can export the complete data for the last course.
        $lastcourseid = null;

        $strtransactions = get_string('transactions', 'enrol_payment');
        $transactions = [];
        $paypalrecords = $DB->get_recordset_sql($sql, $params);
        foreach ($paypalrecords as $paypalrecord) {
            if ($lastcourseid != $paypalrecord->courseid) {
                if (!empty($transactions)) {
                    $coursecontext = \context_course::instance($paypalrecord->courseid);
                    writer::with_context($coursecontext)->export_data(
                            [$strtransactions],
                            (object) ['transactions' => $transactions]
                    );
                }
                $transactions = [];
            }

            $transaction = (object) [
                'receiverid'         => $paypalrecord->receiverid,
                'itemname'           => $paypalrecord->itemname,
                'userid'              => $paypalrecord->userid,
                'memo'                => $paypalrecord->memo,
                'tax'                 => $paypalrecord->tax,
                'optionname1'        => $paypalrecord->optionname1,
                'optionselection1x' => $paypalrecord->optionselection1x,
                'optionname2'        => $paypalrecord->optionname2,
                'optionselection2x' => $paypalrecord->optionselection2x,
                'paymentstatus'      => $paypalrecord->paymentstatus,
                'pendingreason'      => $paypalrecord->pendingreason,
                'reasoncode'         => $paypalrecord->reasoncode,
                'txnid'              => $paypalrecord->txnid,
                'parenttxnid'       => $paypalrecord->parenttxnid,
                'paymenttype'        => $paypalrecord->paymenttype,
                'timeupdated'         => \core_privacy\local\request\transform::datetime($paypalrecord->timeupdated),
            ];
            if ($paypalrecord->userid == $user->id) {
                $transaction->userid = $paypalrecord->userid;
            }
            if ($paypalrecord->business == \core_text::strtolower($user->email)) {
                $transaction->business = $paypalrecord->business;
            }
            if ($paypalrecord->receiveremail == \core_text::strtolower($user->email)) {
                $transaction->receiveremail = $paypalrecord->receiveremail;
            }

            $transactions[] = $paypalrecord;

            $lastcourseid = $paypalrecord->courseid;
        }
        $paypalrecords->close();

        // The data for the last activity won't have been written yet, so make sure to write it now!
        if (!empty($transactions)) {
            $coursecontext = \context_course::instance($paypalrecord->courseid);
            writer::with_context($coursecontext)->export_data(
                    [$strtransactions],
                    (object) ['transactions' => $transactions]
            );
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_course) {
            return;
        }

        $DB->delete_records('enrol_payment_transaction', array('courseid' => $context->instanceid));
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        $contexts = $contextlist->get_contexts();
        $courseids = [];
        foreach ($contexts as $context) {
            if ($context instanceof \context_course) {
                $courseids[] = $context->instanceid;
            }
        }

        list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);

        $select = "userid = :userid AND courseid $insql";
        $params = $inparams + ['userid' => $user->id];
        $DB->delete_records_select('enrol_payment_transaction', $select, $params);

        // We do not want to delete the payment record when the user is just the receiver of payment.
        // In that case, we just delete the receiver's info from the transaction record.

        $select = "business = :business AND courseid $insql";
        $params = $inparams + ['business' => \core_text::strtolower($user->email)];
        $DB->set_field_select('enrol_payment_transaction', 'business', '', $select, $params);

        $select = "receiveremail = :receiveremail AND courseid $insql";
        $params = $inparams + ['receiveremail' => \core_text::strtolower($user->email)];
        $DB->set_field_select('enrol_payment_transaction', 'receiveremail', '', $select, $params);
    }
}
