<?php

namespace DMK\Mkdam2fal\ServiceHelper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 DMK E-BUSINESS GmbH (dev@dmk-ebusiness.de)
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

class FileLogger
{
    private $filename;
    private $handle;
    private $writeable = false;

    public function __construct($filename, $unique = false)
    {
        $folderpath = PATH_site . 'typo3temp/mkdam2fal/logs/';
        $this->filename = $folderpath . 'log_' . $filename . (
            $unique ? '_' . date('Y-m-d-H_i_s') : '') . '.txt';

    }

    /**
     * function to write a log and save it in a file
     *
     * @param string $chosenExtension
     * @param array  $errorMessageArray
     * @param string $logname
     *
     * @return string
     */
    public function writeLog($message)
    {
        if (!$this->writeable) {
            $this->open();
        }

        fwrite($this->handle, $message);
        fwrite($this->handle, "\r\n");
        return $this;
    }

    public function open()
    {
        if ($this->writeable) {
            return $this;
        }
        if (!$this->handle = fopen($this->filename, 'w')) {
            throw new \Exception(sprintf('Could not open log file %s', $this->filename));
        }
        $this->writeable = true;
        return $this;
    }

    public function close()
    {
        if ($this->handle) {
            fclose($this->handle);
        }
        return $this->filename;
    }

    /**
     *
     * @return string
     */
    public function dump()
    {
        return file_get_contents($this->filename);
    }
}
