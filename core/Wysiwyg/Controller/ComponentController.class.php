<?php
/**
 * @copyright   Comvation AG
 * @author      Sebastian Brand <sebastian.brand@comvation.com>
 * @package     contrexx
 * @subpackage  core_wysiwyg
 */

namespace Cx\Core\Wysiwyg\Controller;

use Cx\Core\Wysiwyg\Model\Event\WysiwygEventListener;

class ComponentController extends \Cx\Core\Core\Model\Entity\SystemComponentController implements \Cx\Core\Event\Model\Entity\EventListener {
    
    public function __construct(\Cx\Core\Core\Model\Entity\SystemComponent $systemComponent, \Cx\Core\Core\Controller\Cx $cx) {
        parent::__construct($systemComponent, $cx);
    }

    public function postResolve(\Cx\Core\ContentManager\Model\Entity\Page $page) {
        $evm = \Cx\Core\Core\Controller\Cx::instanciate()->getEvents();
        $evm->addEventListener('wysiwygCssReload', $this);
    }
    
    /**
     * This function controlls the events from the eventListener
     */
    public function onEvent($eventName, array $eventArgs) {
        switch ($eventName) {
            case 'wysiwygCssReload':
                $skinId = $eventArgs[0]['skin'];
                $result = $eventArgs[1];
                
                foreach ($this->getCustomCSSVariables($skinId) as $key => $val) {
                    $result[$key] = $val;
                }
                break;
            default:
                break;
        }
    }
    
    public function getControllerClasses() {
        return array('Backend');
    }
    
    /**
     * find all wysiwyg templates and retrun it in the correct format for the ckeditor
     */
    public function getWysiwygTempaltes() {
        $em = $this->cx->getDb()->getEntityManager();
        $repo = $em->getRepository('Cx\Core\Wysiwyg\Model\Entity\WysiwygTemplate');
        $allWysiwyg = $repo->findBy(array('active'=>'1'));
        $containerArr = array();
        foreach ($allWysiwyg as $wysiwyg) {
            $containerArr[] = array(
                'title' => $wysiwyg->getTitle(),
                'image' => $wysiwyg->getImagePath(),
                'description' => $wysiwyg->getDescription(),
                'html' => $wysiwyg->getHtmlContent(),
            );
        }
        
        return json_encode($containerArr);
    }
    
    
    /**
     * find all custom css variables and return an array with the values
     */
    public function getCustomCSSVariables($skinId) {
        $themeRepo = new \Cx\Core\View\Model\Repository\ThemeRepository();
        $skin = $themeRepo->getDefaultTheme()->getFoldername();
        $componentData = $themeRepo->getDefaultTheme()->getComponentData();
        \Cx\Core\Setting\Controller\Setting::init('Wysiwyg', 'config', 'Yaml');
        //0 is default theme so you dont must change the themefolder
        if(\Cx\Core\Setting\Controller\Setting::getValue('specificStylesheet','Wysiwyg') && !empty($skinId) && $skinId>0){
            $skin = $themeRepo->findById($skinId)->getFoldername();
            $componentData = $themeRepo->findById($skinId)->getComponentData();
        }
        //getThemeFileContent
        $filePath = $skin.'/index.html';
        $content = '';

        if (file_exists($this->cx->getWebsiteThemesPath().'/'.$filePath)) {
            $content = file_get_contents($this->cx->getWebsiteThemesPath().'/'.$filePath);
        } elseif (file_exists($this->cx->getCodeBaseThemesPath().'/'.$filePath)) {
            $content = file_get_contents($this->cx->getCodeBaseThemesPath().'/'.$filePath);
        }

        $cssArr = \JS::findCSS($content);

        $ymlOption = array();
        if(!empty($componentData['rendering']['wysiwyg'])){
            $ymlOption = $componentData['rendering']['wysiwyg'];
        }

        if(!empty($ymlOption['css'])){
            $cssArr[] = ltrim($this->cx->getWebsiteThemesWebPath().'/'.$skin.'/','/').$ymlOption['css'];
        }

        return array(
            'wysiwygCss' => $cssArr,
            'bodyClass' => !empty($ymlOption['bodyClass'])?$ymlOption['bodyClass']:'',
            'bodyId' => !empty($ymlOption['bodyId'])?$ymlOption['bodyId']:'',
        );
    }

    public function preContentParse(\Cx\Core\ContentManager\Model\Entity\Page $page) {
        $eventListener = new WysiwygEventListener($this->cx);
        $this->cx->getEvents()->addEventListener('mediasource.load', $eventListener);
    }
}
