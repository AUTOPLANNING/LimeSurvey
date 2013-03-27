<?php if (!class_exists('Yii', false)) die('No direct script access allowed in ' . __FILE__);

/**
 * This file contains configuration parameters for the Yii framework.
 * Do not change these unless you know what you are doing.
 * 
 */
$internalConfig = array(
	'basePath' => dirname(dirname(__FILE__)),
	'runtimePath' => dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'runtime',
	'name' => 'LimeSurvey',
	'defaultController' => 'survey',
	
	'import' => array(
		'application.core.*',
		'application.models.*',
		'application.controllers.*',
		'application.modules.*',
	),
	'components' => array(
        'bootstrap' => array(
            'class' => 'ext.bootstrap.components.Bootstrap',
            'responsiveCss' => false,
            'jqueryCss' => false
        ),
		'urlManager' => array(
			'urlFormat' => 'get',
			'rules' => require('routes.php'),
			'showScriptName' => true,
		),
        
        'clientScript' => array(
            'packages' => require('third_party.php')
        ),
        'user' => array(
            'class' => 'LSWebUser',
        ),
        'viewRenderer' => array(
            'class' => 'ext.PolyViewRenderer.PolyViewRenderer'
        )
        
	)
);

$userConfig = require(dirname(__FILE__) . '/config.php');
return CMap::mergeArray($internalConfig, $userConfig);
/* End of file internal.php */
/* Location: ./application/config/internal.php */