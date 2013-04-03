<?php

/**
 * JSON Adapter for Cx\Core\ContentManager\Model\Doctrine\Entity\Page
 * @copyright   Comvation AG
 * @author      Florian Schuetz <florian.schuetz@comvation.com>
 * @author      Michael Ritter <michael.ritter@comvation.com>
 * @package     contrexx
 * @subpackage  core_json
 */

namespace Cx\Core\ContentManager\Controller;
use \Cx\Core\Json\JsonAdapter;

/**
 * JSON Adapter for Cx\Core\ContentManager\Model\Doctrine\Entity\Page
 * @copyright   Comvation AG
 * @author      Florian Schuetz <florian.schuetz@comvation.com>
 * @author      Michael Ritter <michael.ritter@comvation.com>
 * @package     contrexx
 * @subpackage  core_json
 */
class JsonPage implements JsonAdapter {

    private $em        = null;
    private $db        = null;
    private $pageRepo  = null;
    private $nodeRepo  = null;
    private $logRepo   = null;
    private $fallbacks = array();
    private $multipleSetState = null;
    
    public $messages;

    /**
     * Constructor
     */
    function __construct() {
        $this->em = \Env::em();
        $this->db = \Env::get('db');
        if ($this->em) {
            $this->pageRepo = $this->em->getRepository('Cx\Core\ContentManager\Model\Doctrine\Entity\Page');
            $this->nodeRepo = $this->em->getRepository('Cx\Core\ContentManager\Model\Doctrine\Entity\Node');
            $this->logRepo  = $this->em->getRepository('Gedmo\Loggable\Entity\LogEntry');
        }
        $this->messages = array();
        $this->tz       = new \DateTimeZone('Europe/Berlin');

        $fallback_lang_codes = \FWLanguage::getFallbackLanguageArray();
        $active_langs = \FWLanguage::getActiveFrontendLanguages();

        // get all active languages and their fallbacks
        foreach ($active_langs as $lang) {
            $this->fallbacks[\FWLanguage::getLanguageCodeById($lang['id'])] = ((array_key_exists($lang['id'], $fallback_lang_codes)) ? \FWLanguage::getLanguageCodeById($fallback_lang_codes[$lang['id']]) : null);
        }
    }
    
    /**
     * Returns the internal name used as identifier for this adapter
     * @return String Name of this adapter
     */
    public function getName() {
        return 'page';
    }
    
    /**
     * Returns an array of method names accessable from a JSON request
     * @author Michael Ritter <michael.ritter@comvation.com>
     * @return array List of method names
     */
    public function getAccessableMethods() {
        return array(
            'get',
            'set',
            'multipleSet',
            'setPagePreview',
            'getHistoryTable',
            'getAccessData',
            'getPathByTarget'
        );
    }

    /**
     * Returns all messages as string
     * @return String HTML encoded error messages
     */
    public function getMessagesAsString() {
        return implode("<br />", $this->messages);
    }

    /**
     * Sends data to the client
     * @todo Clean up usage of $param
     * @param Array $params Client parameters
     * @return Array Page as array
     * @throws Exception If the page could not be found
     */
    public function get($params) {
        global $_CORELANG;
        
        // Global access check
        if (!\Permission::checkAccess(6, 'static', true) ||
                !\Permission::checkAccess(35, 'static', true)) {
            throw new \Exception($_CORELANG['TXT_CORE_CM_USAGE_DENIED']);
        }
        
        if (isset($params['get']) && isset($params['get']['history'])) {
            $params['get']['history'] = contrexx_input2raw($params['get']['history']);
        }
        
        // pages can be requested in two ways:
        // by page id               - default for existing pages
        // by node id + lang        - to translate an existing page into a new language, assigned to the same node
        if (!empty($params['get']['page'])) {
            $page = $this->pageRepo->find($params['get']['page']);
        }

        if (isset($page)) {
            // Page access check
            if ($page->isBackendProtected() &&
                    !\Permission::checkAccess($page->getBackendAccessId(), 'dynamic', true)) {
                throw new \Cx\Core\ContentManager\Model\Doctrine\Entity\PageException($_CORELANG['TXT_CORE_CM_READ_DENIED']);
            }
            
            // load an older revision if asked to do so:
            if (isset($params['get']['history'])) {
                $this->logRepo->revert($page, $params['get']['history']);
            }
            // load the draft revision if one is available and we're not loading historic data:
            else if ($page->getEditingStatus() == 'hasDraft' || $page->getEditingStatus() == 'hasDraftWaiting') {
                $this->logRepo = $this->em->getRepository('Gedmo\Loggable\Entity\LogEntry');
    
                $availableRevisions = $this->logRepo->getLogEntries($page);
                $this->logRepo->revert($page, $availableRevisions[1]->getVersion());
            }
            
            $pageArray = $this->getPageArray($page);
        } else if (!empty($params['get']['node']) && !empty($params['get']['lang'])) {
            $node = $this->nodeRepo->find($params['get']['node']);
            $pageArray = $this->getFallbackPageArray($node, $params['get']['lang']);
        } else {
            throw new \Exception('cannot find that page');
        }

        return $pageArray;
    }

    /**
     * Handles request from the client
     * @todo Clean up usage of $param and $_GET
     * @global Array $_CORELANG Core language data
     * @param Array $params Client parameters
     * @return type 
     */
    public function set($params) {
        global $_CORELANG;
        
        // Global access check
        if (!\Permission::checkAccess(6, 'static', true) || !\Permission::checkAccess(35, 'static', true)) {
            throw new \Exception($_CORELANG['TXT_CORE_CM_USAGE_DENIED']);
        }
        
        $newPage = false;
        $reload  = false;
        $pg      = \Env::get('pageguard');
        
        $dataPost  = !empty($params['post'])   ? $params['post']   : array();
        $pageArray = !empty($dataPost['page']) ? $dataPost['page'] : array(); // Only set in the editing mode.
        
        $pageId = !empty($pageArray['id'])    ? intval($pageArray['id'])   : (!empty($dataPost['pageId']) ? intval($dataPost['pageId']) : 0);
        $nodeId = !empty($pageArray['node'])  ? intval($pageArray['node']) : (!empty($dataPost['nodeId']) ? intval($dataPost['nodeId']) : 0);
        $lang   = !empty($pageArray['lang'])  ? contrexx_input2raw($pageArray['lang'])  : (!empty($dataPost['lang']) ? contrexx_input2raw($dataPost['lang']) : '');
        $action = !empty($dataPost['action']) ? contrexx_input2raw($dataPost['action']) : '';
        
        if (!empty($pageArray)) {
            if (!empty($pageArray['target']) && !empty($pageArray['target_protocol'])) {
                $pageArray['target'] = $pageArray['target_protocol'] . $pageArray['target'];
            } elseif (empty($pageArray['target']) && !empty($pageArray['target_protocol'])) {
                $pageArray['target'] = '';
            }
            $validatedPageArray = $this->validatePageArray($pageArray);
        }

        // UPDATE
        if (!empty($pageId)) {
            // If we got a page id, the page already exists and can be updated.
            $page = $this->pageRepo->find($pageId);
            $node = $page->getNode();
            
        // TRANSLATE
        } else if (!empty($nodeId) && !empty($lang)) {
            // We are translating the page.
            $node = $this->nodeRepo->find($nodeId);
            $page = $node->translatePage(true, \FWLanguage::getLanguageIdByCode($lang));
            $page->setNodeIdShadowed($node->getId());
            $page->setEditingStatus('');
            
            $newPage = true;
            $reload  = true;
        
        // CREATE
        } else if (empty($pageId) && !empty($lang)) {
            if (!\Permission::checkAccess(5, 'static', true)) {
                throw new \Exception($_CORELANG['TXT_CORE_CM_CREATION_DENIED']);
            }
            
            // Create a new node/page combination.
            $node = new \Cx\Core\ContentManager\Model\Doctrine\Entity\Node();
            
            // CREATE WITHIN
            if (isset($dataPost['parent_node'])) {
                $parentNode = $this->nodeRepo->find($dataPost['parent_node']);
                if (!$parentNode) {
                    $parentNode = $this->nodeRepo->getRoot();
                }
                $node->setParent($parentNode);
                
                // add parent node to ID, so the node containing the new page is opened
                if (!isset($_COOKIE['jstree_open'])) {
                    $_COOKIE['jstree_open'] = '';
                }
                $openNodes = explode(',', $_COOKIE['jstree_open']);
                if ($openNodes == array(0=>'')) {
                    $openNodes = array();
                }
                if (!in_array('#node_' . $parentNode->getId(), $openNodes)) {
                    $openNodes[] = '#node_' . $parentNode->getId();
                }
                setcookie('jstree_open', implode(',', $openNodes));

                $this->em->persist($node);
                $this->em->flush();
            
            // CREATE
            } else {
                $node->setParent($this->nodeRepo->getRoot());

                $this->em->persist($node);
                $this->em->flush();
            }

            $page = new \Cx\Core\ContentManager\Model\Doctrine\Entity\Page();
            $page->setNode($node);
            $page->setNodeIdShadowed($node->getId());
            $page->setLang(\FWLanguage::getLanguageIdByCode($lang));
            $page->setUpdatedBy(
                \FWUser::getFWUserObject()->objUser->getUsername()
            );

            $newPage = true;
            $reload  = true;
        } else {
            throw new \Exception('Page cannot be created. There are too little information.');
        }
        
        // Page access check
        if ($page->isBackendProtected() &&
                !\Permission::checkAccess($page->getBackendAccessId(), 'dynamic', true)) {
            throw new \Cx\Core\ContentManager\Model\Doctrine\Entity\PageException('Not allowed to read page');
        }
        
        if (!empty($pageArray)) {
            $page->updateFromArray($validatedPageArray);
        }
        
        if (!empty($action)) {
            switch ($action) {
                case 'activate':
                case 'publish':
                    $page->setActive(true);
                    break;
                case 'deactivate':
                    $page->setActive(false);
                    break;
                case 'show':
                    $page->setDisplay(true);
                    break;
                case 'hide':
                    $page->setDisplay(false);
                    break;
                case 'protect':
                    $page->setFrontendProtection(true);
                    break;
                case 'unprotect':
                    $page->setFrontendProtection(false);
                    break;
                case 'lock':
                    $page->setBackendProtection(true);
                    break;
                case 'unlock':
                    $page->setBackendProtection(false);
                    break;
            }
            
            if (($action != 'publish') && ($page->getEditingStatus() == '')) {
                $action = 'publish';
            }
        }
        
        $page->setUpdatedAtToNow();
        $page->validate();
        
        // Permissions are only updated in the editing mode.
        if (!empty($pageArray)) {
            if ($action == 'publish') {
                if (\Permission::checkAccess(36, 'static', true)) {
                    if ($page->isFrontendProtected()) {
                        // remove all
                        \Permission::removeAccess($page->getFrontendAccessId(), 'dynamic');
                        if (isset($dataPost['frontendGroups'])) {
                            // set new
                            $pg->setAssignedGroupIds($page, $dataPost['frontendGroups'], true);
                        }
                    }
                    if ($page->isBackendProtected()) {
                        // remove all
                        $groupIds = $pg->getAssignedGroupIds($page, false);
                        \Permission::removeAccess($page->getBackendAccessId(), 'dynamic');
                        if (isset($dataPost['backendGroups'])) {
                            // set new
                            $pg->setAssignedGroupIds($page, $dataPost['backendGroups'], false);
                        }
                        if ($page->isBackendProtected() &&
                            !\Permission::checkAccess($page->getBackendAccessId(), 'dynamic', true)) {
                            if (!count($groupIds)) {
                                $page->setBackendProtection(false);
                            } else {
                                $pg->setAssignedGroupIds($page, $groupIds, false);
                            }
                        }
                    }
                }
            }
        }
        
        // Block associations are only updated in the editing mode.
        if (!empty($pageArray)) {
            if (!isset($dataPost['pageBlocks'])) {
                $dataPost['pageBlocks'] = array();
            }
            $page->setRelatedBlocks($dataPost['pageBlocks']);
        }
        
        $draftUpdateLog = null;
        $liveUpdateLog = null;
        if (($action == 'publish') && \Permission::checkAccess(78, 'static', true)) {
            // User w/permission clicked save&publish. we should either publish the page or submit the draft for approval.
            if ($page->getEditingStatus() == 'hasDraftWaiting') {
                $reload = true;
            }
            $page->setEditingStatus('');
            $this->messages[] = $_CORELANG['TXT_CORE_SAVED'];
            // TODO: Define what log data we want to keep in a case like this.
            //       Make adjustments, if necessary.
        } else {
            // User clicked save [as draft], so let's do that.
            if ($newPage) {
                $this->em->persist($page);
                $this->em->flush();
            }
            $updatingDraft = $page->getEditingStatus() != '' ? true : false;

            if ($action == 'publish') {
                // User w/o publish permission clicked save&publish. submit it as a draft.
                $page->setEditingStatus('hasDraftWaiting');
                $this->messages[] = $_CORELANG['TXT_CORE_DRAFT_SUBMITTED'];
            } else {
                if ($page->getEditingStatus() == 'hasDraftWaiting' && \Permission::checkAccess(78, 'static', true)) {
                    $reload = true;
                }
                $page->setEditingStatus('hasDraft');
                $this->messages[] = $_CORELANG['TXT_CORE_SAVED_AS_DRAFT'];
            }

            // Gedmo-loggable generates a LogEntry (i.e. revision) on persist, so we'll have to 
            // store the draft first, then revert the current version to what it previously was.
            // In the end, we'll have the current [published] version properly stored as a page
            // and the draft version stored as a gedmo LogEntry.

            $this->em->persist($page);
            // Gedmo hooks in on persist/flush, so we unfortunately need to flush our em in
            // order to get a clean set of logEntries.
            $this->em->flush();
            $logEntries = $this->logRepo->getLogEntries($page);
            // $logEntries holds an array of Gedmo LogEntries, the most recent one listed first.
            // We need the editing status of the page.
            $logData = $logEntries[1]->getData();
            $logData['editingStatus'] = $page->getEditingStatus();
            $logEntries[1]->setData($logData);

            // Revert to the published version.
            $this->logRepo->revert($page, $logEntries[1]->getVersion());
            
            switch ($action) {
                case 'activate':
                case 'publish':
                    $page->setActive(true);
                    break;
                case 'deactivate':
                    $page->setActive(false);
                    break;
                case 'show':
                    $page->setDisplay(true);
                    break;
                case 'hide':
                    $page->setDisplay(false);
                    break;
                case 'protect':
                    $page->setFrontendProtection(true);
                    break;
                case 'unprotect':
                    $page->setFrontendProtection(false);
                    break;
                case 'lock':
                    $page->setBackendProtection(true);
                    break;
                case 'unlock':
                    $page->setBackendProtection(false);
                    break;
            }
            
            $this->em->persist($page);

            // Gedmo auto-logs slightly too much data. clean up unnecessary revisions:
            if ($updatingDraft) {
                $this->em->flush();

                $logEntries = $this->logRepo->getLogEntries($page);
                $liveUpdateLog = $logEntries[2];
                $draftUpdateLog = $logEntries[3];
                $this->em->remove($logEntries[2]);
                $this->em->remove($logEntries[3]);
            }
        }
        
        $this->em->persist($page);
        
        if ((isset($dataPost['inheritFrontendAccess']) && $dataPost['inheritFrontendAccess'] == 'on')
                || (isset($dataPost['inheritBackendAccess']) && $dataPost['inheritBackendAccess'] == 'on')
                || (isset($dataPost['inheritSkin']) && $dataPost['inheritSkin'] == 'on')
                || (isset($dataPost['inheritCustomContent']) && $dataPost['inheritCustomContent'] == 'on')
                || (isset($dataPost['inheritCssName']) && $dataPost['inheritCssName'] == 'on')
                || (isset($dataPost['inheritCssNavName']) && $dataPost['inheritCssNavName'] == 'on')
                || (isset($dataPost['inheritCaching']) && $dataPost['inheritCaching'] == 'on')
        ) {
            $pageStack = $page->getChildren();
            while (count($pageStack)) {
                $currentPage = array_pop($pageStack);
                foreach ($currentPage->getChildren() as $child) {
                    array_push($pageStack, $child);
                }
                if (isset($dataPost['inheritFrontendAccess']) && $dataPost['inheritFrontendAccess'] == 'on'/*frontendprotection*/) {
                    $reload = true;
                    $page->copyProtection($currentPage, true);
                }
                if (isset($dataPost['inheritBackendAccess']) && $dataPost['inheritBackendAccess'] == 'on'/*backendprotection*/) {
                    $reload = true;
                    $page->copyProtection($currentPage, false);
                }
                if (isset($dataPost['inheritSkin']) && $dataPost['inheritSkin'] == 'on'/*theme*/) {
                    $currentPage->setSkin($page->getSkin());
                }
                if (isset($dataPost['inheritCustomContent']) && $dataPost['inheritCustomContent'] == 'on'/*customContent*/) {
                    $currentPage->setCustomContent($page->getCustomContent());
                }
                if (isset($dataPost['inheritCssName']) && $dataPost['inheritCssName'] == 'on'/*cssName*/) {
                    $currentPage->setCssName($page->getCssName());
                }
                if (isset($dataPost['inheritCssNavName']) && $dataPost['inheritCssNavName'] == 'on'/*cssNavName*/) {
                    $currentPage->setCssNavName($page->getCssNavName());
                }
                if (isset($dataPost['inheritCaching']) && $dataPost['inheritCaching'] == 'on'/*caching*/) {
                    $currentPage->setCaching($page->getCaching());
                }
                $this->em->persist($currentPage);
            }
        }
        
        $this->em->flush();
        
        if ($updatingDraft) {
            $this->em->clear();
            $logs = $this->logRepo->getLogEntries($page);
            $data = $logs[1]->getData();
            if (!empty($action) && $draftUpdateLog) {
                $data = $draftUpdateLog->getData();
            }
            $data['editingStatus'] = 'hasDraft';
            if ($action == 'publish' && !\Permission::checkAccess(78, 'static', true)) {
                $data['editingStatus'] = 'hasDraftWaiting';
            }
            switch ($action) {
                case 'activate':
                    $data['active'] = true;
                    break;
                case 'deactivate':
                    $data['active'] = false;
                    break;
                case 'show':
                    $data['display'] = true;
                    break;
                case 'hide':
                    $data['display'] = false;
                    break;
                case 'protect':
                    $data['protection'] = $data['protection'] | FRONTEND_PROTECTION;
                    break;
                case 'unprotect':
                    $data['protection'] = $data['protection'] & ~FRONTEND_PROTECTION;
                    break;
                case 'lock':
                    $data['protection'] = $data['protection'] | BACKEND_PROTECTION;
                    break;
                case 'unlock':
                    $data['protection'] = $data['protection'] & ~BACKEND_PROTECTION;
                    break;
            }
            $logs[1]->setData($data);
            
            if (!empty($action) && $action != 'publish') {
                $data = $logs[0]->getData();
                if ($liveUpdateLog) {
                    $data = $liveUpdateLog->getData();
                }
                switch ($action) {
                    case 'activate':
                        $data['active'] = true;
                        break;
                    case 'deactivate':
                        $data['active'] = false;
                        break;
                    case 'show':
                        $data['display'] = true;
                        break;
                    case 'hide':
                        $data['display'] = false;
                        break;
                    case 'protect':
                        $data['protection'] = $data['protection'] | FRONTEND_PROTECTION;
                        break;
                    case 'unprotect':
                        $data['protection'] = $data['protection'] & ~FRONTEND_PROTECTION;
                        break;
                    case 'lock':
                        $data['protection'] = $data['protection'] | BACKEND_PROTECTION;
                        break;
                    case 'unlock':
                        $data['protection'] = $data['protection'] & ~BACKEND_PROTECTION;
                        break;
                }
                $logs[0]->setData($data);
            }
            
            $this->em->persist($logs[0]);
            $this->em->persist($logs[1]);
            $this->em->flush();
        }
        
        // Aliases are only updated in the editing mode.
        if (!empty($pageArray)) {
            // Only users with publish rights can create aliases.
            if (\Permission::checkAccess(115, 'static', true) && \Permission::checkAccess(78, 'static', true)) {
                // Aliases are updated after persist.
                $data = array();
                $data['alias'] = $pageArray['alias'];
                $aliases = $page->getAliases();
                $page->updateFromArray($data);
                if ($aliases != $page->getAliases()) {
                    $reload = true;
                }
            } else {
                // Users without permission shouldn't see the aliasses anyway
                //$this->messages[] = $_CORELANG['TXT_CORE_ALIAS_CREATION_DENIED'];
            }
        }
        
        return array(
            'reload' => $reload,
            'id'     => $page->getId(),
            'node'   => $page->getNode()->getId(),
            'lang'   => \FWLanguage::getLanguageCodeById($page->getLang()),
        );
    }

    /**
     * Sets multiple pages.
     * 
     * @param  array  $params  Client parameters.
     */
    public function multipleSet($params) {
        register_shutdown_function(array($this, 'multipleSetShutdown'));
        
        $post = $params['post'];
        $data['post']['lang']   = $post['lang'];
        $data['post']['action'] = $post['action'];
        $recursive = (isset($params['get']['recursive']) && $params['get']['recursive'] == 'true');
        $requestedOffset = (isset($params['get']['offset']) ? $params['get']['offset'] : 0);
        $return = array();
        
        $nodeIdStack = $post['nodes'];
        $this->multipleSetState = array('state' => 'running', 'offset' => 0, 'stack' => $nodeIdStack);
        $offset = 0;
        while (count($nodeIdStack)) {
            $nodeId = array_pop($nodeIdStack);
            $data['post']['nodeId'] = $nodeId;
            $node = $this->nodeRepo->findOneById($nodeId);
            if ($recursive) {
                foreach ($node->getChildren() as $child) {
                    array_push($nodeIdStack, $child->getId());
                }
            }
            $offset++;
            if ($requestedOffset > $offset) {
                continue;
            }
            $page = $node->getPage(\FWLanguage::getLanguageIdByCode($post['lang']));
            switch ($data['post']['action']) {
                case 'activate':
                    if ($page->isActive()) {
                        // we have that already, continue
                        continue;
                    }
                    break;
                case 'deactivate':
                    if (!$page->isActive()) {
                        // we have that already, continue
                        continue;
                    }
                    break;
                case 'show':
                    if ($page->isVisible()) {
                        // we have that already, continue
                        continue;
                    }
                    break;
                case 'hide':
                    if (!$page->isVisible()) {
                        // we have that already, continue
                        continue;
                    }
                    break;
                case 'protect':
                    if ($page->isFrontendProtected()) {
                        // we have that already, continue
                        continue;
                    }
                    break;
                case 'unprotect':
                    if (!$page->isFrontendProtected()) {
                        // we have that already, continue
                        continue;
                    }
                    break;
                case 'lock':
                    if ($page->isBackendProtected()) {
                        // we have that already, continue
                        continue;
                    }
                    break;
                case 'unlock':
                    if (!$page->isBackendProtected()) {
                        // we have that already, continue
                        continue;
                    }
                    break;
                default:
                    // just set what we got, continue
                    break;
            }
            if (!empty($page)) {
                $data['post']['pageId'] = $page->getId();
            } else {
                $data['post']['pageId'] = 0;
            }
            $result = $this->set($data);
            if (   isset($post['currentNodeId'])
                && isset($post['lang'])
                && $result['node'] == $post['currentNodeId']
                && $result['lang'] == $post['lang']
            ) {
                $return['id'] = $result['id'];
            }
            $this->multipleSetState['offset']++;
        }
        $this->multipleSetState = null;
        unset($nodeIdStack);
        
        return $return;
    }
    
    public function multipleSetShutdown() {
        if ($this->multipleSetState) {
            $this->multipleSetState['state'] = 'timeout';
            echo \Cx\Core\Json\JsonData::json($this->multipleSetState, true);
        }
    }

    /**
     * Sets the page object in the session and returns the link to the page (frontend).
     * @param   array  $params
     * @return  array  [link]     The link to the page (frontend).
     */
    public function setPagePreview($params) {
        global $_CORELANG;
        
        // Global access check
        if (!\Permission::checkAccess(6, 'static', true) || !\Permission::checkAccess(35, 'static', true)) {
            throw new \Exception($_CORELANG['TXT_CORE_CM_USAGE_DENIED']);
        }
        
        $page = $this->validatePageArray($params['post']['page']);
        $page['pageId'] = $params['post']['page']['id'];
        $page['lang'] = $params['post']['page']['lang'];

        $_SESSION['page'] = $page;
    }

    /**
     * Returns a validated page array.
     * 
     * @param   array  $page
     * @return  array  $output
     */
    private function validatePageArray($page) {
        $fields = array(
            'type' => array('type' => 'String'),
            'name' => array('type' => 'String', 'required' => true, 'map_to' => 'title'),
            'title' => array('type' => 'String', 'required' => true, 'map_to' => 'contentTitle'),
            // the model can take advantage of proper NULLing, so this needn't be set
            //                  'scheduled_publishing' => array('type' => 'boolean'),
            'start' => array('type' => 'DateTime', 'require' => 'scheduled_publishing'),
            'end' => array('type' => 'DateTime', 'require' => 'scheduled_publishing'),
            'metatitle' => array('type' => 'String'),
            'metakeys' => array('type' => 'String'),
            'metadesc' => array('type' => 'String'),
            'metarobots' => array('type' => 'boolean'),
            'content' => array('type' => 'String'),
            'sourceMode' => array('type' => 'boolean'),
            'protection_frontend' => array('type' => 'boolean', 'map_to' => 'frontendProtection'),
            'protection_backend' => array('type' => 'boolean', 'map_to' => 'backendProtection'),
            'application' => array('type' => 'String', 'map_to' => 'module'),
            'area' => array('type' => 'String', 'map_to' => 'cmd'),
            'target' => array('type' => 'String'),
            'link_target' => array('type' => 'String', 'map_to' => 'linkTarget'),
            'slug' => array('type' => 'String'),
            'caching' => array('type' => 'boolean'),
            'skin' => array('type' => 'integer'),
            'customContent' => array('type' => 'String'),
            'cssName' => array('type' => 'String'),
            'cssNavName' => array('type' => 'String'),
        );

        $output = array();

        foreach ($fields as $field => $meta) {
            $target = isset($meta['map_to']) ? $meta['map_to'] : $field;
            
            if (isset($page[$field])) {
                $page[$field] = contrexx_input2raw($page[$field]);
            } else {
                $page[$field] = null;
            }
            
            if (isset($meta['required']) && $meta['required'] === true) {
                if ($page[$field] == '') {
                    throw new \Cx\Core\ContentManager\Model\Doctrine\Entity\PageException('Required field (' . $field . ') not set!');
                }
            }

            switch ($meta['type']) {
                case 'boolean':
                    // checkboxes and radiobuttons by default aren't submitted
                    // unless checked or selected. in cm.html they are prefixed
                    // with an input type=hidden value=off, so we always get a
                    // value this is required for Page#updateFromArray to work.
                    if ($page[$field] == 'on') {
                        $value = true;
                    } else if ($page[$field] == 'off') {
                        $value = false;
                    }
                    break;
                case 'DateTime':
                    try {
                        $value = new \DateTime($page[$field], $this->tz);
                    } catch (\Exception $e) {
                        $value = new \DateTime('0000-00-00 00:00', $this->tz);
                    }
                    break;
                case 'integer':
                    $value = intval($page[$field]);
                    break;
                case 'String':
                    $value = $page[$field];
                    break;
            }
            if (isset($meta['require']) && isset($page[$meta['require']]) && $page[$meta['require']] == 'off') {
                $value = null;
            }

            $output[$target] = $value;
        }

        //TODO: should we allow filter/callback fns in field processing above?
        $output['content'] = preg_replace('/\\[\\[([A-Z0-9_-]+)\\]\\]/', '{\\1}', $output['content']);

        return $output;
    }

    public function getHistoryTable($params) {
        global $_CORELANG, $_CONFIG;
        
        if (empty($params['get']['page'])) {
            throw new \Exception('please provide a pageId');
        }

        //(I) get the right page
        $page = $this->pageRepo->findOneById($params['get']['page']);

        if (!$page) {
            throw new \Exception('could not find page with id '.$params['get']['page']);
        }

        //(II) build the table with headers
        $table = new \BackendTable(array('width' => '100%'));
        $table->setAutoGrow(true);

        $table->setHeaderContents(0, 0, 'Date', array('width' => '130px'));
        $table->setHeaderContents(0, 1, 'Title');
        $table->setHeaderContents(0, 2, 'Author');
        //make sure those are th's too
        $table->setHeaderContents(0, 3, $_CORELANG['TXT_FUNCTIONS'], array('style' => 'text-align: right;'));

        //(III) collect page informations - path, virtual language directory
        $path         = $this->pageRepo->getPath($page);
        $pageHasDraft = $page->getEditingStatus() != '' ? true : false;
        $langDir      = \FWLanguage::getLanguageCodeById($page->getLang());
        $logs         = $this->logRepo->getLogEntries($page);
        
        //(V) add the history entries
        $row = 0;
        $logCount = count($logs);
        // Paging:
        $offset = !empty($params['get']['pos']) ? $params['get']['pos'] : 0;
        $numberOfEntries = $_CONFIG['corePagingLimit'];
        for ($i = 0; $i < $logCount; $i++) {
            if ($offset > $i) {
                continue;
            }
            if ($i >= ($numberOfEntries + $offset)) {
                continue;
            }
            if (isset($logs[$i + 1])) {
                $data = $logs[$i]->getData();
                $nextData = $logs[$i + 1]->getData();
                if (isset($nextData['editingStatus']) && ($nextData['editingStatus'] == 'hasDraft' || $nextData['editingStatus'] == 'hasDraftWaiting')) {
                    if (!isset($data['editingStatus']) || ($data['editingStatus'] != 'hasDraft' && $data['editingStatus'] != 'hasDraftWaiting')) {
                        continue;
                    }
                }
            }
            
            $version = $logs[$i]->getVersion();
            $row++;
            $user = json_decode($logs[$i]->getUsername());
            $username = $user->{'name'};
            try {
                $this->logRepo->revert($page, $version);
                $page->setUpdatedAt($logs[$i]->getLoggedAt());

                $this->addHistoryEntries($page, $username, $table, $row, $version, $langDir . '/' . $path, $pageHasDraft);
            } catch (\Gedmo\Exception\UnexpectedValueException $e) {
                
            }
        }
        // Add paging widget:
        $paging = '<div id="history_paging">' . getPaging($row, $offset, '?cmd=content&page=16&tab=history', 'Einträge', true) . '</div>';

        //(VI) render
        die($table->toHtml() . $paging);
    }

    private function addHistoryEntries($page, $username, $table, $row, $version = '', $path = '', $pageHasDraft = true) {
        global $_ARRAYLANG;

        $dateString  = $page->getUpdatedAt()->format(ASCMS_DATE_FORMAT_DATETIME);
        $historyLink = ASCMS_PATH_OFFSET . '/' . $path . '?history=' . $version;
        $tableStyle  = '';

        if ($row == 1) {
            $tableStyle  = 'style="display: none;"';
            if ($pageHasDraft) {
                $dateString .= ' (' . $_ARRAYLANG['TXT_CORE_DRAFT'] . ')';
            }
        }
        
        $functions  = '<a id="preview_'.$version.'" class="historyPreview" href="' . $historyLink . '" target="_blank" '.$tableStyle.'>' . $_ARRAYLANG['TXT_CORE_PREVIEW'] . '</a>';
        $functions .= '<a id="load_'.$version.'" class="historyLoad" href="javascript:loadHistoryVersion('.$version.')" '.$tableStyle.'>' . $_ARRAYLANG['TXT_CORE_LOAD'] . '</a>';
        
        $table->setCellContents($row, 0, $dateString);
        $table->setCellContents($row, 1, $page->getTitle());
        $table->setCellContents($row, 2, $username);
        $table->setCellContents($row, 3, $functions);
    }

    /**
     * Returns the access data array
     * @param type $page Unused
     * @return array Access data
     */
    public function getAccessData($page = null) {
        // TODO: add functionality for $page!=null (see below), DRY up #getFallbackPageArray

        $pg = \Env::get('pageguard');

        $accessData = array();

        $accessData['frontend'] = array('groups' => $pg->getGroups(true), 'assignedGroups' => array());
        $accessData['backend'] = array('groups' => $pg->getGroups(false), 'assignedGroups' => array());

        return $accessData;
    }

    /**
     * Returns the array representation of a fallback page
     * @param   \Cx\Core\ContentManager\Model\Doctrine\Entity\Node $node Node to get the page of
     * @param   string  $lang  Language code to get the fallback of
     * @return  array   Array  representing a fallback page
     */
    private function getFallbackPageArray($node, $lang) {
        $page = null;
        foreach ($node->getPages() as $page) {
            if ($page->getLang() == \FWLanguage::getLanguageIdByCode($this->fallbacks[$lang])) {
                break;
            }
        }
        
        if (!$page) {
            throw new \Cx\Core\ContentManager\Model\Doctrine\Entity\NodeException('Node (id '.$node->getId().') has no pages.');
        }

        // Access Permissions
        $pg = \Env::get('pageguard');
        $accessData = array();
        $accessData['frontend'] = array('groups' => $pg->getGroups(true), 'assignedGroups' => $pg->getAssignedGroupIds($page, true));
        $accessData['backend'] = array('groups' => $pg->getGroups(false), 'assignedGroups' => $pg->getAssignedGroupIds($page, false));

        $pageArray = array(
            // Editor Meta
            'id' => 0,
            'lang' => $lang,
            'node' => $node->getId(),
            'type' => ($this->fallbacks[$lang] ? 'fallback' : 'content'),
            // Page Tab
            'name' => $page->getTitle(),
            'title' => $page->getContentTitle(),
            // Metadata
            'metatitle' => $page->getMetatitle(),
            'metadesc' => $page->getMetadesc(),
            'metakeys' => $page->getMetakeys(),
            'metarobots' => $page->getMetarobots(),
            // Access Permissions
            'frontend_protection' => $page->isFrontendProtected(),
            'backend_protection' => $page->isBackendProtected(),
            'accessData' => $accessData,
            // Advanced Settings
            'slug' => $page->getSlug(),
            'sourceMode' => false,
            'sourceMode' => $page->getSourceMode(),
        );

        return $pageArray;
    }

    /**
     * Creates the page array representation of a page
     * @param \Cx\Core\ContentManager\Model\Doctrine\Entity\Page $page Page to "arrayify"
     * @return Array Array representing a page
     */
    private function getPageArray($page) {
        // Scheduled Publishing
        $n = new \DateTime(null, $this->tz);
        if ($page->getStart() && $page->getEnd()) {
            $scheduled_publishing = true;
            $start = $page->getStart()->format('d.m.Y H:i');
            $end = $page->getEnd()->format('d.m.Y H:i');
        } else {
            $scheduled_publishing = false;
            $start = $n->format('d.m.Y H:i');
            $end = $n->format('d.m.Y H:i');
        }

        // Access Permissions
        $pg = \Env::get('pageguard');
        $accessData = array();
        $accessData['frontend'] = array('groups' => $pg->getGroups(true), 'assignedGroups' => $pg->getAssignedGroupIds($page, true));
        $accessData['backend'] = array('groups' => $pg->getGroups(false), 'assignedGroups' => $pg->getAssignedGroupIds($page, false));

        try {
            $parentPath = substr($page->getParent()->getPath(), 1) . '/';
        } catch (\Cx\Core\ContentManager\Model\Doctrine\Entity\PageException $e) {
            $parentPath = '';
        }
        
        $pageArray = array(
            // Editor Meta
            'id' => $page->getId(),
            'lang' => \FWLanguage::getLanguageCodeById($page->getLang()),
            'node' => $page->getNode()->getId(),
            // Page Tab
            'name' => $page->getTitle(),
            'title' => $page->getContentTitle(),
            'type' => $page->getType(),
            'target' => $page->getTarget(),
            'target_path' => $this->getPathByTarget(array('get' => array('target' => $page->getTarget()))),
            'module' => $page->getModule(),
            'area' => $page->getCmd(),
            'scheduled_publishing' => $scheduled_publishing,
            'start' => $start,
            'end' => $end,
            'content' => preg_replace('/{([A-Z0-9_-]+)}/', '[[\\1]]', $page->getContent()),
            'sourceMode' => $page->getSourceMode(),
            // Metadata
            'metatitle' => $page->getMetatitle(),
            'metadesc' => $page->getMetadesc(),
            'metakeys' => $page->getMetakeys(),
            'metarobots' => $page->getMetarobots(),
            // Access Permissions
            'frontend_protection' => $page->isFrontendProtected(),
            'backend_protection' => $page->isBackendProtected(),
            'accessData' => $accessData,
            // Advanced Settings
            'skin' => $page->getSkin(),
            'customContent' => $page->getCustomContent(),
            'cssName' => $page->getCssName(),
            'cssNavName' => $page->getCssNavName(),
            'caching' => $page->getCaching(),
            'linkTarget' => $page->getLinkTarget(),
            'slug' => $page->getSlug(),
            'aliases' => $this->getAliasArray($page),
            'editingStatus' => $page->getEditingStatus(),
            'parentPath' => $parentPath,
            'assignedBlocks' => $this->getBlocks($page),
            'historyId' => $page->getVersion()->getVersion(),
                /* 'display'       =>  $page->getDisplay(),
                  'active'        =>  $page->getActive(),
                  'updatedAt'     =>  $page->getUpdatedAt(), */
        );

        return $pageArray;
    }
    
    private function getBlocks($page) {
        $blocks = $page->getRelatedBlocks();
        $data = array();
        foreach ($blocks as $id=>$block) {
            $data[] = $id;
        }
        return $data;
    }

    /**
     * Returns an array of alias slugs
     * @param Cx\Core\ContentManager\Model\Doctrine\Entity\Page $page Page to get the aliases of
     * @return Array<String>
     */
    private function getAliasArray($page) {
        $pages = $page->getAliases();
        $aliases = array();
        foreach ($pages as $alias) {
            $aliases[] = $alias->getSlug();
        }
        return $aliases;
    }

    /**
     * Returns the page path of the given target (node placeholder).
     * If the target page doesn't exist, the path of the error page will be returned.
     *
     * @param   array   $arguments
     * @return  string  $path
     */
    public function getPathByTarget($arguments) {
        global $_CONFIG;

        $target = contrexx_input2raw($arguments['get']['target']);

        if (!\FWValidator::hasProto($target)) {
            $page = new \Cx\Core\ContentManager\Model\Doctrine\Entity\Page();
            $page->setTarget($target);

            if ($page->isTargetInternal()) {
                $target = str_replace(array('[[', ']]'), array('{', '}'), $target);
                \LinkGenerator::parseTemplate($target);
            } elseif (strpos($target, ASCMS_PATH_OFFSET) === false) {
                if ($target[0] !== '/') {
                    $target = '/' . $target;
                }
                $target = ASCMS_PATH_OFFSET . $target;
            }

            $target = ASCMS_PROTOCOL . '://' . $_CONFIG['domainUrl'] . $target;
        }

        return $target;
    }
}
