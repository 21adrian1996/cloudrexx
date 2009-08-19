<?php

/**
 * CSV Library Class
 *
 * Class which handles cvs files
 *
 * @copyright     CONTREXX CMS - 2005 COMVATION AG
 * @author        Comvation Development Team <info@comvation.com>
 * @version       v1.0.0
 */
class CsvLib
{
	var $firstAreNames = true;
	var $separator = ";";
	var $enclosure = "\"";

	/**
	 * Constructor
	 *
	 * Gets the options
	 */
	function __construct()
	{
		$this->separator = contrexx_stripslashes($_POST['import_options_csv_separator']);
		if ($this->separator == '\t') {
			$this->separator = "\t";
		}

		if (strlen($_POST['import_options']) == 1) {
			$this->enclosure = $_POST['import_options_csv_enclosure'];
		}
	}

	/**
	 * PHP 4 Constructor
	 *
	 * @return CsvLib
	 */
	function CsvLib()
	{
		$this->__construct();
	}


	/**
	 * Returns the content of a csv file
	 *
	 * @param string $file
	 * @param bool $limit Limit
	 * @return array
	 *
	 * Array
		(
		    [fieldnames] => Array
		        (
		            [0] => Name
		            [1] => Vorname
		            [2] => test
		        )

		    [data] => Array
		        (
		            [0] => Array
		                (
		                    [0] => Wert1
		                    [1] => Wert1.2
		                    [2] => Wert1.3
		                )

		        )

		)
	 */
	function parse($file, $looplimit=-1)
	{

		// detect newlines correctly. bit slower, but in exchange
		// we can import old apple CSV files.
		ini_set('auto_detect_line_endings', 1);


		$handle = fopen($file, "r");

		if ($handle) {
			$firstline = true;

			// Get the longest line
			$limit = $looplimit;
			$len = 0;
			while (!feof($handle) && $limit != 0) {
				$length = strlen(fgets($handle));
				$len = ($length > $len) ? $length : $len;
				$limit--;
			}

			// Set the pointer back to 0
			fseek($handle, 0);

			$limit = $looplimit;
			while (($data = fgetcsv($handle, $len, $this->separator, $this->enclosure)) && $limit != 0) {
				if (!empty($data[0]) || $looplimit == 1) {
					if ($firstline && $this->firstAreNames) {
						foreach ($data as $index => $field) {
							if(empty($field)){
								$field = "emptyField_$index";
							}
							$retdata['fieldnames'][] = $field;
						}
						$firstline = false;
					} else {
						$retdata['data'][] = $data;
					}
				}
				$limit--;
			}

			fclose($handle);
			return $retdata;
		}
	}
}

?>
