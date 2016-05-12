<?php
/**
 *  Copyright notice
 *
 *  (c) 2016 DMK E-Business GmbH <dev@dmk-ebusiness.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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
 */

namespace DMK\Mkdam2fal\Utility;


class StringUtility
{
    /**
     * search uid in xml str and add new one
     * <value index="vDEF">1,2,3</value> -> <value index="vDEF">4,1,2,3</value>
     *
     * @param $string
     * @param $existingUid
     * @param $newUid
     * @return mixed
     */
    public static function addUidStr($string, $existingUid, $newUid) {
        $result = preg_replace('/([,>])(' . $existingUid . ')([,<])/i', "\${1}$newUid,\${2}\${3}", $string, 1);

        return $result;
    }
}
