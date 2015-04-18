<?php

/**
 * JSON Adapter for Uploader
 *
 * @copyright   Comvation AG
 * @author      Tobias Schmoker <tobias.schmoker@comvation.com>
 * @package     contrexx
 * @subpackage  core_json
 */

namespace Cx\Core_Modules\MediaBrowser\Controller;

use Cx\Core\ContentManager\Model\Entity\Page;
use Cx\Core\Core\Controller\Cx;
use Cx\Core\Core\Model\Entity\SystemComponentController;
use \Cx\Core\Json\JsonAdapter;
use Cx\Core\Json\JsonData;
use Cx\Core\Routing\NodePlaceholder;
use Cx\Core_Modules\MediaBrowser\Model\Entity\ThumbnailGenerator;
use Cx\Core_Modules\Uploader\Controller\UploaderConfiguration;
use Cx\Lib\FileSystem\FileSystem;

/**
 * JSON Adapter for Uploader
 *
 * @copyright   Comvation AG
 * @author      Tobias Schmoker <tobias.schmoker@comvation.com>
 * @package     contrexx
 * @subpackage  coremodule_mediabrowser
 */
class JsonMediaBrowser extends SystemComponentController implements JsonAdapter
{

    protected $_path = "";
    protected $_mediaType = "";

    /**
     * @var Cx
     */
    protected $cx;

    function __construct()
    {
        $this->cx = Cx::instanciate();
    }

    /**
     * Returns the internal name used as identifier for this adapter
     *
     * @return String Name of this adapter
     */
    public function getName()
    {
        return 'MediaBrowser';
    }

    /**
     * Returns an array of method names accessable from a JSON request
     *
     * @return array List of method names
     */
    public function getAccessableMethods()
    {
        return array('getFiles', 'getSites', 'getSources', 'createThumbnails', 'folderWidget', 'removeFile');
    }

    /**
     * Returns all messages as string
     *
     * @return String HTML encoded error messages
     */
    public function getMessagesAsString()
    {
        return '';
    }

    public function getSources()
    {
        global $_ARRAYLANG, $_CORELANG;
        $mediaBrowser = MediaBrowserConfiguration::getInstance();

        \Env::get('init')->loadLanguageData('MediaBrowser');
        // standard


        \Env::get('init')->loadLanguageData('FileBrowser');
        foreach (
            $mediaBrowser->getMediaTypes() as $type =>
            $name
        ) {
            if (!$this->_checkForModule($type)) {
                continue;
            }
            $return[] = array(
                'name' => $name->getHumanName(),
                'value' => $type,
                'path' => array_values(
                    array_filter(
                        explode(
                            '/', $mediaBrowser->getMediaTypePathsbyNameAndOffset($type,1)
                        )
                    )
                )
            );
        }
        return $return;
    }

    /**
     *
     *
     * @param $params
     *
     * @return array
     */
    public function getFiles($params)
    {
        $this->_path = (strlen($params['get']['path']) > 0)
            ? $params['get']['path'] : '/';
        $this->_mediaType = (strlen($params['get']['mediatype']) > 0)
            ? $params['get']['mediatype'] : 'files';

        /* paramas
          current $path
          current $strPath

         */

        if (array_key_exists(
            $this->_mediaType,
            MediaBrowserConfiguration::getInstance()->getMediaTypePaths()
        )) {
            $strPath = MediaBrowserConfiguration::getInstance(
                )->getMediaTypePaths();
             $strPath=    $strPath[$this->_mediaType][0] . $this->_path;
        } else {
            $strPath = $this->cx->getWebsiteImagesPath() . $this->_path;
        }

        $recursiveIteratorIterator = new \RegexIterator(
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($strPath),
                \RecursiveIteratorIterator::SELF_FIRST
            ), '/^((?!thumb_[a-z]+).)*$/'
        );

        $jsonFileArray = array();

        $thumbnailList = UploaderConfiguration::getInstance()->getThumbnails();

        foreach ($recursiveIteratorIterator as $file) {
            /**
             * @var $file \SplFileInfo
             */
            $extension = 'Dir';
            if (!$file->isDir()) {
                $extension = ucfirst(
                    pathinfo($file->getFilename(), PATHINFO_EXTENSION)
                );
            }
            $filePathinfo = pathinfo($file->getRealPath());

            $fileNamePlain = $filePathinfo['filename'];
            // set preview if image
            $preview = 'none';


            $thumbnails = array();
            if (preg_match("/(jpg|jpeg|gif|png)/i", ucfirst($extension))) {

                foreach (
                    UploaderConfiguration::getInstance()->getThumbnails() as
                    $thumbnail
                ) {
                    $thumbnails[$thumbnail['size']] = preg_replace(
                        '/\.' . lcfirst($extension) . '$/',
                        $thumbnail['value'] . '.' . lcfirst($extension),
                        $this->cx->getWebsiteOffsetPath() . str_replace(
                            $this->cx->getWebsitePath(), '',
                            $file->getRealPath()
                        )
                    );
                }
                $preview = current($thumbnails);
            }

            $fileInfos = array(
                'filepath' => mb_strcut(
                    $file->getPath() . '/' . $file->getFilename(),
                    mb_strlen($this->cx->getWebsitePath())
                ),
                // preselect in mediabrowser or mark a folder
                'name' => $file->getFilename(),
                'size' => $this->formatBytes($file->getSize()),
                'cleansize' => $file->getSize(),
                'extension' => ucfirst(mb_strtolower($extension)),
                'preview' => $preview,
                'active' => false, // preselect in mediabrowser or mark a folder
                'type' => $file->getType(),
                'thumbnail' => $thumbnails
            );

            // filters
            if (
                $fileInfos['name'] == '.'
                || preg_match(
                    '/\.thumb/', $fileInfos['name']
                )
                || $fileInfos['name'] == 'index.php'
                || (0 === strpos($fileInfos['name'], '.'))
            ) {
                continue;
            }

            // filter thumbnail images
            $thumbFilter = false;
            foreach (
                UploaderConfiguration::getInstance()->getThumbnails() as
                $thumbnail
            ) {
                if (false !== strpos(
                        $fileInfos['name'], $thumbnail['value'] . '.'
                    )
                ) {
                    $thumbFilter = true;
                }
            }
            if ($thumbFilter) {
                continue;
            }

            $path = array(
                $file->getFilename() => array('datainfo' => $fileInfos)
            );


            for (
                $depth = $recursiveIteratorIterator->getDepth() - 1;
                $depth >= 0; $depth--
            ) {
                $path = array(
                    $recursiveIteratorIterator->getSubIterator($depth)->current(
                    )->getFilename() => $path
                );
            }
            $jsonFileArray = array_merge_recursive($jsonFileArray, $path);
        }
        return ($jsonFileArray);
    }

    public function getSites()
    {
        $jd = new JsonData();
        $data = $jd->data(
            'node', 'getTree', array('get' => array('recursive' => 'true'))
        );
        $pageStack = array();
        $data['data']['tree'] = array_reverse($data['data']['tree']);
        foreach ($data['data']['tree'] as &$entry) {
            $entry['attr']['level'] = 0;
            array_push($pageStack, $entry);
        }
        $return = array();
        while (count($pageStack)) {
            $entry = array_pop($pageStack);
            $page = $entry['data'][0];
            $arrPage['level'] = $entry['attr']['level'];
            $arrPage['node_id'] = $entry['attr']['rel_id'];
            $children = $entry['children'];
            $children = array_reverse($children);
            foreach ($children as &$entry) {
                $entry['attr']['level'] = $arrPage['level'] + 1;
                array_push($pageStack, $entry);
            }
            $arrPage['catname'] = $page['title'];
            $arrPage['catid'] = $page['attr']['id'];
            $arrPage['lang'] = BACKEND_LANG_ID;
            $arrPage['protected'] = $page['attr']['protected'];
            $arrPage['type'] = Page::TYPE_CONTENT;
            $arrPage['alias'] = $page['title'];
            $arrPage['frontend_access_id']
                = $page['attr']['frontend_access_id'];
            $arrPage['backend_access_id'] = $page['attr']['backend_access_id'];
            $jsondata = json_decode($page['attr']['data-href']);
            $path = $jsondata->path;
            if (trim($jsondata->module) != '') {
                $arrPage['type'] = Page::TYPE_APPLICATION;
                $module = explode(' ', $jsondata->module, 2);
                $arrPage['modulename'] = $module[0];
                if (count($module) > 1) {
                    $arrPage['cmd'] = $module[1];
                }
            }

            $url = '[[' . NodePlaceholder::PLACEHOLDER_PREFIX;

// TODO: This only works for regular application pages. Pages of type fallback that are linked to an application
//       will be parsed using their node-id ({NODE_<ID>})
            if (($arrPage['type'] == Page::TYPE_APPLICATION)
                && ($this->_mediaType !== 'alias')
            ) {
                $url .= $arrPage['modulename'];
                if (!empty($arrPage['cmd'])) {
                    $url .= '_' . $arrPage['cmd'];
                }

                $url = strtoupper($url);
            } else {
                $url .= $arrPage['node_id'];
            }


            $url .= "]]";

            $return[] = array(
                'click' =>
                    "javascript:{setUrl('$url',null,null,'"
                    . \FWLanguage::getLanguageCodeById(
                        BACKEND_LANG_ID
                    )
                    . $path . "','page')}",
                'name' => $arrPage['catname'],
                'extension' => 'Html',
                'level' => $arrPage['level'],
                'url' => $path,
                'node' => $url
            );
        }
        return $return;
    }

    protected function formatBytes($bytes, $unit = "", $decimals = 2)
    {
        $units = array(
            'B' => 0, 'KB' => 1, 'MB' => 2, 'GB' => 3, 'TB' => 4,
            'PB' => 5, 'EB' => 6, 'ZB' => 7, 'YB' => 8
        );

        $value = 0;
        if ($bytes > 0) {
            // Generate automatic prefix by bytes 
            // If wrong prefix given
            if (!array_key_exists($unit, $units)) {
                $pow = floor(log($bytes) / log(1024));
                $unit = array_search($pow, $units);
            }

            // Calculate byte value by prefix
            $value = ($bytes / pow(1024, floor($units[$unit])));
        }

        // If decimals is not numeric or decimals is less than 0 
        // then set default value
        if (!is_numeric($decimals) || $decimals < 0) {
            $decimals = 2;
        }

        // Format output
        return sprintf('%.' . $decimals . 'f ' . $unit, $value);
    }

    public function createThumbnails($params)
    {
        if (isset($params['get']['file'])) {
            ThumbnailGenerator::createThumbnailFromPath($params['get']['file']);
            return true;
        }
        return false;
    }

    /**
     * checks whether a module is available and active
     *
     * @param $strModuleName
     *
     * @return bool
     */
    function _checkForModule($strModuleName)
    {
        global $objDatabase;
        /**
         * @var $objRS \ADORecordSet
         */
        if (($objRS = $objDatabase->SelectLimit(
                "SELECT `status` FROM " . DBPREFIX . "modules WHERE NAME = '"
                . $strModuleName
                . "' AND `is_active` = '1' AND `is_licensed` = '1'", 1
            )) != false
        ) {
            if ($objRS->RecordCount() > 0) {
                if ($objRS->fields['status'] == 'n') {
                    return false;
                }
                return true;
            }
            return true;
        }
        return true;
    }

    /**
     * Returns default permission as object
     *
     * @return Object
     */
    public function getDefaultPermissions()
    {
        // TODO: Implement getDefaultPermissions() method.
    }

    /**
     * Folder widget
     * 
     * @param array $params
     * 
     * @return boolean|array
     */
    public function folderWidget($params)
    {
        \cmsSession::getInstance();
        
        $folderWidgetId = isset($params['get']['id']) ? $params['get']['id'] : 0;
        if (   empty($folderWidgetId)
            || !isset($_SESSION['MediaBrowser'])
            || !isset($_SESSION['MediaBrowser']['FolderWidget'])
            || !isset($_SESSION['MediaBrowser']['FolderWidget'][$folderWidgetId])
        ) {
            return false;
        }
        
        $folder = $_SESSION['MediaBrowser']['FolderWidget'][$folderWidgetId]['folder'];
        
        $arrFileNames = array();
        if(!file_exists($folder)) {
            return false;
        }
        $h = opendir($folder);
        while(false !== ($f = readdir($h))) {
            // skip folders and thumbnails
            if($f == '.' || $f == '..' || preg_match("/(?:\.(?:thumb_thumbnail|thumb_medium|thumb_large)\.[^.]+$)|(?:\.thumb)$/i", $f))
                continue;
            if (!is_dir($folder .'/' . $f)) {                
                array_push($arrFileNames, $f);
            }
        }
        closedir($h);
        
        return $arrFileNames;
    }

    /**
     * Remove the file from Folder widget
     * 
     * @param array $params
     */
    public function removeFile($params)
    {
        \cmsSession::getInstance();
        
        $folderWidgetId = isset($params['get']['widget']) ? $params['get']['widget'] : 0;
        $fileName       = isset($params['get']['file']) ? $params['get']['file'] : '';
        if (   empty($folderWidgetId)
            || empty($fileName)
            || !isset($_SESSION['MediaBrowser'])
            || !isset($_SESSION['MediaBrowser']['FolderWidget'])
            || !isset($_SESSION['MediaBrowser']['FolderWidget'][$folderWidgetId])
            || $_SESSION['MediaBrowser']['FolderWidget'][$folderWidgetId]['mode'] == \Cx\Core_Modules\MediaBrowser\Model\Entity\FolderWidget::MODE_VIEW_ONLY
        ) {
            return false;
        }
        
        $folder = $_SESSION['MediaBrowser']['FolderWidget'][$folderWidgetId]['folder'];
        
        if(!file_exists($folder .'/'. $fileName)) {
            return false;
        }
        
        try {
            $objFile = new \Cx\Lib\FileSystem\File($folder .'/'. $fileName);
            $objFile->delete();
            return array();
        } catch (\Cx\Lib\FileSystem\FileSystemException $e) {
            \DBG::msg($e->getMessage());
        }
        
        return false;
    }
}
