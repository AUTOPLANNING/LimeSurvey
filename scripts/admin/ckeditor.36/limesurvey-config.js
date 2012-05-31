CKEDITOR.editorConfig = function( config )
{

    config.filebrowserBrowseUrl = CKEDITOR.basePath+'../../../../admin/kcfinder/index/load/browse?type=files';
    config.filebrowserImageBrowseUrl = CKEDITOR.basePath+'../../../../admin/kcfinder/index/load/browse?type=images';
    config.filebrowserFlashBrowseUrl = CKEDITOR.basePath+'../../../../admin/kcfinder/index/load/browse?type=flash';

    config.filebrowserUploadUrl = CKEDITOR.basePath+'../../../../admin/kcfinder/index/load/upload?type=files';
    config.filebrowserImageUploadUrl = CKEDITOR.basePath+'../../../../admin/kcfinder/index/load/upload?type=images';
    config.filebrowserFlashUploadUrl = CKEDITOR.basePath+'../../../../admin/kcfinder/index/load/upload?type=flash';

	config.skin = 'office2003';
	config.toolbarCanCollapse = false;
	config.resize_enabled = false;
    config.autoParagraph = false;
	
	if($('html').attr('dir') == 'rtl') {
		config.contentsLangDirection = 'rtl';
	}

    config.toolbar_popup =
    [
        ['Save','Createlimereplacementfields'],
        ['Cut','Copy','Paste','PasteText','PasteFromWord'],
        ['Undo','Redo','-','Find','Replace','-','SelectAll','RemoveFormat','Source'],
        ['Image','Flash','Table','HorizontalRule','Smiley','SpecialChar'],
        '/',
        ['Bold','Italic','Underline','Strike','-','Subscript','Superscript'],
        ['NumberedList','BulletedList','-','Outdent','Indent','Blockquote','CreateDiv'],
        ['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
        ['BidiLtr', 'BidiRtl'],
        ['Link','Unlink','Anchor','Iframe'],
        '/',
        ['Styles','Format','Font','FontSize'],
        ['TextColor','BGColor'],
        [ 'ShowBlocks','Templates']
    ];

    config.toolbar_inline =
    [
        ['Maximize','Createlimereplacementfields'],
        ['Cut','Copy','Paste','PasteText','PasteFromWord'],
        ['Undo','Redo','-','Find','Replace','-','SelectAll','RemoveFormat','Source'],
        ['Image','Flash','Table','HorizontalRule','Smiley','SpecialChar'],
        '/',
        ['Bold','Italic','Underline','Strike','-','Subscript','Superscript'],
        ['NumberedList','BulletedList','-','Outdent','Indent','Blockquote','CreateDiv'],
        ['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
        ['BidiLtr', 'BidiRtl'],
        ['Link','Unlink','Anchor','Iframe'],
        '/',
        ['Styles','Format','Font','FontSize'],
        ['TextColor','BGColor'],
        [ 'ShowBlocks','Templates'],
        '/',
        ['Maximize','Createlimereplacementfields'],
        ['Bold','Italic','Underline'],
        ['NumberedList','BulletedList','-','Outdent','Indent'],
        ['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
        ['Link','Unlink','Image'],
        ['Source']
    ];


   /* for a later time when CKEditor can change the toolbar on maximize

   config.toolbar_inline =
    [
        ['Maximize,'Createlimereplacementfields'],
        ['Bold','Italic','Underline'],
        ['NumberedList','BulletedList','-','Outdent','Indent'],
        ['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
        ['Link','Unlink','Image'],
        ['Source']
    ];*/


   	config.extraPlugins = "limereplacementfields,ajax";

};