<?php

/**
 * Data Set
 *
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      COMVATION Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  core_module_listing
 */

namespace Cx\Core_Modules\Listing\Model\Entity;

/**
 * Data Set Exception
 *
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      COMVATION Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  core_module_listing
 */

class DataSetException extends \Exception {}

/**
 * Data Set
 *
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      COMVATION Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  core_module_listing
 */

class DataSet implements \Iterator {
    protected static $yamlInterface = null;
    protected $data = array();

    public function __construct($data, callable $converter = null) {
        if (is_callable($converter)) {
            $this->data = $converter($data);
        } else {
            $this->data = $this->convert($data);
        }
    }

    protected function convert($data) {
        $convertedData = array();
        if (is_array($data)) {
            foreach ($data as $key=>$value) {
                if (is_array($value)) {
                    foreach ($value as $attribute=>$property) {
                        $convertedData[$key][$attribute] = $property;
                    }
                } else if (is_object($value)) {
                    $convertedData[$key] = $this->convertObject($value);
                } else {
                    throw new DataSetException('Supplied argument could not be converted to DataSet');
                }
            }
        } else if (is_object($data)) {
            $convertedData[0] = $this->convertObject($data);
        } else {
            throw new DataSetException('Supplied argument could not be converted to DataSet');
        }
        return $convertedData;
    }

    protected function convertObject($object) {
        $data = array();
        if ($object instanceof \Cx\Model\Base\EntityBase) {
            $em = \Env::get('em');
            foreach ($em->getClassMetadata(get_class($object))->getColumnNames() as $field) {
                $value = $em->getClassMetadata(get_class($object))->getFieldValue($object, $field);
                if ($value instanceof \DateTime) {
                    $value = $value->format('d.M.Y H:i:s');
                }
                $data[$field] = $value;
            }
            return $data;
        }
        foreach ($object as $attribute=>$property) {
            $data[$attribute] = $property;
        }
        return $data;
    }

    protected static function getYamlInterface() {
        if (self::$yamlInterface) {
            self::$yamlInterface = new \Cx\Core_Modules\Listing\Model\YamlInterface();
        }
        return self::$yamlInterface;
    }

    public function toYaml() {
        return $this->export(self::getYamlInterface());
    }

    public static function import(Cx\Core_Modules\Listing\Model\Importable $importInterface, $content) {
        return new static($importInterface->import($content));
    }

    /**
     *
     * @param Cx\Core_Modules\Listing\Model\ImportInterface $importInterface
     * @param type $filename
     * @throws \Cx\Lib\FileSystem\FileSystemException
     * @return type 
     */
    public static function importFromFile(Cx\Core_Modules\Listing\Model\Importable $importInterface, $filename) {
        $objFile = new \Cx\Lib\FileSystem\File($filename);
        return self::import($importInterface, $objFile->getData());
    }

    public function export(Cx\Core_Modules\Listing\Model\Exportable $exportInterface) {
        return $exportInterface->export($this);
    }

    /**
     *
     * @param Cx\Core_Modules\Listing\Model\ExportInterface $exportInterface
     * @param type $filename 
     * @throws \Cx\Lib\FileSystem\FileSystemException
     */
    public function exportToFile(Cx\Core_Modules\Listing\Model\Exportable $exportInterface, $filename) {
        $objFile = new \Cx\Lib\FileSystem\File($filename);
        $objFile->write($this->export($exportInterface));
    }

    /**
     *
     * @param type $filename 
     * @throws \Cx\Lib\FileSystem\FileSystemException
     */
    public function save($filename) {
        $this->exportToFile($this->getYamlInterface(), $filename);
    }

    public static function fromYaml($data) {
        return self::import(self::getYamlInterface(), $data);
    }

    /**
     *
     * @param type $filename
     * @throws \Cx\Lib\FileSystem\FileSystemException
     * @return type 
     */
    public static function load($filename) {
        return self::importFromFile(self::getYamlInterface(), $filename);
    }

    public function current() {
        return current($this->data);
    }

    public function next() {
        return next($this->data);
    }

    public function key() {
        return key($this->data);
    }

    public function valid() {
        return current($this->data);
    }

    public function rewind() {
        return reset($this->data);
    }
}
