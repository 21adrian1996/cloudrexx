<?php

/**
 * Class ComponentController
 *
 * @copyright   CONTREXX CMS - Comvation AG Thun
 * @author      Tobias Schmoker <tobias.schmoker@comvation.com>
 * @package     contrexx
 * @subpackage  coremodule_mediabrowser
 * @version     1.0.0
 */

namespace Cx\Core_Modules\MediaBrowser\Controller;

// don't load Frontend and BackendController for this core_module
use Cx\Core\ContentManager\Model\Entity\Page;
use Cx\Core\Core\Controller\Cx;
use Cx\Core\Core\Model\Entity\SystemComponent;
use Cx\Core\Core\Model\Entity\SystemComponentController;
use Cx\Core\Html\Sigma;
use Cx\Core_Modules\MediaBrowser\Model\Event\MediaBrowserEventListener;
use Cx\Core_Modules\MediaBrowser\Model\MediaBrowser;
use Cx\Core_Modules\Uploader\Controller\UploaderConfiguration;
use Cx\Lib\FileSystem\FileSystemException;

class ComponentController extends
    SystemComponentController
{

    protected $mediaBrowserInstances = array();

    public function getControllerClasses() {
        // Return an empty array here to let the component handler know that there
        // does not exist a backend, nor a frontend controller of this component.
        return array('Backend');
    }

    public function addMediaBrowser(MediaBrowser $mediaBrowser) {
        $this->mediaBrowserInstances[] = $mediaBrowser;
    }


    public function getControllersAccessableByJson() {
        return array(
            'JsonMediaBrowser',
        );
    }

    public function preContentLoad(Page $page) {
        $eventHandlerInstance = $this->cx->getEvents();
        $eventHandlerInstance->addEvent('LoadMediaTypes');
    }

    public function preContentParse(Page $page) {
        $this->cx->getEvents()->addEventListener(
            'LoadMediaTypes', new MediaBrowserEventListener($this->cx)
        );
    }

    /**
     * @param Sigma $template
     */
    public function preFinalize(Sigma $template) {
        if (count($this->mediaBrowserInstances) == 0) {
            return;
        }
        else {
            global $_ARRAYLANG;
            /**
             * @var $init \InitCMS
             */
            $init = \Env::get('init');
            $init->loadLanguageData('MediaBrowser');
            foreach ($_ARRAYLANG as $key => $value) {
                if (preg_match("/TXT_FILEBROWSER_[A-Za-z0-9]+/", $key)) {
                    \ContrexxJavascript::getInstance()->setVariable(
                        $key, $value, 'mediabrowser'
                    );
                }
            }

            $thumbnailsTemplate = new Sigma();
            $thumbnailsTemplate->loadTemplateFile(
                $this->cx->getCoreModuleFolderName()
                . '/MediaBrowser/View/Template/Thumbnails.html'
            );
            $thumbnailsTemplate->setVariable(
                'TXT_FILEBROWSER_THUMBNAIL_ORIGINAL_SIZE', sprintf(
                    $_ARRAYLANG['TXT_FILEBROWSER_THUMBNAIL_ORIGINAL_SIZE']
                )
            );
            foreach (
                UploaderConfiguration::getInstance()->getThumbnails() as
                $thumbnail
            ) {
                $thumbnailsTemplate->setVariable(
                    array(
                        'THUMBNAIL_NAME' => sprintf(
                            $_ARRAYLANG[
                            'TXT_FILEBROWSER_THUMBNAIL_' . strtoupper(
                                $thumbnail['name']
                            ) . '_SIZE'], $thumbnail['size']
                        ),
                        'THUMBNAIL_ID' => $thumbnail['id'],
                        'THUMBNAIL_SIZE' => $thumbnail['size']
                    )
                );
                $thumbnailsTemplate->parse('thumbnails');
            }

            \ContrexxJavascript::getInstance()->setVariable(
                'thumbnails_template', $thumbnailsTemplate->get(),
                'mediabrowser'
            );

            \JS::activate('mediabrowser');
            \JS::registerJS('core_modules/MediaBrowser/View/Script/mediabrowser.js');
        }
    }


}
