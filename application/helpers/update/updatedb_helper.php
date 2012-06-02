<?PHP
/*
* LimeSurvey
* Copyright (C) 2007-2011 The LimeSurvey Project Team / Carsten Schmitz
* All rights reserved.
* License: GNU/GPL License v2 or later, see LICENSE.php
* LimeSurvey is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*
*	$Id$
*/

// There will be a file for each database (accordingly named to the dbADO scheme)
// where based on the current database version the database is upgraded
// For this there will be a settings table which holds the last time the database was upgraded

function db_upgrade_all($oldversion) {
    /// This function does anything necessary to upgrade
    /// older versions to match current functionality
    global $modifyoutput, $usertemplaterootdir, $standardtemplaterootdir;
    $usertemplaterootdir = Yii::app()->getConfig('usertemplaterootdir');
    $standardtemplaterootdir = Yii::app()->getConfig('standardtemplaterootdir');
    $clang = Yii::app()->lang;
    echo str_pad($clang->gT('The LimeSurvey database is being upgraded').' ('.date('Y-m-d H:i:s').')',14096).".<br /><br />". $clang->gT('Please be patient...')."<br /><br />\n";

    $sDBDriverName=Yii::app()->db->getDriverName();
    if ($sDBDriverName=='mysqli') $sDBDriverName='mysql';
    if ($sDBDriverName=='sqlsrv') $sDBDriverName='mssql';

    // Special customization because Yii is too limited to handle a varchar field of a length other than 255 in a cross-DB compatible way
    // see http://www.yiiframework.com/forum/index.php/topic/32289-cross-db-compatible-varchar-field-length-definitions/
    // and http://github.com/yiisoft/yii/issues/765
    if ($sDBDriverName=='pgsql')
    {
        Yii::app()->setConfig('char',$sChar='character');
        Yii::app()->setConfig('varchar',$sVarchar='character varying');
        Yii::app()->setConfig('autoincrement', $sAutoIncrement='serial');
    }
    elseif ($sDBDriverName=='mssql')
    {
        Yii::app()->setConfig('char',$sChar='char');
        Yii::app()->setConfig('varchar',$sVarchar='varchar');
        Yii::app()->setConfig('autoincrement', $sAutoIncrement='integer NOT NULL IDENTITY (1,1)');
    }
    else
    {
        Yii::app()->setConfig('char',$sChar='char');
        Yii::app()->setConfig('varchar',$sVarchar='varchar');
        Yii::app()->setConfig('autoincrement', $sAutoIncrement='int(11) NOT NULL AUTO_INCREMENT');
    }

    if ($oldversion < 111)
    {
        // Language upgrades from version 110 to 111 because the language names did change

        $aOldNewLanguages=array('german_informal'=>'german-informal',
        'cns'=>'cn-Hans',
        'cnt'=>'cn-Hant',
        'pt_br'=>'pt-BR',
        'gr'=>'el',
        'jp'=>'ja',
        'si'=>'sl',
        'se'=>'sv',
        'vn'=>'vi');
        foreach  ($aOldNewLanguages as $sOldLanguageCode=>$sNewLanguageCode)
        {
            alterLanguageCode($sOldLanguageCode,$sNewLanguageCode);
        }
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>111),"stg_name='DBVersion'");
    }

    if ($oldversion < 112) {
        // New size of the username field (it was previously 20 chars wide)
        Yii::app()->db->createCommand()->alterColumn('{{users}}','users_name',"{$sVarchar}(64) NOT NULL");
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>112),"stg_name='DBVersion'");
    }

    if ($oldversion < 113) {
        //Fixes the collation for the complete DB, tables and columns

        if ($sDBDriverName=='mysql')
        {
            $databasename=getDBConnectionStringProperty('dbname');
            fixMySQLCollations();
            modifyDatabase("","ALTER DATABASE `$databasename` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;");echo $modifyoutput; flush();@ob_flush();
        }
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>113),"stg_name='DBVersion'");
    }

    if ($oldversion < 114) {
        Yii::app()->db->createCommand()->alterColumn('{{saved_control}}','email',"{$sVarchar}(320) NOT NULL");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','adminemail',"{$sVarchar}(320) NOT NULL");
        Yii::app()->db->createCommand()->alterColumn('{{users}}','email',"{$sVarchar}(320) NOT NULL");
        Yii::app()->db->createCommand()->insert('{{settings_global}}',array('stg_name'=>'SessionName','stg_value'=>'ls'.randomChars(25,'abcdefghijklmnopqrstuvw123456789')));
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>114),"stg_name='DBVersion'");
    }

    if ($oldversion < 126) {

        Yii::app()->db->createCommand()->addColumn('{{surveys}}','printanswers',"{$sVarchar}(1) default 'N'");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','listpublic',"{$sVarchar}(1) default 'N'");

        upgradeSurveyTables126();
        upgradeTokenTables126();

        // Create quota table
        Yii::app()->db->createCommand()->createTable('{{quota}}',array(
        'id' => 'pk',
        'sid' => 'integer',
        'qlimit' => 'integer',
        'name' => 'string',
        'action' => 'integer',
        'active' => 'integer NOT NULL DEFAULT 1'
        ));

        // Create quota_members table
        Yii::app()->db->createCommand()->createTable('{{quota_members}}',array(
        'id' => 'pk',
        'sid' => 'integer',
        'qid' => 'integer',
        'quota_id' => 'integer',
        'code' => $sVarchar.'(5)'
        ));
        Yii::app()->db->createCommand()->createIndex('sid','{{quota_members}}','sid,qid,quota_id,code',true);


        // Create templates_rights table
        Yii::app()->db->createCommand()->createTable('{{templates_rights}}',array(
        'uid' => 'integer NOT NULL',
        'folder' => 'string NOT NULL',
        'use' => 'integer',
        'PRIMARY KEY (uid, folder)'
        ));

        // Create templates table
        Yii::app()->db->createCommand()->createTable('{{templates}}',array(
        'folder' => 'string NOT NULL',
        'creator' => 'integer NOT NULL',
        'PRIMARY KEY (folder)'
        ));

        // Rename Norwegian language codes
        alterLanguageCode('no','nb');

        Yii::app()->db->createCommand()->addColumn('{{surveys}}','htmlemail',"{$sVarchar}(1) default 'N'");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','tokenanswerspersistence',"{$sVarchar}(1) default 'N'");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','usecaptcha',"{$sVarchar}(1) default 'N'");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','bounce_email','text');
        Yii::app()->db->createCommand()->addColumn('{{users}}','htmleditormode',"{$sVarchar}(7) default 'default'");
        Yii::app()->db->createCommand()->addColumn('{{users}}','superadmin',"integer NOT NULL default '0'");
        Yii::app()->db->createCommand()->addColumn('{{questions}}','lid1',"integer NOT NULL default '0'");

        Yii::app()->db->createCommand()->alterColumn('{{conditions}}','value',"string NOT NULL default ''");
        Yii::app()->db->createCommand()->alterColumn('{{labels}}','title','text');

        Yii::app()->db->createCommand()->update('{{users}}',array('superadmin'=>1),"create_survey=1 AND create_user=1 AND move_user=1 AND delete_user=1 AND configurator=1");
        Yii::app()->db->createCommand()->update('{{conditions}}',array('method'=>'=='),"(method is null) or method='' or method='0'");

        Yii::app()->db->createCommand()->dropColumn('{{users}}','move_user');

        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>126),"stg_name='DBVersion'");
    }

    if ($oldversion < 127) {
        modifyDatabase("","create index answers_idx2 on {{answers}} (sortorder)"); echo $modifyoutput;
        modifyDatabase("","create index assessments_idx2 on {{assessments}} (sid)"); echo $modifyoutput;
        modifyDatabase("","create index assessments_idx3 on {{assessments}} (gid)"); echo $modifyoutput;
        modifyDatabase("","create index conditions_idx2 on {{conditions}} (qid)"); echo $modifyoutput;
        modifyDatabase("","create index conditions_idx3 on {{conditions}} (cqid)"); echo $modifyoutput;
        modifyDatabase("","create index groups_idx2 on {{groups}} (sid)"); echo $modifyoutput;
        modifyDatabase("","create index question_attributes_idx2 on {{question_attributes}} (qid)"); echo $modifyoutput;
        modifyDatabase("","create index questions_idx2 on {{questions}} (sid)"); echo $modifyoutput;
        modifyDatabase("","create index questions_idx3 on {{questions}} (gid)"); echo $modifyoutput;
        modifyDatabase("","create index questions_idx4 on {{questions}} (type)"); echo $modifyoutput;
        modifyDatabase("","create index quota_idx2 on {{quota}} (sid)"); echo $modifyoutput;
        modifyDatabase("","create index saved_control_idx2 on {{saved_control}} (sid)"); echo $modifyoutput;
        modifyDatabase("","create index user_in_groups_idx1 on {{user_in_groups}} (ugid, uid)"); echo $modifyoutput;
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>127),"stg_name='DBVersion'");
    }

    if ($oldversion < 128) {
        upgradeTokens128();
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>128),"stg_name='DBVersion'");
    }

    if ($oldversion < 129) {
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','startdate',"datetime");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','usestartdate',"{$sVarchar}(1) NOT NULL default 'N'");
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>129),"stg_name='DBVersion'");
    }

    if ($oldversion < 130)
    {
        Yii::app()->db->createCommand()->addColumn('{{conditions}}','scenario',"integer NOT NULL default '1'");
        Yii::app()->db->createCommand()->update('{{conditions}}',array('scenario'=>1),"(scenario is null) or scenario='' or scenario=0");
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>130),"stg_name='DBVersion'");
    }

    if ($oldversion < 131)
    {
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','publicstatistics',"{$sVarchar}(1) NOT NULL default 'N'");
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>131),"stg_name='DBVersion'");
    }

    if ($oldversion < 132)
    {
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','publicgraphs',"{$sVarchar}(1) NOT NULL default 'N'");
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>132),"stg_name='DBVersion'");
    }

    if ($oldversion < 133)
    {
        Yii::app()->db->createCommand()->addColumn('{{users}}','one_time_pw','text');
        // Add new assessment setting
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','assessments',"{$sVarchar}(1) NOT NULL default 'N'");
        // add new assessment value fields to answers & labels
        Yii::app()->db->createCommand()->addColumn('{{answers}}','assessment_value',"integer NOT NULL default '0'");
        Yii::app()->db->createCommand()->addColumn('{{labels}}','assessment_value',"integer NOT NULL default '0'");
        // copy any valid codes from code field to assessment field
        switch ($sDBDriverName){
            case 'mysql':
                Yii::app()->db->createCommand("UPDATE {{answers}} SET assessment_value=CAST(`code` as SIGNED) where `code` REGEXP '^-?[0-9]+$'")->execute();
                Yii::app()->db->createCommand("UPDATE {{labels}} SET assessment_value=CAST(`code` as SIGNED) where `code` REGEXP '^-?[0-9]+$'")->execute();
                break;
            case 'mssql':
                Yii::app()->db->createCommand()->update('{{answers}}',array('assessment_value=CAST([code] as int)'));
                Yii::app()->db->createCommand()->update('{{labels}}',array('assessment_value=CAST([code] as int)'));
                break;
            case 'pgsql':
                Yii::app()->db->createCommand()->update('{{answers}}',array('assessment_value=CAST(code as integer)'));
                Yii::app()->db->createCommand()->update('{{labels}}',array('assessment_value=CAST(code as integer)'));
                break;
            default: die('Unkown database type');
        }
        // activate assessment where assessment rules exist
        Yii::app()->db->createCommand("UPDATE {{surveys}} SET assessments='Y' where sid in (SELECT sid FROM {{assessments}} group by sid)")->execute();
        // add language field to assessment table
        Yii::app()->db->createCommand()->addColumn('{{assessments}}','language',"{$sVarchar}(20) NOT NULL default 'en'");
        // update language field with default language of that particular survey
        Yii::app()->db->createCommand("UPDATE {{assessments}} SET language=(select language from {{surveys}} where sid={{assessments}}.sid)")->execute();
        // copy assessment link to message since from now on we will have HTML assignment messages
        Yii::app()->db->createCommand("UPDATE {{assessments}} set message=concat(replace(message,'/''',''''),'<br /><a href=\"',link,'\">',link,'</a>')")->execute();
        // drop the old link field
        Yii::app()->db->createCommand()->dropColumn('{{assessments}}','link');

        // Add new fields to survey language settings
        Yii::app()->db->createCommand()->addColumn('{{surveys_languagesettings}}','surveyls_url',"string");
        Yii::app()->db->createCommand()->addColumn('{{surveys_languagesettings}}','surveyls_endtext','text');
        // copy old URL fields ot language specific entries
        Yii::app()->db->createCommand("UPDATE {{surveys_languagesettings}} set surveyls_url=(select url from {{surveys}} where sid={{surveys_languagesettings}}.surveyls_survey_id)")->execute();
        // drop old URL field
        Yii::app()->db->createCommand()->dropColumn('{{surveys}}','url');
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>133),"stg_name='DBVersion'");
    }

    if ($oldversion < 134)
    {
        // Add new tokens setting
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','usetokens',"{$sVarchar}(1) NOT NULL default 'N'");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','attributedescriptions','text');
        Yii::app()->db->createCommand()->dropColumn('{{surveys}}','attribute1');
        Yii::app()->db->createCommand()->dropColumn('{{surveys}}','attribute2');
        upgradeTokenTables134();
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>134),"stg_name='DBVersion'");
    }

    if ($oldversion < 135)
    {
        Yii::app()->db->createCommand()->alterColumn('{{question_attributes}}','value','text');
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>135),"stg_name='DBVersion'");
    }

    if ($oldversion < 136) //New Quota Functions
    {
        Yii::app()->db->createCommand()->addColumn('{{quota}}','autoload_url',"integer NOT NULL default 0");
        // Create quota table
        $fields = array(
        'quotals_id' => 'pk',
        'quotals_quota_id' => 'integer NOT NULL DEFAULT 0',
        'quotals_language' => "{$sVarchar}(45) NOT NULL default 'en'",
        'quotals_name' => 'string',
        'quotals_message' => 'text NOT NULL',
        'quotals_url' => 'string',
        'quotals_urldescrip' => 'string',
        );
        Yii::app()->db->createCommand()->createTable('{{quota_languagesettings}}',$fields);
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>136),"stg_name='DBVersion'");
    }

    if ($oldversion < 137) //New Quota Functions
    {
        Yii::app()->db->createCommand()->addColumn('{{surveys_languagesettings}}','surveyls_dateformat',"integer NOT NULL default 1");
        Yii::app()->db->createCommand()->addColumn('{{users}}','dateformat',"integer NOT NULL default 1");
        Yii::app()->db->createCommand()->update('{{surveys}}',array('startdate'=>NULL),"usestartdate='N'");
        Yii::app()->db->createCommand()->update('{{surveys}}',array('expires'=>NULL),"useexpiry='N'");
        Yii::app()->db->createCommand()->dropColumn('{{surveys}}','useexpiry');
        Yii::app()->db->createCommand()->dropColumn('{{surveys}}','usestartdate');
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>137),"stg_name='DBVersion'");
    }

    if ($oldversion < 138) //Modify quota field
    {
        Yii::app()->db->createCommand()->alterColumn('{{quota_members}}','code',"{$sVarchar}(11) default NULL");
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>138),"stg_name='DBVersion'");
    }

    if ($oldversion < 139) //Modify quota field
    {
        upgradeSurveyTables139();
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>139),"stg_name='DBVersion'");
    }

    if ($oldversion < 140) //Modify surveys table
    {
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','emailresponseto','text');
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>140),"stg_name='DBVersion'");
    }

    if ($oldversion < 141) //Modify surveys table
    {
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','tokenlength','integer NOT NULL default 15');
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>141),"stg_name='DBVersion'");
    }

    if ($oldversion < 142) //Modify surveys table
    {
        upgradeQuestionAttributes142();
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','expires',"datetime");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','startdate',"datetime");
        Yii::app()->db->createCommand()->update('{{question_attributes}}',array('value'=>0),"value='false'");
        Yii::app()->db->createCommand()->update('{{question_attributes}}',array('value'=>1),"value='true'");
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>142),"stg_name='DBVersion'");
    }

    if ($oldversion < 143)
    {
        Yii::app()->db->createCommand()->addColumn('{{questions}}','parent_qid','integer NOT NULL default 0');
        Yii::app()->db->createCommand()->addColumn('{{answers}}','scale_id','integer NOT NULL default 0');
        Yii::app()->db->createCommand()->addColumn('{{questions}}','scale_id','integer NOT NULL default 0');
        Yii::app()->db->createCommand()->addColumn('{{questions}}','same_default','integer NOT NULL default 0');
        dropPrimaryKey('answers');
        addPrimaryKey('answers', array('qid','code','language','scale_id'));

        $fields = array(
        'qid' => "integer NOT NULL default 0",
        'scale_id' => 'integer NOT NULL default 0',
        'sqid' => 'integer  NOT NULL default 0',
        'language' => $sVarchar.'(20) NOT NULL',
        'specialtype' => $sVarchar."(20) NOT NULL default ''",
        'defaultvalue' => 'text',
        );
        Yii::app()->db->createCommand()->createTable('{{defaultvalues}}',$fields);
        addPrimaryKey('defaultvalues', array('qid','specialtype','language','scale_id','sqid'));

        // -Move all 'answers' that are subquestions to the questions table
        // -Move all 'labels' that are answers to the answers table
        // -Transscribe the default values where applicable
        // -Move default values from answers to questions
        upgradeTables143();

        Yii::app()->db->createCommand()->dropColumn('{{answers}}','default_value');
        Yii::app()->db->createCommand()->dropColumn('{{questions}}','lid');
        Yii::app()->db->createCommand()->dropColumn('{{questions}}','lid1');

        modifyDatabase("", "CREATE TABLE prefix_sessions(
        sesskey VARCHAR( 64 ) NOT NULL DEFAULT '',
        expiry DATETIME NOT NULL ,
        expireref VARCHAR( 250 ) DEFAULT '',
        created DATETIME NOT NULL ,
        modified DATETIME NOT NULL ,
        sessdata LONGTEXT,
        PRIMARY KEY ( sesskey ) ,
        INDEX sess2_expiry( expiry ),
        INDEX sess2_expireref( expireref ))  CHARACTER SET utf8 COLLATE utf8_unicode_ci;"); echo $modifyoutput; flush();@ob_flush();

        // Move all user templates to the new user template directory
        echo sprintf($clang->gT("Moving user templates to new location at %s..."),$usertemplaterootdir)."<br />";
        $myDirectory = opendir($standardtemplaterootdir);
        $aFailedTemplates=array();
        // get each entry
        while($entryName = readdir($myDirectory)) {
            if (!in_array($entryName,array('.','..','.svn')) && is_dir($standardtemplaterootdir.DIRECTORY_SEPARATOR.$entryName) && !isStandardTemplate($entryName))
            {
                if (!rename($standardtemplaterootdir.DIRECTORY_SEPARATOR.$entryName,$usertemplaterootdir.DIRECTORY_SEPARATOR.$entryName))
                {
                    $aFailedTemplates[]=$entryName;
                };
            }
        }
        if (count($aFailedTemplates)>0)
        {
            echo "The following templates at {$standardtemplaterootdir} could not be moved to the new location at {$usertemplaterootdir}:<br /><ul>";
            foreach ($aFailedTemplates as $sFailedTemplate)
            {
                echo "<li>{$sFailedTemplate}</li>";
            }
            echo "</ul>Please move these templates manually after the upgrade has finished.<br />";
        }
        // close directory
        closedir($myDirectory);
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>143),"stg_name='DBVersion'");
    }

    if ($oldversion < 145)
    {
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','savetimings',"{$sVarchar}(1) NULL default 'N'");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','showXquestions',"{$sVarchar}(1) NULL default 'Y'");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','showgroupinfo',"{$sVarchar}(1) NULL default 'B'");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','shownoanswer',"{$sVarchar}(1) NULL default 'Y'");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','showqnumcode',"{$sVarchar}(1) NULL default 'X'");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','bouncetime',"integer");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','bounceprocessing',"{$sVarchar}(1) NULL default 'N'");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','bounceaccounttype',"{$sVarchar}(4)");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','bounceaccounthost',"{$sVarchar}(200)");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','bounceaccountpass',"{$sVarchar}(100)");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','bounceaccountencryption',"{$sVarchar}(3)");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','bounceaccountuser',"{$sVarchar}(200)");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','showwelcome',"{$sVarchar}(1) default 'Y'");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','showprogress',"{$sVarchar}(1) default 'Y'");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','allowjumps',"{$sVarchar}(1) default 'N'");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','navigationdelay',"integer default 0");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','nokeyboard',"{$sVarchar}(1) default 'N'");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','alloweditaftercompletion',"{$sVarchar}(1) default 'N'");


        $fields = array(
        'sid' => "integer NOT NULL",
        'uid' => "integer NOT NULL",
        'permission' => $sVarchar.'(20) NOT NULL',
        'create_p' => "integer NOT NULL default 0",
        'read_p' => "integer NOT NULL default 0",
        'update_p' => "integer NOT NULL default 0",
        'delete_p' => "integer NOT NULL default 0",
        'import_p' => "integer NOT NULL default 0",
        'export_p' => "integer NOT NULL default 0"
        );
        Yii::app()->db->createCommand()->createTable('{{survey_permissions}}',$fields);
        addPrimaryKey('survey_permissions', array('sid','uid','permission'));

        upgradeSurveyPermissions145();

        // drop the old survey rights table
        Yii::app()->db->createCommand()->dropTable('{{surveys_rights}}');

        // Add new fields for email templates
        Yii::app()->db->createCommand()->addColumn('{{surveys_languagesettings}}','email_admin_notification_subj',"string");
        Yii::app()->db->createCommand()->addColumn('{{surveys_languagesettings}}','email_admin_responses_subj',"string");
        Yii::app()->db->createCommand()->addColumn('{{surveys_languagesettings}}','email_admin_notification',"text");
        Yii::app()->db->createCommand()->addColumn('{{surveys_languagesettings}}','email_admin_responses',"text");

        //Add index to questions table to speed up subquestions
        Yii::app()->db->createCommand()->createIndex('parent_qid_idx','{{questions}}','parent_qid');

        Yii::app()->db->createCommand()->addColumn('{{surveys}}','emailnotificationto',"text");

        upgradeSurveys145();
        Yii::app()->db->createCommand()->dropColumn('{{surveys}}','notification');
        Yii::app()->db->createCommand()->alterColumn('{{conditions}}','method',"{$sVarchar}(5) NOT NULL default ''");

        Yii::app()->db->createCommand()->renameColumn('{{surveys}}','private','anonymized');
        Yii::app()->db->createCommand()->update('{{surveys}}',array('anonymized'=>'N'),"anonymized is NULL");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','anonymized',"{$sVarchar}(1) NOT NULL default 'N'");

        //now we clean up things that were not properly set in previous DB upgrades
        Yii::app()->db->createCommand()->update('{{answers}}',array('answer'=>''),"answer is NULL");
        Yii::app()->db->createCommand()->update('{{assessments}}',array('scope'=>''),"scope is NULL");
        Yii::app()->db->createCommand()->update('{{assessments}}',array('name'=>''),"name is NULL");
        Yii::app()->db->createCommand()->update('{{assessments}}',array('message'=>''),"message is NULL");
        Yii::app()->db->createCommand()->update('{{assessments}}',array('minimum'=>''),"minimum is NULL");
        Yii::app()->db->createCommand()->update('{{assessments}}',array('maximum'=>''),"maximum is NULL");
        Yii::app()->db->createCommand()->update('{{groups}}',array('group_name'=>''),"group_name is NULL");
        Yii::app()->db->createCommand()->update('{{labels}}',array('code'=>''),"code is NULL");
        Yii::app()->db->createCommand()->update('{{labelsets}}',array('label_name'=>''),"label_name is NULL");
        Yii::app()->db->createCommand()->update('{{questions}}',array('type'=>'T'),"type is NULL");
        Yii::app()->db->createCommand()->update('{{questions}}',array('title'=>''),"title is NULL");
        Yii::app()->db->createCommand()->update('{{questions}}',array('question'=>''),"question is NULL");
        Yii::app()->db->createCommand()->update('{{questions}}',array('other'=>'N'),"other is NULL");

        Yii::app()->db->createCommand()->alterColumn('{{answers}}','answer',"text NOT NULL");
        Yii::app()->db->createCommand()->alterColumn('{{answers}}','assessment_value',"integer NOT NULL default '0'");
        Yii::app()->db->createCommand()->alterColumn('{{assessments}}','scope',"{$sVarchar}(5) NOT NULL default ''");
        Yii::app()->db->createCommand()->alterColumn('{{assessments}}','name',"text NOT NULL");
        Yii::app()->db->createCommand()->alterColumn('{{assessments}}','message',"text NOT NULL");
        Yii::app()->db->createCommand()->alterColumn('{{assessments}}','minimum',"{$sVarchar}(50) NOT NULL default ''");
        Yii::app()->db->createCommand()->alterColumn('{{assessments}}','maximum',"{$sVarchar}(50) NOT NULL default ''");
        // change the primary index to include language
        if ($sDBDriverName=='mysql') // special treatment for mysql because this needs to be in one step since an AUTOINC field is involved
        {
            Yii::app()->db->createCommand("ALTER TABLE {{assessments}} DROP PRIMARY KEY, ADD PRIMARY KEY  USING BTREE(`id`, `language`)")->execute();
        }
        else
        {
            dropPrimaryKey('assessments');
            addPrimaryKey('assessments',array('id','language'));
        }


        Yii::app()->db->createCommand()->alterColumn('{{conditions}}','cfieldname',"{$sVarchar}(50) NOT NULL default ''");
        Yii::app()->db->createCommand()->alterColumn('{{defaultvalues}}','specialtype',"{$sVarchar}(20) NOT NULL default ''");
        Yii::app()->db->createCommand()->alterColumn('{{groups}}','group_name',"{$sVarchar}(100) NOT NULL default ''");
        Yii::app()->db->createCommand()->alterColumn('{{labels}}','code',"{$sVarchar}(5) NOT NULL default ''");
        Yii::app()->db->createCommand()->alterColumn('{{labels}}','language',"{$sVarchar}(20) NOT NULL default 'en'");
        Yii::app()->db->createCommand()->alterColumn('{{labelsets}}','label_name',"{$sVarchar}(100) NOT NULL default ''");
        Yii::app()->db->createCommand()->alterColumn('{{questions}}','parent_qid',"integer NOT NULL default 0");
        Yii::app()->db->createCommand()->alterColumn('{{questions}}','title',"{$sVarchar}(20) NOT NULL default ''");
        Yii::app()->db->createCommand()->alterColumn('{{questions}}','question',"text NOT NULL");
        Yii::app()->db->createCommand()->alterColumn('{{questions}}','type',"{$sVarchar}(1) NOT NULL default 'T'");
        Yii::app()->db->createCommand()->alterColumn('{{questions}}','other',"{$sVarchar}(1) NOT NULL default 'N'");
        Yii::app()->db->createCommand()->alterColumn('{{questions}}','mandatory',"{$sVarchar}(1) NULL default NULL");
        Yii::app()->db->createCommand()->alterColumn('{{question_attributes}}','attribute',"{$sVarchar}(50) NULL default NULL");
        Yii::app()->db->createCommand()->alterColumn('{{quota}}','qlimit',"integer NULL");

        Yii::app()->db->createCommand()->update('{{saved_control}}',array('identifier'=>''),"identifier is NULL");
        Yii::app()->db->createCommand()->alterColumn('{{saved_control}}','identifier',"text NOT NULL");
        Yii::app()->db->createCommand()->update('{{saved_control}}',array('access_code'=>''),"access_code is NULL");
        Yii::app()->db->createCommand()->alterColumn('{{saved_control}}','access_code',"text NOT NULL");
        Yii::app()->db->createCommand()->alterColumn('{{saved_control}}','email',"{$sVarchar}(320) NULL default NULL");
        Yii::app()->db->createCommand()->update('{{saved_control}}',array('ip'=>''),"ip is NULL");
        Yii::app()->db->createCommand()->alterColumn('{{saved_control}}','ip',"text NOT NULL");
        Yii::app()->db->createCommand()->update('{{saved_control}}',array('saved_thisstep'=>''),"saved_thisstep is NULL");
        Yii::app()->db->createCommand()->alterColumn('{{saved_control}}','saved_thisstep',"text NOT NULL");
        Yii::app()->db->createCommand()->update('{{saved_control}}',array('status'=>''),"status is NULL");
        Yii::app()->db->createCommand()->alterColumn('{{saved_control}}','status',"{$sVarchar}(1) NOT NULL default ''");
        Yii::app()->db->createCommand()->update('{{saved_control}}',array('saved_date'=>'1980-01-01 00:00:00'),"saved_date is NULL");
        Yii::app()->db->createCommand()->alterColumn('{{saved_control}}','saved_date',"datetime NOT NULL");
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>''),"stg_value is NULL");
        Yii::app()->db->createCommand()->alterColumn('{{settings_global}}','stg_value',"string NOT NULL default ''");

        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','admin',"{$sVarchar}(50) default NULL");
        Yii::app()->db->createCommand()->update('{{surveys}}',array('active'=>'N'),"active is NULL");

        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','active',"{$sVarchar}(1) NOT NULL default 'N'");

        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','startdate',"datetime default NULL");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','adminemail',"{$sVarchar}(320) NULL default NULL");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','anonymized',"{$sVarchar}(1) NOT NULL default 'N'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','faxto',"{$sVarchar}(20) NULL default NULL");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','format',"{$sVarchar}(1) NULL default NULL");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','language',"{$sVarchar}(50) NULL default NULL");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','additional_languages',"string NULL default NULL");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','printanswers',"{$sVarchar}(1) default 'N'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','publicstatistics',"{$sVarchar}(1) default 'N'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','publicgraphs',"{$sVarchar}(1) default 'N'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','assessments',"{$sVarchar}(1) default 'N'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','usetokens',"{$sVarchar}(1) default 'N'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','bounce_email',"{$sVarchar}(320) NULL default NULL");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','tokenlength',"integer default 15");

        Yii::app()->db->createCommand()->update('{{surveys_languagesettings}}',array('surveyls_title'=>''),"surveyls_title is NULL");
        Yii::app()->db->createCommand()->alterColumn('{{surveys_languagesettings}}','surveyls_title',"{$sVarchar}(200) NOT NULL");
        Yii::app()->db->createCommand()->alterColumn('{{surveys_languagesettings}}','surveyls_endtext',"text");
        Yii::app()->db->createCommand()->alterColumn('{{surveys_languagesettings}}','surveyls_url',"string default NULL");
        Yii::app()->db->createCommand()->alterColumn('{{surveys_languagesettings}}','surveyls_urldescription',"string default NULL");
        Yii::app()->db->createCommand()->alterColumn('{{surveys_languagesettings}}','surveyls_email_invite_subj',"string default NULL");
        Yii::app()->db->createCommand()->alterColumn('{{surveys_languagesettings}}','surveyls_email_remind_subj',"string default NULL");
        Yii::app()->db->createCommand()->alterColumn('{{surveys_languagesettings}}','surveyls_email_register_subj',"string default NULL");
        Yii::app()->db->createCommand()->alterColumn('{{surveys_languagesettings}}','surveyls_email_confirm_subj',"string default NULL");
        Yii::app()->db->createCommand()->alterColumn('{{surveys_languagesettings}}','surveyls_dateformat',"integer NOT NULL default 1");

        Yii::app()->db->createCommand()->update('{{users}}',array('users_name'=>''),"users_name is NULL");
        Yii::app()->db->createCommand()->update('{{users}}',array('full_name'=>''),"full_name is NULL");
        Yii::app()->db->createCommand()->alterColumn('{{users}}','users_name',"{$sVarchar}(64) NOT NULL default ''");
        Yii::app()->db->createCommand()->alterColumn('{{users}}','full_name',"{$sVarchar}(50) NOT NULL");
        Yii::app()->db->createCommand()->alterColumn('{{users}}','lang',"{$sVarchar}(20) default NULL");
        Yii::app()->db->createCommand()->alterColumn('{{users}}','email',"{$sVarchar}(320) default NULL");
        Yii::app()->db->createCommand()->alterColumn('{{users}}','superadmin',"integer NOT NULL default 0");
        Yii::app()->db->createCommand()->alterColumn('{{users}}','htmleditormode',"{$sVarchar}(7) default 'default'");
        Yii::app()->db->createCommand()->alterColumn('{{users}}','dateformat',"integer NOT NULL default 1");
        Yii::app()->db->createCommand()->dropIndex('email','{{users}}');

        Yii::app()->db->createCommand()->update('{{user_groups}}',array('name'=>''),"name is NULL");
        Yii::app()->db->createCommand()->update('{{user_groups}}',array('description'=>''),"description is NULL");
        Yii::app()->db->createCommand()->alterColumn('{{user_groups}}','name',"{$sVarchar}(20) NOT NULL");
        Yii::app()->db->createCommand()->alterColumn('{{user_groups}}','description',"text NOT NULL");

        Yii::app()->db->createCommand()->dropIndex('user_in_groups_idx1','{{user_in_groups}}');
        addPrimaryKey('user_in_groups', array('ugid','uid'));

        Yii::app()->db->createCommand()->addColumn('{{surveys_languagesettings}}','surveyls_numberformat',"integer NOT NULL DEFAULT 0");

        Yii::app()->db->createCommand()->createTable('{{failed_login_attempts}}',array(
        'id' => "pk",
        'ip' => $sVarchar.'(37) NOT NULL',
        'last_attempt' => $sVarchar.'(20) NOT NULL',
        'number_attempts' => "integer NOT NULL"
        ));
        upgradeTokens145();
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>145),"stg_name='DBVersion'");
    }

    if ($oldversion < 146) //Modify surveys table
    {
        upgradeSurveyTimings146();
        // Fix permissions for new feature quick-translation
        modifyDatabase("", "INSERT into {{survey_permissions}} (sid,uid,permission,read_p,update_p) SELECT sid,owner_id,'translations','1','1' from {{surveys}}"); echo $modifyoutput; flush();@ob_flush();
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>146),"stg_name='DBVersion'");
    }

    if ($oldversion < 147)
    {
        Yii::app()->db->createCommand()->addColumn('{{users}}','templateeditormode',"{$sVarchar}(7) NOT NULL default 'default'");
        Yii::app()->db->createCommand()->addColumn('{{users}}','questionselectormode',"{$sVarchar}(7) NOT NULL default 'default'");
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>147),"stg_name='DBVersion'");
    }

    if ($oldversion < 148)
    {
        Yii::app()->db->createCommand()->addColumn('{{users}}','participant_panel',"integer NOT NULL default 0");

        Yii::app()->db->createCommand()->createTable('{{participants}}',array(
        'participant_id' => $sVarchar.'(50) NOT NULL',
        'firstname' => $sVarchar.'(40) default NULL',
        'lastname' => $sVarchar.'(40) default NULL',
        'email' => $sVarchar.'(80) default NULL',
        'language' => $sVarchar.'(40) default NULL',
        'blacklisted' => $sVarchar.'(1) NOT NULL',
        'owner_uid' => "integer NOT NULL"
        ));
        addPrimaryKey('participants', array('participant_id'));

        Yii::app()->db->createCommand()->createTable('{{participant_attribute}}',array(
        'participant_id' => $sVarchar.'(50) NOT NULL',
        'attribute_id' => "integer NOT NULL",
        'value' => $sVarchar.'(50) NOT NULL'
        ));
        addPrimaryKey('participant_attribute', array('participant_id','attribute_id'));

        Yii::app()->db->createCommand()->createTable('{{participant_attribute_names}}',array(
        'attribute_id' => $sAutoIncrement,
        'attribute_type' => $sVarchar.'(4) NOT NULL',
        'visible' => $sVarchar.'(5) NOT NULL',
        'PRIMARY KEY (attribute_id,attribute_type)'
        ));

        Yii::app()->db->createCommand()->createTable('{{participant_attribute_names_lang}}',array(
        'attribute_id' => 'integer NOT NULL',
        'attribute_name' => $sVarchar.'(30) NOT NULL',
        'lang' => $sVarchar.'(20) NOT NULL'
        ));
        addPrimaryKey('participant_attribute_names_lang', array('attribute_id','lang'));

        Yii::app()->db->createCommand()->createTable('{{participant_attribute_values}}',array(
        'attribute_id' => 'integer NOT NULL',
        'value_id' => 'pk',
        'value' => $sVarchar.'(20) NOT NULL'
        ));

        Yii::app()->db->createCommand()->createTable('{{participant_shares}}',array(
        'participant_id' => $sVarchar.'(50) NOT NULL',
        'share_uid' => 'integer NOT NULL',
        'date_added' => 'datetime NOT NULL',
        'can_edit' => $sVarchar.'(5) NOT NULL'
        ));
        addPrimaryKey('participant_shares', array('participant_id','share_uid'));

        Yii::app()->db->createCommand()->createTable('{{survey_links}}',array(
        'participant_id' => $sVarchar.'(50) NOT NULL',
        'token_id' => 'integer NOT NULL',
        'survey_id' => 'integer NOT NULL',
        'date_created' => 'datetime NOT NULL'
        ));
        addPrimaryKey('survey_links', array('participant_id','token_id','survey_id'));

        // Add language field to question_attributes table
        Yii::app()->db->createCommand()->addColumn('{{question_attributes}}','language',"{$sVarchar}(20)");

        upgradeQuestionAttributes148();
        fixSubquestions();
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>148),"stg_name='DBVersion'");
    }

    if ($oldversion < 149)
    {
        $fields = array(
        'id' => 'INT',
        'sid' => 'INT',
        'parameter' => 'VARCHAR(50)',
        'targetqid' => 'INT NULL',
        'targetsqid' => 'INT NULL'
        );
        Yii::app()->db->createCommand()->createTable('{{survey_url_parameters}}',$fields);
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>149),"stg_name='DBVersion'");
    }

    if ($oldversion < 150)
    {
        Yii::app()->db->createCommand()->addColumn('{{questions}}','relevance','TEXT');
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>150),"stg_name='DBVersion'");
    }

    if ($oldversion < 151)
    {
        Yii::app()->db->createCommand()->addColumn('{{groups}}','randomization_group',"{$sVarchar}(20) NOT NULL default ''");
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>151),"stg_name='DBVersion'");
    }

    if ($oldversion < 152)
    {
        Yii::app()->db->createCommand()->createIndex('question_attributes_idx3','{{question_attributes}}','attribute');
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>152),"stg_name='DBVersion'");
    }

    if ($oldversion < 153)
    {
        Yii::app()->db->createCommand()->createTable('{{expression_errors}}',array(
        'id' => 'pk',
        'errortime' => $sVarchar.'(50)',
        'sid' => 'integer',
        'gid' => 'integer',
        'qid' => 'integer',
        'gseq' => 'integer',
        'qseq' => 'integer',
        'type' => $sVarchar.'(50)',
        'eqn' => 'text',
        'prettyprint' => 'text'
        ));
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>153),"stg_name='DBVersion'");
    }

    if ($oldversion < 154)
    {
        Yii::app()->db->createCommand()->addColumn('{{groups}}','grelevance',"text");
        LimeExpressionManager::UpgradeConditionsToRelevance();
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>154),"stg_name='DBVersion'");
    }

    if ($oldversion < 155)
    {
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','googleanalyticsstyle',"{$sVarchar}(1)");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','googleanalyticsapikey',"{$sVarchar}(25)");
        try{
            Yii::app()->db->createCommand()->renameColumn('{{surveys}}','showXquestions','showxquestions');
        }
        catch(Exception $e)
        {
            // do nothing
        }
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>155),"stg_name='DBVersion'");
    }

    if ($oldversion < 156)
    {
        try
        {
            Yii::app()->db->createCommand()->dropTable('{{survey_url_parameters}}');
        }
        catch(Exception $e)
        {
            // do nothing
        }
        Yii::app()->db->createCommand()->createTable('{{survey_url_parameters}}',array(
        'id' => 'pk',
        'sid' => 'integer NOT NULL',
        'parameter' => $sVarchar.'(50) NOT NULL',
        'targetqid' => 'integer NOT NULL',
        'targetsqid' => 'integer NOT NULL'
        ));

        Yii::app()->db->createCommand()->dropTable('{{sessions}}');
        Yii::app()->db->createCommand()->createTable('{{sessions}}',array(
        'id' => $sVarchar.'(32) NOT NULL',
        'expire' => 'integer',
        'data' => 'text'
        ));
        addPrimaryKey('sessions', array('id'));

        Yii::app()->db->createCommand()->addColumn('{{surveys_languagesettings}}','surveyls_attributecaptions',"TEXT");
        Yii::app()->db->createCommand()->addColumn('{{surveys}}','sendconfirmation',"{$sVarchar}(1) default 'Y'");

        upgradeSurveys156();

        // If a survey has an deleted owner, re-own the survey to the superadmin
        Yii::app()->db->schema->refresh();
        Survey::model()->refreshMetaData();
        $surveys = Survey::model();
        $surveys = $surveys->with(array('owner'))->findAll();
        foreach ($surveys as $row)
        {
            if (!isset($row->owner->attributes))
            {
                Survey::model()->updateByPk($row->sid,array('owner_id'=>1));
            }
        }
    }

    if ($oldversion < 157)
    {
        Yii::app()->db->createCommand()->alterColumn('{{answers}}','assessment_value',"integer NOT NULL default '0'");
        Yii::app()->db->createCommand()->alterColumn('{{answers}}','scale_id',"integer NOT NULL default '0'");
        Yii::app()->db->createCommand()->alterColumn('{{conditions}}','method',"{$sVarchar}(5) NOT NULL default ''");
        Yii::app()->db->createCommand()->alterColumn('{{participants}}','owner_uid',"integer NOT NULL");
        Yii::app()->db->createCommand()->alterColumn('{{participant_attribute_names}}','visible',$sVarchar.'(5) NOT NULL');
        Yii::app()->db->createCommand()->alterColumn('{{questions}}','type',"{$sVarchar}(1) NOT NULL default 'T'");
        Yii::app()->db->createCommand()->alterColumn('{{questions}}','other',"{$sVarchar}(1) NOT NULL default 'N'");
        Yii::app()->db->createCommand()->alterColumn('{{questions}}','mandatory',"{$sVarchar}(1) NULL default NULL");
        Yii::app()->db->createCommand()->alterColumn('{{questions}}','scale_id','integer NOT NULL default 0');
        Yii::app()->db->createCommand()->alterColumn('{{questions}}','same_default','integer NOT NULL default 0');
        Yii::app()->db->createCommand()->alterColumn('{{quota}}','qlimit',"integer NULL");
        Yii::app()->db->createCommand()->alterColumn('{{quota}}','action',"integer NULL");
        Yii::app()->db->createCommand()->alterColumn('{{quota}}','active',"integer NOT NULL DEFAULT 1");
        Yii::app()->db->createCommand()->alterColumn('{{quota}}','autoload_url',"integer NOT NULL DEFAULT 0");
        Yii::app()->db->createCommand()->alterColumn('{{saved_control}}','status',"{$sVarchar}(1) NOT NULL default ''");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','active',"{$sVarchar}(1) NOT NULL default 'N'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','anonymized',"{$sVarchar}(1) NOT NULL default 'N'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','format',"{$sVarchar}(1) NULL default NULL");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','printanswers',"{$sVarchar}(1) default 'N'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','listpublic',"{$sVarchar}(1) default 'N'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','htmlemail',"{$sVarchar}(1) default 'N'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','tokenanswerspersistence',"{$sVarchar}(1) default 'N'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','usecaptcha',"{$sVarchar}(1) default 'N'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','publicstatistics',"{$sVarchar}(1) default 'N'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','publicgraphs',"{$sVarchar}(1) default 'N'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','assessments',"{$sVarchar}(1) default 'N'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','usetokens',"{$sVarchar}(1) default 'N'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','tokenlength',"integer default 15");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','savetimings',"{$sVarchar}(1) default 'N'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','showxquestions',"{$sVarchar}(1) NULL default 'Y'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','showgroupinfo',"{$sVarchar}(1) NULL default 'B'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','shownoanswer',"{$sVarchar}(1) NULL default 'Y'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','showqnumcode',"{$sVarchar}(1) NULL default 'X'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','bouncetime',"integer");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','showwelcome',"{$sVarchar}(1) default 'Y'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','showprogress',"{$sVarchar}(1) default 'Y'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','allowjumps',"{$sVarchar}(1) default 'N'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','navigationdelay',"integer default 0");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','nokeyboard',"{$sVarchar}(1) default 'N'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','alloweditaftercompletion',"{$sVarchar}(1) default 'N'");
        Yii::app()->db->createCommand()->alterColumn('{{surveys}}','googleanalyticsstyle',"{$sVarchar}(1)");
        Yii::app()->db->createCommand()->alterColumn('{{surveys_languagesettings}}','surveyls_dateformat',"integer NOT NULL default 1");
        Yii::app()->db->createCommand()->alterColumn('{{survey_permissions}}','sid',"integer NOT NULL");
        Yii::app()->db->createCommand()->alterColumn('{{survey_permissions}}','uid', "integer NOT NULL");
        Yii::app()->db->createCommand()->alterColumn('{{survey_permissions}}','create_p', "integer NOT NULL default 0");
        Yii::app()->db->createCommand()->alterColumn('{{survey_permissions}}','read_p', "integer NOT NULL default 0");
        Yii::app()->db->createCommand()->alterColumn('{{survey_permissions}}','update_p',"integer NOT NULL default 0");
        Yii::app()->db->createCommand()->alterColumn('{{survey_permissions}}','delete_p' ,"integer NOT NULL default 0");
        Yii::app()->db->createCommand()->alterColumn('{{survey_permissions}}','import_p',"integer NOT NULL default 0");
        Yii::app()->db->createCommand()->alterColumn('{{survey_permissions}}','export_p' ,"integer NOT NULL default 0");
        Yii::app()->db->createCommand()->alterColumn('{{templates_rights}}','use' ,"integer NOT NULL ");
        Yii::app()->db->createCommand()->alterColumn('{{users}}','superadmin',"integer NOT NULL default 0");
        Yii::app()->db->createCommand()->alterColumn('{{users}}','one_time_pw','text');
        Yii::app()->db->createCommand()->alterColumn('{{users}}','dateformat',"integer NOT NULL default 1");
        Yii::app()->db->createCommand()->alterColumn('{{users}}','participant_panel',"integer NOT NULL default 0");
        Yii::app()->db->createCommand()->update('{{question_attributes}}',array('value'=>'1'),"attribute = 'random_order' and value = '2'");

        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>157),"stg_name='DBVersion'");
    }

    if ($oldversion < 158)
    {
        Yii::app()->db->createCommand()->createTable('{{question_types}}',array(
        'tid' => 'pk',
        'order' => 'integer NOT NULL',
        'group' => 'integer NOT NULL',
        'name' => $sVarchar.'(50) NOT NULL',
        'class' => $sVarchar.'(50) NOT NULL',
        'legacy' => $sChar.'(1)',
        'system' => $sChar."(1) NOT NULL DEFAULT 'N'",
        ));
        addUnique('question_types', array('order','group'));
        addUnique('question_types', array('name'));
        addUnique('question_types', array('legacy'));

        Yii::app()->db->createCommand()->createTable('{{question_type_groups}}',array(
        'id' => 'pk',
        'name' => $sVarchar.'(50) NOT NULL',
        'order' => 'integer NOT NULL',
        'system' => $sChar."(1) NOT NULL DEFAULT 'N'",
        ));
        addUnique('question_type_groups', array('order'));

        Yii::app()->db->createCommand()->addColumn('{{questions}}','tid',"integer NOT NULL DEFAULT '0' AFTER `gid`");

        upgradeSurveys158();
        Yii::app()->db->createCommand()->update('{{settings_global}}',array('stg_value'=>158),"stg_name='DBVersion'");
    }

    fixLanguageConsistencyAllSurveys();
    echo '<br /><br />'.sprintf($clang->gT('Database update finished (%s)'),date('Y-m-d H:i:s')).'<br /><br />';
}

function upgradeSurveys158()
{
    $types = array(array(1, 1, 1, '5 point choice', 'FiveList', '5', 'Y'),array(2, 2, 1, 'List (dropdown)', 'Select', '!', 'Y'),array(3, 3, 1, 'List (radio)', 'List', 'L', 'Y'),array(4, 4, 1, 'List with comment', 'CommentList', 'O', 'Y'),array(5, 1, 2, 'Array', 'RadioArray', 'F', 'Y'),array(6, 2, 2, 'Array (10 point choice)', 'TenRadioArray', 'B', 'Y'),array(7, 3, 2, 'Array (5 point choice)', 'FiveRadioArray', 'A', 'Y'),array(8, 4, 2, 'Array (Increase/Same/Decrease)', 'IDRadioArray', 'E', 'Y'),array(9, 5, 2, 'Array (Numbers)', 'NumberArray', ':', 'Y'),array(10, 6, 2, 'Array (Texts)', 'TextArray', ';', 'Y'),array(11, 7, 2, 'Array (Yes/No/Uncertain)', 'YNRadioArray', 'C', 'Y'),array(12, 8, 2, 'Array by column', 'ColumnRadioArray', 'H', 'Y'),array(13, 9, 2, 'Array dual scale', 'DualRadioArray', '1', 'Y'),array(14, 1, 3, 'Date/Time', 'Date', 'D', 'Y'),array(15, 2, 3, 'Equation', 'Equation', '*', 'Y'),array(16, 3, 3, 'File upload', 'File', '|', 'Y'),array(17, 4, 3, 'Gender', 'Gender', 'G', 'Y'),array(18, 5, 3, 'Language switch', 'Language', 'I', 'Y'),array(19, 6, 3, 'Multiple numerical input', 'Multinumerical', 'K', 'Y'),array(20, 7, 3, 'Numerical input', 'Numerical', 'N', 'Y'),array(21, 8, 3, 'Ranking', 'Ranking', 'R', 'Y'),array(22, 9, 3, 'Text display', 'Display', 'X', 'Y'),array(23, 10, 3, 'Yes/No', 'YN', 'Y', 'Y'),array(24, 1, 4, 'Huge free text', 'HugeText', 'U', 'Y'),array(25, 2, 4, 'Long free text', 'LongText', 'T', 'Y'),array(26, 3, 4, 'Multiple short text', 'Multitext', 'Q', 'Y'),array(27, 4, 4, 'Short free text', 'ShortText', 'S', 'Y'),array(28, 1, 5, 'Multiple choice', 'Check', 'M', 'Y'),array(29, 2, 5, 'Multiple choice with comments', 'CommentCheck', 'P', 'Y')); 
    $groups = array(array(1, 'Single choice questions', 1, 'Y'),array(2, 'Arrays', 2, 'Y'),array(3, 'Mask questions', 3, 'Y'),array(4, 'Text questions', 4, 'Y'),array(5, 'Multiple choice questions', 5, 'Y'));
    
    foreach($types as $type)
    {
        Yii::app()->db->createCommand()->insert('{{question_types}}', array('tid' => $type[0], 'order' => $type[1],'group' => $type[2], 'name' => $type[3], 'class' => $type[4], 'legacy' => $type[5], 'system' => $type[6]));
    }
    foreach($groups as $group)
    {
        Yii::app()->db->createCommand()->insert('{{question_type_groups}}', array('id' => $group[0], 'name' => $group[1], 'order' => $group[2], 'system' => $group[3]));
    }

    Yii::app()->db->createCommand('UPDATE {{questions}} INNER JOIN {{question_types}} ON {{questions}}.type={{question_types}}.legacy SET {{questions}}.tid={{question_types}}.tid')->execute();
}

function upgradeSurveys156()
{
    global $modifyoutput;
    $sSurveyQuery = "SELECT * FROM {{surveys_languagesettings}}";
    $oSurveyResult = Yii::app()->db->createCommand($sSurveyQuery)->queryAll();
    foreach ( $oSurveyResult as $aSurveyRow )
    {

        Yii::app()->loadLibrary('Limesurvey_lang',array("langcode"=>$aSurveyRow['surveyls_language']));
        $oLanguage = Yii::app()->lang;
        $aDefaultTexts=templateDefaultTexts($oLanguage,'unescaped');
        unset($oLanguage);

        if (trim(strip_tags($aSurveyRow['surveyls_email_confirm'])) == '')
        {
            $sSurveyUpdateQuery= "update {{surveys}} set sendconfirmation='N' where sid=".$aSurveyRow['surveyls_survey_id'];
            Yii::app()->db->createCommand($sSurveyUpdateQuery)->execute();

            $aValues=array('surveyls_email_confirm_subj'=>$aDefaultTexts['confirmation_subject'],
            'surveyls_email_confirm'=>$aDefaultTexts['confirmation']);
            Surveys_languagesettings::model()->updateAll($aValues,'surveyls_survey_id=:sid',array(':sid'=>$aSurveyRow['surveyls_survey_id']));
        }
    }
}

function upgradeQuestionAttributes148()
{
    global $modifyoutput;
    $sSurveyQuery = "SELECT sid FROM {{surveys}}";
    $oSurveyResult = dbExecuteAssoc($sSurveyQuery);
    foreach ( $oSurveyResult->readAll()  as $aSurveyRow)
    {
        $surveyid=$aSurveyRow['sid'];
        $languages=array_merge(array(Survey::model()->findByPk($surveyid)->language), Survey::model()->findByPk($surveyid)->additionalLanguages);

        $sAttributeQuery = "select q.qid,attribute,value from {{question_attributes}} qa , {{questions}} q where q.qid=qa.qid and sid={$surveyid}";
        $oAttributeResult = dbExecuteAssoc($sAttributeQuery);
        $aAllAttributes=questionAttributes();
        foreach ( $oAttributeResult->readAll() as $aAttributeRow)
        {
            if (isset($aAllAttributes[$aAttributeRow['attribute']]['i18n']) && $aAllAttributes[$aAttributeRow['attribute']]['i18n'])
            {
                Yii::app()->db->createCommand("delete from {{question_attributes}} where qid={$aAttributeRow['qid']} and attribute='{$aAttributeRow['attribute']}'")->execute();
                foreach ($languages as $language)
                {
                    $sAttributeInsertQuery="insert into {{question_attributes}} (qid,attribute,value,language) VALUES({$aAttributeRow['qid']},'{$aAttributeRow['attribute']}','{$aAttributeRow['value']}','{$language}' )";
                    modifyDatabase("",$sAttributeInsertQuery); echo $modifyoutput; flush();@ob_flush();
                }
            }
        }
    }
}


function upgradeSurveyTimings146()
{
    global $modifyoutput;
    $aTimingTables = dbGetTablesLike("%timings");
    foreach ($aTimingTables as $sTable) {
        Yii::app()->db->createCommand()->renameColumn($sTable,'interviewTime','interviewtime');
    }
}


// Add the usesleft field to all existing token tables
function upgradeTokens145()
{
    global $modifyoutput;
    $surveyidresult = dbGetTablesLike("tokens%");
    if (!$surveyidresult) {return "Database Error";}
    else
    {
        foreach ( $surveyidresult as $sv )
        {
            Yii::app()->db->createCommand()->addColumn(reset($sv),'usesleft',"integer NOT NULL default 1");
            Yii::app()->db->createCommand()->update(reset($sv),array('usesleft'=>'0'),"completed<>'N'");
        }
    }
}


function upgradeSurveys145()
{
    global $modifyoutputt;
    $sSurveyQuery = "SELECT * FROM {{surveys}} where notification<>'0'";
    $oSurveyResult = dbExecuteAssoc($sSurveyQuery);
    foreach ( $oSurveyResult->readAll() as $aSurveyRow )
    {
        if ($aSurveyRow['notification']=='1' && trim($aSurveyRow['adminemail'])!='')
        {
            $aEmailAddresses=explode(';',$aSurveyRow['adminemail']);
            $sAdminEmailAddress=$aEmailAddresses[0];
            $sEmailnNotificationAddresses=implode(';',$aEmailAddresses);
            $sSurveyUpdateQuery= "update {{surveys}} set adminemail='{$sAdminEmailAddress}', emailnotificationto='{$sEmailnNotificationAddresses}' where sid=".$aSurveyRow['sid'];
            Yii::app()->db->createCommand($sSurveyUpdateQuery)->execute();
        }
        else
        {
            $aEmailAddresses=explode(';',$aSurveyRow['adminemail']);
            $sAdminEmailAddress=$aEmailAddresses[0];
            $sEmailDetailedNotificationAddresses=implode(';',$aEmailAddresses);
            if (trim($aSurveyRow['emailresponseto'])!='')
            {
                $sEmailDetailedNotificationAddresses=$sEmailDetailedNotificationAddresses.';'.trim($aSurveyRow['emailresponseto']);
            }
            $sSurveyUpdateQuery= "update {{surveys}} set adminemail='{$sAdminEmailAddress}', emailnotificationto='{$sEmailDetailedNotificationAddresses}' where sid=".$aSurveyRow['sid'];
            Yii::app()->db->createCommand($sSurveyUpdateQuery)->execute();
        }
    }
    $sSurveyQuery = "SELECT * FROM {{surveys_languagesettings}}";
    $oSurveyResult = Yii::app()->db->createCommand($sSurveyQuery)->queryAll();
    foreach ( $oSurveyResult as $aSurveyRow )
    {
        $oLanguage = new Limesurvey_lang($aSurveyRow['surveyls_language']);
        $oLanguage = Yii::app()->lang;
        $aDefaultTexts=templateDefaultTexts($oLanguage,'unescaped');
        unset($oLanguage);
        $aDefaultTexts['admin_detailed_notification']=$aDefaultTexts['admin_detailed_notification'].$aDefaultTexts['admin_detailed_notification_css'];
        $sSurveyUpdateQuery = "update {{surveys_languagesettings}} set
        email_admin_responses_subj=".$aDefaultTexts['admin_detailed_notification_subject'].",
        email_admin_responses=".$aDefaultTexts['admin_detailed_notification'].",
        email_admin_notification_subj=".$aDefaultTexts['admin_notification_subject'].",
        email_admin_notification=".$aDefaultTexts['admin_notification']."
        where surveyls_survey_id=".$aSurveyRow['surveyls_survey_id'];
        Yii::app()->db->createCommand()->update('{{surveys_languagesettings}}',array('email_admin_responses_subj'=>$aDefaultTexts['admin_detailed_notification_subject'],
        'email_admin_responses'=>$aDefaultTexts['admin_detailed_notification'],
        'email_admin_notification_subj'=>$aDefaultTexts['admin_notification_subject'],
        'email_admin_notification'=>$aDefaultTexts['admin_notification']
        ),"surveyls_survey_id={$aSurveyRow['surveyls_survey_id']}");
    }

}


function upgradeSurveyPermissions145()
{
    global $modifyoutput;
    $sPermissionQuery = "SELECT * FROM {{surveys_rights}}";
    $oPermissionResult = Yii::app()->db->createCommand($sPermissionQuery)->queryAll();
    if (empty($oPermissionResult)) {return "Database Error";}
    else
    {
        $tablename = '{{survey_permissions}}';
        foreach ( $oPermissionResult as $aPermissionRow )
        {

            $sPermissionInsertQuery=Yii::app()->db->createCommand()->insert($tablename, array('permission'=>'assessments',
            'create_p'=>$aPermissionRow['define_questions'],
            'read_p'=>$aPermissionRow['define_questions'],
            'update_p'=>$aPermissionRow['define_questions'],
            'delete_p'=>$aPermissionRow['define_questions'],
            'sid'=>$aPermissionRow['sid'],
            'uid'=>$aPermissionRow['uid']));

            $sPermissionInsertQuery=Yii::app()->db->createCommand()->insert($tablename,array('permission'=>'quotas',
            'create_p'=>$aPermissionRow['define_questions'],
            'read_p'=>$aPermissionRow['define_questions'],
            'update_p'=>$aPermissionRow['define_questions'],
            'delete_p'=>$aPermissionRow['define_questions'],
            'sid'=>$aPermissionRow['sid'],
            'uid'=>$aPermissionRow['uid']));

            $sPermissionInsertQuery=Yii::app()->db->createCommand()->insert($tablename,array('permission'=>'responses',
            'create_p'=>$aPermissionRow['browse_response'],
            'read_p'=>$aPermissionRow['browse_response'],
            'update_p'=>$aPermissionRow['browse_response'],
            'delete_p'=>$aPermissionRow['delete_survey'],
            'export_p'=>$aPermissionRow['export'],
            'import_p'=>$aPermissionRow['browse_response'],
            'sid'=>$aPermissionRow['sid'],
            'uid'=>$aPermissionRow['uid']));

            $sPermissionInsertQuery=Yii::app()->db->createCommand()->insert($tablename,array('permission'=>'statistics',
            'read_p'=>$aPermissionRow['browse_response'],
            'sid'=>$aPermissionRow['sid'],
            'uid'=>$aPermissionRow['uid']));

            $sPermissionInsertQuery=Yii::app()->db->createCommand()->insert($tablename,array('permission'=>'survey',
            'read_p'=>1,
            'delete_p'=>$aPermissionRow['delete_survey'],
            'sid'=>$aPermissionRow['sid'],
            'uid'=>$aPermissionRow['uid']));

            $sPermissionInsertQuery=Yii::app()->db->createCommand()->insert($tablename,array('permission'=>'surveyactivation',
            'update_p'=>$aPermissionRow['activate_survey'],
            'sid'=>$aPermissionRow['sid'],
            'uid'=>$aPermissionRow['uid']));

            $sPermissionInsertQuery=Yii::app()->db->createCommand()->insert($tablename,array('permission'=>'surveycontent',
            'create_p'=>$aPermissionRow['define_questions'],
            'read_p'=>$aPermissionRow['define_questions'],
            'update_p'=>$aPermissionRow['define_questions'],
            'delete_p'=>$aPermissionRow['define_questions'],
            'export_p'=>$aPermissionRow['export'],
            'import_p'=>$aPermissionRow['define_questions'],
            'sid'=>$aPermissionRow['sid'],
            'uid'=>$aPermissionRow['uid']));

            $sPermissionInsertQuery=Yii::app()->db->createCommand()->insert($tablename,array('permission'=>'surveylocale',
            'read_p'=>$aPermissionRow['edit_survey_property'],
            'update_p'=>$aPermissionRow['edit_survey_property'],
            'sid'=>$aPermissionRow['sid'],
            'uid'=>$aPermissionRow['uid']));

            $sPermissionInsertQuery=Yii::app()->db->createCommand()->insert($tablename,array('permission'=>'surveysettings',
            'read_p'=>$aPermissionRow['edit_survey_property'],
            'update_p'=>$aPermissionRow['edit_survey_property'],
            'sid'=>$aPermissionRow['sid'],
            'uid'=>$aPermissionRow['uid']));

            $sPermissionInsertQuery=Yii::app()->db->createCommand()->insert($tablename,array('permission'=>'tokens',
            'create_p'=>$aPermissionRow['activate_survey'],
            'read_p'=>$aPermissionRow['activate_survey'],
            'update_p'=>$aPermissionRow['activate_survey'],
            'delete_p'=>$aPermissionRow['activate_survey'],
            'export_p'=>$aPermissionRow['export'],
            'import_p'=>$aPermissionRow['activate_survey'],
            'sid'=>$aPermissionRow['sid'],
            'uid'=>$aPermissionRow['uid']));
        }
    }
}

function upgradeTables143()
{
    global $modifyoutput;

    $aQIDReplacements=array();
    $answerquery = "select a.*, q.sid, q.gid from {{answers}} a,{{questions}} q where a.qid=q.qid and q.type in ('L','O','!') and a.default_value='Y'";
    $answerresult = Yii::app()->db->createCommand($answerquery)->queryAll();
    foreach ( $answerresult as $row )
    {
        modifyDatabase("","INSERT INTO {{defaultvalues}} (qid, scale_id,language,specialtype,defaultvalue) VALUES ({$row['qid']},0,".dbQuoteAll($row['language']).",'',".dbQuoteAll($row['code']).")"); echo $modifyoutput; flush();@ob_flush();
    }

    // Convert answers to subquestions

    $answerquery = "select a.*, q.sid, q.gid, q.type from {{answers}} a,{{questions}} q where a.qid=q.qid and a.language=q.language and q.type in ('1','A','B','C','E','F','H','K',';',':','M','P','Q')";
    $answerresult = Yii::app()->db->createCommand($answerquery)->queryAll();
    foreach ( $answerresult as $row )
    {

        $aInsert=array();
        if (isset($aQIDReplacements[$row['qid'].'_'.$row['code']]))
        {
            $aInsert['qid']=$aQIDReplacements[$row['qid'].'_'.$row['code']];
        }
        $aInsert['sid']=$row['sid'];
        $aInsert['gid']=$row['gid'];
        $aInsert['parent_qid']=$row['qid'];
        $aInsert['type']=$row['type'];
        $aInsert['title']=$row['code'];
        $aInsert['question']=$row['answer'];
        $aInsert['question_order']=$row['sortorder'];
        $aInsert['language']=$row['language'];
        $tablename="{{questions}}";
        $query=Yii::app()->db->createCommand()->insert($tablename,$aInsert);
        if (!isset($aInsert['qid']))
        {
            $aQIDReplacements[$row['qid'].'_'.$row['code']]=Yii::app()->db->getLastInsertId();
            $iSaveSQID=$aQIDReplacements[$row['qid'].'_'.$row['code']];
        }
        else
        {
            $iSaveSQID=$aInsert['qid'];
        }
        if (($row['type']=='M' || $row['type']=='P') && $row['default_value']=='Y')
        {
            modifyDatabase("","INSERT INTO {{defaultvalues}} (qid, sqid, scale_id,language,specialtype,defaultvalue) VALUES ({$row['qid']},{$iSaveSQID},0,".dbQuoteAll($row['language']).",'','Y')"); echo $modifyoutput; flush();@ob_flush();
        }
    }
    // Sanitize data
    modifyDatabase("","delete {{answers}} from {{answers}} LEFT join {{questions}} ON {{answers}}.qid={{questions}}.qid where {{questions}}.type in ('1','F','H','M','P','W','Z')"); echo $modifyoutput; flush();@ob_flush();

    // Convert labels to answers
    $answerquery = "select qid ,type ,lid ,lid1, language from {{questions}} where parent_qid=0 and type in ('1','F','H','M','P','W','Z')";
    $answerresult = Yii::app()->db->createCommand($answerquery)->queryAll();
    foreach ( $answerresult as $row )
    {
        $labelquery="Select * from {{labels}} where lid={$row['lid']} and language=".dbQuoteAll($row['language']);
        $labelresult = Yii::app()->db->createCommand($labelquery)->queryAll();
        foreach ( $labelresult as $lrow )
        {
            modifyDatabase("","INSERT INTO {{answers}} (qid, code, answer, sortorder, language, assessment_value) VALUES ({$row['qid']},".dbQuoteAll($lrow['code']).",".dbQuoteAll($lrow['title']).",{$lrow['sortorder']},".dbQuoteAll($lrow['language']).",{$lrow['assessment_value']})"); echo $modifyoutput; flush();@ob_flush();
            //$labelids[]
        }
        if ($row['type']=='1')
        {
            $labelquery="Select * from {{labels}} where lid={$row['lid1']} and language=".dbQuoteAll($row['language']);
            $labelresult = Yii::app()->db->createCommand($labelquery)->queryAll();
            foreach ( $labelresult as $lrow )
            {
                modifyDatabase("","INSERT INTO {{answers}} (qid, code, answer, sortorder, language, scale_id, assessment_value) VALUES ({$row['qid']},".dbQuoteAll($lrow['code']).",".dbQuoteAll($lrow['title']).",{$lrow['sortorder']},".dbQuoteAll($lrow['language']).",1,{$lrow['assessment_value']})"); echo $modifyoutput; flush();@ob_flush();
            }
        }
    }

    // Convert labels to subquestions
    $answerquery = "select * from {{questions}} where parent_qid=0 and type in (';',':')";
    $answerresult = Yii::app()->db->createCommand($answerquery)->queryAll();
    foreach ( $answerresult as $row )
    {
        $labelquery="Select * from {{labels}} where lid={$row['lid']} and language=".dbQuoteAll($row['language']);
        $labelresult = Yii::app()->db->createCommand($labelquery)->queryAll();
        foreach ( $labelresult as $lrow )
        {
            $aInsert=array();
            if (isset($aQIDReplacements[$row['qid'].'_'.$lrow['code'].'_1']))
            {
                $aInsert['qid']=$aQIDReplacements[$row['qid'].'_'.$lrow['code'].'_1'];
            }
            $aInsert['sid']=$row['sid'];
            $aInsert['gid']=$row['gid'];
            $aInsert['parent_qid']=$row['qid'];
            $aInsert['type']=$row['type'];
            $aInsert['title']=$lrow['code'];
            $aInsert['question']=$lrow['title'];
            $aInsert['question_order']=$lrow['sortorder'];
            $aInsert['language']=$lrow['language'];
            $aInsert['scale_id']=1;
            $tablename="{{questions}}";
            $query=Yii::app()->db->createCommand()->insert($tablename,$aInsert);
            if (isset($aInsert['qid']))
            {
                $aQIDReplacements[$row['qid'].'_'.$lrow['code'].'_1']=Yii::app()->db->getLastInsertID();
            }
        }
    }



    $updatequery = "update {{questions}} set type='!' where type='W'";
    modifyDatabase("",$updatequery); echo $modifyoutput; flush();@ob_flush();
    $updatequery = "update {{questions}} set type='L' where type='Z'";
    modifyDatabase("",$updatequery); echo $modifyoutput; flush();@ob_flush();

    // Now move all non-standard templates to the /upload dir
    global $usertemplaterootdir, $standardtemplates,$standardtemplaterootdir;

    if (!$usertemplaterootdir) {die("getTemplateList() no template directory");}
    if ($handle = opendir($standardtemplaterootdir))
    {
        while (false !== ($file = readdir($handle)))
        {
            if (!is_file("$standardtemplaterootdir/$file") && $file != "." && $file != ".." && $file!=".svn" && !isStandardTemplate($file))
            {
                if (!rename($standardtemplaterootdir.DIRECTORY_SEPARATOR.$file,$usertemplaterootdir.DIRECTORY_SEPARATOR.$file))
                {
                    echo "There was a problem moving directory '".$standardtemplaterootdir.DIRECTORY_SEPARATOR.$file."' to '".$usertemplaterootdir.DIRECTORY_SEPARATOR.$file."' due to missing permissions. Please do this manually.<br />";
                };
            }
        }
        closedir($handle);
    }

}


function upgradeQuestionAttributes142()
{
    global $modifyoutput;
    $attributequery="Select qid from {{question_attributes}} where attribute='exclude_all_other'  group by qid having count(qid)>1 ";
    $questionids = Yii::app()->db->createCommand($attributequery)->queryRow();
    if(!is_array($questionids)) { return "Database Error"; }
    else
    {
        foreach ($questionids as $questionid)
        {
            //Select all affected question attributes
            $attributevalues=dbSelectColumn("SELECT value from {{question_attributes}} where attribute='exclude_all_other' and qid=".$questionid);
            modifyDatabase("","delete from {{question_attributes}} where attribute='exclude_all_other' and qid=".$questionid); echo $modifyoutput; flush();@ob_flush();
            $record['value']=implode(';',$attributevalues);
            $record['attribute']='exclude_all_other';
            $record['qid']=$questionid;
            Yii::app()->db->createCommand()->insert('{{question_attributes}}', $record)->execute();
        }
    }
}

function upgradeSurveyTables139()
{
    global $modifyoutput;
    $dbprefix = Yii::app()->db->tablePrefix;
    $surveyidresult = dbGetTablesLike("survey\_%");
    if (empty($surveyidresult)) {return "Database Error";}
    else
    {
        foreach ( $surveyidresult as $sv )
        {
            Yii::app()->db->createCommand()->addColumn(reset($sv),'lastpage',"integer");
        }
    }
}


// Add the reminders tracking fields
function upgradeTokenTables134()
{
    global $modifyoutput;
    $surveyidresult = dbGetTablesLike("tokens%");
    if (!$surveyidresult) {return "Database Error";}
    else
    {
        foreach ( $surveyidresult as $sv )
        {
            Yii::app()->db->createCommand()->addColumn(reset($sv),'validfrom',"datetime");
            Yii::app()->db->createCommand()->addColumn(reset($sv),'validuntil',"datetime");
        }
    }
}

// Add the reminders tracking fields
function upgradeTokens128()
{
    global $modifyoutput;
    $sVarchar=Yii::app()->getConfig('varchar');
    $surveyidresult = dbGetTablesLike("tokens%");
    if (!$surveyidresult) {return "Database Error";}
    else
    {
        foreach ( $surveyidresult as $sv )
        {
            Yii::app()->db->createCommand()->addColumn(reset($sv),'remindersent',"{$sVarchar}(17) DEFAULT 'N'");
            Yii::app()->db->createCommand()->addColumn(reset($sv),'remindercount',"integer DEFAULT '0'");
        }
    }
}


function fixMySQLCollations()
{
    global $modifyoutput;
    $sql = 'SHOW TABLE STATUS';
    $dbprefix = Yii::app()->db->tablePrefix;
    $result = Yii::app()->db->createCommand($sql)->queryAll();
    foreach ( $result as $tables ) {
        // Loop through all tables in this database
        $table = $tables['Name'];
        $tablecollation=$tables['Collation'];
        if (strpos($table,'old_')===false  && ($dbprefix==''  || ($dbprefix!='' && strpos($table,$dbprefix)!==false)))
        {
            if ($tablecollation!='utf8_unicode_ci')
            {
                modifyDatabase("","ALTER TABLE $table COLLATE utf8_unicode_ci");
                echo $modifyoutput; flush();@ob_flush();
            }

            # Now loop through all the fields within this table
            $result2 = Yii::app()->db->createCommand("SHOW FULL COLUMNS FROM ".$table)->queryAll();
            foreach ( $result2 as $column )
            {
                if ($column['Collation']!= 'utf8_unicode_ci' )
                {
                    $field_name = $column['Field'];
                    $field_type = $column['Type'];
                    $field_default = $column['Default'];
                    if ($field_default!='NULL') {$field_default="'".$field_default."'";}
                    # Change text based fields
                    $skipped_field_types = array('char', 'text', 'enum', 'set');

                    foreach ( $skipped_field_types as $type )
                    {
                        if ( strpos($field_type, $type) !== false )
                        {
                            $modstatement="ALTER TABLE $table CHANGE `$field_name` `$field_name` $field_type CHARACTER SET utf8 COLLATE utf8_unicode_ci";
                            if ($type!='text') {$modstatement.=" DEFAULT $field_default";}
                            modifyDatabase("",$modstatement);
                            echo $modifyoutput; flush();@ob_flush();
                        }
                    }
                }
            }
        }
    }
}

function upgradeSurveyTables126()
{
    $surveyidquery = "SELECT sid FROM {{surveys}} WHERE active='Y' and datestamp='Y'";
    $surveyidresult = Yii::app()->db->createCommand($surveyidquery)->queryAll();
    if (!$surveyidresult) {return "Database Error";}
    else
    {
        foreach ( $surveyidresult as $sv )
        {
            Yii::app()->db->createCommand()->addColumn('{{survey_'.$sv['sid'].'}}','startdate','datetime');
        }
    }
}

function upgradeTokenTables126()
{
    global $modifyoutput;
    $sVarchar=Yii::app()->getConfig('varchar');
    $surveyidresult = dbGetTablesLike("tokens%");
    if (!$surveyidresult) {return "Database Error";}
    else
    {
        foreach ( $surveyidresult as $sv )
        {
            Yii::app()->db->createCommand()->alterColumn(reset($sv),'token',"{$sVarchar}(15)");
            Yii::app()->db->createCommand()->addColumn(reset($sv),'emailstatus',"{$sVarchar}(300) NOT NULL DEFAULT 'OK'");
        }
    }
}

function alterLanguageCode($sOldLanguageCode,$sNewLanguageCode)
{
    Yii::app()->db->createCommand()->update('{{answers}}',array('language'=>$sNewLanguageCode),'language=:language',array(':language'=>$sOldLanguageCode));
    Yii::app()->db->createCommand()->update('{{questions}}',array('language'=>$sNewLanguageCode),'language=:language',array(':language'=>$sOldLanguageCode));
    Yii::app()->db->createCommand()->update('{{groups}}',array('language'=>$sNewLanguageCode),'language=:language',array(':language'=>$sOldLanguageCode));
    Yii::app()->db->createCommand()->update('{{labels}}',array('language'=>$sNewLanguageCode),'language=:language',array(':language'=>$sOldLanguageCode));
    Yii::app()->db->createCommand()->update('{{surveys}}',array('language'=>$sNewLanguageCode),'language=:language',array(':language'=>$sOldLanguageCode));
    Yii::app()->db->createCommand()->update('{{surveys_languagesettings}}',array('surveyls_language'=>$sNewLanguageCode),'surveyls_language=:language',array(':language'=>$sOldLanguageCode));
    Yii::app()->db->createCommand()->update('{{users}}',array('lang'=>$sNewLanguageCode),'lang=:language',array(':language'=>$sOldLanguageCode));

    $resultdata=Yii::app()->db->createCommand("select * from {{labelsets}}");
    foreach ($resultdata->queryAll() as $datarow){
        $toreplace=str_replace($sOldLanguageCode,$sNewLanguageCode,$datarow['languages']);
        Yii::app()->db->createCommand()->update('{{labelsets}}',array('languages'=>$toreplace),'lid=:lid',array(':lid'=>$datarow['lid']));
    }

    $resultdata=Yii::app()->db->createCommand("select * from {{surveys}}");
    foreach ($resultdata->queryAll() as $datarow){
        $toreplace=str_replace($sOldLanguageCode,$sNewLanguageCode,$datarow['additional_languages']);
        Yii::app()->db->createCommand()->update('{{surveys}}',array('additional_languages'=>$toreplace),'sid=:sid',array(':sid'=>$datarow['sid']));
    }
}

function addPrimaryKey($sTablename, $aColumns)
{
    Yii::app()->db->createCommand("ALTER TABLE {{".$sTablename."}} ADD PRIMARY KEY (".implode(',',$aColumns).")")->execute();
}


function addUnique($sTablename, $aColumns)
{
    Yii::app()->db->createCommand("ALTER TABLE {{".$sTablename."}} ADD UNIQUE (`".implode('`,`',$aColumns)."`)")->execute();
}

function dropPrimaryKey($sTablename)
{
    $sDBDriverName=Yii::app()->db->getDriverName();
    if ($sDBDriverName=='mysqli') $sDBDriverName='mysql';
    if ($sDBDriverName=='sqlsrv') $sDBDriverName='mssql';

    global $modifyoutput;
    switch ($sDBDriverName){
        case 'mysql':
            $sQuery="ALTER TABLE {{".$sTablename."}} DROP PRIMARY KEY";
            Yii::app()->db->createCommand($sQuery)->execute();
            break;
        case 'pgsql':
        case 'mssql':
            $pkquery = "SELECT CONSTRAINT_NAME "
            ."FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS "
            ."WHERE     (TABLE_NAME = '{{{$sTablename}}}') AND (CONSTRAINT_TYPE = 'PRIMARY KEY')";

            $primarykey = Yii::app()->db->createCommand($pkquery)->query();
            if ($primarykey!=false)
            {
                modifyDatabase("","ALTER TABLE {{".$sTablename."}} DROP CONSTRAINT {$primarykey}"); echo $modifyoutput; flush();@ob_flush();
            }
            break;
        default: die('Unkown database type');
    }

    // find out the constraint name of the old primary key
}

function fixLanguageConsistencyAllSurveys()
{
    $surveyidquery = "SELECT sid,additional_languages FROM ".dbQuoteID('{{surveys}}');
    $surveyidresult = Yii::app()->db->createCommand($surveyidquery)->queryAll();
    foreach ( $surveyidresult as $sv )
    {
        fixLanguageConsistency($sv['sid'],$sv['additional_languages']);
    }
}