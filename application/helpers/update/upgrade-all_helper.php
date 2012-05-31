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

    if ($oldversion < 143)
    {
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
    }
    if ($oldversion < 150)
    {
        $fields = array(
            'relevance' => 'TEXT'
        );
        Yii::app()->db->createCommand()->addColumn('{{questions}}','relevance','TEXT');
    }
    if ($oldversion < 151)
    {
        $fields = array(
            'randomization_group' => 'VARCHAR(20) NOT NULL default \'\''
        );
        Yii::app()->db->createCommand()->addColumn('{{groups}}','randomization_group','VARCHAR(20) NOT NULL default \'\'');
    }
    if ($oldversion < 152)
    {
        $sql = "CREATE INDEX question_attributes_idx3 ON {{question_attributes}}(attribute)";
        Yii::app()->db->createCommand($sql)->execute();
    }
    if ($oldversion < 153)
    {
        $fields = array(
            'id' => 'INT',
            'errortime' => 'VARCHAR(50)',
            'sid' => 'INT',
            'gid' => 'INT',
            'qid' => 'INT',
            'gseq' => 'INT',
            'qseq' => 'INT',
            'type' => 'VARCHAR(50)',
            'eqn' => 'TEXT',
            'prettyprint' => 'TEXT'
        );
        Yii::app()->db->createCommand()->createTable('{{expression_errors}}',$fields);
    }
    if ($oldversion < 156)
    {
        // If a survey has an deleted owner, re-own the survey to the superadmin
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

}

function upgrade_question_attributes148()
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
        $aAllAttributes=questionAttributes(true);
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

function upgrade_survey_table145()
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
        Yii::app()->db->createCommand($sSurveyUpdateQuery)->execute();
    }

}


function upgrade_surveypermissions_table145()
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
                                                                            'uid'=>$aPermissionRow['uid']))->getText();
            modifyDatabase("",$sPermissionInsertQuery); echo $modifyoutput; flush();@ob_flush();

            $sPermissionInsertQuery=Yii::app()->db->createCommand()->insert($tablename,array('permission'=>'quotas',
                                                                            'create_p'=>$aPermissionRow['define_questions'],
                                                                            'read_p'=>$aPermissionRow['define_questions'],
                                                                            'update_p'=>$aPermissionRow['define_questions'],
                                                                            'delete_p'=>$aPermissionRow['define_questions'],
                                                                            'sid'=>$aPermissionRow['sid'],
                                                                            'uid'=>$aPermissionRow['uid']))->getText();
            modifyDatabase("",$sPermissionInsertQuery); echo $modifyoutput; flush();@ob_flush();

            $sPermissionInsertQuery=Yii::app()->db->createCommand()->insert($tablename,array('permission'=>'responses',
                                                                            'create_p'=>$aPermissionRow['browse_response'],
                                                                            'read_p'=>$aPermissionRow['browse_response'],
                                                                            'update_p'=>$aPermissionRow['browse_response'],
                                                                            'delete_p'=>$aPermissionRow['delete_survey'],
                                                                            'export_p'=>$aPermissionRow['export'],
                                                                            'import_p'=>$aPermissionRow['browse_response'],
                                                                            'sid'=>$aPermissionRow['sid'],
                                                                            'uid'=>$aPermissionRow['uid']))->getText();
            modifyDatabase("",$sPermissionInsertQuery); echo $modifyoutput; flush();@ob_flush();

            $sPermissionInsertQuery=Yii::app()->db->createCommand()->insert($tablename,array('permission'=>'statistics',
                                                                            'read_p'=>$aPermissionRow['browse_response'],
                                                                            'sid'=>$aPermissionRow['sid'],
                                                                            'uid'=>$aPermissionRow['uid']))->getText();
            modifyDatabase("",$sPermissionInsertQuery); echo $modifyoutput; flush();@ob_flush();

            $sPermissionInsertQuery=Yii::app()->db->createCommand()->insert($tablename,array('permission'=>'survey',
                                                                            'read_p'=>1,
                                                                            'delete_p'=>$aPermissionRow['delete_survey'],
                                                                            'sid'=>$aPermissionRow['sid'],
                                                                            'uid'=>$aPermissionRow['uid']))->getText();
            modifyDatabase("",$sPermissionInsertQuery); echo $modifyoutput; flush();@ob_flush();

            $sPermissionInsertQuery=Yii::app()->db->createCommand()->insert($tablename,array('permission'=>'surveyactivation',
                                                                            'update_p'=>$aPermissionRow['activate_survey'],
                                                                            'sid'=>$aPermissionRow['sid'],
                                                                            'uid'=>$aPermissionRow['uid']))->getText();
            modifyDatabase("",$sPermissionInsertQuery); echo $modifyoutput; flush();@ob_flush();

            $sPermissionInsertQuery=Yii::app()->db->createCommand()->insert($tablename,array('permission'=>'surveycontent',
                                                                            'create_p'=>$aPermissionRow['define_questions'],
                                                                            'read_p'=>$aPermissionRow['define_questions'],
                                                                            'update_p'=>$aPermissionRow['define_questions'],
                                                                            'delete_p'=>$aPermissionRow['define_questions'],
                                                                            'export_p'=>$aPermissionRow['export'],
                                                                            'import_p'=>$aPermissionRow['define_questions'],
                                                                            'sid'=>$aPermissionRow['sid'],
                                                                            'uid'=>$aPermissionRow['uid']))->getText();
            modifyDatabase("",$sPermissionInsertQuery); echo $modifyoutput; flush();@ob_flush();

            $sPermissionInsertQuery=Yii::app()->db->createCommand()->insert($tablename,array('permission'=>'surveylocale',
                                                                            'read_p'=>$aPermissionRow['edit_survey_property'],
                                                                            'update_p'=>$aPermissionRow['edit_survey_property'],
                                                                            'sid'=>$aPermissionRow['sid'],
                                                                            'uid'=>$aPermissionRow['uid']))->getText();
            modifyDatabase("",$sPermissionInsertQuery); echo $modifyoutput; flush();@ob_flush();

            $sPermissionInsertQuery=Yii::app()->db->createCommand()->insert($tablename,array('permission'=>'surveysettings',
                                                                            'read_p'=>$aPermissionRow['edit_survey_property'],
                                                                            'update_p'=>$aPermissionRow['edit_survey_property'],
                                                                            'sid'=>$aPermissionRow['sid'],
                                                                            'uid'=>$aPermissionRow['uid']))->getText();
            modifyDatabase("",$sPermissionInsertQuery); echo $modifyoutput; flush();@ob_flush();

            $sPermissionInsertQuery=Yii::app()->db->createCommand()->insert($tablename,array('permission'=>'tokens',
                                                                            'create_p'=>$aPermissionRow['activate_survey'],
                                                                            'read_p'=>$aPermissionRow['activate_survey'],
                                                                            'update_p'=>$aPermissionRow['activate_survey'],
                                                                            'delete_p'=>$aPermissionRow['activate_survey'],
                                                                            'export_p'=>$aPermissionRow['export'],
                                                                            'import_p'=>$aPermissionRow['activate_survey'],
                                                                            'sid'=>$aPermissionRow['sid'],
                                                                            'uid'=>$aPermissionRow['uid']))->getText();
            modifyDatabase("",$sPermissionInsertQuery); echo $modifyoutput; flush();@ob_flush();
        }
    }
}

function upgrade_survey_table156()
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
