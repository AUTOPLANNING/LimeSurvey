<?php 
    class FreeTextQuestionObject extends QuestionBase implements iQuestion 
    {
        protected $attributes = array(
            'question' => array(
                'type' => 'html',
                'localized' => true,
                'label' => 'Question text:'
            ),
            'help' => array(
                'type' => 'html',
                'localized' => true,
                'label' => 'Help text:'
            )
        );
        
        public static $info = array(
            'name' => 'Free text question'
        );
        /**
         * The signature array is used for deriving a unique identifier for
         * a question type.
         * After initial release the contents of this array may NEVER be changed.
         * Changing the contents of the array will identify the question object
         * as a new question type and will break many if not all existing surves.
         * 
         * 
         * - Add more keys to make it more unique.
         * @var array
         */
        protected static $signature = array(
            'orignalAuthor' => 'Sam Mousa',
            'originalName' => 'Free Text',
            'startDev' => '2013-30-1'
        );


        public static function getJavascript()
        {
            $functions = parent::getJavascript();
            // Override get and set if using checkbox layout.
            $functions['bindChange'] = 'js:function(callback) { $(this).bind("change keyup", callback) }';
            return $functions;
        }
        /**
         * 
         * @param Twig_Environment $twig
         * @param boolean $return
         * @param string $name Unique string prefix to be used for all elements with a name and or id attribute.
         * @return null|html
         */
        public function render($name, $language, $return = false)
        {
            $questionText = $this->get('question', '', $language);

            $value = $this->getResponse();


            $out = CHtml::label($this->api->EMevaluateExpression($questionText), $name);

            $out .= CHtml::textField($name, $value, $data);
            if ($return)
            {
                return $out;
            }
            else
            {
                echo $out;
            }
        }
    }
?>