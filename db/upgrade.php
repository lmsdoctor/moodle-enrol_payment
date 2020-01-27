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
 * This file keeps track of upgrades to the enrolment plugin
 *
 * @package    enrol_payment
 * @copyright  2010 Eugene Venter (originally for enrol_paypal)
 * @author     Eugene Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installation to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the methods of database_manager class
//
// Please do not forget to use upgrade_set_timeout()
// before any action that may take longer time to finish.

defined('MOODLE_INTERNAL') || die();

function xmldb_enrol_payment_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2018051401) {

        // Define field instanceid to be added to enrol_payment.
        // For some reason, some Moodle instances that are upgraded from old versions do not have this field.
        $table = new xmldb_table('enrol_payment');
        $field = new xmldb_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'userid');

        // Conditionally launch add field instanceid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Paypal savepoint reached.
        upgrade_plugin_savepoint(true, 2018051401, 'enrol', 'payment');
    }

    if ($oldversion < 2018051402) {

        // Define key courseid (foreign) to be added to enrol_payment.
        $table = new xmldb_table('enrol_payment');
        $key = new xmldb_key('courseid', XMLDB_KEY_FOREIGN, array('courseid'), 'course', array('id'));

        // Launch add key courseid.
        $dbman->add_key($table, $key);

        // Paypal savepoint reached.
        upgrade_plugin_savepoint(true, 2018051402, 'enrol', 'payment');
    }

    if ($oldversion < 2018051403) {

        // Define key userid (foreign) to be added to enrol_payment.
        $table = new xmldb_table('enrol_payment');
        $key = new xmldb_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));

        // Launch add key userid.
        $dbman->add_key($table, $key);

        // Paypal savepoint reached.
        upgrade_plugin_savepoint(true, 2018051403, 'enrol', 'payment');
    }

    if ($oldversion < 2018051404) {

        // Define key instanceid (foreign) to be added to enrol_payment.
        $table = new xmldb_table('enrol_payment');
        $key = new xmldb_key('instanceid', XMLDB_KEY_FOREIGN, array('instanceid'), 'enrol', array('id'));

        // Launch add key instanceid.
        $dbman->add_key($table, $key);

        // Paypal savepoint reached.
        upgrade_plugin_savepoint(true, 2018051404, 'enrol', 'payment');
    }

    if ($oldversion < 2018051405) {

        $table = new xmldb_table('enrol_payment');

        // Define index business (not unique) to be added to enrol_payment.
        $index = new xmldb_index('business', XMLDB_INDEX_NOTUNIQUE, array('business'));

        // Conditionally launch add index business.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index receiver_email (not unique) to be added to enrol_payment.
        $index = new xmldb_index('receiver_email', XMLDB_INDEX_NOTUNIQUE, array('receiver_email'));

        // Conditionally launch add index receiver_email.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Paypal savepoint reached.
        upgrade_plugin_savepoint(true, 2018051405, 'enrol', 'payment');
    }

    if ($oldversion < 2019013001) {
        $oldtransactiontable = new xmldb_table('enrol_payment');
        if ($dbman->table_exists($oldtransactiontable)) {
            $dbman->rename_table($oldtransactiontable, 'enrol_payment_transaction');
        }

        $oldsessiontable = new xmldb_table('enrol_payment_ipn');
        if ($dbman->table_exists($oldsessiontable)) {
            $dbman->rename_table($oldsessiontable, 'enrol_payment_session');
        }

        // Rename field discounted on table enrol_payment_session to code_given.
        $table = new xmldb_table('enrol_payment_session');
        $field = new xmldb_field('discounted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'multiple_userids');
        $newfield = new xmldb_field('code_given', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'multiple_userids');

        if (!($dbman->field_exists($table, $newfield))) {
            // Launch rename field code_given.
            $dbman->rename_field($table, $field, 'code_given');
        }

        // Payment savepoint reached.
        upgrade_plugin_savepoint(true, 2019013001, 'enrol', 'payment');
    }

    // There was an upgrade issue in the 2019013001 version, so re-doing those steps here if necessary.
    if ($oldversion < 2019013100) {
        $oldtransactiontable = new xmldb_table('enrol_payment');
        if ($dbman->table_exists($oldtransactiontable)) {
            $dbman->rename_table($oldtransactiontable, 'enrol_payment_transaction');
        }

        $oldsessiontable = new xmldb_table('enrol_payment_ipn');
        if ($dbman->table_exists($oldsessiontable)) {
            $dbman->rename_table($oldsessiontable, 'enrol_payment_session');
        }

        // Rename field discounted on table enrol_payment_session to code_given.
        $table = new xmldb_table('enrol_payment_session');
        $field = new xmldb_field('discounted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'multiple_userids');
        $newfield = new xmldb_field('code_given', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'multiple_userids');

        if (!($dbman->field_exists($table, $newfield))) {
            // Launch rename field code_given.
            $dbman->rename_field($table, $field, 'code_given');
        }

        // Payment savepoint reached.
        upgrade_plugin_savepoint(true, 2019013100, 'enrol', 'payment');
    }

    if ($oldversion < 2020012302) {

        // Rename field original_cost on table enrol_payment_session to originalcost.
        $table = new xmldb_table('enrol_payment_session');
        $field = new xmldb_field('original_cost', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'units');
        $field2 = new xmldb_field('tax_percent', XMLDB_TYPE_FLOAT, null, null, null, null, null, 'originalcost');
        $field3 = new xmldb_field('paypal_txn_id', XMLDB_TYPE_TEXT, null, null, null, null, null, 'taxpercent');
        $field4 = new xmldb_field('code_given', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'multipleuserids');
        $field5 = new xmldb_field('multiple_userids', XMLDB_TYPE_TEXT, null, null, null, null, null, 'multiple');

        // Launch rename field original_cost.
        $dbman->rename_field($table, $field, 'originalcost');
        $dbman->rename_field($table, $field2, 'taxpercent');
        $dbman->rename_field($table, $field3, 'paypaltxnid');
        $dbman->rename_field($table, $field4, 'codegiven');
        $dbman->rename_field($table, $field5, 'multipleuserids');

        // Rename field receiver_email on table enrol_payment_transaction to receiveremail.
        $table = new xmldb_table('enrol_payment_transaction');
        $field = new xmldb_field('receiver_email', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'business');
        $field2 = new xmldb_field('receiver_id', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'receiveremail');
        $field3 = new xmldb_field('item_name', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'receiverid');
        $field4 = new xmldb_field('option_name1', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'tax');
        $field5 = new xmldb_field('option_selection1_x', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'optionname1');
        $field6 = new xmldb_field('option_name2', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'optionselection1x');
        $field7 = new xmldb_field('option_selection2_x', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'optionname2');
        $field8 = new xmldb_field('payment_status', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'optionselection2x');
        $field9 = new xmldb_field('pending_reason', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'paymentstatus');
        $field10 = new xmldb_field('reason_code', XMLDB_TYPE_CHAR, '30', null, null, null, null, 'pendingreason');
        $field11 = new xmldb_field('txn_id', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'reasoncode');
        $field12 = new xmldb_field('parent_txn_id', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'txnid');
        $field13 = new xmldb_field('payment_type', XMLDB_TYPE_CHAR, '30', null, null, null, null, 'parenttxnid');

        // Launch rename field receiver_email.
        $dbman->rename_field($table, $field, 'receiveremail');
        $dbman->rename_field($table, $field2, 'receiverid');
        $dbman->rename_field($table, $field3, 'itemname');
        $dbman->rename_field($table, $field4, 'optionname1');
        $dbman->rename_field($table, $field5, 'optionselection1x');
        $dbman->rename_field($table, $field6, 'optionname2');
        $dbman->rename_field($table, $field7, 'optionselection2x');
        $dbman->rename_field($table, $field8, 'paymentstatus');
        $dbman->rename_field($table, $field9, 'pendingreason');
        $dbman->rename_field($table, $field10, 'reasoncode');
        $dbman->rename_field($table, $field11, 'txnid');
        $dbman->rename_field($table, $field12, 'parenttxnid');
        $dbman->rename_field($table, $field13, 'paymenttype');

        // Payment savepoint reached.
        upgrade_plugin_savepoint(true, 2020012302, 'enrol', 'payment');
    }

    return true;
}
