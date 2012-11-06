<?php

namespace Cx\Model\ContentManager\Repository;

use Doctrine\Common\Util\Debug as DoctrineDebug;
use Doctrine\ORM\EntityRepository,
    Doctrine\ORM\EntityManager,
    Doctrine\ORM\Mapping\ClassMetadata,
    Doctrine\ORM\Query\Expr;

class PageRepositoryException extends \Exception {};
class TranslateException extends \Exception {};

class PageRepository extends EntityRepository {
    const SEARCH_MODE_PAGES_ONLY = 1;
    const SEARCH_MODE_ALIAS_ONLY = 2;
    const SEARCH_MODE_ALL = 3;
    const DataProperty = '__data';
    protected $em = null;
    private $virtualPages = array();

    public function __construct(EntityManager $em, ClassMetadata $class)
    {
        parent::__construct($em, $class);
        $this->em = $em;
    }

    /**
     * Finds entities by a set of criteria.
     *
     * @param array $criteria
     * @param boolean $inactive_langs
     * @return array
     * @override
     */
    public function findBy(array $criteria, $inactive_langs = false)
    {
        $activeLangs = \FWLanguage::getActiveFrontendLanguages();
        $pages = $this->_em->getUnitOfWork()->getEntityPersister($this->_entityName)->loadAll($criteria);
        if (!$inactive_langs) {
            foreach ($pages as $index=>$page) {
                if (!in_array($page->getLang(), array_keys($activeLangs))) {
                    unset($pages[$index]);
                }
            }
        }
        return $pages;
    }

    /**
     * Finds a single entity by a set of criteria.
     *
     * @param array $criteria
     * @param boolean $inactive_langs
     * @return object
     * @override
     */
    public function findOneBy(array $criteria, $inactive_langs = false)
    {
        $activeLangs = \FWLanguage::getActiveFrontendLanguages();
        $page = $this->_em->getUnitOfWork()->getEntityPersister($this->_entityName)->load($criteria);
        if (!$inactive_langs && $page) {
            if (!in_array($page->getLang(), array_keys($activeLangs))) {
                return null;
            }
        }
        return $page;
    }

    /**
     * Find a single page specified by module, cmd and lang.
     * Use to find a specific module page within a certain language.
     *
     * @param   string $module Module name
     * @param   string $cmd Cmd of the module
     * @param   int    $lang Language-Id
     * @return  \Cx\Model\ContentManager\Page
     */
    public function findOneByModuleCmdLang($module, $cmd, $lang)
    {
        $page = $this->findOneBy(array(
            'module' => $module,
            'cmd'    => $cmd,
            'lang'   => $lang,
        ));
        if (!$page) {
            // try to fetch the requested page by doing a reverse lookup
            // through the fallback-logic
            $page = $this->lookupPageFromModuleAndCmdByFallbackLanguage($module, $cmd, $lang);
        }

        return $page;
    }

    /**
     * Tries to find a page that acts as a module page, but that does physically 
     * not exist in the specified language, but might exist as a fallback page.
     *
     * @param   string  $module
     * @param   string  $cmd
     * @param   int     $lang
     * @return  mixed   \Cx\Model\ContentManager\Page if a page was found, otherwise NULL
     */
    private function lookupPageFromModuleAndCmdByFallbackLanguage($module, $cmd, $lang)
    {
        $fallbackLangId = \FWLanguage::getFallbackLanguageIdById($lang);

        // The language of the requested page does not have a fallback-language,
        // therefore we can stop here.
        if (!$fallbackLangId) {
            return null;
        }

        // 1. try to fetch the requested module page from the fallback-language
        //$pageRepo = \Env::get('em')->getRepository('Cx\Model\ContentManager\Page');
        $page = $this->findOneBy(array(
            'module' => $module,
            'cmd'    => $cmd,
            'lang'   => $fallbackLangId,
        ));

        if (!$page) {
            // We could not find the requested module page in the fallback-language.
            // Lets try to find the requested module page in the fallback-language
            // of the fallback-language (this will start a recursion until we will 
            // reach the end of the fallback-language tree)
            $page = $this->lookupPageFromModuleAndCmdByFallbackLanguage($module, $cmd, $fallbackLangId);
        }

        // In case we have not found the requested module page within the
        // fallback-language tree, we can stop here.
        if (!$page) {
            return null;
        }

        // 2. We found the requested module page in the fallback-language.
        // Now lets check if the associated NODE also has a page in the
        // language we were originally looking for. If not, we can stop here.
        $page = $page->getNode()->getPage($lang);
        if (!$page) {
            return null;
        }

        // 3. We found a page in our language!
        // Now lets do a final check if this page is of type fallback.
        // If so, we were unlucky and have to stop here.
        if ($page->getType() != \Cx\Model\ContentManager\Page::TYPE_FALLBACK) {
            return null;
        }

        // Reaching this point, means that our reverse lookup was successfull.
        // Meaning the we found the requested module page.
        return $page;
    }


    /**
     * An array of pages sorted by their langID for specified module and cmd.
     *
     * @param string $module
     * @param string $cmd optional
     *
     * @return array ( langId => Page )
     */
    public function getFromModuleCmdByLang($module, $cmd = null) {
        $crit = array( 'module' => $module );
        if($cmd)
            $crit['cmd'] = $cmd;

        $pages = $this->findBy($crit);
        $ret = array();

        foreach($pages as $page) {
            $ret[$page->getLang()] = $page;
        }

        return $ret;
    }

    /**
     * Adds a virtual page to the page repository.
     * @todo Remembering virtual pages is no longer necessary, rewrite method to create new virtual pages
     * @param  \Cx\Model\ContentManager\Page  $virtualPage
     * @param  string                         $beforeSlug
     */
    public function addVirtualPage($virtualPage, $beforeSlug = '') {
        $virtualPage->setVirtual(true);
        if (!$virtualPage->getLang()) {
            $virtualPage->setLang(FRONTEND_LANG_ID);
        }
        $this->virtualPages[] = array(
            'page'       => $virtualPage,
            'beforeSlug' => $beforeSlug,
        );
    }
    
    /**
     * Adds all virtual pages to the original tree.
     * 
     * @param   array    $tree         Original tree.
     * @param   integer  $lang
     * @param   integer  $rootNodeLvl
     * @param   string   $rootPath
     * 
     * @return  array    $tree         New tree with virtual pages.
     */
    /*protected function addVirtualTree($tree, $lang, $rootNodeLvl, $rootPath) {
        $tree = $this->addVirtualTreeLvl($tree, $lang, $rootNodeLvl, $rootPath);
        foreach ($tree as $slug=>$data) {
            if ($slug == '__data') {
                continue;
            }
            if ($tree[$slug]['__data']['page']->isVirtual()) {
                continue;
            }
            $tree[$slug] = $this->addVirtualTreeLvl($data, $lang, $rootNodeLvl, $tree[$slug]['__data']['page']->getPath());
            // Recursion for the tree
            $tree[$slug] = $this->addVirtualTree($data, $lang, $rootNodeLvl + 1, $tree[$slug]['__data']['page']->getPath());
        }
        return $tree;
    }*/
    
    /**
     * Adds the pages of the given node level to the tree.
     * 
     * @param   array    $tree
     * @param   integer  $lang
     * @param   integer  $rootNodeLvl
     * @param   string   $rootPath
     * 
     * @return  array    $tree
     */
    /*protected function addVirtualTreeLvl($tree, $lang, $rootNodeLvl, $rootPath) {
        foreach ($this->virtualPages as $virtualPage) {
            $page = $virtualPage['page'];
            $node = $page->getNode();
            
            if (count(explode('/', $page->getPath())) - 2 != $rootNodeLvl ||
                    // Only add pages within path of currently parsed node
                    substr($page->getPath().'/', 0, strlen($rootPath.'/')) != $rootPath.'/') {
                continue;
            }
            
            $beforeSlug = $virtualPage['beforeSlug'];
            $position   = array_search($beforeSlug, array_keys($tree));
            
            if (!empty($beforeSlug) && $position !== false) {
                $head = array_splice($tree, 0, $position);
                $insert[$page->getSlug()] = array(
                    '__data' => array(
                        'lang' => array($lang),
                        'page' => $page,
                        'node' => $node,
                    ),
                );
                $tree = array_merge($head, $insert, $tree);
            } else {
                $tree[$page->getSlug()] = array(
                    '__data' => array(
                        'lang' => array($lang),
                        'page' => $page,
                        'node' => $node,
                    ),
                );
            }
            // Recursion for virtual subpages of a virtual page
            $tree[$page->getSlug()] = $this->addVirtualTreeLvl($tree[$page->getSlug()], $lang, $rootNodeLvl + 1, $page->getPath());
        }
        
        return $tree;
    }*/

    /**
     * Get a tree of all Nodes with their Pages assigned.
     *
     * @todo there has once been a $lang param here, but fetching only a certain language fills 
     *       the pages collection on all nodes with only those fetched pages. this means calling
     *       getPages() later on said nodes will yield a collection containing only a subset of
     *       all pages linked to the node. now, we're fetching all pages and sorting those not
     *       matching the desired language out in @link getTreeBySlug() to prevent the
     *       associations from being destroyed.
     *       naturally, this generates big overhead. this strategy should be rethought.
     * @todo $titlesOnly param is not respected - huge overhead.
     * @param Node $rootNode limit query to subtree.
     * @param boolean $titlesOnly fetch titles only. You may want to use @link getTreeBySitle()
     * @return array
     */
    /*public function getTree($rootNode = null, $titlesOnly = false,
            $search_mode = self::SEARCH_MODE_PAGES_ONLY, $inactive_langs = false) {
        $repo = $this->em->getRepository('Cx\Model\ContentManager\Node');
        $qb = $this->em->createQueryBuilder();

        $joinConditionType = null;
        $joinCondition = null;

        $qb->addSelect('p');
        
        //join the pages
        $qb->leftJoin('node.pages', 'p', $joinConditionType, $joinCondition);
        $qb->where($qb->expr()->gt('node.lvl', 0)); //exclude root node
        if (!$inactive_langs) {
            $activeLangs = \FWLanguage::getActiveFrontendLanguages();
            $qb->andWhere($qb->expr()->in('p.lang', array_keys($activeLangs)));
        }
        switch ($search_mode) {
            case self::SEARCH_MODE_ALIAS_ONLY:
                $qb->andWhere(
                        'p.type = \'' . 
                        \Cx\Model\ContentManager\Page::TYPE_ALIAS .
                        '\''
                ); //exclude non alias nodes
                continue;
            case self::SEARCH_MODE_ALL:
                continue;
            case self::SEARCH_MODE_PAGES_ONLY:
            default:
                $qb->andWhere(
                        'p.type != \'' . 
                        \Cx\Model\ContentManager\Page::TYPE_ALIAS .
                        '\''
                ); //exclude alias nodes
                continue;
        }
        
        //get all nodes
        if (is_object($rootNode) && !$rootNode->getId()) {
            $tree = array();
        } else {
            $tree = $repo->children($rootNode, false, 'lft', 'ASC', $qb);
        }

        return $tree;
    }*/
    
    /**
     * Get a tree mapping slugs to Page, Node and language.
     *
     * @see getTree()
     * @return array ( slug => array( '__data' => array(lang => langId, page =>), child1Title => array, child2Title => array, ... ) ) recursively array-mapped tree.
     */
    /*public function getTreeBySlug($rootNode = null, $lang = null, $titlesOnly = false, $search_mode = self::SEARCH_MODE_PAGES_ONLY) {
        $tree = $this->getTree($rootNode, true, $search_mode);

        $result = array();

        $isRootQuery = !$rootNode || ( isset($rootNode) && $rootNode->getLvl() == 0 );

        for($i = 0; $i < count($tree); $i++) {
            $lang2Arr = null;
            $rightLevel = false;
            $node = $tree[$i];
            if($isRootQuery)
                $rightLevel = $node->getLvl() == 1;
            else
                $rightLevel = $node->getLvl() == $rootNode->getLvl() + 1;

            if($rightLevel)
                $i = $this->treeBySlug($tree, $i, $result, $lang2Arr, $lang);
            else {
                $i++;
            }
        }

        if (!empty($this->virtualPages)) {
            $rootNodeLvl = $rootNode ? $rootNode->getLvl() : 0;
            $rootPath = $rootNode ? $rootNode->getPage($lang) ? $rootNode->getPage($lang)->getPath() : '' : '';
            $result = $this->addVirtualTree($result, $lang, $rootNodeLvl, $rootPath);
        }

        return $result;
    }*/

    /*protected function treeBySlug(&$nodes, $startIndex, &$result, &$lang2Arr = null, $lang = null) {
        //first node we treat
        $index = $startIndex;
        $node = $nodes[$index];
        $nodeCount = count($nodes);

        //only treat nodes on this level and higher
        $minLevel = $node->getLvl();

        $thisLevelLang2Arr = array();
        do {
            if($node->getLvl() == $minLevel) {
                $this->treeBySlugPages($nodes[$index], $result, $lang2Arr, $lang, $thisLevelLang2Arr);
                $index++;
            }
            else {
                $index = $this->treeBySlug($nodes, $index, $result, $thisLevelLang2Arr, $lang);
            }

            if($index == $nodeCount) //we traversed all nodes
                break;
            $node = $nodes[$index];
        }
        while($node->getLvl() >= $minLevel);

        return $index;
    }

    protected function treeBySlugPages($node, &$result, &$lang2Arr, $lang, &$thisLevelLang2Arr) {
        //get titles of all Pages linked to this Node
        $pages = null;

        if (!$lang) {
            $pages = $node->getPages();
        } else {
            $pages = array();
            $page  = $node->getPage($lang);
            
            if ($page) {
                $pages = array($page);
            }
        }

        foreach ($pages as $page) {
            $slug = $page->getSlug();
            $lang = $page->getLang();

            if ($lang2Arr) { //this won't be set for the first node
                $target = &$lang2Arr[$lang];
            } else {
                $target = &$result;
            }

            if (isset($target[$slug])) { //another language's Page has the same title
                //add the language
                $target[$slug]['__data']['lang'][] = $lang;
            } else {
                $target[$slug] = array();
                $target[$slug]['__data'] = array(
                                                'lang' => array($lang),
                                                'page' => $page,
                                                'node' => $node,
                                            );
            }
            //remember mapping for recursion
            $thisLevelLang2Arr[$lang] = &$target[$slug];
        }
    }*/

    /**
     * Tries to find the path's Page.
     *
     * @param  string  $path e.g. Hello/APage/AModuleObject
     * @param  Node    $root
     * @param  int     $lang
     * @param  boolean $exact if true, returns null on partially matched path
     * @return array (
     *     matchedPath => string (e.g. 'Hello/APage/'),
     *     unmatchedPath => string (e.g. 'AModuleObject') | null,
     *     node => Node,
     *     lang => array (the langIds where this matches),
     *     [ pages = array ( all pages ) ] #langId = null only
     *     [ page => Page ] #langId != null only
     * )
     */
    public function getPagesAtPath($path, $root = null, $lang = null, $exact = false, $search_mode = self::SEARCH_MODE_PAGES_ONLY) {
        $result = $this->resolve($path, $search_mode);
        if (!$result) {
            return null;
        }
        $treePointer = $result['treePointer'];

        if (!$lang) {
            $result['page'] = $treePointer['__data']['node']->getPagesByLang($search_mode == self::SEARCH_MODE_ALIAS_ONLY);
            $result['lang'] = $treePointer['__data']['lang'];
        } else {
            $page = $treePointer['__data']['node']->getPagesByLang();
            $page = $page[$lang];
            $result['page'] = $page;
        }
        return $result;
    }

    /**
     * Returns the matched and unmatched path.
     * 
     * @param  string  $path e.g. Hello/APage/AModuleObject
     * @param  array   $tree
     * @param  boolean $exact if true, returns null on partially matched path
     * @return array(
     *     matchedPath   => string (e.g. 'Hello/APage/'),
     *     unmatchedPath => string (e.g. 'AModuleObject') | null,
     *     treePointer   => array,
     * )
     */
    /*public function getPathes($path, $tree, $exact = false) {
        //this is a mock strategy. if we use this method, it should be rewritten to use bottom up
        $pathParts = explode('/', $path);
        $matchedLen = 0;
        $treePointer = &$tree;

        foreach ($pathParts as $part) {
            if (isset($treePointer[$part])) {
                $treePointer = &$treePointer[$part];
                $matchedLen += strlen($part);
                if ('/' == substr($path, $matchedLen,1)) {
                    $matchedLen++;
                }
            } else {
                if ($exact) {
                    return false;
                }
                break;
            }
        }

        //no level matched
        if ($matchedLen == 0) {
            return false;
        }

        $unmatchedPath = substr($path, $matchedLen);
        if (!$unmatchedPath) { //beautify the to empty string
            $unmatchedPath = '';
        }

        return array(
            'matchedPath'   => substr($path, 0, $matchedLen),
            'unmatchedPath' => $unmatchedPath,
            'treePointer'   => $treePointer,
        );
    }*/
    
    /**
     * @todo We could use this in a much more efficient way. There's no need to call this method twice!
     * @todo Remove parameter $search_mode
     * @todo Return a single page or null
     * @param type $path
     * @param type $search_mode
     * @return boolean 
     */
    public function resolve($path, $search_mode) {
        // remove slash at the beginning
        if (substr($path, 0, 1) == '/') {
            $path = substr($path, 1);
        }
        $parts = explode('/', $path);
        $lang = \FWLanguage::getLanguageIdByCode($parts[0]);
        // let's see if path starts with a language (which it should)
        if ($lang !== false) {
            if ($search_mode != self::SEARCH_MODE_PAGES_ONLY) {
                return false;
            }
            unset($parts[0]);
        } else {
            if ($search_mode != self::SEARCH_MODE_ALIAS_ONLY) {
                return false;
            }
            // it's an alias we try to resolve
            // search for alias pages with matching slug
            $pages = $this->findBy(array(
                'type' => \Cx\Model\ContentManager\Page::TYPE_ALIAS,
                'slug' => $parts[0],
            ), true);
            if (count($pages) == 1) {
                $page = $pages[0];
                return array(
                    'matchedPath'   => substr($page->getPath(), 1) . '/',
                    'unmatchedPath' => implode('/', $parts),
                    'treePointer'   => array('__data'=>array('lang'=>array($lang), 'page'=>$page, 'node'=>$page->getNode())),
                );
            }
            return false;
        }
        
        $nodeRepo = $this->em->getRepository('Cx\Model\ContentManager\Node');
        
        $page = null;
        $node = $nodeRepo->getRoot();
        foreach ($parts as $index=>$slug) {
            foreach ($node->getChildren() as $child) {
                $childPage = $child->getPage($lang);
                if (!$childPage) {
                    continue;
                }
                if ($childPage->getSlug() == $slug) {
                    $node = $child;
                    $page = $childPage;
                    unset($parts[$index]);
                    break;
                }
            }
        }
        if (!$page) {
            // no matching page
            return false;
        }
        return array(
            'matchedPath'   => substr($page->getPath(), 1) . '/',
            'unmatchedPath' => implode('/', $parts),
            'treePointer'   => array('__data'=>array('lang'=>array($lang), 'page'=>$page, 'node'=>$page->getNode())),
        );
    }

    /**
     * Get a pages' path. Alias for $page->getPath() for compatibility reasons
     * For compatibility reasons, this path won't start with a slash!
     * @todo remove this method
     *
     * @param \Cx\Model\ContentManager\Page $page
     * @return string path, e.g. 'This/Is/It'
     */
    public function getPath($page) {
        return substr($page->getPath(), 1);
    }
    
    /**
     * Returns an array with the page translations of the given page id.
     * 
     * @param  int  $pageId
     * @param  int  $historyId  If the page does not exist, we need the history id to revert them.
     */
    public function getPageTranslations($pageId, $historyId) {
        $pages = array();
        $pageTranslations = array();
        
        $currentPage = $this->findOneById($pageId);
        // If page is deleted
        if (!is_object($currentPage)) {
            $currentPage = new \Cx\Model\ContentManager\Page();
            $currentPage->setId($pageId);
            $logRepo = $this->em->getRepository('Gedmo\Loggable\Entity\LogEntry');
            $logRepo->revert($currentPage, $historyId);
            
            $logs = $logRepo->getLogsByAction('remove');
            foreach ($logs as $log) {
                $page = new \Cx\Model\ContentManager\Page();
                $page->setId($log->getObjectId());
                $logRepo->revert($page, $log->getVersion() - 1);
                if ($page->getNodeIdShadowed() == $currentPage->getNodeIdShadowed()) {
                    $pages[] = $page;
                }
            }
        } else { // Page exists
            $pages = $this->findByNodeIdShadowed($currentPage->getNodeIdShadowed());
        }
        
        foreach ($pages as $page) {
            $pageTranslations[$page->getLang()] = \FWLanguage::getLanguageCodeById($page->getLang());
        }
        
        return $pageTranslations;
    }

    /**
     * Returns the type of the page as string.
     * 
     * @param   \Cx\Model\ContentManager\Page  $page
     * @return  string                         $type
     */
    public function getTypeByPage($page) {
        global $_CORELANG;
        
        switch ($page->getType()) {
            case \Cx\Model\ContentManager\Page::TYPE_REDIRECT:
                $criteria = array(
                    'nodeIdShadowed' => $page->getTargetNodeId(),
                    'lang'           => $page->getLang(),
                );
                $targetPage  = $this->findOneBy($criteria);
                $targetTitle = $targetPage ? $targetPage->getTitle() : $page->getTarget();
                $type        = $_CORELANG['TXT_CORE_CM_TYPE_REDIRECT'].': ';
                $type       .= $targetTitle;
                break;
            case \Cx\Model\ContentManager\Page::TYPE_APPLICATION:
                $type  = $_CORELANG['TXT_CORE_CM_TYPE_APPLICATION'].': ';
                $type .= $page->getModule();
                $type .= $page->getCmd() != '' ? ' | '.$page->getCmd() : '';
                break;
            case \Cx\Model\ContentManager\Page::TYPE_FALLBACK:
                $fallbackLangId = \FWLanguage::getFallbackLanguageIdById($page->getLang());
                if ($fallbackLangId == 0) {
                    $fallbackLangId = \FWLanguage::getDefaultLangId();
                }
                $type  = $_CORELANG['TXT_CORE_CM_TYPE_FALLBACK'].' ';
                $type .= \FWLanguage::getLanguageCodeById($fallbackLangId);
                break;
            default:
                $type = $_CORELANG['TXT_CORE_CM_TYPE_CONTENT'];
        }
        
        return $type;
    }
    
    /**
     * Returns the target page for a page with internal target
     * @todo use this everywhere (resolver!)
     * @param   \Cx\Model\ContentManager\Page  $page
     */
    public function getTargetPage($page) {
        if (!$page->isTargetInternal()) {
            throw new PageRepositoryException('Tried to get target node, but page has no internal target');
        }

// TODO: basically the method \Cx\Model\ContentManager\Page::cutTarget() would provide us a ready to use $crit array
//       Check if we could directly use the array from cutTarget() and implement a public method to cutTarget()
        $nodeId = $page->getTargetNodeId();
        $module = $page->getTargetModule();
        $cmd    = $page->getTargetCmd();
        $langId = $page->getTargetLangId();
        if ($langId == 0) {
            $langId = FRONTEND_LANG_ID;
        }

        $page = $this->findOneByModuleCmdLang($module, $cmd, $langId);
        if (!$page) {
            $page = $this->findOneByModuleCmdLang($module, $cmd.'_'.$langId, FRONTEND_LANG_ID);
        }
        if (!$page) {
            $page = $this->findOneByModuleCmdLang($module, $langId, FRONTEND_LANG_ID);
        }

        return $page;
    }

    /**
     * Searches the content and returns an array that is built as needed by the search module.
     *
     * Please do not use this anywhere else, write a search method with proper results instead. Ideally, this
     * method would then be invoked by searchResultsForSearchModule().
     *
     * @param string $string the string to match against.
     * @return array (
     *     'Score' => int
     *     'Title' => string
     *     'Content' => string
     *     'Link' => string
     * )
     */
    public function searchResultsForSearchModule($string, $license) {
        if ($string == '') {
            return array();
        }

//TODO: use MATCH AGAINST for score
//      Doctrine can be extended as mentioned in http://groups.google.com/group/doctrine-user/browse_thread/thread/69d1f293e8000a27
//TODO: shorten content in query rather than in php

        $qb = $this->em->createQueryBuilder();
        $qb->add('select', 'p')
           ->add('from', 'Cx\Model\ContentManager\Page p')
           ->add('where',
                 $qb->expr()->andx(
                     $qb->expr()->eq('p.lang', FRONTEND_LANG_ID),
                     $qb->expr()->orx(
                         $qb->expr()->like('p.content', ':searchString'),
                         $qb->expr()->like('p.title', ':searchString')
                     ),
                     $qb->expr()->orX(
                        'p.module = \'\'',
                        'p.module IS NULL',
                        'p.module IN (:modules)'
                     )
                 )
           )
           ->setParameter('searchString', '%'.$string.'%')
           ->setParameter('modules', $license->getLegalFrontendComponentsList());
        $pages   = $qb->getQuery()->getResult();
        $config  = \Env::get('config');
        $results = array();

        foreach($pages as $page) {
            $isNotVisible  = ($config['searchVisibleContentOnly'] == 'on') && !$page->isVisible();
            $hasPageAccess = true;
            if ($page->isFrontendProtected()) {
                $hasPageAccess = \Permission::checkAccess($page->getFrontendAccessId(), 'dynamic', true);
            }
            
            if (!$page->isActive() || $isNotVisible || !$hasPageAccess) {
                continue;
            }
            
            $results[] = array(
                'Score' => 100,
                'Title' => $page->getTitle(),
                'Content' => substr($page->getTitle(),0, $config['searchDescriptionLength']),
                'Link' => $this->getPath($page)
            );
        }

        return $results;
    }

    /**
     * Returns true if the page selected by its language, module name (section)
     * and optional cmd parameters exists
     * @param   integer     $lang       The language ID
     * @param   string      $module     The module (aka section) name
     * @param   string      $cmd        The optional cmd parameter value
     * @return  boolean                 True if the page exists, false
     *                                  otherwise
     * @author  Reto Kohli <reto.kohli@comvation.com>
     * @since   3.0.0
     * @internal    Required by the Shop module
     */
    public function existsModuleCmd($lang, $module, $cmd=null)
    {
        $crit = array(
            'module' => $module,
            'lang' => $lang,
        );
        if (isset($cmd)) $crit['cmd'] = $cmd;
        return (boolean)$this->findOneBy($crit);
    }

    public function getLastModifiedPages($from, $count) {
        $query = $this->em->createQuery("
            select p from Cx\Model\ContentManager\Page p 
                 order by p.updatedAt asc
        ");
        $query->setFirstResult($from);
        $query->setMaxResults($count);

        return $query->getResult();
    }
}
