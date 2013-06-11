<?php
/**
 * Class FrontendController
 *
 * This is the frontend controller for the frontend editing.
 * This adds the necessary javascripts and toolbars
 *
 * @copyright   CONTREXX CMS - Comvation AG Thun
 * @author      Ueli Kramer <ueli.kramer@comvation.com>
 * @package     contrexx
 * @subpackage  core_module_frontendediting
 * @version     1.0.0
 */

namespace Cx\Core_Modules\FrontendEditing\Controller;

/**
 * Class FrontendController
 *
 * This is the frontend controller for the frontend editing.
 * This adds the necessary javascripts and toolbars
 *
 * @copyright   CONTREXX CMS - Comvation AG Thun
 * @author      Ueli Kramer <ueli.kramer@comvation.com>
 * @package     contrexx
 * @subpackage  core_module_frontendediting
 * @version     1.0.0
 */
class FrontendController
{
    /**
     * Init the frontend editing.
     *
     * Register the javascripts and css files
     * Adds the used language variables to contrexx-js variables, so the toolbar has access to these variables
     *
     * @param ComponentController $componentController
     * @param \Cx\Core\Cx $cx
     */
    public function initFrontendEditing(\Cx\Core_Modules\FrontendEditing\Controller\ComponentController $componentController, \Cx\Core\Cx &$cx)
    {
        global $objInit, $_ARRAYLANG, $page;
        // add css and javascript file
        $jsFilesRoot = substr(ASCMS_CORE_MODULE_FOLDER . '/' . $componentController->getName() . '/View/Script', 1);

        \JS::registerCSS(substr(ASCMS_CORE_MODULE_FOLDER . '/' . $componentController->getName() . '/View/Style/Main.css', 1));
        \JS::registerJS($jsFilesRoot . '/Main.js');
        \JS::registerJS($jsFilesRoot . '/CKEditorPlugins.js');

        // activate ckeditor
        \JS::activate('ckeditor');
        \JS::activate('jquery-cookie');

        // load language data
        $_ARRAYLANG = $objInit->loadLanguageData('FrontendEditing');
        $langVariables = array(
            'TXT_FRONTEND_EDITING_SHOW_TOOLBAR' => $_ARRAYLANG['TXT_FRONTEND_EDITING_SHOW_TOOLBAR'],
            'TXT_FRONTEND_EDITING_HIDE_TOOLBAR' => $_ARRAYLANG['TXT_FRONTEND_EDITING_HIDE_TOOLBAR'],
            'TXT_FRONTEND_EDITING_PUBLISH' => $_ARRAYLANG['TXT_FRONTEND_EDITING_PUBLISH'],
            'TXT_FRONTEND_EDITING_SAVE' => $_ARRAYLANG['TXT_FRONTEND_EDITING_SAVE'],
            'TXT_FRONTEND_EDITING_EDIT' => $_ARRAYLANG['TXT_FRONTEND_EDITING_EDIT'],
            'TXT_FRONTEND_EDITING_STOP_EDIT' => $_ARRAYLANG['TXT_FRONTEND_EDITING_STOP_EDIT'],
            'TXT_FRONTEND_EDITING_THE_DRAFT' => $_ARRAYLANG['TXT_FRONTEND_EDITING_THE_DRAFT'],
            'TXT_FRONTEND_EDITING_SAVE_CURRENT_STATE' => $_ARRAYLANG['TXT_FRONTEND_EDITING_SAVE_CURRENT_STATE'],
            'TXT_FRONTEND_EDITING_MODULE_PAGE' => $_ARRAYLANG['TXT_FRONTEND_EDITING_MODULE_PAGE'],
        );

        // add toolbar to html
        $this->prepareTemplate($componentController, $cx);

        // assign js variables
        $contrexxJavascript = \ContrexxJavascript::getInstance();
        $contrexxJavascript->setVariable('langVars', $langVariables, 'FrontendEditing');
        $contrexxJavascript->setVariable('pageId', $page->getId(), 'FrontendEditing');
        $contrexxJavascript->setVariable('hasPublishPermission', \Permission::checkAccess(35, 'static', true), 'FrontendEditing');
        $contrexxJavascript->setVariable('contentTemplates', $this->getCustomContentTemplates(), 'FrontendEditing');
        $contrexxJavascript->setVariable('defaultTemplate', $this->getDefaultTemplate(), 'FrontendEditing');

        $configPath = ASCMS_PATH_OFFSET . substr(\Env::get('ClassLoader')->getFilePath(ASCMS_CORE_PATH . '/Wysiwyg/ckeditor.config.js.php'), strlen(ASCMS_DOCUMENT_ROOT));
        $contrexxJavascript->setVariable('configPath', $configPath . "?langId=" . FRONTEND_LANG_ID, 'FrontendEditing');
    }

    /**
     * Adds the toolbar to the current html structure (after the starting body tag)
     *
     * @param ComponentController $componentController
     * @param \Cx\Core\Cx $cx
     */
    private function prepareTemplate(\Cx\Core_Modules\FrontendEditing\Controller\ComponentController $componentController, \Cx\Core\Cx &$cx)
    {
        global $_ARRAYLANG, $license, $objInit, $objTemplate, $page, $_CORELANG;

        $componentTemplate = new \Cx\Core\Html\Sigma(ASCMS_CORE_MODULE_PATH . '/' . $componentController->getName() . '/View/Template');
        $componentTemplate->setErrorHandling(PEAR_ERROR_DIE);

        // add div for toolbar after starting body tag
        $componentTemplate->loadTemplateFile('Toolbar.html');
        $componentTemplate->setVariable(array(
            'TXT_UPGRADE' => $_ARRAYLANG['TXT_FRONTEND_EDITING_TOOLBAR_UPGRADE'],
            'LINK_LICENSE' => ASCMS_PATH_OFFSET . '/cadmin/index.php?cmd=license',
        ));

        // @author: Michael Ritter
        $template = $objTemplate;
        $root = $componentTemplate->fileRoot;
        $componentTemplate->setRoot(ASCMS_ADMIN_TEMPLATE_PATH);
        $objTemplate = $componentTemplate;
        \Env::get('ClassLoader')->loadFile(ASCMS_DOCUMENT_ROOT . '/lang/en/backend.php');
        $langCode = \FWLanguage::getLanguageCodeById(FRONTEND_LANG_ID);
        if ($langCode != 'en') {
            \Env::get('ClassLoader')->loadFile(ASCMS_DOCUMENT_ROOT . '/lang/' . $langCode . '/backend.php');
        }
        $_CORELANG = array_merge($_CORELANG, $_ARRAYLANG);
        $menu = new \adminMenu('fe');
        $menu->getAdminNavbar();
        $componentTemplate->setRoot($root);
        $objTemplate = $template;
        // end code from Michael Ritter

        $objUser = $cx->getUser()->objUser;
        $firstname = $objUser->getProfileAttribute('firstname');
        $lastname = $objUser->getProfileAttribute('lastname');
        $componentTemplate->setGlobalVariable(array(
            'LOGGED_IN_USER' => !empty($firstname) && !empty($lastname) ? $firstname . ' ' . $lastname : $objUser->getUsername(),
            'TXT_LOGOUT' => $_ARRAYLANG['TXT_FRONTEND_EDITING_TOOLBAR_LOGOUT'],
            'TXT_FRONTEND_EDITING_TOOLBAR_OPEN_CM' => $_ARRAYLANG['TXT_FRONTEND_EDITING_TOOLBAR_OPEN_CM'],
            'TXT_FRONTEND_EDITING_HISTORY' => $_ARRAYLANG['TXT_FRONTEND_EDITING_HISTORY'],
            'TXT_FRONTEND_EDITING_OPTIONS' => $_ARRAYLANG['TXT_FRONTEND_EDITING_OPTIONS'],
            'TXT_FRONTEND_EDITING_ADMINMENU' => $_ARRAYLANG['TXT_FRONTEND_EDITING_ADMINMENU'],
            'TXT_FRONTEND_EDITING_CSS_CLASS' => $_ARRAYLANG['TXT_FRONTEND_EDITING_CSS_CLASS'],
            'TXT_FRONTEND_EDITING_CUSTOM_CONTENT' => $_ARRAYLANG['TXT_FRONTEND_EDITING_CUSTOM_CONTENT'],
            'TXT_FRONTEND_EDITING_THEMES' => $_ARRAYLANG['TXT_FRONTEND_EDITING_THEMES'],
            'SKIN_OPTIONS' => $this->getSkinOptions(),
            'LINK_LOGOUT' => $objInit->getUriBy('section', 'logout'),
            'LINK_PROFILE' => ASCMS_PATH_OFFSET . '/cadmin/index.php?cmd=access&amp;act=user&amp;tpl=modify&amp;id=' . $objUser->getId(),
            'LINK_CM' => ASCMS_PATH_OFFSET . '/cadmin/index.php?cmd=content&amp;page=' . $page->getId() . '&amp;tab=content',
        ));

        if ($componentTemplate->blockExists('upgradable')) {
            if ($license->isUpgradable()) {
                $componentTemplate->parse('upgradable');
            } else {
                $componentTemplate->hideBlock('upgradable');
            }
        }
        $objTemplate->_blocks['__global__'] = preg_replace('/<body[^>]*>/', '\\0' . $componentTemplate->get(), $objTemplate->_blocks['__global__']);
    }

    /**
     * Returns the html code for the select element for the skin option
     *
     * @return string html for select element
     */
    private function getSkinOptions()
    {
        $options = '';
        foreach ($this->getThemes() as $id => $name) {
            $options .= '<option value="' . $id . '">' . $name . '</option>' . "\n";
        }

        return $options;
    }

    /**
     * Returns all themes which are defined in the backend
     *
     * @return array all available themes
     */
    private function getThemes()
    {
        global $objDatabase;
        $query = 'SELECT id,themesname FROM ' . DBPREFIX . 'skins ORDER BY id';
        $rs = $objDatabase->Execute($query);

        $themes = array();
        while (!$rs->EOF) {
            $themes[$rs->fields['id']] = $rs->fields['themesname'];
            $rs->MoveNext();
        }

        return $themes;
    }

    /**
     * Get all custom content templates by template id
     *
     * @return array all custom content files
     */
    private function getCustomContentTemplates()
    {
        global $objInit;
        $templates = array();
        // foreach theme
        foreach ($this->getThemes() as $id => $name) {
            $templates[$id] = $objInit->getCustomContentTemplatesForTheme($id);
        }

        return $templates;
    }

    /**
     * Get the default template for the current frontend language
     *
     * @return mixed default theme id
     */
    private function getDefaultTemplate()
    {
        global $objDatabase;
        $query = 'SELECT `id`, `lang`, `themesid` FROM `' . DBPREFIX . 'languages` WHERE `id` = ' . FRONTEND_LANG_ID;
        $rs = $objDatabase->SelectLimit($query, 1);
        return $rs->fields['themesid'];
    }
}
