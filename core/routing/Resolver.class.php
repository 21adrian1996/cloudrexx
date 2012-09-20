<?php
namespace Cx\Core\Routing;

class ResolverException extends \Exception {};

/**
 * Takes an URL and tries to find the Page.
 */
class Resolver {
    protected $em = null;
    protected $url = null;
    /**
     * language id.
     * @var integer
     */
    protected $lang = null;

    /**
     * the page we found.
     * @var Cx\Model\ContentManager\Page
     */
    protected $page = null;

    /**
     * Doctrine PageRepository
     */
    protected $pageRepo = null;

    /**
     * Doctrine NodeRepository
     */
    protected $nodeRepo = null;

    /**
     * Remembers if we've come across a redirection while resolving the URL.
     * This allow to properly redirect via 302.
     * @var boolean
     */
    protected $isRedirection = false;

    /**
     * Maps language ids to fallback language ids.
     * @var array ($languageId => $fallbackLanguageId)
     */
    protected $fallbackLanguages = null;
    
    /**
     * Contains the resolved module name (if any, empty string if none)
     * @var String
     */
    protected $section = '';
    
    /**
     * Contains the resolved module command (if any, empty string if none)
     * @var String
     */
    protected $command = '';
    
    /**
     * Remembers if it's a page preview.
     * @var boolean
     */
    protected $pagePreview = 0;
    
    /**
     * Contains the history id to revert the page to an older version.
     * @var int
     */
    protected $historyId = 0;
    
    /**
     * Contains the page array from the session.
     * @var array
     */
    protected $sessionPage = array();
    protected $path;
    
    /**
     * @param URL $url the url to resolve
     * @param integer $lang the language Id
     * @param $entityManager
     * @param string $pathOffset ASCMS_PATH_OFFSET
     * @param array $fallbackLanguages (languageId => fallbackLanguageId)
     * @param boolean $forceInternalRedirection does not redirect by 302 for internal redirections if set to true.
     *                this is used mainly for testing currently. 
     *                IMPORTANT: Do insert new parameters before this one if you need to and correct the tests.
     */
    public function __construct($url, $lang, $entityManager, $pathOffset, $fallbackLanguages, $forceInternalRedirection=false) {
        $this->init($url, $lang, $entityManager, $pathOffset, $fallbackLanguages, $forceInternalRedirection);
    }
    
    
    /**
     * @param URL $url the url to resolve
     * @param integer $lang the language Id
     * @param $entityManager
     * @param string $pathOffset ASCMS_PATH_OFFSET
     * @param array $fallbackLanguages (languageId => fallbackLanguageId)
     * @param boolean $forceInternalRedirection does not redirect by 302 for internal redirections if set to true.
     *                this is used mainly for testing currently. 
     *                IMPORTANT: Do insert new parameters before this one if you need to and correct the tests.
     */
    public function init($url, $lang, $entityManager, $pathOffset, $fallbackLanguages, $forceInternalRedirection=false) {
        $this->url = $url;
        $this->em = $entityManager;
        $this->lang = $lang;
        $this->pathOffset = $pathOffset;
        $this->pageRepo = $this->em->getRepository('Cx\Model\ContentManager\Page');
        $this->nodeRepo = $this->em->getRepository('Cx\Model\ContentManager\Node');
        $this->logRepo  = $this->em->getRepository('Gedmo\Loggable\Entity\LogEntry');
        $this->forceInternalRedirection = $forceInternalRedirection;
        $this->fallbackLanguages = $fallbackLanguages;
        $this->pagePreview = !empty($_GET['pagePreview']) && ($_GET['pagePreview'] == 1) ? 1 : 0;
        $this->historyId = !empty($_GET['history']) ? $_GET['history'] : 0;
        $this->sessionPage = !empty($_SESSION['page']) ? $_SESSION['page'] : array();
    }
    
    /**
     * Checks for alias request
     * @return Page or null
     */
    public function resolveAlias() {
        // This is our alias, if any
        $path = $this->url->getSuggestedTargetPath();
        $this->path = $path;

        //(I) see what the model has for us, aliases only.
        $result = $this->pageRepo->getPagesAtPath($path, null, null, false, \Cx\Model\ContentManager\Repository\PageRepository::SEARCH_MODE_ALIAS_ONLY);
        
        //(II) sort out errors
        if(!$result) {
            // no alias
            return null;
        }

        if(!$result['pages']) {
            // no alias
            return null;
        }
        if (count($result['pages']) != 1) {
            throw new ResolverException('Unable to match a single page for this alias (tried path ' . $path . ').');
        }
        $page = current($result['pages']);

        $this->page = $page;
        
        return $this->page;
    }

    /**
     * Does the resolving work, extends $this->url with targetPath and params.
     */
    public function resolve($internal = false) {
        $path = $this->url->getSuggestedTargetPath();
        
        if (!$this->page || $internal) {
            if ($this->pagePreview) {
                if (!empty($this->sessionPage)) {
                    $this->getPreviewPage();
                }
            }
            
            //(I) see what the model has for us
            $result = $this->pageRepo->getPagesAtPath($this->url->getLangDir().'/'.$path, null, $this->lang, false, \Cx\Model\ContentManager\Repository\PageRepository::SEARCH_MODE_PAGES_ONLY);
            if ($this->pagePreview) {
                if (empty($this->sessionPage)) {
                    if (\Permission::checkAccess(6, 'static', true)) {
                        $result['page']->setActive(true);
                        $result['page']->setDisplay(true);
                        if (($result['page']->getEditingStatus() == 'hasDraft') || (($result['page']->getEditingStatus() == 'hasDraftWaiting'))) {
                            $logEntries = $this->logRepo->getLogEntries($result['page']);
                            $this->logRepo->revert($result['page'], $logEntries[1]->getVersion());
                        }
                    }
                }
            }
            
            //(II) sort out errors
            if(!$result) {
                throw new ResolverException('Unable to locate page (tried path ' . $path .').');
            }

            if(!$result['page']) {
                throw new ResolverException('Unable to locate page for this language. (tried path ' . $path .').');
            }

            // If an older revision was requested, revert to that in-place:
            if (!empty($this->historyId) && \Permission::checkAccess(6, 'static', true)) {
                $this->logRepo->revert($result['page'], $this->historyId);
            }
            
            //(III) extend our url object with matched path / params
            $this->url->setTargetPath($result['matchedPath']);
            $this->url->setParams($result['unmatchedPath'] . $this->url->getSuggestedParams());

            $this->page = $result['page'];
        }
        /*
          the page we found could be a redirection.
          in this case, the URL object is overwritten with the target details and
          resolving starts over again.
         */
        $target = $this->page->getTarget();
        $isRedirection = $this->page->getType() == \Cx\Model\ContentManager\Page::TYPE_REDIRECT;
        $isAlias = $this->page->getType() == \Cx\Model\ContentManager\Page::TYPE_ALIAS;
        
        //handles alias redirections internal / disables external redirection
        $this->forceInternalRedirection = $this->forceInternalRedirection || $isAlias;
        
        if($target && ($isRedirection || $isAlias)) {
            // Check if page is a internal redirection and if so handle it
            if($this->page->isTargetInternal()) {
//TODO: add check for endless/circular redirection (a -> b -> a -> b ... and more complex)
                $nId = $this->page->getTargetNodeId();
                $lId = $this->page->getTargetLangId();
                $module = $this->page->getTargetModule();
                $cmd = $this->page->getTargetCmd();
                $qs = $this->page->getTargetQueryString();
                
                $langId = $lId ? $lId : $this->lang;

                // try to find the redirection target page
                if ($nId) {
                    $targetPage = $this->pageRepo->findOneBy(array('node' => $nId, 'lang' => $langId));

                    // revert to default language if we could not retrieve the specified langauge by the redirection.
                    // so lets try to load the redirection of the current language
                    if(!$targetPage) {
                        if($langId != 0) { //make sure we weren't already retrieving the default language
                            $targetPage = $this->pageRepo->findOneBy(array('node' => $nId, 'lang' => $this->lang));
                            $langId = $this->lang;
                        }
                    }
                } else {
                    $targetPage = $this->pageRepo->findOneByModuleCmdLang($module, $cmd, $langId);

                    // revert to default language if we could not retrieve the specified langauge by the redirection.
                    // so lets try to load the redirection of the current language
                    if(!$targetPage) {
                        if($langId != 0) { //make sure we weren't already retrieving the default language
                            $targetPage = $this->pageRepo->findOneByModuleCmdLang($module, $cmd, $this->lang);
                            $langId = $this->lang;
                        }
                    }
                }

                //check whether we have a page now.
                if(!$targetPage) {
                    throw new ResolverException('Found invalid redirection target on page "'.$this->page->getTitle().'" with id "'.$this->page->getId().'": tried to find target page with node '.$nId.' and language '.$langId.', which does not exist.');
                }

                // the redirection page is located within a different language.
                // therefore, we must set $this->lang to the target's language of the redirection.
                // this is required because we will next try to resolve the redirection target
                if ($langId != $this->lang) {
                    $this->lang = $langId;
                    $this->url->setLangDir(\FWLanguage::getLanguageCodeById($langId));
                    $this->pathOffset = ASCMS_PATH_OFFSET.'/'.\FWLanguage::getLanguageCodeById($langId);
                }

                $targetPath = substr($targetPage->getPath(), 1);

                $this->url->setPath($targetPath.$qs);
                $this->isRedirection = true;
                $this->resolve(true);
            } else { //external target - redirect via HTTP 302
                header('Location: '.$target);
                exit;
            }
        }
        
        //if we followed one or more redirections, the user shall be redirected by 302.
        if ($this->isRedirection && !$this->forceInternalRedirection) {
            $params = $this->url->getSuggestedParams();
            header('Location: '.$this->page->getURL($this->pathOffset, $params));
            exit;
        }
        
        // in case the requested page is of type fallback, we will now handle/load this page
        $this->handleFallbackContent($this->page, !$internal);
        
        // set legacy <section> and <cmd> in case the requested page is an application
        if ($this->page->getType() == \Cx\Model\ContentManager\Page::TYPE_APPLICATION
                || $this->page->getType() == \Cx\Model\ContentManager\Page::TYPE_FALLBACK) {
            $this->command = $this->page->getCmd();
            $this->section = $this->page->getModule();
        }
    }

    /**
     * Returns the preview page built from the session page array.
     * @return Cx\Model\ContentManager\Page $page
     */
    private function getPreviewPage() {
        $data = $this->sessionPage;
        
        $page = $this->pageRepo->findOneById($data['pageId']);
        if (!$page) {
            $page = new \Cx\Model\ContentManager\Page();
            $node = new \Cx\Model\ContentManager\Node();
            $node->setParent($this->nodeRepo->getRoot());
            $node->setLvl(1);
            $this->nodeRepo->getRoot()->addChildren($node);
            $node->addPage($page);
            $page->setNode($node);
            
            $this->pageRepo->addVirtualPage($page);
        }
        
        unset($data['pageId']);
        $page->setLang(\FWLanguage::getLanguageIdByCode($data['lang']));
        unset($data['lang']);
        $page->updateFromArray($data);
        $page->setUpdatedAtToNow();
        $page->setActive(true);
        $page->setVirtual(true);
        $page->validate();
        
        return $page;
    }

    /**
     * Checks whether $page is of type 'fallback'. Loads fallback content if yes.
     * @param Cx\Model\ContentManager $page
     * @param boolean $requestedPage Set to TRUE (default) if the $page passed by $page is the first resolved page (actual requested page)
     * @throws ResolverException
     */
    public function handleFallbackContent($page, $requestedPage = true) {
        //handle untranslated pages - replace them by the right language version.
        if($page->getType() == \Cx\Model\ContentManager\Page::TYPE_FALLBACK) {
            // in case the first resolved page (= original requested page) is a fallback page
            // we must check here if this very page is active.
            // If we miss this check, we would only check if the referenced fallback page is active!
            if ($requestedPage && !$page->isActive()) {
                return;
            }

            $langId = $this->fallbackLanguages[$page->getLang()];
            $fallbackPage = $page->getNode()->getPage($langId);
            if(!$fallbackPage)
                throw new ResolverException('Followed fallback page, but couldn\'t find content of fallback Language');

            $page->getFallbackContentFrom($fallbackPage);

            // due that the fallback is located within a different language
            // we must set $this->lang to the fallback's language.
            // this is required because we will next try to resolve the page
            // that is referenced by the fallback page
            $this->lang = $langId;
            $this->url->setLangDir(\FWLanguage::getLanguageCodeById($langId));
            $this->url->setSuggestedTargetPath(substr($fallbackPage->getPath(), 1));

            // now lets resolve the page that is referenced by our fallback page
            $this->resolve(true);
        }
    }

    public function getPage() {
        return $this->page;
    }
    
    public function getURL() {
        return $this->url;
    }
    
    /**
     * Returns the resolved module name (if any, empty string if none)
     * @return String Module name
     */
    public function getSection() {
        return $this->section;
    }
    
    /**
     * Returns the resolved module command (if any, empty string if none)
     * @return String Module command
     */
    public function getCmd() {
        return $this->command;
    }
    
    /**
     * Sets the value of the resolved module name and command
     * This should not be called from any (core_)module!
     * For legacy requests only!
     * 
     * @param String $section Module name
     * @param String $cmd Module command
     * @todo Remove this method as soon as legacy request are no longer possible
     */
    public function setSection($section, $command = '') {
        $this->section = $section;
        $this->command = $command;
    }
}
