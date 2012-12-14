<?php

function _filesharingUpdate()
{
    try {

        //Contrexx 3.0.1 (initial creation)
        \Cx\Lib\UpdateUtil::table(
            DBPREFIX.'module_filesharing',
            array(
                'id'                 => array('type' => 'INT(10)', 'notnull' => true, 'auto_increment' => true, 'primary' => true),
                'file'               => array('type' => 'VARCHAR(250)', 'notnull' => true, 'after' => 'id'),
                'source'             => array('type' => 'VARCHAR(250)', 'notnull' => true, 'after' => 'file'),
                'cmd'                => array('type' => 'VARCHAR(50)', 'notnull' => true, 'after' => 'source'),
                'hash'               => array('type' => 'VARCHAR(50)', 'notnull' => true, 'after' => 'cmd'),
                'check'              => array('type' => 'VARCHAR(50)', 'notnull' => true, 'after' => 'hash'),
                'expiration_date'    => array('type' => 'TIMESTAMP', 'notnull' => false, 'default' => NULL, 'after' => 'check'),
                'upload_id'          => array('type' => 'INT(10)', 'notnull' => false, 'default' => NULL, 'after' => 'expiration_date')
            )
        );

        \Cx\Lib\UpdateUtil::table(
            DBPREFIX.'module_filesharing_mail_template',
            array(
                'id'         => array('type' => 'INT(10)', 'notnull' => true, 'auto_increment' => true, 'primary' => true),
                'lang_id'    => array('type' => 'INT(1)', 'notnull' => true, 'after' => 'id'),
                'subject'    => array('type' => 'VARCHAR(250)', 'notnull' => true, 'after' => 'lang_id'),
                'content'    => array('type' => 'TEXT', 'notnull' => true, 'after' => 'subject')
            )
        );
        \Cx\Lib\UpdateUtil::sql('
            INSERT INTO `'.DBPREFIX.'module_filesharing_mail_template` (`id`, `lang_id`, `subject`, `content`)
            VALUES  (1, 1, "Jemand teilt eine Datei mit Ihnen", "Guten Tag,\r\n\r\nJemand hat auf [[DOMAIN]] eine Datei mit Ihnen geteilt.\r\n\r\n<!-- BEGIN filesharing_file -->\r\nDownload-Link: [[FILE_DOWNLOAD]]\r\n<!-- END filesharing_file -->\r\n\r\nDie Person hat eine Nachricht hinterlassen:\r\n[[MESSAGE]]\r\n\r\nFreundliche Grüsse"),
                    (2, 2, "Somebody is sharing a file with you", "Hi,\r\n\r\nSomebody shared a file with you on [[DOMAIN]].\r\n\r\n<!-- BEGIN filesharing_file -->\r\nDownload link: [[FILE_DOWNLOAD]]\r\n<!-- END filesharing_file -->\r\n\r\nThe person has left a message for you:\r\n[[MESSAGE]]\r\n\r\nBest regards")
            ON DUPLICATE KEY UPDATE `id` = `id`
        ');

    } catch (UpdateException $e) {
        return \Cx\Lib\UpdateUtil::DefaultActionHandler($e);
    }
}
