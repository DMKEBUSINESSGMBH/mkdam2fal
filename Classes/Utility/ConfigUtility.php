<?php
namespace DMK\Mkdam2fal\Utility;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 DMK E-BUSINESS GmbH (dev@dmk-ebusiness.de)
 *  (c) 2014 Daniel Hasse - websedit AG <extensions@websedit.de>
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
 * DamfalfileController
 */
class ConfigUtility {

	/**
	 *
	 * @param string $cfgKey
	 * @return mixed
	 */
	private static function getExtensionCfgValue($cfgKey) {
		$extConfig = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['mkdam2fal']);
		return (is_array($extConfig) && array_key_exists($cfgKey, $extConfig)) ? $extConfig[$cfgKey] : NULL;
	}

	/**
	 * limit for migrations for run
	 *
	 * @return number
	 */
	public static function getDefaultLimit() {
		$limit = (int) self::getExtensionCfgValue('defaultLimit');
		return $limit ? $limit : 5000;
	}

}