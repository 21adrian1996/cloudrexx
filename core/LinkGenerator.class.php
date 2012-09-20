<?php
class LinkGeneratorException {}
/**
 * Handles the node-Url placeholders: [[ NODE_(<node_id>|<module>[_<cmd>])[_<lang_id>] ]]
 */
class LinkGenerator {
    /**
     * array ( placeholder_name => placeholder_link
     *
     * @var array stores the placeholders found by scan()
     */
    protected $placeholders = array();
    /**
     * @var boolean whether fetch() ran.
     */
    protected $fetchingDone = false;

    public static function parseTemplate(&$content)
    {
        $lg = new LinkGenerator();

        if (!is_array($content)) {
            $arrTemplates = array(&$content);
        } else {
            $arrTemplates = &$content;
        }

        foreach ($arrTemplates as &$template) {
            $lg->scan($template);
        }

        $lg->fetch(Env::get('em'));        

        foreach ($arrTemplates as &$template) {
            $lg->replaceIn($template);
        }
    }

    /**
     * Scans the given string for placeholders and remembers them
     * @param string $content
     */
    public function scan(&$content) {
        $this->fetchingDone = false;

        $regex = '/\{'.\Cx\Model\ContentManager\Page::NODE_URL_PCRE.'\}/xi';

        $matches = array();
        if (!preg_match_all($regex, $content, $matches)) {
            return;
        }

        for($i = 0; $i < count($matches[0]); $i++) {           
            $nodeId = isset($matches[\Cx\Model\ContentManager\Page::NODE_URL_NODE_ID][$i]) ?$matches[\Cx\Model\ContentManager\Page::NODE_URL_NODE_ID][$i] : 0;
            $module = isset($matches[\Cx\Model\ContentManager\Page::NODE_URL_MODULE][$i]) ? strtolower($matches[\Cx\Model\ContentManager\Page::NODE_URL_MODULE][$i]) : '';
            $cmd = isset($matches[\Cx\Model\ContentManager\Page::NODE_URL_CMD][$i]) ? strtolower($matches[\Cx\Model\ContentManager\Page::NODE_URL_CMD][$i]) : '';

            if (empty($matches[\Cx\Model\ContentManager\Page::NODE_URL_LANG_ID][$i])) {
                $langId = FRONTEND_LANG_ID;
            } else {
                $langId = $matches[\Cx\Model\ContentManager\Page::NODE_URL_LANG_ID][$i];
            }

            if ($nodeId) {
                # page is referenced by NODE-ID (i.e.: [[NODE_1]])
                $type = 'id';
            } else {
                # page is referenced by NODE-ID (i.e.: [[NODE_1]])
                $type = 'module';
            }

            $this->placeholders[$matches[\Cx\Model\ContentManager\Page::NODE_URL_PLACEHOLDER][$i]] = array(
                'type'      => $type,
                'nodeid'    => $nodeId,
                'module'    => $module,
                'cmd'       => $cmd,
                'lang'      => $langId,
            );
        }
    }

    public function getPlaceholders() {
        return $this->placeholders;
    }

    /**
     * Uses the given Entity Manager to retrieve all links for the placeholders
     * @param EntityManager $em
     */
    public function fetch($em) {
        if($this->placeholders === null)
            throw new LinkGeneratorException('Seems like scan() was never called before calling fetch().');

        $qb = $em->createQueryBuilder();
        $qb->add('select', new Doctrine\ORM\Query\Expr\Select(array('p')));
        $qb->add('from', new Doctrine\ORM\Query\Expr\From('Cx\Model\ContentManager\Page', 'p'));
       
        //build a big or with all the node ids and pages 
        $arrExprs = null;
        $fetchedPages = array();
        $pIdx = 0;
        foreach($this->placeholders as $placeholder => $data) {
            if ($data['type'] == 'id') {
                # page is referenced by NODE-ID (i.e.: [[NODE_1]])

                if (isset($fetchedPages[$data['nodeid']][$data['lang']])) {
                    continue;
                }

                $arrExprs[] = $qb->expr()->andx(
                    $qb->expr()->eq('p.node', $data['nodeid']),
                    $qb->expr()->eq('p.lang', $data['lang'])
                );

                $fetchedPages[$data['nodeid']][$data['lang']] = true;
            } else {
                # page is referenced by module (i.e.: [[NODE_SHOP_CART]])

                if (isset($fetchedPages[$data['module']][$data['cmd']][$data['lang']])) {
                    continue;
                }

                $arrExprs[] = $qb->expr()->andx(
                    $qb->expr()->eq('p.type', ':type'),
                    $qb->expr()->eq('p.module', ':module_'.$pIdx),
                    $qb->expr()->eq('p.cmd', ':cmd_'.$pIdx),
                    $qb->expr()->eq('p.lang', $data['lang'])
                );
                $qb->setParameter('module_'.$pIdx, $data['module']);
                $qb->setParameter('cmd_'.$pIdx, empty($data['cmd']) ? null : $data['cmd']);
                $qb->setParameter('type', \Cx\Model\ContentManager\Page::TYPE_APPLICATION);

                $fetchedPages[$data['module']][$data['cmd']][$data['lang']] = true;

                $pIdx++;
            }
        }

        //fetch the nodes if there are any in the query
        if($arrExprs) {
            foreach ($arrExprs as $expr) {
                $qb->orWhere($expr);
            }

            $pages = $qb->getQuery()->getResult();
            foreach($pages as $page) {
                // build placeholder's value -> URL
                $url = \Cx\Core\Routing\URL::fromPage($page);

                $placeholderByApp = '';
                $placeholderById = \Cx\Model\ContentManager\Page::PLACEHOLDER_PREFIX.$page->getNode()->getId();
                $this->placeholders[$placeholderById.'_'.$page->getLang()] = $url;

                if ($page->getType() == \Cx\Model\ContentManager\Page::TYPE_APPLICATION) {
                    $module = $page->getModule();
                    $cmd = $page->getCmd();
                    $placeholderByApp = \Cx\Model\ContentManager\Page::PLACEHOLDER_PREFIX;
                    $placeholderByApp .= strtoupper($module.(empty($cmd) ? '' : '_'.$cmd));
                    $this->placeholders[$placeholderByApp.'_'.$page->getLang()] = $url;
                }

                if ($page->getLang() == FRONTEND_LANG_ID) {
                    $this->placeholders[$placeholderById] = $url;

                    if (!empty($placeholderByApp)) {
                        $this->placeholders[$placeholderByApp] = $url;
                    }
                }
            }
        }

        // there might be some placeholders we were unable to resolve.
        // try to resolve them by using the fallback-language-reverse-lookup
        // methode provided by \Cx\Core\Routing\URL::fromModuleAndCmd().
        foreach($this->placeholders as $placeholder => $data) {
            if (!$data instanceof \Cx\Core\Routing\URL) {
                if (!empty($data['module'])) {
                    $this->placeholders[$placeholder] = \Cx\Core\Routing\URL::fromModuleAndCmd($data['module'], $data['cmd'], $data['lang']);
                } else {
                    $this->placeholders[$placeholder] = \Cx\Core\Routing\URL::fromModuleAndCmd('error', '', $data['lang']);
                }
            }
        }

        $this->fetchingDone = true;
    }

    /**
     * Replaces all variables in the given string
     * @var string $string
     */
    public function replaceIn(&$string) {
        if($this->placeholders === null)
            throw new LinkGeneratorException('Usage: scan(), then fetch(), then replace().');
        if($this->fetchingDone === false)
            throw new LinkGeneratorException('Seems like fetch() was not called before calling replace().');

        foreach($this->placeholders as $placeholder => $link) {
            $string = str_replace('{'.$placeholder.'}', $link, $string);
        }
    }
}

