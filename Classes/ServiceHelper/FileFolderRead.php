<?php
namespace DMK\Mkdam2fal\ServiceHelper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 DMK E-BUSINESS GmbH (dev@dmk-ebusiness.de)
 *  (c) 2013 Daniel Hasse - websedit AG <extensions@websedit.de>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 *
 *
 * @package mkdam2fal
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 *
 */
class FileFolderRead {

	/**
     * function to write a log and save it in a file
	 *
	 * @param string $chosenExtension
	 * @param array $errorMessageArray
	 * @param string $logname
     * @return string
     */
	public function writeLog($chosenExtension, $errorMessageArray, $logname = '') {

		$folderpath = PATH_site . 'typo3temp/mkdam2fal/logs/';

		if ($logname) {
			$filename = $folderpath . $logname . '.txt';
		} else {
			$filename = $folderpath . 'log_' . $chosenExtension . '_' . date('Y-m-d-H_i_s') . '.txt';
		}

		if (!$handle = fopen($filename, 'a')) {
			// echo 'no handle';
			exit;
		}

		foreach ($errorMessageArray as $content) {
			$actualDate = date('H:i:s Y-m-d') . ';';
			fwrite($handle, $actualDate);
			foreach ($content as $contentInner) {
				$contentInner = $contentInner . ';';
				if (!fwrite($handle, $contentInner)) {
					exit;
				}
			}
			fwrite($handle, "\r\n");
		}

		fclose($handle);

		return $filename;
	}

	/**
	 * function to read a folder
	 *
	 * @param string $path
	 * @param string $filter filter if filename contains filter string
	 * @return array
	 */
	public function getFolderFilenames($path, $filter = '') {
		$arr = array();
		if ($handle = opendir($path)) {
			$counter=0;
			while (false !== ($file = readdir($handle))) {
				if ($file != '.' && $file != '..' && $file[0] != '.') {
					if (!empty($filter)) {
						if (strpos($file, $filter) === false) {
							continue;
						}
					}
					$arr[$counter] = $file;
					$counter++;
				}
			}
			closedir($handle);
		}
		return $arr;
	}

}
