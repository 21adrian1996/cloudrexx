<?php

/**
 * Navigation
 * Note: modified 27/06/2006 by Sébastien Perret => sva.perret@bluewin.ch
 *
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author        Comvation Development Team <info@comvation.com>
 * @version        1.0.0
 * @package     contrexx
 * @subpackage  core
 * @todo        Edit PHP DocBlocks!
 */

/**
 * Class Navigation
 * This class creates the navigation tree
 * Note: modified 27/06/2006 by Sébastien Perret => sva.perret@bluewin.ch
 *
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author        Comvation Development Team <info@comvation.com>
 * @access        public
 * @version        1.0.0
 * @package     contrexx
 * @subpackage  core
 */
class Navigation
{
    public $langId;
    public $data = array();
    public $table = array();
    public $tree = array();
    public $parents = array();
    public $pageId;
    public $styleNameActive = 'active';
    public $styleNameNormal = 'inactive';
    public $separator = ' > ';
    public $spacer = '&nbsp;';
    public $levelInfo = 'down';
    public $subNavSign = '&nbsp;&raquo;';
    public $subNavTag = '<ul id="menubuilder%s" class="menu">{SUB_MENU}</ul>';
    public $_cssPrefix = 'menu_level_';
    public $_objTpl;
    public $topLevelPageId;
    public $_menuIndex = 0;

    protected $page = null;


    /**
    * Constructor
    * @global   integer
    * @param     integer  $pageId
    * @param Cx\Core\ContentManager\Model\Entity\Page $page
    */
    function __construct($pageId, $page)
    {
        global $_LANGID;

        $this->langId = $_LANGID;
        $this->pageId = $pageId;
        $this->page = $page;
        //$this->_initialize();
        //$this->_getParents();
        // $parcat is the starting parent id
        // optional $maxLevel is the maximum level, set to 0 to show all levels
        //$this->_buildTree();
    }



    public function getSubnavigation($templateContent, $license, $boolShop=false)
    {
        return $this->parseNavigation($templateContent, $license, $boolShop, true);
    }
    

    public function getNavigation($templateContent, $license, $boolShop=false)
    {
        return $this->parseNavigation($templateContent, $license, $boolShop, false);
    }

    /**
     * @param   string  $templateContent
     * @param   boolean $boolShop         If true, parse the shop navigation
     *                                    into {SHOPNAVBAR_FILE}
     * @param   \Cx\Core\ContentManager\Model\Entity\Page requestedPage
     * @access  private
    * @return mixed parsed navigation
    */
    private function parseNavigation($templateContent, $license, $boolShop=false, $parseSubnavigation=false)
    {
        // only proceed if a navigation template had been set
        if (empty($templateContent)) {
            return;
        }

        $this->_objTpl = new \Cx\Core\Html\Sigma('.');
        CSRF::add_placeholder($this->_objTpl);
        $this->_objTpl->setErrorHandling(PEAR_ERROR_DIE);
        $this->_objTpl->setTemplate($templateContent);

        if ($boolShop) {
            $this->_objTpl->setVariable('SHOPNAVBAR_FILE', Shop::getNavbar());
        }

        $rootNode = null;
        if ($parseSubnavigation) {
// TODO: add comment to why the subnavigation will need the rootNode
            $rootNode = $this->page->getNode();
            while($rootNode->getLvl() > 1) {
                $rootNode = $rootNode->getParent();
            }
        }

        if (isset($this->_objTpl->_blocks['navigation_dropdown'])) {
            // set submenu tag
            if ($this->_objTpl->blockExists('sub_menu')) {
                $this->subNavTag = trim($this->_objTpl->_blocks['sub_menu']);
                $templateContent = preg_replace('<!--\s+BEGIN\s+sub_menu\s+-->.*<!--\s+END\s+sub_menu\s+-->/ms', NULL, $templateContent);
            }
            $navi = new \Cx\Core\PageTree\DropdownNavigationPageTree(Env::em(), $license, 0, $rootNode, $this->langId, $this->page);
            $navi->setVirtualLanguageDirectory(Env::get('virtualLanguageDirectory'));
            $navi->setTemplate($this->_objTpl);
            $renderedNavi = $navi->render();
            $templateContent = preg_replace('/<!--\s+BEGIN\s+level_\d+\s+-->.*<!--\s+END\s+level_\d+\s+-->/ms', $renderedNavi, $templateContent);
            return preg_replace('/<!--\s+BEGIN\s+navigation_dropdown\s+-->(.*)<!--\s+END\s+navigation_dropdown\s+-->/ms', '\1', $templateContent);
        }

        if (isset($this->_objTpl->_blocks['navigation'])) {
            $navi = new \Cx\Core\PageTree\NavigationPageTree(Env::em(), $license, 0, $rootNode, $this->langId, $this->page);
            $navi->setVirtualLanguageDirectory(Env::get('virtualLanguageDirectory'));
            $navi->setTemplate($this->_objTpl);
            return $navi->render();
        }

        // Create a nested list, formatted with ul and li-Tags
        if (isset($this->_objTpl->_blocks['nested_navigation'])) {
            $navi = new \Cx\Core\PageTree\NestedNavigationPageTree(Env::em(), $license, 0, $rootNode, $this->langId, $this->page);
            $navi->setVirtualLanguageDirectory(Env::get('virtualLanguageDirectory'));
            $navi->setTemplate($this->_objTpl);
            $renderedNavi = $navi->render();
            return preg_replace('/<!--\s+BEGIN\s+nested_navigation\s+-->.*<!--\s+END\s+nested_navigation\s+-->/ms', $renderedNavi, $templateContent);
        }
    }




    /**
     * Get trail
     * @return    string     The trail with links
     */
    function getTrail()
    {
        $lang = $this->page->getLang();
        $node = $this->page->getNode()->getParent();
        $result = '';
        while($node->getLvl() > 0) {
            $page = $node->getPage($lang);
            $title = $page->getTitle();
            $path = \Cx\Core\Routing\Url::fromPage($page);
            $result = '<a href="'.$path.'" title="'.contrexx_raw2xhtml($title).'">'.contrexx_raw2xhtml($title).'</a>'.$this->separator.' '.$result;
            $node = $node->getParent();
        }
        return $result;
    }


    /**
     * getFrontendLangNavigation()
     * @param \Cx\Core\Routing\Url $pageUrl
     * @param boolean $langNameContraction
     * @return string 
     */
    function getFrontendLangNavigation($page, $pageUrl, $langNameContraction = false)
    {
        $activeLanguages = \FWLanguage::getActiveFrontendLanguages();
        $node = $page->getNode();

        $langNavigation = array();
        foreach ($activeLanguages as $langId => $langData) {
            $targetPage = $node->getPage($langId);
            if ($targetPage && $targetPage->isActive()) {
                $url = clone $pageUrl;
                $url->setLangDir($langData['lang']);
                $url->setPath(substr($targetPage->getPath(), 1));

                $name  = contrexx_raw2xhtml($langNameContraction ? strtoupper($langData['lang']) : $langData['name']);
                $class = $langId == FRONTEND_LANG_ID ? $langData['lang'].' active' : $langData['lang'];

                $langNavigation[] = '<a class="'.$class.'" href="'.$url.'" title="'.$name.'">'.$name.'</a>';
            }
        }

        return implode('', $langNavigation);
    }

    /**
     * Sets the language placeholders in the provided template
     * @param \Cx\Core\Routing\Url $pageUrl
     * @param \Cx\Core\Html\Sigma $objTemplate 
     */
    public function setLanguagePlaceholders($page, $pageUrl, $objTemplate)
    {
        $activeLanguages = \FWLanguage::getActiveFrontendLanguages();
        $node = $page->getNode();

        $placeholders = array();
        foreach ($activeLanguages as $langId => $langData) {
            $url = clone $pageUrl;
            $url->setLangDir($langData['lang']);

            if (($targetPage = $node->getPage($langId)) && $targetPage->isActive()) {
                $url->setPath(substr($targetPage->getPath(), 1));
                $link = $url->__toString();
            } else {
                $link = $url->fromModuleAndCmd('error', '', $langId);
            }
            $placeholders['LANG_CHANGE_'.strtoupper($langData['lang'])] = $link;
            $placeholders['LANG_SELECTED_'.strtoupper($langData['lang'])] = '';
        }
        $placeholders['LANG_SELECTED_'.strtoupper($pageUrl->getLangDir())] = 'selected';
        $objTemplate->setVariable($placeholders);
    }


    /**
    * builds the navigation tree sorted by parents
    * @access private
     * @param   integer   $parentId (Optional)
     * @param   integer   $maxlevel (Optional)
     * @param   integer   $level    (Optional)
    */
    function _buildTree($parentId=0,$maxlevel=0,$level=0)
    {
        if (!empty($this->table[$parentId])) {
            foreach (array_keys($this->table[$parentId]) as $id) {
                $this->tree[$id]=$level+1;
                if (   isset($this->table[$id])
                    && (   $maxlevel >= $level+1
                        || $maxlevel == 0)) {
                    $this->_buildTree($id,$maxlevel,$level+1);
                }
            }
        }
    }


    /**
     * Build navigation menu with a continuous list (default)
     * @return   string   $result
     */
    function _buildNavigation()
    {
        // not set
        $topLevelBlockName = NULL;
        $blockName = NULL;
        $htmlOutput = NULL;
        $navigationId[] = 0; // CSS styling ID's

        $array_ids = array_keys( $this->tree );
        // foreach ($this->tree as $id => $level)
        foreach ( $array_ids as $key => $id ) {
            $level = $this->tree[$id];

            if (!isset($navigationId[$level])) {
                $navigationId[$level] = 0;
            }

            $navigationId[$level]++;

            if (isset($array_ids[$key+1])) {
                $nextlevel = ( isset( $this->tree[$array_ids[$key+1]] ) ) ? $this->tree[$array_ids[$key+1]] : 0;
            } else {
                $nextlevel = 0;
            }

            $hideLevel=false;

            // checks if the page is in the current tree
            if (in_array($this->parentId[$id], $this->parents)){
                // checks for customized blocks. e.g. "level_1"
                if ($this->_objTpl->blockExists('level_'.$level)){
                    // gets the top level block name from the template
                    if (!isset($topLevelBlockName)){
                        $topLevelBlockName='level_'.$level;
                    }
                    $blockName = 'level_'.$level;
                }
                // no customized blocks for this level
                else {
                    // we already had customized blocks
                    if (isset($topLevelBlockName)){
                        // checks for the standard block e.g. "level"
                        if ($this->_objTpl->blockExists('level')){
                            $blockName = 'level';
                        } else {
                            $hideLevel=true;
                        }
                    }
                }

                if (isset($topLevelBlockName)){
                    if (!$hideLevel){
                        // checks if we are in the active tree
                        $activeTree = (in_array($id, $this->parents)) ? true : false;
                        if ($this->data[$id]['status'] == 'on') {
                            // gets the style sheets value for active or inactive
                            $style = ($activeTree) ? $this->styleNameActive : $this->styleNameNormal;
                            // get information about the next level id -> down or empty                            // $levelInfo = (($level > $nextlevel) OR $activeTree AND $id <> $this->pageId) ? $this->levelInfo : "";
                            $levelInfo = ($level > $nextlevel) ? $this->levelInfo : "";
                            $target = empty($this->data[$id]['target']) ? "_self" : $this->data[$id]['target'];
                            $this->_objTpl->setCurrentBlock($blockName);
                            $this->_objTpl->setVariable('URL',$this->data[$id]['url']);
                            $this->_objTpl->setVariable('NAME',htmlentities($this->data[$id]['catname'], ENT_QUOTES, CONTREXX_CHARSET));
                            $this->_objTpl->setVariable('TARGET',$target);
                            $this->_objTpl->setVariable('LEVEL_INFO',$levelInfo);
                            $this->_objTpl->setVariable('NAVIGATION_ID',$navigationId[$level]);
                            $this->_objTpl->setVariable('STYLE',$style);
                            $this->_objTpl->setVariable('CSS_NAME',$this->data[$id]['css_name']);
                            $this->_objTpl->parse($blockName);
                            $htmlOutput.=$this->_objTpl->get($blockName, true);
                        }
                    }
                }
            }
        }
        unset($navigationId);

        if (isset($topLevelBlockName)){
            // replaces the top level block with the complete parsed navigation
            // this is because the Sigma Template system don't support nested blocks
            // with difference object based orders
            $this->_objTpl->replaceBlock($topLevelBlockName, $htmlOutput, true);
            $this->_objTpl->touchBlock($topLevelBlockName);
            if ($this->_objTpl->blockExists('navigation')){
                $this->_objTpl->parse('navigation');
            }
        }
    }


    /**
     * Build nested navigation menu with unordered list
     * if [[nested_navigation]] is placed in navbar.
     * Formatting should be done with CSS.
     * Tags (ul and li) are inserted by the code.
     *
     * Navigation can be restricted to specific levels with the tag [[levels_AB]],
     * where A and B can take following values:
     *    starting level A: [1-9]
     *    ending level B: [1-9], [+] or [];
     *              [+]: any level starting from A;
     *              [] : just level A;
     *    examples: [[levels_24]] means navigation levels 2 to 4;
     *              [[levels_3+]] means any navigation levels starting from 3;
     *              [[levels_1]] means navigation level 1 only;
     * @return   string   $result
     */
    function _buildNestedNavigation()
    {
        $navigationBlock = "";

        // Checks which levels to use
        $match = array();
        if (!preg_match('/levels_([1-9])([1-9\+]*)/', trim($this->_objTpl->_blocks['nested_navigation']), $match)) {
            $match[1] = 1;
            $match[2] = '+';
        }

        $array_ids = array_keys( $this->tree );

        // Make an array with visible items only
        foreach ($array_ids as $key => $id ) {
            $level = $this->tree[$id];
            // Checks if the menu item is in the current tree
             if (in_array($this->parentId[$id], $this->parents)) {
                // Checks if the menu item level is visible
                if (($level>=$match[1] && $level<=$match[2]) || ($level>=$match[1] && $match[2] == "+")) {
                    if ($this->data[$id]['status'] == 'on') {
                        $array_visible_ids[] = $array_ids[$key];
                    }
                }
            }
        }

        // Build nested menu list
        $navigationId = array();
        foreach ($array_visible_ids as $key => $id) {
            $level = $this->tree[$id];
            $nextLevel = $this->tree[$array_visible_ids[$key+1]];
            if (!isset($navigationId[$level])) {
                $navigationId[$level] = 0;
            }
            $navigationId[$level]++;
            if ($nextLevel == NULL) $nextLevel = $match[1];
            $closingTags = '';
            for ($i=1; $i<=($level - $nextLevel); $i++) {
                $closingTags .= "\n</ul>\n</li>";
            }

            // Current block
            $this->_rowBlock = trim($this->_objTpl->_blocks['level']);

            // Build menu structure
            if ($level < $nextLevel) {
                $cssStyle = $this->_cssPrefix.($nextLevel);
// TODO: Must not use the "id" attribute here, as this is repeated!
                $nestedRow = "<li>".$this->_rowBlock."\n<ul id='$cssStyle'>";
            } elseif ($level > $match[1] && $level > $nextLevel) {
                $nestedRow = "<li>".$this->_rowBlock."</li>".$closingTags;
            } else {
                $nestedRow = "<li>".$this->_rowBlock."</li>";
            }

            // Checks if this is an active menu item
            $style = (in_array($id, $this->parents)) ? $this->styleNameActive : $this->styleNameNormal;
            $target = empty($this->data[$id]['target']) ? "_self" : $this->data[$id]['target'];
            $tmpNavigationBlock = str_replace('{NAME}', $this->data[$id]['catname'], $nestedRow);
            $tmpNavigationBlock = str_replace('<li>', '<li class="'.$style.'">', $tmpNavigationBlock);
            $tmpNavigationBlock = str_replace('{URL}', $this->data[$id]['url'], $tmpNavigationBlock);
            $tmpNavigationBlock = str_replace('{TARGET}', $target, $tmpNavigationBlock);
            $tmpNavigationBlock = str_replace('{CSS_NAME}',  $this->data[$id]['css_name'], $tmpNavigationBlock);
            $tmpNavigationBlock = str_replace('{NAVIGATION_ID}', $navigationId[$level], $tmpNavigationBlock);

            $navigationBlock .= $tmpNavigationBlock."\n";
        }
        if ($navigationBlock != "") {
            // Return nested menu
            return "<ul id='".$this->_cssPrefix.$match[1]."'>\n".$navigationBlock."</ul>";
        }
        return '';
    }


    /**
    * Build a drop down navigation menu
    * @access private
    * @param mixed $arrPage
    * @param integer $level
    * @param boolean $mainPage
    * @return string $navigation
    */
    function _buildDropDownNavigation($arrPage, $level, $mainPage = false)
    {
        $navigation = "";
        $tmpNavigation = "";
        $mainCat = 1;
        $navigationId = array();
        foreach ($arrPage as $page) {
            // prevent undefined variable notice
            if (!isset($navigationId[$level])) {
                $navigationId[$level] = 0;
            }
            ++$navigationId[$level];
            // page has children
            if (is_array($page)) {
                // get page id
                $keys = array_keys($page);
                $id = $keys[0];
                // get sub navigation
                $subNavigation = $this->_buildDropDownNavigation($page[$id], $level + 1);
                if (!empty($subNavigation)) {
                    $subNavigation = str_replace("{SUB_MENU}", $subNavigation, sprintf($this->subNavTag, $this->_menuIndex++)); //sprintf for js dropdown unique ID
                    if (($this->_objTpl->blockExists('level_all') || $this->_objTpl->blockExists('level_'.($level+1))) && !$mainPage ) {
                        if (!empty($subNavigation) && ($id != $this->parentId[$this->pageId] )) { //)$this->data[$id]['status'] == 'on') {
                            $this->data[$id]['catname'] .= $this->subNavSign;
                        }
                    }
                }
            } else {
                $subNavigation = "";
                $id = $page;
            }

            if ($this->_objTpl->blockExists('level_'.$level)) {
                $tmpNavigation = trim($this->_objTpl->_blocks['level_'.$level]);
            }

            if (!empty($tmpNavigation)) {
                if ($this->data[$id]['status'] == 'on') {
                    $target = empty($this->data[$id]['target']) ? "_self" : $this->data[$id]['target'];

                    if ($level == 1 && $this->topLevelPageId == $id) {
                        $tmpNavigation = str_replace('{STYLE}', "starter_active", $tmpNavigation);
                        $mainCat++; //inc if new maincat
                    } elseif ($level == 1) {
                        $tmpNavigation = str_replace('{STYLE}', "starter_normal", $tmpNavigation);
                        $mainCat++; //inc if new maincat
                    } else {
                        $tmpNavigation = str_replace('{STYLE}', $id == $this->pageId || in_array($id, $this->parents) ? $this->styleNameActive : $this->styleNameNormal, $tmpNavigation);
                    }

                    $tmpNavigation = str_replace('{URL}', $this->data[$id]['url'], $tmpNavigation);
                    $tmpNavigation = str_replace('{NAME}', $this->data[$id]['catname'], $tmpNavigation);
                    $tmpNavigation = str_replace('{TARGET}', $target, $tmpNavigation);
                    $tmpNavigation = str_replace('{NAVIGATION_ID}', $navigationId[$level], $tmpNavigation);
                    $tmpNavigation = str_replace('{SUB_MENU}', $subNavigation, $tmpNavigation);
                    $tmpNavigation = str_replace('{CSS_NAME}', $this->data[$id]['css_name'], $tmpNavigation);

                    $navigation .= $tmpNavigation;
                }
            }
        }
        return $navigation;
    }

    /**
     * Builds the array containing all parent IDs of the current page
     *
     * The array starts with the ID of the parent of the current page, and
     * continues up the hierarchy up to and including the root (ID 0).
     * The array contains the page ID as keys, and their respective parents
     * as values.  {@see get_parent_id()} relies on that structure.
     * @access  private
     */
    function _getParents()
    {
        $parentId = !empty($this->parentId[$this->pageId]) ? $this->parentId[$this->pageId] : 0;
        while ($parentId!=0) {
            if (is_array($this->table[$parentId])) {
                array_push($this->parents, $parentId);
                if (!empty($this->parentId[$parentId])) {
                    $parentId = $this->parentId[$parentId];
                } else {
                    $parentId = 0;
                }
            }
        }
        // adds the current pageId and the root id 0 to the parents array
        array_push($this->parents, $this->pageId, 0);
    }

    static function is_local_url($url)
    {
        $url = strtolower($url);
        if (strpos($url, 'http://' ) === 0) return false;
        if (strpos($url, 'https://') === 0) return false;
        if (strpos($url, '/'       ) === 0) return false;
        return true;
    }


    function _debug($obj)
    {
        echo "<pre>";
        print_r($obj);
        echo "</pre>";
    }




}
