<?php

/**
 * BackendTable
 *
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      COMVATION Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  core
 */

/**
 * BackendTable
 *
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      COMVATION Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  core
 */
class BackendTable extends HTML_Table {

    public function __construct($attrs = array(), $options = array()) {
        global $_ARRAYLANG;
        
    	if ($attrs instanceof \Cx\Core_Modules\Listing\Model\Entity\DataSet) {
    		$first = true;
    		$row = 1;
            foreach ($attrs as $rowname=>$rows) {
    			$col = 0;
    			foreach ($rows as $header=>$data) {
                    $encode = true;
                    if (
                        isset($options['fields']) &&
                        isset($options['fields'][$header]) &&
                        isset($options['fields'][$header]['showOverview']) &&
                        !$options['fields'][$header]['showOverview']
                    ) {
                        continue;
                    }
                    $origHeader = $header;
    				if ($first) {
                        if (isset($_ARRAYLANG[$header])) {
                            $header = $_ARRAYLANG[$header];
    				}
                        if (
                            is_array($options['functions']) &&
                            isset($options['functions']['sorting']) &&
                            $options['functions']['sorting']
                        ) {
                            $order = '';
                            $img = '&uarr;&darr;';
                            if (isset($_GET['order'])) {
                                $supOrder = explode('/', $_GET['order']);
                                if (current($supOrder) == $origHeader) {
                                    $order = '/DESC';
                                    $img = '&darr;';
                                    if (count($supOrder) > 1 && $supOrder[1] == 'DESC') {
                                        $order = '';
                                        $img = '&uarr;';
                                    }
                                }
                            }
                            $header = '<a href="' .  \Env::get('cx')->getRequest()->getUrl() . '&order=' . $origHeader . $order . '" style="white-space: nowrap;">' . $header . ' ' . $img . '</a>';
                        }
                        $this->setCellContents(0, $col, $header, 'th', 0);
                    }
                    if (
                        isset($options['fields']) &&
                        isset($options['fields'][$origHeader]) &&
                        isset($options['fields'][$origHeader]['table']) &&
                        isset($options['fields'][$origHeader]['table']['parse']) &&
                        is_callable($options['fields'][$origHeader]['table']['parse'])
                    ) {
                        $callback = $options['fields'][$origHeader]['table']['parse'];
                        $data = $callback($data);
                        $encode = false; // todo: this should be set by callback
                    } else if (is_object($data) && get_class($data) == 'DateTime') {
                        $data = $data->format(ASCMS_DATE_FORMAT);
                    } else if (isset($options['fields'][$origHeader]) && isset($options['fields'][$origHeader]['type']) && $options['fields'][$origHeader]['type'] == '\Country') {
                        $data = \Country::getNameById($data);
                        if (empty($data)) {
                            $data = \Country::getNameById(204);
                        }
                    } else if (gettype($data) == 'boolean') {
                        $data = '<i>' . ($data ? 'Yes' : 'No') . '</i>';
                        $encode = false;
                    } else if ($data === null) {
                        $data = '<i>NULL</i>';
                        $encode = false;
                    } else if (empty($data)) {
                        $data = '<i>(empty)</i>';
                        $encode = false;
                    }
                    $this->setCellContents($row, $col, $data, 'TD', 0, $encode);
    				$col++;
                }
                if (is_array($options['functions'])) {
                    if ($first) {
                        $header = 'FUNCTIONS';
                        if (isset($_ARRAYLANG['FUNCTIONS'])) {
                            $header = $_ARRAYLANG['FUNCTIONS'];
                        }
                        $this->setCellContents(0, $col, $header, 'th', 0, true);
                    }
                    if (!isset($options['functions']['baseUrl'])) {
                        $options['functions']['baseUrl'] = clone \Env::get('cx')->getRequest()->getUrl();
                    }
                    $this->setCellContents($row, $col, $this->getFunctionsCode($rowname, $options['functions']), 'TD', 0);
    			}
    			$first = false;
    			$row++;
    		}
    		$attrs = array();
    	}
        parent::__construct(array_merge($attrs, array('class' => 'adminlist')));
    }

    /**
     * Override from parent. Added contrexx_raw2xhtml support
     * @param type $row
     * @param type $col
     * @param type $contents
     * @param type $type
     * @param type $body
     * @param type $encode
     * @return type 
     */
    function setCellContents($row, $col, $contents, $type = 'TD', $body = 0, $encode = false)
    {
        if ($encode) {
            $contents = contrexx_raw2xhtml($contents);
        }
        $ret = $this->_adjustTbodyCount($body, 'setCellContents');
        if (PEAR::isError($ret)) {
            return $ret;
        }
        $ret = $this->_tbodies[$body]->setCellContents($row, $col, $contents, $type);
        if (PEAR::isError($ret)) {
            return $ret;
        }
    }
    
    protected function getFunctionsCode($rowname, $functions) {
        $baseUrl = $functions['baseUrl'];
        $code = '<span class="functions">';
        if (isset($functions['edit']) && $functions['edit']) {
            $editUrl = clone $baseUrl;
            $editUrl->setParam('editid', $rowname);
            $code .= '<a href="' . $editUrl . '" class="edit"></a>';
        }
        if (isset($functions['delete']) && $functions['delete']) {
            $deleteUrl = clone $baseUrl;
            $deleteUrl->setParam('deleteid', $rowname);
            $deleteUrl.='&csrf='.\Cx\Core\Csrf\Controller\ComponentController::code();
            $onclick ='if (confirm(\'Do you really want to delete?\'))'.
                    'window.location.replace(\''.$deleteUrl.'\');';
            $_uri = 'javascript:void(0);';
            $code .= '<a onclick="'.$onclick.'" href="'.$_uri.'" class="delete"></a>';
        }
        return $code . '</span>';
    }

    public function toHtml() {
        $this->altRowAttributes(1, array('class' => 'row1'), array('class' => 'row2'), true);
        return parent::toHtml();
    }

}
