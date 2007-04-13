<?php
/**
 * Includes
 */
require_once ASCMS_CORE_MODULE_PATH.'/alias/lib/aliasLib.class.php';

/**
 * AliasAdmin
 * @copyright   CONTREXX CMS - ASTALAVISTA IT AG
 * @author		Astalavista Development Team <thun@astalvista.ch>
 * @version		1.0.0
 * @package     contrexx
 * @subpackage  core_module_alias
 * @todo        Edit PHP DocBlocks!
 */
class AliasAdmin extends aliasLib
{
	/**
	* Template object
	*
	* @access private
	* @var object
	*/
	var $_objTpl;

	/**
	* Page title
	*
	* @access private
	* @var string
	*/
	var $_pageTitle;

	/**
	* Status message
	*
	* @access private
	* @var array
	*/
	var $arrStatusMsg = array('ok' => array(), 'error' => array());

	/**
	* Constructor
	*/
	function AliasAdmin()
	{
		$this->__construct();
	}

	/**
	* PHP5 constructor
	*
	* @global object $objTemplate
	* @global array $_ARRAYLANG
	*/
	function __construct()
	{
		global $objTemplate, $_ARRAYLANG;

		$this->_objTpl = &new HTML_Template_Sigma(ASCMS_CORE_MODULE_PATH.'/alias/template');
		$this->_objTpl->setErrorHandling(PEAR_ERROR_DIE);

		if (isset($_REQUEST['act']) && $_REQUEST['act'] == 'settings' && isset($_POST['alias_save'])) {
			if ($this->_setAliasAdministrationStatus(isset($_POST['alias_status']) && $_POST['alias_status'])) {
				$this->arrStatusMsg['ok'][] = $_ARRAYLANG['TXT_ALIAS_CONFIG_SUCCESSFULLY_APPLYED'];
			} else {
				$this->arrStatusMsg['error'][] = $_ARRAYLANG['TXT_ALIAS_CONFIG_FAILED_APPLY'];
			}
		}

		$arrConfig = $this->_getConfig();
		if ($arrConfig['aliasStatus'] == '') {
			if ($this->_isModRewriteInUse()) {
				$this->_setAliasAdministrationStatus(true);
				$this->_initConfig();
				$arrConfig = $this->_getConfig();
			}
		}

    	$objTemplate->setVariable("CONTENT_NAVIGATION",
    		($arrConfig['aliasStatus'] == '1' ? "<a href='index.php?cmd=alias'>".$_ARRAYLANG['TXT_ALIAS_ALIASES']."</a>"
    		."<a href='index.php?cmd=alias&amp;act=modify'>".$_ARRAYLANG['TXT_ALIAS_ADD_ALIAS']."</a>" : '')
    		."<a href='index.php?cmd=alias&amp;act=settings'>".$_ARRAYLANG['TXT_ALIAS_SETTINGS']."</a>"
    	);
	}

	/**
	* Set the backend page
	*
	* @access public
	* @global object $objTemplate
	* @global array $_ARRAYLANG
	*/
	function getPage()
	{
		global $objTemplate, $_ARRAYLANG;

		$arrConfig = $this->_getConfig();
		if ($arrConfig['aliasStatus'] == '0') {
			$_REQUEST['act'] = 'settings';
		}

		if (!isset($_REQUEST['act'])) {
    	    $_REQUEST['act'] = '';
    	}

    	switch ($_REQUEST['act']) {
			case 'settings':
				$this->_settings();
				break;

			case 'modify':
				$this->_modifyAlias();
				break;

			case 'delete':
				$this->_delete();

			case 'rewriteRules':
				$this->_rewriteRules();

			default:
				$this->_list();
				break;
    	}

		$this->_pageTitle = $_ARRAYLANG['TXT_OVERVIEW'];

		$objTemplate->setVariable(array(
			'CONTENT_TITLE'				=> $this->_pageTitle,
			'CONTENT_OK_MESSAGE'		=> implode("<br />\n", $this->arrStatusMsg['ok']),
			'CONTENT_STATUS_MESSAGE'	=> implode("<br />\n", $this->arrStatusMsg['error']),
			'ADMIN_CONTENT'				=> $this->_objTpl->get()
		));
	}

	function _rewriteRules()
	{
		$this->_activateRewriteEngine();
	}

	function _list()
	{
		global $_ARRAYLANG, $_CONFIG;

		$this->_objTpl->loadTemplateFile('module_alias_list.html');
		$this->_pageTitle = $_ARRAYLANG['TXT_ALIAS_ALIAS_ES'];
		$this->_objTpl->setGlobalVariable('TXT_ALIAS_ALIASES', $_ARRAYLANG['TXT_ALIAS_ALIASES']);

		$arrAliases = $this->_getAliases();
		$nr = 1;
		if (count($arrAliases)) {
			$this->_objTpl->setVariable(array(
				'TXT_ALIAS_PAGE'		=> $_ARRAYLANG['TXT_ALIAS_PAGE'],
				'TXT_ALIAS_ALIAS'	=> $_ARRAYLANG['TXT_ALIAS_ALIAS'],
				'TXT_ALIAS_FUNCTIONS'	=> $_ARRAYLANG['TXT_ALIAS_FUNCTIONS'],
				'TXT_ALIAS_CONFIRM_DELETE_ALIAS'	=> $_ARRAYLANG['TXT_ALIAS_CONFIRM_DELETE_ALIAS'],
				'TXT_ALIAS_OPERATION_IRREVERSIBLE'	=> $_ARRAYLANG['TXT_ALIAS_OPERATION_IRREVERSIBLE']
			));

			$this->_objTpl->setGlobalVariable(array(
				'TXT_ALIAS_DELETE'	=> $_ARRAYLANG['TXT_ALIAS_DELETE'],
				'TXT_ALIAS_MODIFY'	=> $_ARRAYLANG['TXT_ALIAS_MODIFY']
			));

			$arrRewriteInfo = $this->_getRewriteInfo();

			foreach ($arrAliases as $aliasId => $arrAlias) {
				foreach ($arrAlias['sources'] as $arrAliasSource) {

					$this->_objTpl->setVariable(array(
						'ALIAS_SOURCE_URL'	=> 'http://'.$_CONFIG['domainUrl'].ASCMS_PATH_OFFSET.'<strong>/'.$arrAliasSource['url'].'</strong>',
					));

					if (!isset($arrRewriteInfo['rules'][($arrAlias['type'] == 'local' ? $arrAlias['pageUrl'] : $arrAlias['url'])]) || !in_array($arrAliasSource['url'], $arrRewriteInfo['rules'][($arrAlias['type'] == 'local' ? $arrAlias['pageUrl'] : $arrAlias['url'])])) {
						$this->_objTpl->setVariable('TXT_ALIAS_NOT_ACTIVE_ALIAS_MSG', $_ARRAYLANG['TXT_ALIAS_NOT_ACTIVE_ALIAS_MSG']);
						$this->_objTpl->touchBlock('alias_source_not_set');
					} else {
						$this->_objTpl->hideBlock('alias_source_not_set');
					}

					$this->_objTpl->parse('alias_source_list');
				}
				$this->_objTpl->setVariable(array(
					'ALIAS_ROW_CLASS'	=> $arrAlias['type'] == 'local' && empty($arrAlias['pageUrl']) && $nr++ ? 'rowWarn ' : 'row'.($nr++ % 2 + 1),
					'ALIAS_TARGET_ID'		=> $aliasId,
					'ALIAS_TARGET_TITLE'	=> $arrAlias['type'] == 'local' ? (!empty($arrAlias['pageUrl']) ? $arrAlias['title'].' ('.$arrAlias['pageUrl'].')' : $_ARRAYLANG['TXT_ALIAS_TARGET_PAGE_NOT_EXIST']) : htmlentities($arrAlias['url'], ENT_QUOTES, CONTREXX_CHARSET)
				));
				$this->_objTpl->parse('aliases_list');
			}

			$this->_objTpl->parse('alias_data');
			$this->_objTpl->hideBlock('alias_no_data');
		} else {
			$this->_objTpl->setVariable('TXT_ALIAS_NO_ALIASES_MSG', $_ARRAYLANG['TXT_ALIAS_NO_ALIASES_MSG']);

			$this->_objTpl->hideBlock('alias_data');
			$this->_objTpl->parse('alias_no_data');
		}
	}

	function _modifyAlias()
	{
		global $_ARRAYLANG, $_CONFIG;

		$aliasId = !empty($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
		$arrSourceUrls = array();

		if (isset($_POST['alias_save'])) {
			$arrAlias['type'] = in_array($_POST['alias_source_type'], $this->_arrAliasTypes) ? $_POST['alias_source_type'] : $this->_arrAliasTypes[0];

			if ($arrAlias['type'] == 'local') {
				$arrAlias['url'] = !empty($_POST['alias_local_source']) ? intval($_POST['alias_local_source']) : 0;
				$arrAlias['pageUrl'] = !empty($_POST['alias_local_page_url']) ? trim(contrexx_stripslashes($_POST['alias_local_page_url'])) : '';
			} else {
				$arrAlias['url'] = !empty($_POST['alias_url_source']) ? trim(contrexx_stripslashes($_POST['alias_url_source'])) : '';
			}

			$arrAlias['sources'] = array();
			if (!empty($_POST['alias_aliases']) && is_array($_POST['alias_aliases'])) {
				foreach ($_POST['alias_aliases'] as $sourceId => $aliasSource) {
					$aliasSource = trim(contrexx_stripslashes($aliasSource));
					if (!empty($aliasSource)) {
						$arrAlias['sources'][] = array(
							'id'	=> intval($sourceId),
							'url'	=> $aliasSource
						);
					}
				}
			}

			if (!empty($_POST['alias_aliases_new']) && is_array($_POST['alias_aliases_new'])) {
				foreach ($_POST['alias_aliases_new'] as $newAliasSource) {
					$newAliasSource = trim(contrexx_stripslashes($newAliasSource));
					if (!empty($newAliasSource)) {
						if (file_exists(ASCMS_DOCUMENT_ROOT.'/'.$newAliasSource)) {
							$this->arrStatusMsg['error'][] = sprintf($_ARRAYLANG['TXT_ALIAS_MUST_NOT_BE_A_FILE'], htmlentities($newAliasSource, ENT_QUOTES, CONTREXX_CHARSET));
							continue;
						}

						$arrAlias['sources'][] = array('url' => $newAliasSource);
					}
				}
			}

			if (!empty($arrAlias['url'])) {
				if (!$this->_isUniqueAliasTarget($arrAlias['url'], $aliasId)) {
					$this->arrStatusMsg['error'][] = sprintf($_ARRAYLANG['TXT_ALIAS_TARGET_ALREADY_IN_USE'], htmlentities(($arrAlias['type'] == 'local' ? $arrAlias['pageUrl'] : $arrAlias['url']), ENT_QUOTES, CONTREXX_CHARSET));
				} elseif (count($arrAlias['sources'])) {
					$error = false;

					foreach ($arrAlias['sources'] as $arrSource) {
						$target = $arrAlias['type'] == 'local' ? $arrAlias['pageUrl'] : $arrAlias['url'];
						if (in_array($arrSource['url'], $arrSourceUrls) || !$this->_isUniqueAliasSource($arrSource['url'], $target,(!empty($arrSource['id']) ? $arrSource['id'] : 0))) {
							$error = true;
							$this->arrStatusMsg['error'][] = sprintf($_ARRAYLANG['TXT_ALIAS_ALREADY_IN_USE'], htmlentities($arrSource['url'], ENT_QUOTES, CONTREXX_CHARSET));
						} elseif (@file_exists(ASCMS_DOCUMENT_ROOT.'/'.$arrSource['url'])) {
							$error = true;
							$this->arrStatusMsg['error'][] = sprintf($_ARRAYLANG['TXT_ALIAS_MUST_NOT_BE_A_FILE'], htmlentities($arrSource['url'], ENT_QUOTES, CONTREXX_CHARSET));
						} else {
							$arrSourceUrls[] = $arrSource['url'];
						}
					}

					if (!$error) {
						if (($aliasId ? $this->_updateAlias($aliasId, $arrAlias) : $this->_addAlias($arrAlias))) {
							$this->arrStatusMsg['ok'][] = $aliasId ? $_ARRAYLANG['TXT_ALIAS_ALIAS_SUCCESSFULLY_UPDATED'] : $_ARRAYLANG['TXT_ALIAS_ALIAS_SUCCESSFULLY_ADDED'];
							return $this->_list();
						} else {
							$this->arrStatusMsg['error'][] = $aliasId ? $_ARRAYLANG['TXT_ALIAS_ALIAS_UPDATE_FAILED'] : $_ARRAYLANG['TXT_ALIAS_ALIAS_ADD_FAILED'];
							$this->arrStatusMsg['error'][] = $_ARRAYLANG['TXT_ALIAS_RETRY_OPERATION'];
						}
					}
				} else {
					$this->arrStatusMsg['error'][] = $_ARRAYLANG['TXT_ALIAS_ONE_ALIAS_REQUIRED_MSG'];
				}
			} else {
				if ($arrAlias['type'] == 'local') {
					$this->arrStatusMsg['error'][] = $_ARRAYLANG['TXT_ALIAS_PAGE_REQUIRED_MSG'];
				} else {
					$this->arrStatusMsg['error'][] = $_ARRAYLANG['TXT_ALIAS_URL_REQUIRED_MSG'];
				}
			}
		} elseif (($arrAlias = $this->_getAlias($aliasId)) === false) {
			$arrAlias = array(
				'type'		=> 'local',
				'url'		=> '',
				'pageUrl'	=> '',
				'sources'	=> array()
			);
			$aliasId = 0;
		}

		$this->_objTpl->loadTemplateFile('module_alias_modify.html');
		$this->_pageTitle = $aliasId ? $_ARRAYLANG['TXT_ALIAS_MODIFY_ALIAS'] : $_ARRAYLANG['TXT_ALIAS_ADD_ALIAS'];

		$this->_objTpl->setVariable(array(
			'TXT_ALIAS_TARGET_PAGE'				=> $_ARRAYLANG['TXT_ALIAS_TARGET_PAGE'],
			'TXT_ALIAS_LOCAL'					=> $_ARRAYLANG['TXT_ALIAS_LOCAL'],
			'TXT_ALIAS_URL'						=> $_ARRAYLANG['TXT_ALIAS_URL'],
			'TXT_ALIAS_BROWSE'					=> $_ARRAYLANG['TXT_ALIAS_BROWSE'],
			'TXT_ALIAS_ALIAS_ES'				=> $_ARRAYLANG['TXT_ALIAS_ALIAS_ES'],
			'TXT_ALIAS_DELETE'					=> $_ARRAYLANG['TXT_ALIAS_DELETE'],
			'TXT_ALIAS_CONFIRM_REMOVE_ALIAS'	=> $_ARRAYLANG['TXT_ALIAS_CONFIRM_REMOVE_ALIAS'],
			'TXT_ALIAS_ADD_ANOTHER_ALIAS'		=> $_ARRAYLANG['TXT_ALIAS_ADD_ANOTHER_ALIAS'],
			'TXT_ALIAS_CANCEL'					=> $_ARRAYLANG['TXT_ALIAS_CANCEL'],
			'TXT_ALIAS_SAVE'					=> $_ARRAYLANG['TXT_ALIAS_SAVE']
		));

		$this->_objTpl->setGlobalVariable(array(
			'TXT_ALIAS_DELETE'					=> $_ARRAYLANG['TXT_ALIAS_DELETE'],
			'ALIAS_DOMAIN_URL'				=> 'http://'.$_CONFIG['domainUrl'].ASCMS_PATH_OFFSET.'/'
		));

		$this->_objTpl->setVariable(array(
			'ALIAS_ID'					=> $aliasId,
			'ALIAS_TITLE_TXT'			=> $this->_pageTitle,
			'ALIAS_SELECT_LOCAL_PAGE'	=> $arrAlias['type'] == 'local' ? 'checked="checked"' : '',
			'ALIAS_SELECT_URL_PAGE'		=> $arrAlias['type'] == 'url' ? 'checked="checked"' : '',
			'ALIAS_SELECT_LOCAL_BOX'	=> $arrAlias['type'] == 'local' ? 'block' : 'none',
			'ALIAS_LOCAL_SOURCE'		=> $arrAlias['type'] == 'local' ? $arrAlias['url'] : '',
			'ALIAS_LOCAL_PAGE_URL'		=> $arrAlias['type'] == 'local' ? htmlentities($arrAlias['pageUrl'], ENT_QUOTES, CONTREXX_CHARSET) : '',
			'ALIAS_SELECT_URL_BOX'		=> $arrAlias['type'] == 'url' ? 'block' : 'none',
			'ALIAS_URL_SOURCE'			=> $arrAlias['type'] == 'url' ? htmlentities($arrAlias['url'], ENT_QUOTES, CONTREXX_CHARSET) : 'http://'
		));

		$nr = 0;

		foreach ($arrAlias['sources'] as $arrAliasSource) {
			$this->_objTpl->setVariable(array(
				'ALIAS_DOMAIN_URL'		=> 'http://'.$_CONFIG['domainUrl'].ASCMS_PATH_OFFSET.'/',
				'ALIAS_ALIAS_ID'		=> !empty($arrAliasSource['id']) ? $arrAliasSource['id'] : '',
				'ALIAS_ALIAS_NR'		=> $nr++,
				'ALIAS_ALIAS_PREFIX'	=> empty($arrAliasSource['id']) ? '_new' : '',
				'ALIAS_ALIAS_URL'		=> htmlentities($arrAliasSource['url'], ENT_QUOTES, CONTREXX_CHARSET)
			));
			$this->_objTpl->parse('alias_list');
		}
	}

	function _settings()
	{
		global $_ARRAYLANG;

		$this->_objTpl->loadTemplateFile('module_alias_settings.html');

		$apacheEnv = preg_match('#apache#i', $_SERVER['SERVER_SOFTWARE']);

		ob_start();
		phpinfo(INFO_MODULES);
		$phpinfo = ob_get_contents();
		ob_end_clean();

		$modRewriteLoaded = preg_match('#mod_rewrite#i', $phpinfo);

		$this->_objTpl->setVariable(array(
			'TXT_ALIAS_SETTINGS'					=> $_ARRAYLANG['TXT_ALIAS_SETTINGS'],
			'TXT_ALIAS_REQUIREMENTS_DESC'			=> $_ARRAYLANG['TXT_ALIAS_REQUIREMENTS_DESC'],
			'TXT_ALIAS_SAVE'						=> $_ARRAYLANG['TXT_ALIAS_SAVE']
		));

		$this->_objTpl->setVariable(array(
			'ALIAS_REQUIREMENTS_STATUS_MSG'	=> ($apacheEnv && $modRewriteLoaded) ? $_ARRAYLANG['TXT_ALIAS_HTACCESS_HINT'] : ($apacheEnv ? $_ARRAYLANG['TXT_ALIAS_MOD_REWRITE_MISSING'] : $_ARRAYLANG['TXT_ALIAS_APACHE_MISSING']),

		));

		$arrConfig = $this->_getConfig();

		if ($apacheEnv && $modRewriteLoaded) {
			$this->_objTpl->setVariable(array(
				'TXT_ALIAS_USE_ALIAS_ADMINISTRATION'	=> $_ARRAYLANG['TXT_ALIAS_USE_ALIAS_ADMINISTRATION'],
				'ALIAS_STATUS_CHECKED'					=> $arrConfig['aliasStatus'] == '1' ? 'checked="checked"' : ''
			));
			$this->_objTpl->parse('alias_status_form');
		} else {
			$this->_objTpl->hideBlock('alias_status_form');
		}
	}

	function _setAliasAdministrationStatus($active = false)
	{
		global $objDatabase;

		if ($active) {
			if (!$this->_activateRewriteEngine()) {
				return false;
			}
		} else {
			if (!$this->_deactivateRewriteEngine()) {
				return false;
			}
		}

		if ($objDatabase->Execute("UPDATE `".DBPREFIX."settings` SET `setvalue` = '".($active ? '1' : '0')."' WHERE `setname` = 'aliasStatus' AND `setmodule` = 41") !== false) {
			return true;
		} else {
			return false;
		}
	}

	function _delete()
	{
		global $_ARRAYLANG;

		$aliasId = !empty($_GET['id']) ? intval($_GET['id']) : 0;

		if ($aliasId) {
			if ($this->_deleteAlias($aliasId)) {
				$this->arrStatusMsg['ok'][] = $_ARRAYLANG['TXT_ALIAS_ALIAS_SUCCESSFULLY_REMOVED'];
			} else {
				$this->arrStatusMsg['error'][] = $_ARRAYLANG['TXT_ALIAS_ALIAS_REMOVE_FAILED'];
				$this->arrStatusMsg['error'][] = $_ARRAYLANG['TXT_ALIAS_RETRY_OPERATION'];
			}
		}
	}
}
?>
