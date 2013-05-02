<script type='text/javascript'>
    var attr_url = "<?php echo $this->createUrl('/admin/question/sa/ajaxquestionattributes'); ?>";
    var local_attr_url = "<?php echo $this->createUrl('/admin/question/sa/ajaxLocalizedAttributes'); ?>";
    var local_advanced_attr_url = "<?php echo $this->createUrl('/admin/question/sa/ajaxLocalizedAdvancedAttributes'); ?>";
    var imgurl = '<?php echo Yii::app()->getConfig('imageurl'); ?>';
</script>
<?php PrepareEditorScript(true, $this); ?>

<script type='text/javascript'><?php echo $qTypeOutput; ?></script>

<div class='header ui-widget-header'>
    <?php 
    if ($adding) { ?>
        <?php eT("Add a new question"); ?>
        <?php } elseif ($copying) { ?>
        <?php eT("Copy question"); ?>
        <?php } else { ?>
        <?php eT("Edit question"); ?>
        <?php } ?>

</div>

<?php
    $form = 'frmeditquestion';
    $this->renderPartial('/admin/survey/Question/editQuestion_nonlocalized', compact('form', 'selectormodeclass', 'clang', 'eqrow', 'groupList', 'questionList', 'qTypeGroups', 'questionOrderList'));
    $this->renderPartial('/admin/survey/Question/editQuestion_localized', compact('form', 'surveyid', 'clang', 'eqrow', 'gid', 'qid', 'action', 'activated', 'aqresult', 'adding', 'copying'));
?>





<?php if ($adding)
    {
        echo CHtml::form(array("questions/create"), 'post',array(
            'id'=> $form,
            'name'=> $form,
            'onsubmit'=>"return isEmpty(document.getElementById('title'), '".gT("Error: You have to enter a question code.",'js')."');"
        ));
        echo CHtml::endForm();

        if (hasSurveyPermission($surveyid,'surveycontent','import'))
        { ?>
        <br /><div class='header ui-widget-header'><?php eT("...or import a question"); ?></div>
        <?php echo CHtml::form(array("admin/question/sa/import"), 'post', array('id'=>'importquestion', 'name'=>'importquestion', 'enctype'=>'multipart/form-data','onsubmit'=>"return validatefilename(this, '".gT("Please select a file to import!",'js')."');")); ?>
            <ul>
                <li>
                    <label for='the_file'><?php eT("Select LimeSurvey question file (*.lsq/*.csv)"); ?>:</label>
                    <input name='the_file' id='the_file' type="file"/>
                </li>
                <li>
                    <label for='translinksfields'><?php eT("Convert resource links?"); ?></label>
                    <input name='translinksfields' id='translinksfields' type='checkbox' checked='checked'/>
                </li>
            </ul>
            <p>
            <input type='submit' value='<?php eT("Import question"); ?>' />
            <input type='hidden' name='action' value='importquestion' />
            <input type='hidden' name='sid' value='<?php echo $surveyid; ?>' />
            <input type='hidden' name='gid' value='<?php echo $gid; ?>' />
        </form>

        <?php } ?>

    
    <?php } ?>
