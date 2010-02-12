<?php

function _newsletterUpdate()
{
    global $objDatabase;
    try{
        UpdateUtil::table(
            DBPREFIX.'module_newsletter_category',
            array(
                'id'                     => array('type' => 'INT(11)', 'notnull' => true, 'auto_increment' => true, 'primary' => true),
                'status'                 => array('type' => 'TINYINT(1)', 'notnull' => true, 'default' => '0'),
                'name'                   => array('type' => 'VARCHAR(255)', 'notnull' => true, 'default' => ''),
                'notification_email'     => array('type' => 'VARCHAR(250)')
            ),
            array(
                'name'                   => array('fields' => array('name'))
            )
        );

        UpdateUtil::table(
            DBPREFIX.'module_newsletter_confirm_mail',
            array(
                'id'             => array('type' => 'INT(1)', 'notnull' => true, 'auto_increment' => true, 'primary' => true),
                'title'          => array('type' => 'VARCHAR(255)', 'notnull' => true, 'default' => ''),
                'content'        => array('type' => 'LONGTEXT'),
                'recipients'     => array('type' => 'TEXT')
            )
        );

        UpdateUtil::table(
            DBPREFIX.'module_newsletter',
            array(
                'id'             => array('type' => 'INT(11)', 'notnull' => true, 'auto_increment' => true, 'primary' => true),
                'subject'        => array('type' => 'VARCHAR(255)', 'notnull' => true, 'default' => ''),
                'template'       => array('type' => 'INT(11)', 'notnull' => true, 'default' => '0'),
                'content'        => array('type' => 'TEXT'),
                'content_text'   => array('type' => 'TEXT'),
                'attachment'     => array('type' => 'ENUM(\'0\',\'1\')', 'notnull' => true, 'default' => '0'),
                'format'         => array('type' => 'ENUM(\'text\',\'html\',\'html/text\')', 'notnull' => true, 'default' => 'text', 'after' => 'attachment'),
                'priority'       => array('type' => 'TINYINT(1)', 'notnull' => true, 'default' => '0'),
                'sender_email'   => array('type' => 'VARCHAR(255)', 'notnull' => true, 'default' => ''),
                'sender_name'    => array('type' => 'VARCHAR(255)', 'notnull' => true, 'default' => ''),
                'return_path'    => array('type' => 'VARCHAR(255)', 'notnull' => true, 'default' => ''),
                'smtp_server'    => array('type' => 'INT(10)', 'unsigned' => true, 'notnull' => true, 'default' => '0'),
                'status'         => array('type' => 'INT(1)', 'notnull' => true, 'default' => '0'),
                'count'          => array('type' => 'INT(11)', 'notnull' => true, 'default' => '0'),
                'recipient_count'=> array('type' => 'INT(11)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'after' => 'count'),
                'date_create'    => array('type' => 'INT(14)', 'unsigned' => true, 'notnull' => true, 'default' => '0'),
                'date_sent'      => array('type' => 'INT(14)', 'unsigned' => true, 'notnull' => true, 'default' => '0'),
                'tmp_copy'       => array('type' => 'TINYINT(1)', 'notnull' => true, 'default' => '0')
            )
        );

        UpdateUtil::table(
            DBPREFIX.'module_newsletter_user',
            array(
                'id'         => array('type' => 'INT(11)', 'notnull' => true, 'auto_increment' => true, 'primary' => true),
                'code'       => array('type' => 'VARCHAR(255)', 'notnull' => true, 'default' => ''),
                'email'      => array('type' => 'VARCHAR(255)', 'notnull' => true, 'default' => ''),
                'uri'        => array('type' => 'VARCHAR(255)', 'notnull' => true, 'default' => '', 'after' => 'email'),
                'sex'        => array('type' => 'ENUM(\'m\',\'f\')', 'notnull' => false),
                'title'      => array('type' => 'INT(10)', 'unsigned' => true, 'notnull' => true, 'default' => '0'),
                'lastname'   => array('type' => 'VARCHAR(255)', 'notnull' => true, 'default' => ''),
                'firstname'  => array('type' => 'VARCHAR(255)', 'notnull' => true, 'default' => ''),
                'company'    => array('type' => 'VARCHAR(255)', 'notnull' => true, 'default' => ''),
                'street'     => array('type' => 'VARCHAR(255)', 'notnull' => true, 'default' => ''),
                'zip'        => array('type' => 'VARCHAR(255)', 'notnull' => true, 'default' => ''),
                'city'       => array('type' => 'VARCHAR(255)', 'notnull' => true, 'default' => ''),
                'country'    => array('type' => 'VARCHAR(255)', 'notnull' => true, 'default' => ''),
                'phone'      => array('type' => 'VARCHAR(255)', 'notnull' => true, 'default' => ''),
                'birthday'   => array('type' => 'VARCHAR(10)', 'notnull' => true, 'default' => '00-00-0000'),
                'status'     => array('type' => 'INT(1)', 'notnull' => true, 'default' => '0'),
                'emaildate'  => array('type' => 'INT(14)', 'unsigned' => true, 'notnull' => true, 'default' => '0')
            ),
            array(
                'email'      => array('fields' => array('email'), 'type' => 'UNIQUE'),
                'status'     => array('fields' => array('status'))
            )
        );
    }
    catch (UpdateException $e) {
        return UpdateUtil::DefaultActionHandler($e);
    }
    DBG::msg("Done checking tables.. going to check settings");
    $settings = array(
        'sender_mail'             => array('setid' =>  1, 'setname' => 'sender_mail',             'setvalue' => 'info@example.com', 'status' => 1),
        'sender_name'             => array('setid' =>  2, 'setname' => 'sender_name',             'setvalue' => 'admin',            'status' => 1),
        'reply_mail'              => array('setid' =>  3, 'setname' => 'reply_mail',              'setvalue' => 'info@example.com', 'status' => 1),
        'mails_per_run'           => array('setid' =>  4, 'setname' => 'mails_per_run',           'setvalue' => '30',               'status' => 1),
        'text_break_after'        => array('setid' =>  5, 'setname' => 'text_break_after',        'setvalue' => '100',              'status' => 1),
        'test_mail'               => array('setid' =>  6, 'setname' => 'test_mail',               'setvalue' => 'info@example.com', 'status' => 1),
        'overview_entries_limit'  => array('setid' =>  7, 'setname' => 'overview_entries_limit',  'setvalue' => '10',               'status' => 1),
        'rejected_mail_operation' => array('setid' =>  8, 'setname' => 'rejected_mail_operation', 'setvalue' => 'delete',           'status' => 1),
        'defUnsubscribe'          => array('setid' =>  9, 'setname' => 'defUnsubscribe',          'setvalue' => '0',                'status' => 1),
        'notifyOnUnsubscribe'     => array('setid' => 10, 'setname' => 'notifyOnUnsubscribe',     'setvalue' => '1',                'status' => 1),
    );

    try {
        DBG::msg("Reading current settings");
        $res = UpdateUtil::sql("SELECT * FROM ".DBPREFIX."module_newsletter_settings");
        while (!$res->EOF) {
            $field = $res->fields['setname'];
            DBG::msg("...merging $field with default settings");
            $settings[$field]['setvalue'] = $res->fields['setvalue'];
            $res->MoveNext();
        }
        DBG::msg("Updating settings");
        foreach ($settings as $entry) {
            $setid = intval    ($entry['setid']);
            $field = addslashes($entry['setname']);
            $value = addslashes($entry['setvalue']);
            $status= intval    ($entry['status']);
            DBG::msg("...deleting field $field");
            UpdateUtil::sql("DELETE FROM ".DBPREFIX."module_newsletter_settings WHERE setid = '$setid' OR setname = '$field'");
            DBG::msg("...rewriting field $field");
            UpdateUtil::sql("
                INSERT INTO ".DBPREFIX."module_newsletter_settings
                    (setid, setname, setvalue, status)
                VALUES (
                    '$setid', '$field', '$value', '$status'
                );
            ");

        }
        DBG::msg("Done with newsletter update");
    }
    catch (UpdateException $e) {
        return UpdateUtil::DefaultActionHandler($e);
    }

    try {
        DBG::msg("Setting recipient count");
        UpdateUtil::sql("UPDATE `".DBPREFIX."module_newsletter` AS n SET n.`recipient_count` = (SELECT COUNT(1) FROM `".DBPREFIX."module_newsletter_tmp_sending` WHERE `newsletter`=n.`id` GROUP BY `newsletter`)");
    }
    catch (UpdateException $e) {
        return UpdateUtil::DefaultActionHandler($e);
    }

    // Add notification recipians to confirm_mail table
    if (!isset($arrUserColumns['RECIPIENTS'])) {
        $query = "
            ALTER TABLE `".DBPREFIX."module_newsletter_confirm_mail`
            ADD `recipients`  MEDIUMTEXT NOT NULL;
        ";
        $objResult = $objDatabase->Execute($query);
        if (!$objResult) {
            return _databaseError($query, $objDatabase->ErrorMsg());
        }
    }

    //insert notification values
    $query = "SELECT id FROM `".DBPREFIX."module_newsletter_confirm_mail` WHERE id='3'";
    $objCheck = $objDatabase->SelectLimit($query, 1);
    if ($objCheck !== false) {
        if ($objCheck->RecordCount() == 0) {
            $query =     "INSERT INTO `".DBPREFIX."module_newsletter_confirm_mail` (`id` ,`title` ,`content` ,`recipients`) VALUES ('3', '[[url]] - Neue Newsletter Empf�nger [[action]]', 'Hallo Admin Eine neue Empf�nger [[action]] in ihrem Newsletter System. Automatisch generierte Nachricht [[date]]', '');";
            if ($objDatabase->Execute($query) === false) {
                return _databaseError($query, $objDatabase->ErrorMsg());
            }
        }
    } else {
        return _databaseError($query, $objDatabase->ErrorMsg());
    }

    //insert settings values
    $query = "SELECT setid FROM `".DBPREFIX."module_newsletter_settings` WHERE setname='notificationSubscribe'";
    $objCheck = $objDatabase->SelectLimit($query, 1);
    if ($objCheck !== false) {
        if ($objCheck->RecordCount() == 0) {
            $query =     "INSERT INTO `".DBPREFIX."module_newsletter_settings` (`setid` ,`setname` ,`setvalue` ,`status`) VALUES ('11', 'notificationSubscribe', '1', '1');
            ";
            if ($objDatabase->Execute($query) === false) {
                return _databaseError($query, $objDatabase->ErrorMsg());
            }
        }
    } else {
        return _databaseError($query, $objDatabase->ErrorMsg());
    }

    $query = "SELECT setid FROM `".DBPREFIX."module_newsletter_settings` WHERE setname='notificationUnsubscribe'";
    $objCheck = $objDatabase->SelectLimit($query, 1);
    if ($objCheck !== false) {
        if ($objCheck->RecordCount() == 0) {
            $query =     "INSERT INTO `".DBPREFIX."module_newsletter_settings` (`setid` ,`setname` ,`setvalue` ,`status`) VALUES ('12', 'notificationUnsubscribe', '1', '1');
            ";
            if ($objDatabase->Execute($query) === false) {
                return _databaseError($query, $objDatabase->ErrorMsg());
            }
        }
    } else {
        return _databaseError($query, $objDatabase->ErrorMsg());
    }

    return true;
}

?>
