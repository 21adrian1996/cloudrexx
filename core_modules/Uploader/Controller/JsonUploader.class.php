<?php

/**
 * JSON Adapter for Uploader
 *
 * @copyright   Comvation AG
 * @author      Tobias Schmoker <tobias.schmoker@comvation.com>
 * @package     contrexx
 * @subpackage  core_json
 */

namespace Cx\Core_Modules\Uploader\Controller;

use Cx\Core\Core\Controller\Cx;
use Cx\Core\Core\Model\Entity\SystemComponent;
use Cx\Core\Core\Model\Entity\SystemComponentController;
use \Cx\Core\Json\JsonAdapter;
use Cx\Core\Model\RecursiveArrayAccess;
use Cx\Core_Modules\MediaBrowser\Controller\MediaBrowserConfiguration;
use Cx\Core_Modules\MediaBrowser\Model\MoveFileException;
use Cx\Core_Modules\MediaBrowser\Model\RemoveDirectoryException;
use Cx\Core_Modules\MediaBrowser\Model\RemoveFileException;
use Cx\Lib\FileSystem\FileSystem;

/**
 * JSON Adapter for Uploader
 *
 * @copyright   CONTREXX CMS - Comvation AG Thun
 * @author      Tobias Schmoker <tobias.schmoker@comvation.com>
 * @package     contrexx
 * @subpackage  coremodule_uploader
 */
class JsonUploader extends SystemComponentController implements JsonAdapter
{
    protected $message = '';

    /**
     * @var Cx
     */
    protected $cx;

    function __construct(SystemComponent $systemComponent, Cx $cx)
    {
        parent::__construct($systemComponent, $cx);
        $this->cx = Cx::instanciate();
    }


    /**
     * Returns the internal name used as identifier for this adapter
     *
     * @return String Name of this adapter
     */
    public function getName()
    {
        return 'Uploader';
    }

    /**
     * Returns an array of method names accessable from a JSON request
     *
     * @return array List of method names
     */
    public function getAccessableMethods()
    {
        return array('upload', 'createDir', 'renameFile', 'removeFile');
    }

    /**
     * Returns all messages as string
     *
     * @return String HTML encoded error messages
     */
    public function getMessagesAsString()
    {
        return $this->message;
    }

    public function upload($params)
    {
        global $_ARRAYLANG;

        if (isset($params['get']['id'])
            && is_int(
                intval($params['get']['id'])
            )
        ) {
            $id = intval($params['get']['id']);
            $uploadedFileCount = isset($params['get']['uploadedFileCount']) ? intval($params['get']['uploadedFileCount']) : 0;
            $path = $_SESSION->getTempPath() . '/'.$id.'/';
            $tmpPath = $path;
        } elseif (isset($params['post']['path'])) {
            $path_part = explode("/", $params['post']['path'], 2);
            $mediaBrowserConfiguration
                = MediaBrowserConfiguration::getInstance(
            );
            $path = $mediaBrowserConfiguration->getMediaTypePathsbyNameAndOffset($path_part[0],0)
                . '/' . $path_part[1];

            $tmpPath = $_SESSION->getTempPath();

        } else {
            return array(
                'OK' => 0,
                'error' => array(
                    'message' => 'No id specified'
                )
            );
        }

        $uploader = UploaderController::handleRequest(
            array(
                'allow_extensions' => 'jpg,jpeg,png,pdf,gif,mkv,zip,',
                'target_dir' => $path,
                'tmp_dir' => $tmpPath
            )
        );

        $fileLocation = array(
            $uploader['path'],
            str_replace($this->cx->getWebsitePath(), '', $uploader['path'])
        );


        if (isset($_SESSION['uploader']['handlers'][$id]['callback'])) {

            /**
             * @var $callback RecursiveArrayAccess
             * @var $data RecursiveArrayAccess
             */
            $callback = $_SESSION['uploader']['handlers'][$id]['callback'];
            $data = $_SESSION['uploader']['handlers'][$id]['data'];

            if (   isset($_SESSION['uploader']['handlers'][$id]['config']['upload-limit'])
                && $_SESSION['uploader']['handlers'][$id]['config']['upload-limit'] <= $uploadedFileCount
                ) {
                return array('status' => 'error', 'message' => $_ARRAYLANG['TXT_CORE_MODULE_UPLOADER_MAX_LIMIT_REACHED']);
            }

            if (!is_string($callback)) {
                $callback = $callback->toArray();
            }

            if ($data){
                $data = $data->toArray();
            }

            $filePath = dirname( $uploader['path']);

            if (!is_array($callback)) {
                $class = new \ReflectionClass($callback);
                if ($class->implementsInterface(
                    '\Cx\Core_Modules\Uploader\Model\UploadCallbackInterface'
                )
                ) {
                    /**
                     * @var \Cx\Core_Modules\Uploader\Model\UploadCallbackInterface $callbackInstance
                     */
                    $callbackInstance = $class->newInstance($this->cx);
                    $fileLocation = $callbackInstance->uploadFinished(
                        $filePath, str_replace(
                            $this->cx->getWebsiteTempPath(), $this->cx->getWebsiteTempWebPath(),
                            $filePath
                        ), $data,
                        $id,
                        $uploader
                    );
                }
            } else {
                $fileLocation = call_user_func(
                    array($callback[1], $callback[2]), $filePath,
                    str_replace(
                        $this->cx->getWebsiteTempPath(), $this->cx->getWebsiteTempWebPath(), $filePath
                    ), $data, $id, $uploader, null
                );
            }
            \Cx\Lib\FileSystem\FileSystem::move(
                $uploader['path'], $fileLocation[0] . '/' . $uploader['name'],
                true
            );
            \Cx\Lib\FileSystem\FileSystem::delete_file($uploader['path']);

            $fileLocation = array(
                $fileLocation[0] . '/' . $uploader['name'],
                $fileLocation[1] . '/' . $uploader['name']
            );
        }
        
        if (isset($uploader['error'])) {
            throw new UploaderException(UploaderController::getErrorCode());
        } else {
            return array(
                'OK' => 1,
                'file' => $fileLocation
            );
        }
    }

    public function createDir($params)
    {
        global $_ARRAYLANG;

        \Env::get('init')->loadLanguageData('MediaBrowser');
        $mediaBrowserConfiguration
            = \Cx\Core_Modules\MediaBrowser\Controller\MediaBrowserConfiguration::getInstance(
        );
        $mediaBrowserConfiguration->getMediaTypes();
        $pathArray = explode('/', $params['get']['path']);
        $strPath = MediaBrowserConfiguration::getInstance(
        )->getMediaTypePathsbyNameAndOffset(array_shift($pathArray),0);


        $strPath .= '/' . join('/', $pathArray);
        $dir = $params['post']['dir'] . '/';

        if (preg_match('#^[0-9a-zA-Z_\-\/]+$#', $dir)) {
            if (!FileSystem::make_folder($strPath . '/' . $dir)) {
                $this->setMessage(
                    sprintf(
                        $_ARRAYLANG['TXT_FILEBROWSER_UNABLE_TO_CREATE_FOLDER'],
                        $dir
                    )
                );
                return;
            } else {
                $this->setMessage(
                    sprintf(
                        $_ARRAYLANG['TXT_FILEBROWSER_DIRECTORY_SUCCESSFULLY_CREATED'],
                        $dir
                    )
                );
                return;
            }
        } else {
            if (!empty($dir)) {
                // error: TXT_FILEBROWSER_INVALID_CHARACTERS
                $this->setMessage(
                    $_ARRAYLANG['TXT_FILEBROWSER_INVALID_CHARACTERS']
                );
                return;
            }
        }

        $this->setMessage(
            sprintf(
                $_ARRAYLANG['TXT_FILEBROWSER_UNABLE_TO_CREATE_FOLDER'], $dir
            )
        );
        return;
    }

    /**
     * @param $params
     */
    public function renameFile($params)
    {
        global $_ARRAYLANG;
        $fileArray = explode('.', $params['post']['oldName']);
        $fileExtension = '';
        if (count($fileArray) != 1) {
            $fileExtension = end($fileArray);
        }
        \Env::get('init')->loadLanguageData('MediaBrowser');
        $strPath = MediaBrowserConfiguration::getInstance(
        )->getMediaTypePathsbyNameAndOffset(rtrim($params['get']['path'], "/"),0);

        $fileDot = '.';
        if (is_dir($strPath . '/' . $params['post']['oldName'])) {
            $fileDot = '';
        }

        try {
            \Cx\Core_Modules\MediaBrowser\Model\FileSystem::moveFile(
                $strPath . '/',
                $strPath . '/',
                $params['post']['oldName'],
                $params['post']['newName'],
                false
            );
        } catch (MoveFileException $e) {
            $this->setMessage(
                sprintf(
                    $_ARRAYLANG['TXT_FILEBROWSER_FILE_UNSUCCESSFULLY_RENAMED'],
                    $params['post']['oldName']
                )
            );
            return;
        }
        $this->setMessage(
            sprintf(
                $_ARRAYLANG['TXT_FILEBROWSER_FILE_SUCCESSFULLY_RENAMED'],
                $params['post']['oldName']
            )
        );
    }

    /**
     * @param $params
     */
    public function removeFile($params)
    {
        global $_ARRAYLANG;

        \Env::get('init')->loadLanguageData('MediaBrowser');
        $strPath = \Cx\Core_Modules\MediaBrowser\Model\FileSystem::getAbsolutePath($params['get']['path']);

        if (!empty($params['post']['file']['datainfo']['name'])
            && !empty($strPath)
        ) {
            if (is_dir(
                $strPath . '/' . $params['post']['file']['datainfo']['name']
            )) {
                try {
                    \Cx\Core_Modules\MediaBrowser\Model\FileSystem::removeDirectory(
                        $strPath, $params['post']['file']['datainfo']['name']
                    );
                    $this->setMessage(
                        sprintf(
                            $_ARRAYLANG['TXT_FILEBROWSER_DIRECTORY_SUCCESSFULLY_REMOVED'],
                            $params['post']['file']['datainfo']['name']
                        )
                    );
                } catch (RemoveDirectoryException $e) {
                    $this->setMessage(
                        sprintf(
                            $_ARRAYLANG['TXT_FILEBROWSER_DIRECTORY_UNSUCCESSFULLY_REMOVED'],
                            $params['post']['file']['datainfo']['name']
                        )
                    );
                }
                return;
            } else {
                try {
                    \Cx\Core_Modules\MediaBrowser\Model\FileSystem::removeFile(
                        $strPath, $params['post']['file']['datainfo']['name']
                    );
                    $this->setMessage(
                        sprintf(
                            $_ARRAYLANG['TXT_FILEBROWSER_FILE_SUCCESSFULLY_REMOVED'],
                            $params['post']['file']['datainfo']['name']
                        )
                    );
                } catch (RemoveFileException $e) {
                    $this->setMessage(
                        sprintf(
                            $_ARRAYLANG['TXT_FILEBROWSER_FILE_UNSUCCESSFULLY_REMOVED'],
                            $params['post']['file']['datainfo']['name']
                        )
                    );
                }
                return;
            }
        }
        $this->setMessage(
            sprintf(
                $_ARRAYLANG['TXT_FILEBROWSER_FILE_UNSUCCESSFULLY_REMOVED'],
                $params['post']['file']['datainfo']['name']
            )
        );
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
     * @param mixed $message
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }


}
