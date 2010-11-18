<?php
/**
 * @package modx
 * @subpackage controllers.resource.staticresource
 */
if (!$modx->hasPermission('new_document')) return $modx->error->failure($modx->lexicon('access_denied'));

$resource = $modx->newObject('modStaticResource');

/* invoke OnDocFormPrerender event */
$onDocFormPrerender = $modx->invokeEvent('OnDocFormPrerender',array(
    'id' => 0,
    'mode' => modSystemEvent::MODE_NEW,
));
if (is_array($onDocFormPrerender)) {
    $onDocFormPrerender = implode('',$onDocFormPrerender);
}
$modx->smarty->assign('onDocFormPrerender',$onDocFormPrerender);

/* handle default parent */
$parentname = $context->getOption('site_name', '', $modx->_userConfig);
$resource->set('parent',0);
if (isset ($_REQUEST['parent'])) {
    if ($_REQUEST['parent'] == 0) {
        $parentname = $context->getOption('site_name', '', $modx->_userConfig);
    } else {
        $parent = $modx->getObject('modResource',$_REQUEST['parent']);
        if ($parent != null) {
          $parentname = $parent->get('pagetitle');
          $resource->set('parent',$parent->get('id'));
        }
    }
}
$modx->smarty->assign('parentname',$parentname);

/* invoke OnDocFormRender event */
$onDocFormRender = $modx->invokeEvent('OnDocFormRender',array(
    'id' => 0,
    'mode' => modSystemEvent::MODE_NEW,
));
if (is_array($onDocFormRender)) $onDocFormRender = implode('',$onDocFormRender);
$onDocFormRender = str_replace(array('"',"\n","\r"),array('\"','',''),$onDocFormRender);
$modx->smarty->assign('onDocFormRender',$onDocFormRender);


/*
 *  Initialize RichText Editor
 */
/* Set which RTE if not core */
if ($context->getOption('use_editor', false, $modx->_userConfig) && !empty($rte)) {
    /* invoke OnRichTextEditorRegister event */
    $text_editors = $modx->invokeEvent('OnRichTextEditorRegister');
    $modx->smarty->assign('text_editors',$text_editors);

    $replace_richtexteditor = array();
    $modx->smarty->assign('replace_richtexteditor',$replace_richtexteditor);

    /* invoke OnRichTextEditorInit event */
    $onRichTextEditorInit = $modx->invokeEvent('OnRichTextEditorInit',array(
        'editor' => $rte,
        'elements' => $replace_richtexteditor,
        'id' => 0,
        'mode' => modSystemEvent::MODE_NEW,
    ));
    if (is_array($onRichTextEditorInit)) {
        $onRichTextEditorInit = implode('',$onRichTextEditorInit);
        $modx->smarty->assign('onRichTextEditorInit',$onRichTextEditorInit);
    }
}


/* assign static resource to smarty */
$modx->smarty->assign('resource',$resource);

/* check permissions */
$publish_document = $modx->hasPermission('publish_document');
$access_permissions = $modx->hasPermission('access_permissions');

/* set default template */
$default_template = (isset($_REQUEST['template']) ? $_REQUEST['template'] : ($parent != null ? $parent->get('template') : $context->getOption('default_template', 0, $modx->_userConfig)));
$userGroups = $modx->user->getUserGroups();
$c = $modx->newQuery('modActionDom');
$c->leftJoin('modAccessActionDom','Access');
$principalCol = $this->modx->getSelectColumns('modAccessActionDom','Access','',array('principal'));
$c->where(array(
    'action' => $this->action['id'],
    'name' => 'template',
    'container' => 'modx-panel-resource',
    'rule' => 'fieldDefault',
    'active' => true,
    array(
        array(
            'Access.principal_class:=' => 'modUserGroup',
            $principalCol.' IN ('.implode(',',$userGroups).')',
        ),
        'OR:Access.principal:IS' => null,
    ),
));
$fcDt = $modx->getObject('modActionDom',$c);
if ($fcDt) {
    $p = $parent ? $parent->get('id') : 0;
    $constraintField = $fcDt->get('constraint_field');
    if ($constraintField == 'id' && $p == $fcDt->get('constraint')) {
        $default_template = $fcDt->get('value');
    } else if (empty($constraintField)) {
        $default_template = $fcDt->get('value');
    }
}
$ctx = !empty($_REQUEST['context_key']) ? $_REQUEST['context_key'] : 'web';
$modx->smarty->assign('_ctx',$ctx);

$defaults = array(
    'template' => $default_template,
    'content_type' => 1,
    'class_key' => isset($_REQUEST['class_key']) ? $_REQUEST['class_key'] : 'modStaticResource',
    'context_key' => $ctx,
    'parent' => isset($_REQUEST['parent']) ? $_REQUEST['parent'] : 0,
    'richtext' => 0,
    'published' => $context->getOption('publish_default', 0, $modx->_userConfig),
    'searchable' => $context->getOption('search_default', 1, $modx->_userConfig),
    'cacheable' => $context->getOption('cache_default', 1, $modx->_userConfig),
);

/* register JS scripts */
$managerUrl = $context->getOption('manager_url', MODX_MANAGER_URL, $modx->_userConfig);
$modx->regClientStartupScript($managerUrl.'assets/modext/util/datetime.js');
$modx->regClientStartupScript($managerUrl.'assets/modext/widgets/element/modx.panel.tv.renders.js');
$modx->regClientStartupScript($managerUrl.'assets/modext/widgets/resource/modx.grid.resource.security.js');
$modx->regClientStartupScript($managerUrl.'assets/modext/widgets/resource/modx.panel.resource.tv.js');
$modx->regClientStartupScript($managerUrl.'assets/modext/widgets/resource/modx.panel.resource.static.js');
$modx->regClientStartupScript($managerUrl.'assets/modext/sections/resource/static/create.js');
$modx->regClientStartupHTMLBlock('
<script type="text/javascript">
// <![CDATA[
MODx.config.publish_document = "'.$publish_document.'";
MODx.onDocFormRender = "'.$onDocFormRender.'";
MODx.ctx = "'.$ctx.'";
Ext.onReady(function() {
    MODx.load({
        xtype: "modx-page-static-create"
        ,record: '.$modx->toJSON($defaults).'
        ,which_editor: "'.$which_editor.'"
        ,access_permissions: "'.$access_permissions.'"
        ,publish_document: "'.$publish_document.'"
        ,canSave: "'.($modx->hasPermission('save_document') ? 1 : 0).'"
    });
});
// ]]>
</script>');

$this->checkFormCustomizationRules($parent != null ? $parent : null,true);
/* fire the FC rules on the actual resource as well; this allows moving of TVs
 * and other FC manips after the default template FC rule */
$resource = $modx->newObject('modResource');
$resource->fromArray($defaults);
$this->checkFormCustomizationRules($resource);
return $modx->smarty->fetch('resource/staticresource/create.tpl');
