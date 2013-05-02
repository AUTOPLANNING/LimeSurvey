<?php
class LanguageQuestion extends QuestionModule
{
    public function getAnswerHTML()
    {
        

        $checkconditionFunction = "checkconditions";

        $answerlangs = Survey::model()->findByPk($this->surveyid)->additionalLanguages;
        $answerlangs [] = Survey::model()->findByPk($this->surveyid)->language;
        $answer = "\n\t<p class=\"question answer-item dropdown-item langage-item\">\n"
        ."<label for='answer{$this->fieldname}' class='hide label'>{gT('Choose your language')}</label>"
        ."<select name=\"$this->fieldname\" id=\"answer$this->fieldname\" onchange=\"document.getElementById('lang').value=this.value; $checkconditionFunction(this.value, this.name, this.type);\">\n";
        if (!$_SESSION['survey_'.$this->surveyid][$this->fieldname]) {$answer .= "\t<option value=\"\" selected=\"selected\">".gT('Please choose...')."</option>\n";}
        foreach ($answerlangs as $ansrow)
        {
            $answer .= "\t<option value=\"{$ansrow}\"";
            if ($_SESSION['survey_'.$this->surveyid][$this->fieldname] == $ansrow)
            {
                $answer .= SELECTED;
            }
            $aLanguage=getLanguageNameFromCode($ansrow, true);
            $answer .= '>'.$aLanguage[1]."</option>\n";
        }
        $answer .= "</select>\n";
        $answer .= "<input type=\"hidden\" name=\"java$this->fieldname\" id=\"java$this->fieldname\" value=\"".$_SESSION['survey_'.$this->surveyid][$this->fieldname]."\" />\n";

        $answer .= "\n<input type=\"hidden\" name=\"lang\" id=\"lang\" value=\"\" />\n\t</p>\n";

        return $answer;
    }

    public function getDataEntry($idrow, &$fnames, $language)
    {
        $lquery = "SELECT * FROM {{answers}} WHERE qid={$this->id} AND language = '{$language}' ORDER BY sortorder, answer";
        $lresult = dbExecuteAssoc($lquery);


        $slangs = Survey::model()->findByPk($this->surveyid)->additionalLanguages;
        $baselang = Survey::model()->findByPk($this->surveyid)->language;
        array_unshift($slangs,$baselang);

        $output.= "<select name='{$this->fieldname}'>\n";
        $output .= "<option value=''";
        if ($idrow[$this->fieldname] == "") {$output .= " selected='selected'";}
        $output .= ">".gT("Please choose")."..</option>\n";

        foreach ($slangs as $lang)
        {
            $output.="<option value='{$lang}'";
            if ($lang == $idrow[$this->fieldname]) {$output .= " selected='selected'";}
            $output.=">".getLanguageNameFromCode($lang,false)."</option>\n";
        }
        $output .= "</select>";
        return $output;
    }

    public function getExtendedAnswer($value, $language)
    {
        if ($value == "-oth-")
        {
            return $language->gT("Other")." [$value]";
        }
        $result = Answers::model()->getAnswerFromCode($this->id,$value,$language->langcode) or die ("Couldn't get answer type."); //Checked
        if($result->count())
        {
            $result =array_values($result->readAll());
            return $result[count($result)-1]['answer']." [$value]";
        }
        return $value;
    }

    public function getQuotaValue($value)
    {
        return array($this->surveyid.'X'.$this->gid.'X'.$this->id => $value);
    }

    public function getDBField()
    {
        return 'VARCHAR(20)';
    }

    public function jsVarNameOn()
    {
        return 'java'.$this->fieldname;
    }

    public function getQuotaAnswers($iQuotaId)
    {
        $sBaseLang = Survey::model()->findByPk($this->surveyid)->language;
        $slangs = Survey::model()->findByPk($this->surveyid)->additionalLanguages;
        array_unshift($slangs, $sBaseLang);

        while (list($key, $value) = each($slangs))
        {
            $aAnswerList[$value] = array('Title' => $this->title, 'Display' => getLanguageNameFromCode($value, false), $value);
        }

        $aResults = Quota_members::model()->findAllByAttributes(array('sid' => $this->surveyid, 'qid' => $this->id, 'quota_id' => $iQuotaId));
        foreach ($aResults as $aQuotaList)
        {
            $aAnswerList[$aQuotaList['code']]['rowexists'] = '1';
        }
    }

    public function getDataEntryView($language)
    {
        $slangs = Survey::model()->findByPk($this->surveyid)->additionalLanguages;
        $sbaselang = Survey::model()->findByPk($this->surveyid)->language;
        array_unshift($slangs,$sbaselang);

        $output = "<select name='{$this->fieldname}'>";
        $output .= "<option value='' selected='selected'>{$language->gT("Please choose")}..</option>";
        foreach ($slangs as $lang)
        {
            $output .= "<option value='{$lang}'>" . getLanguageNameFromCode($lang,false) . "</option>";
        }
        $output .= "</select>";
        return $output;
    }

    public function getConditionAnswers()
    {
        return array();
    }

    public function availableAttributes($attr = false)
    {
        $attrs=array("statistics_showgraph","statistics_graphtype","hide_tip","hidden","random_group");
        return $attr?in_array($attr,$attrs):$attrs;
    }

    public function questionProperties($prop = false)
    {
        $clang=Yii::app()->lang;
        $props=array('description' => gT("Language Switch"),'group' => gT("Mask questions"),'class' => 'language','hasdefaultvalues' => 0,'subquestions' => 0,'assessable' => 0,'answerscales' => 0,'enum' => 0);
        return $prop?$props[$prop]:$props;
    }
}
?>