<?php 

    class GroupsController extends LSYii_Controller
    {
        public function actionPreview($id, $language = 'en')
        {
            $group = Groups::model()->findByAttributes(array(
                'gid' => $id
            ));
            $renderedQuestions = array();
            if (isset($group))
            {
                App()->getSurveySession()->create($group->sid, true);

                
                if (!isset($language))
                {
                    $language = Survey::model()->findFieldByPk($group->sid, 'language');
                }
                
                $questions = Questions::model()->findAllByAttributes(array(
                    'gid' => $id,
                    'parent_id' => null
                ));
                Yii::import('ext.ExpressionManager.ExpressionManager');
                $em = new ExpressionManager(array(
                    'isValidVariable' => function($name) {
                        //echo 'isvalid:<br>'; var_dump($name);
                        return true;
                    },
                     'GetVarAttribute' => function($name, $attr, $default) {
                        //echo 'getattr:<br>'; var_dump(func_get_args());
                        switch ($attr) {
                            case 'code': return $name;
                            case 'jsName' : return 'JS' . $name;
                            default: return $default;
                        }
                    }

                ));

                $renderedQuestions = array();
                $data = array();
                $i = 0;
                foreach ($questions as $question)
                {
                    $questionObject = App()->getPluginManager()->constructQuestionFromGUID($question->questiontype, $question->qid);
                    if (!isset($data[$questionObject->getGUID()]))
                    {
                        $data[$questionObject->getGUID()] = $questionObject->getJavascript();
                    }
                    $name = "{$group->group_name}-{$question->qid}";
                    $renderedQuestions[] = $questionObject->render($name, $language, true);
                    App()->getLimeScript()->add("p.questions.{$question->code}.type", $questionObject->getGUID());
                    
                    App()->getLimeScript()->add("p.questions.{$question->code}.div",  "question$i");
                    
                    // This is used to determine forward references in expressions.
                    App()->getLimeScript()->add("p.questions.{$question->code}.index", $i);
                    // Register variables.
                    $code = $question->code;
                    // More variables shoudl be added here.
                    App()->getLimeScript()->add("p.VariableToCode.$code", $code);



                    App()->getLimeScript()->add("p.questions.{$question->code}.id", $name);
                    if ($question->relevance != null)
                    {
                        $em->ProcessBooleanExpression($question->relevance);
                        $js = $em->GetJavaScriptEquivalentOfExpression();
                        App()->getLimeScript()->add("p.questions.{$question->code}.relevanceStatus", new CJavaScriptExpression('function() { return ' . $js . '; }'));
                        
                    }
                    foreach ($questionObject->getVariables() as $variable)
                    {
                        App()->getLimeScript()->add("p.VariableToCode.{$code}_{$variable}", $code);
                    }
                    $i++;
                }
                App()->getLimeScript()->add("p.QuestionTypes", $data);
            }
            $template = Survey::model()->findFieldByPk($group->sid, 'template');
            $this->layout = 'survey';
            
            /*
            var_dump($em->ProcessBooleanExpression('q1 == 1'));
            echo '---------------------';
            var_dump($em->GetJavaScriptEquivalentOfExpression());
            echo '---------------------';
            var_dump($em->sProcessStringContainingExpressions('test123 {1+2}'));
            */
            $this->render('/groups/preview', compact('renderedQuestions', 'template'));
        }
        
        
        
        public function actionView($id)
        {
            
                
            $group = Groups::model()->findByAttributes(array(
                'gid' => $id
            ));
            
            $this->navData['groupId'] = $id;
            $this->navData['surveyId'] = $group['sid'];
            $this->render('/groups/view', compact('group'));
        }
    }
?>