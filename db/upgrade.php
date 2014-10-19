<?php

// This file keeps track of upgrades to
// the groupmanagement module
//
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

function xmldb_groupmanagement_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();
    if ($oldversion < 2013070900) {

        if ($oldversion < 2012042500) {

            /// remove the no longer needed groupmanagement_answers DB table
            $groupmanagement_answers = new xmldb_table('groupmanagement_answers');
            $dbman->drop_table($groupmanagement_answers);

            /// change the groupmanagement_options.text (text) field as groupmanagement_options.groupid (int)
            $groupmanagement_options =  new xmldb_table('groupmanagement_options');
            $field_text =           new xmldb_field('text', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'groupmanagementid');
            $field_groupid =        new xmldb_field('groupid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'groupmanagementid');

            $dbman->rename_field($groupmanagement_options, $field_text, 'groupid');
            $dbman->change_field_type($groupmanagement_options, $field_groupid);

        }
        // Define table groupmanagement to be created
        $table = new xmldb_table('groupmanagement');

        // Adding fields to table groupmanagement
        $newField = $table->add_field('multipleenrollmentspossible', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $dbman->add_field($table, $newField); 


        upgrade_mod_savepoint(true, 2013070900, 'groupmanagement');
    }

    
    return true;
}


