<?php if (!class_exists('Yii', false)) die('No direct script access allowed in ' . __FILE__);
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
 *    $Id$
 */

/**
 * Printable Survey Controller
 *
 * This controller shows a printable survey.
 *
 * @package        LimeSurvey
 * @subpackage    Backend
 */
class printablesurvey extends Survey_Common_Action
{

    /**
     * Show printable survey
     */
    function index($surveyid, $lang = null)
    {
        $surveyid = sanitize_int($surveyid);
        if(!hasSurveyPermission($surveyid,'surveycontent','read'))
        {
            $clang = $this->getController()->lang;
            $aData['surveyid'] = $surveyid;
            $this->getController()->_css_admin_includes(Yii::app()->getConfig('adminstyleurl')."superfish.css");
            $message['title']= gT('Access denied!');
            $message['message']= gT('You do not have sufficient rights to access this page.');
            $message['class']= "error";
            $this->_renderWrappedTemplate('survey', array("message"=>$message), $aData);
        }
        else
        {
            // PRESENT SURVEY DATAENTRY SCREEN
            // Set the language of the survey, either from GET parameter of session var
            if (isset($lang))
            {
                $lang = preg_replace("/[^a-zA-Z0-9-]/", "", $lang);
                if ($lang) $surveyprintlang = $lang;
            } else
            {
                $surveyprintlang=getBaseLanguageFromSurveyID((int) $surveyid);
            }
            $_POST['surveyprintlang']=$surveyprintlang;

            $desrow = Survey::model()->with(array('languagesettings'=>array('condition'=>'surveyls_language=:language','params'=>array(':language'=>$surveyprintlang))))->findByAttributes(array('sid' => $surveyid));

            if (is_null($desrow))
                $this->getController()->error('Invalid survey ID');

            $desrow = array_merge($desrow->attributes, $desrow->languagesettings[0]->attributes);

            //echo '<pre>'.print_r($desrow,true).'</pre>';
            $template = $desrow['template'];
            $welcome = $desrow['surveyls_welcometext'];
            $end = $desrow['surveyls_endtext'];
            $surveyname = $desrow['surveyls_title'];
            $surveydesc = $desrow['surveyls_description'];
            $surveyactive = $desrow['active'];
            $surveytable = "{{survey_".$desrow['sid']."}}";
            $surveyexpirydate = $desrow['expires'];
            $surveyfaxto = $desrow['faxto'];
            $dateformattype = $desrow['surveyls_dateformat'];

            Yii::app()->loadHelper('surveytranslator');
            
            if (!is_null($surveyexpirydate))
            {
                $dformat=getDateFormatData($dateformattype);
                $dformat=$dformat['phpdate'];

                $expirytimestamp = strtotime($surveyexpirydate);
                $expirytimeofday_h = date('H',$expirytimestamp);
                $expirytimeofday_m = date('i',$expirytimestamp);

                $surveyexpirydate = date($dformat,$expirytimestamp);

                if(!empty($expirytimeofday_h) || !empty($expirytimeofday_m))
                {
                    $surveyexpirydate .= ' &ndash; '.$expirytimeofday_h.':'.$expirytimeofday_m;
                };            
                sprintf(gT("Please submit by %s"), $surveyexpirydate);
            }
            else
            {
                $surveyexpirydate='';
            }

            if(is_file(Yii::app()->getConfig('usertemplaterootdir').DIRECTORY_SEPARATOR.$template.DIRECTORY_SEPARATOR.'print_survey.pstpl'))
            {
                define('PRINT_TEMPLATE_DIR' , Yii::app()->getConfig('usertemplaterootdir').DIRECTORY_SEPARATOR.$template.DIRECTORY_SEPARATOR , true);
                define('PRINT_TEMPLATE_URL' , Yii::app()->getConfig('usertemplaterooturl').'/'.$template.'/' , true);
            }
            elseif(is_file(Yii::app()->getConfig('usertemplaterootdir').'/'.$template.'/print_survey.pstpl'))
            {
                define('PRINT_TEMPLATE_DIR' , Yii::app()->getConfig('standardtemplaterootdir').DIRECTORY_SEPARATOR.$template.DIRECTORY_SEPARATOR , true);
                define('PRINT_TEMPLATE_URL' , Yii::app()->getConfig('standardtemplaterooturl').'/'.$template.'/' , true);
            }
            else
            {
                define('PRINT_TEMPLATE_DIR' , Yii::app()->getConfig('standardtemplaterootdir').DIRECTORY_SEPARATOR.'default'.DIRECTORY_SEPARATOR , true);
                define('PRINT_TEMPLATE_URL' , Yii::app()->getConfig('standardtemplaterooturl').'/default/' , true);
            }

            LimeExpressionManager::StartSurvey($surveyid, 'survey',NULL,false,LEM_PRETTY_PRINT_ALL_SYNTAX);
            $moveResult = LimeExpressionManager::NavigateForwards();

            $condition = "sid = '{$surveyid}' AND language = '{$surveyprintlang}'";
            $degresult = Groups::model()->getAllGroups($condition, array('group_order'));  //xiao,

            if (!isset($surveyfaxto) || !$surveyfaxto and isset($surveyfaxnumber))
            {
                $surveyfaxto=$surveyfaxnumber; //Use system fax number if none is set in survey.
            }


            $headelements = getPrintableHeader();

            //if $showsgqacode is enabled at config.php show table name for reference
            $showsgqacode = Yii::app()->getConfig("showsgqacode");
            if(isset($showsgqacode) && $showsgqacode == true)
            {
                $surveyname =  $surveyname."<br />[".gT('Database')." ".gT('table').": $surveytable]";
            }
            else
            {
                $surveyname = $surveyname;
            }

            $survey_output = array(
            'SITENAME' => Yii::app()->getConfig("sitename")
            ,'SURVEYNAME' => $surveyname
            ,'SURVEYDESCRIPTION' => $surveydesc
            ,'WELCOME' => $welcome
            ,'END' => $end
            ,'THEREAREXQUESTIONS' => 0
            ,'SUBMIT_TEXT' => gT("Submit Your Survey.")
            ,'SUBMIT_BY' => $surveyexpirydate
            ,'THANKS' => gT("Thank you for completing this survey.")
            ,'HEADELEMENTS' => $headelements
            ,'TEMPLATEURL' => PRINT_TEMPLATE_URL
            ,'FAXTO' => $surveyfaxto
            ,'PRIVACY' => ''
            ,'GROUPS' => ''
            );

            $survey_output['FAX_TO'] ='';
            if(!empty($surveyfaxto) && $surveyfaxto != '000-00000000') //If no fax number exists, don't display faxing information!
            {
                $survey_output['FAX_TO'] = gT("Please fax your completed survey to:")." $surveyfaxto";
            }

            /**
             * Output arrays:
             *    $survey_output  =       final vaiables for whole survey
             *        $survey_output['SITENAME'] =
             *        $survey_output['SURVEYNAME'] =
             *        $survey_output['SURVEY_DESCRIPTION'] =
             *        $survey_output['WELCOME'] =
             *        $survey_output['THEREAREXQUESTIONS'] =
             *        $survey_output['HEADELEMENTS'] =
             *        $survey_output['TEMPLATEURL'] =
             *        $survey_output['SUBMIT_TEXT'] =
             *        $survey_output['SUBMIT_BY'] =
             *        $survey_output['THANKS'] =
             *        $survey_output['FAX_TO'] =
             *        $survey_output['SURVEY'] =     contains an array of all the group arrays
             *
             *    $groups[]       =       an array of all the groups output
             *        $group['GROUPNAME'] =
             *        $group['GROUPDESCRIPTION'] =
             *        $group['QUESTIONS'] =     templated formatted content if $question is appended to this at the end of processing each question.
             *        $group['ODD_EVEN'] =     class to differentiate alternate groups
             *        $group['SCENARIO'] =
             *
             *    $questions[]    =       contains an array of all the questions within a group
             *        $question['QUESTION_CODE'] =         content of the question code field
             *        $question['QUESTION_TEXT'] =         content of the question field
             *        $question['QUESTION_SCENARIO'] =         if there are conditions on a question, list the conditions.
             *        $question['QUESTION_MANDATORY'] =     translated 'mandatory' identifier
             *        $question['QUESTION_CLASS'] =         classes to be added to wrapping question div
             *        $question['QUESTION_TYPE_HELP'] =         instructions on how to complete the question
             *        $question['QUESTION_MAN_MESSAGE'] =     (not sure if this is used) mandatory error
             *        $question['QUESTION_VALID_MESSAGE'] =     (not sure if this is used) validation error
             *        $question['ANSWER'] =                contains formatted HTML answer
             *        $question['QUESTIONHELP'] =         content of the question help field.
             *
             */

            $total_questions = 0;
            $mapquestionsNumbers=Array();
            $answertext = '';   // otherwise can throw an error on line 1617

            // =========================================================
            // START doin the business:
            foreach ($degresult->readAll() as $degrow)
            {
                // ---------------------------------------------------
                // START doing groups


                $deqrows=Questions::model()->with('question_types')->findAllByAttributes(array('sid'=>$surveyid, 'gid'=>$degrow['gid'], 'language'=>$surveyprintlang, 'parent_qid'=>0), array('order' => 'question_order'));

                if ($degrow['description'])
                {
                    $group_desc = $degrow['description'];
                }
                else
                {
                    $group_desc = '';
                }

                $group = array(
                    'GROUPNAME' => $degrow['group_name']
                    ,'GROUPDESCRIPTION' => $group_desc
                    ,'QUESTIONS' => '' // templated formatted content if $question is appended to this at the end of processing each question.
                );

                // A group can have only hidden questions. In that case you don't want to see the group's header/description either.
                $bGroupHasVisibleQuestions = false;
                $gid = $degrow['gid'];
                //Alternate bgcolor for different groups
                if (!isset($group['ODD_EVEN']) || $group['ODD_EVEN'] == ' g-row-even')
                {
                    $group['ODD_EVEN'] = ' g-row-odd';}
                else
                {
                    $group['ODD_EVEN'] = ' g-row-even';
                }

                //Loop through questions
                foreach ($deqrows as $deqrow)
                {
                    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
                    // START doing questions

                    $q = createQuestion($deqrow->question_types['class'], array('id'=>$deqrow['qid'], 'gid'=>$deqrow['gid'], 'surveyid'=>$deqrow['sid'], 'isother'=>$deqrow['other']));
                    $qidattributes=$q->getAttributeValues();
                    if ($qidattributes['hidden'] == 1 && !$q->isEquation())
                    {
                        continue;
                    }
                    $bGroupHasVisibleQuestions = true;

                    //GET ANY CONDITIONS THAT APPLY TO THIS QUESTION

                    $printablesurveyoutput = '';
                    $explanation = ''; //reset conditions explanation
                    $s=0;

                    $qinfo = LimeExpressionManager::GetQuestionStatus($deqrow['qid']);
                    $relevance = trim($qinfo['info']['relevance']);
                    $explanation = $qinfo['relEqn'];

                    if (trim($relevance) != '' && trim($relevance) != '1')
                    {
                        $explanation = "<b>".gT('Only answer this question if the following conditions are met:')."</b>"
                        ."<br/> ° ".$explanation;
                    }
                    else
                    {
                        $explanation = '';
                    }

                    ++$total_questions;

                    //TIBO map question qid to their q number
                    $mapquestionsNumbers[$deqrow['qid']]=$total_questions;
                    //END OF GETTING CONDITIONS

                    $qid = $deqrow['qid'];
                    $fieldname = "$surveyid"."X"."$gid"."X"."$qid";

                    if(isset($showsgqacode) && $showsgqacode == true)
                    {
                        $deqrow['question'] = $deqrow['question']."<br />".gT("ID:")." $fieldname <br />".
                                              gT("Question code:")." ".$deqrow['title'];
                    }

                    $question = array(
                     'QUESTION_NUMBER' => $total_questions    // content of the question code field
                    ,'QUESTION_CODE' => $deqrow['title']
                    ,'QUESTION_TEXT' => preg_replace('/(?:<br ?\/?>|<\/(?:p|h[1-6])>)$/is' , '' , $deqrow['question'])    // content of the question field
                    ,'QUESTION_SCENARIO' => $explanation    // if there are conditions on a question, list the conditions.
                    ,'QUESTION_MANDATORY' => ''        // translated 'mandatory' identifier
                    ,'QUESTION_ID' => $deqrow['qid']    // id to be added to wrapping question div
                    ,'QUESTION_CLASS' => $q->questionProperties('class')    // classes to be added to wrapping question div
                    ,'QUESTION_TYPE_HELP' => $qinfo['validTip']   // instructions on how to complete the question // prettyValidTip is too verbose; assuming printable surveys will use static values
                    ,'QUESTION_MAN_MESSAGE' => ''        // (not sure if this is used) mandatory error
                    ,'QUESTION_VALID_MESSAGE' => ''        // (not sure if this is used) validation error
                    ,'QUESTION_FILE_VALID_MESSAGE' => ''// (not sure if this is used) file validation error
                    ,'QUESTIONHELP' => ''            // content of the question help field.
                    ,'ANSWER' => ''                // contains formatted HTML answer
                    );

                    if($question['QUESTION_TYPE_HELP'] != "") {
                        $question['QUESTION_TYPE_HELP'] .= "<br />\n";
                    }

                    if ($deqrow['mandatory'] == 'Y')
                    {
                        $question['QUESTION_MANDATORY'] = gT('*');
                        $question['QUESTION_CLASS'] .= ' mandatory';
                    }


                    //DIFFERENT TYPES OF DATA FIELD HERE
                    if ($deqrow['help'])
                    {
                        $hh = $deqrow['help'];
                        $question['QUESTIONHELP'] = $hh;
                    }


                    if (!empty($qidattributes['page_break']))
                    {
                        $question['QUESTION_CLASS'] .=' breakbefore ';
                    }


                    if (isset($qidattributes['maximum_chars']) && $qidattributes['maximum_chars']!='') {
                        $question['QUESTION_CLASS'] ="max-chars-{$qidattributes['maximum_chars']} ".$question['QUESTION_CLASS'];
                    }

                    $help = $q->getTypeHelp($clang);
                    $question['QUESTION_TYPE_HELP'] .= $help;
                    $question['QUESTION_TYPE_HELP'] .= self::_array_filter_help($qidattributes, $surveyprintlang, $surveyid);
                    $question['ANSWER'] .= $q->getPrintAnswers($clang);
                    $question['QUESTION_TYPE_HELP'] = self::_star_replace($question['QUESTION_TYPE_HELP']);
                    $group['QUESTIONS'] .= self::_populate_template( 'question' , $question);
                }
                if ($bGroupHasVisibleQuestions)
                {
                    $survey_output['GROUPS'] .= self::_populate_template( 'group' , $group );
                }
            }

            $survey_output['THEREAREXQUESTIONS'] =  str_replace( '{NUMBEROFQUESTIONS}' , $total_questions , gT('There are {NUMBEROFQUESTIONS} questions in this survey'));

            // START recursive tag stripping.
            // PHP 5.1.0 introduced the count parameter for preg_replace() and thus allows this procedure to run with only one regular expression.
            // Previous version of PHP needs two regular expressions to do the same thing and thus will run a bit slower.
            $server_is_newer = version_compare(PHP_VERSION , '5.1.0' , '>');
            $rounds = 0;
            while($rounds < 1)
            {
                $replace_count = 0;
                if($server_is_newer) // Server version of PHP is at least 5.1.0 or newer
                {
                    $survey_output['GROUPS'] = preg_replace(
                    array(
                                             '/<td>(?:&nbsp;|&#160;| )?<\/td>/isU'
                                             ,'/<th[^>]*>(?:&nbsp;|&#160;| )?<\/th>/isU'
                                             ,'/<([^ >]+)[^>]*>(?:&nbsp;|&#160;|\r\n|\n\r|\n|\r|\t| )*<\/\1>/isU'
                                             )
                                             ,array(
                                             '[[EMPTY-TABLE-CELL]]'
                                             ,'[[EMPTY-TABLE-CELL-HEADER]]'
                                             ,''
                                             )
                                             ,$survey_output['GROUPS']
                                             ,-1
                                             ,$replace_count
                                             );
                }
                else // Server version of PHP is older than 5.1.0
                {
                    $survey_output['GROUPS'] = preg_replace(
                    array(
                                             '/<td>(?:&nbsp;|&#160;| )?<\/td>/isU'
                                             ,'/<th[^>]*>(?:&nbsp;|&#160;| )?<\/th>/isU'
                                             ,'/<([^ >]+)[^>]*>(?:&nbsp;|&#160;|\r\n|\n\r|\n|\r|\t| )*<\/\1>/isU'
                                             )
                                             ,array(
                                             '[[EMPTY-TABLE-CELL]]'
                                             ,'[[EMPTY-TABLE-CELL-HEADER]]'
                                             ,''
                                             )
                                             ,$survey_output['GROUPS']
                                             );
                                             $replace_count = preg_match(
                                     '/<([^ >]+)[^>]*>(?:&nbsp;|&#160;|\r\n|\n\r|\n|\r|\t| )*<\/\1>/isU'
                                     , $survey_output['GROUPS']
                                     );
                }

                if($replace_count == 0)
                {
                    ++$rounds;
                    $survey_output['GROUPS'] = preg_replace(
                    array(
                                     '/\[\[EMPTY-TABLE-CELL\]\]/'
                                     ,'/\[\[EMPTY-TABLE-CELL-HEADER\]\]/'
                                     ,'/\n(?:\t*\n)+/'
                                     )
                                     ,array(
                                     '<td>&nbsp;</td>'
                                     ,'<th>&nbsp;</th>'
                                     ,"\n"
                                     )
                                     ,$survey_output['GROUPS']
                                     );

                }
            }

            $survey_output['GROUPS'] = preg_replace( '/(<div[^>]*>){NOTEMPTY}(<\/div>)/' , '\1&nbsp;\2' , $survey_output['GROUPS']);

            // END recursive empty tag stripping.

            echo self::_populate_template( 'survey' , $survey_output );
        }// End print
    }

    /**
     * A poor mans templating system.
     *
     *     $template    template filename (path is privided by config.php)
     *     $input        a key => value array containg all the stuff to be put into the template
     *     $line         for debugging purposes only.
     *
     * Returns a formatted string containing template with
     * keywords replaced by variables.
     *
     * How:
     */
    private function _populate_template( $template , $input  , $line = '')
    {
        $full_path = PRINT_TEMPLATE_DIR.'print_'.$template.'.pstpl';
        $full_constant = 'TEMPLATE'.$template.'.pstpl';
        if(!defined($full_constant))
        {
            if(is_file($full_path))
            {
                define( $full_constant , file_get_contents($full_path));

                $template_content = constant($full_constant);
                $test_empty = trim($template_content);
                if(empty($test_empty))
                {
                    return "<!--\n\t$full_path\n\tThe template was empty so is useless.\n-->";
                }
            }
            else
            {
                define($full_constant , '');
                return "<!--\n\t$full_path is not a propper file or is missing.\n-->";
            }
        }
        else
        {
            $template_content = constant($full_constant);
            $test_empty = trim($template_content);
            if(empty($test_empty))
            {
                return "<!--\n\t$full_path\n\tThe template was empty so is useless.\n-->";
            }
        }

        if(is_array($input))
        {
            foreach($input as $key => $value)
            {
                $find[] = '{'.$key.'}';
                $replace[] = $value;
            }
            return str_replace( $find , $replace , $template_content );
        }
        else
        {
            if(Yii::app()->getConfig('debug') > 0)
            {
                if(!empty($line))
                {
                    $line =  'LINE '.$line.': ';
                }
                return '<!-- '.$line.'There was nothing to put into the template -->'."\n";
            }
        }
    }

    private function _min_max_answers_help($qidattributes, $surveyprintlang, $surveyid) {
        $clang = $this->getController()->lang;
        $output = "";
        if(!empty($qidattributes['min_answers'])) {
            $output .= "\n<p class='extrahelp'>".sprintf(gT("Please choose at least %s items."), $qidattributes['min_answers'])."</p>\n";
        }
        if(!empty($qidattributes['max_answers'])) {
            $output .= "\n<p class='extrahelp'>".sprintf(gT("Please choose no more than %s items."),$qidattributes['max_answers'])."</p>\n";
        }
        return $output;
    }


    public static function input_type_image( $type , $title = '' , $x = 40 , $y = 1 , $line = '' )
    {
        if($type == 'other' or $type == 'othercomment')
        {
            $x = 1;
        }
        $tail = substr($x , -1 , 1);
        switch($tail)
        {
            case '%':
            case 'm':
            case 'x':    $x_ = $x;
            break;
            default:    $x_ = $x / 2;
        }

        if($y < 2)
        {
            $y_ = 2;
        }
        else
        {
            $y_ = $y * 2;
        }

        if(!empty($title))
        {
            $div_title = ' title="'.htmlspecialchars($title).'"';
        }
        else
        {
            $div_title = '';
        }
        switch($type)
        {
            case 'textarea':
            case 'text':    $style = ' style="width:'.$x_.'em; height:'.$y_.'em;"';
            break;
            default:    $style = '';
        }

        switch($type)
        {
            case 'radio':
            case 'checkbox':if(!defined('IMAGE_'.$type.'_SIZE'))
            {
                $image_dimensions = getimagesize(PRINT_TEMPLATE_DIR.'print_img_'.$type.'.png');
                // define('IMAGE_'.$type.'_SIZE' , ' width="'.$image_dimensions[0].'" height="'.$image_dimensions[1].'"');
                define('IMAGE_'.$type.'_SIZE' , ' width="14" height="14"');
            }
            $output = '<img src="'.PRINT_TEMPLATE_URL.'print_img_'.$type.'.png"'.constant('IMAGE_'.$type.'_SIZE').' alt="'.htmlspecialchars($title).'" class="input-'.$type.'" />';
            break;

            case 'rank':
            case 'other':
            case 'othercomment':
            case 'text':
            case 'textarea':$output = '<div class="input-'.$type.'"'.$style.$div_title.'>{NOTEMPTY}</div>';
            break;

            default:    $output = '';
        }
        return $output;
    }

    private function _star_replace($input)
    {
        return preg_replace(
                 '/\*(.*)\*/U'
                 ,'<strong>\1</strong>'
                 ,$input
                 );
    }

    private function _array_filter_help($qidattributes, $surveyprintlang, $surveyid) 
    {
        $clang = $this->getController()->lang;
        $output = "";
        if(!empty($qidattributes['array_filter']))
        {
            $newquestiontext = Questions::model()->findByAttributes(array('title' => $qidattributes['array_filter'], 'language' => $surveyprintlang, 'sid' => $surveyid))->getAttribute('question');
            $output .= "\n<p class='extrahelp'>
                ".sprintf(gT("Only answer this question for the items you selected in question %s ('%s')"),$qidattributes['array_filter'], flattenText(breakToNewline($newquestiontext['question'])))."
            </p>\n";
        }
        if(!empty($qidattributes['array_filter_exclude']))
        {
            $newquestiontext = Questions::model()->findByAttributes(array('title' => $qidattributes['array_filter_exclude'], 'language' => $surveyprintlang, 'sid' => $surveyid))->getAttribute('question');

            $output .= "\n    <p class='extrahelp'>
                ".sprintf(gT("Only answer this question for the items you did not select in question %s ('%s')"),$qidattributes['array_filter_exclude'], breakToNewline($newquestiontext['question']))."
            </p>\n";
        }
        return $output;
    }

    /*
     * $code: Text string containing the reference (column heading) for the current (sub-) question
     *
     * Checks if the $showsgqacode setting is enabled at config and adds references to the column headings
     * to the output so it can be used as a code book for customized SQL queries when analysing data.
     *
     * return: adds the text string to the overview
     */
    private function _addsgqacode($code)
    {
        $showsgqacode = Yii::app()->getConfig('showsgqacode');
        if(isset($showsgqacode) && $showsgqacode == true)
        {
            return $code;
        }
    }

}
