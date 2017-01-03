<?php
/**
 *  Copyright notice
 *
 *  (c) 2015 DMK E-Business GmbH <dev@dmk-ebusiness.de>
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

namespace DMK\Mkdam2fal\ServiceHelper;

use DMK\Mkdam2fal\Utility\StringUtility;
use Tx_Rnbase_Database_Connection;
use tx_rnbase_util_Logger;
use TYPO3\CMS\Core\Controller\CommandLineController;
use TYPO3\CMS\Core\FormProtection\Exception;
use TYPO3\CMS\Core\Resource\Collection\CategoryBasedFileCollection;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

\tx_rnbase::load('tx_rnbase_util_DB');
\tx_rnbase::load('tx_rnbase_util_Strings');

/**
 * Class DamFrontendConverter
 *
 * @author Mario Seidel <mario.seidel@dmk-ebusiness.de>
 */
class DamFrontendConverter {

	const DAM_FE_PI_1 = 'dam_frontend_pi1';
	const DAM_FE_PI_2 = 'dam_frontend_pi2';
	const DAM_FE_PI_3 = 'dam_frontend_pi3';

	const DB_ITEM_LIMIT = 20000; //TODO: remove hard testlimit
	const DEBUG = FALSE;

	protected $force = false;

	protected $migratedElements = array();

	protected static $progress = array(
		self::DAM_FE_PI_1 => array('success' => 0, 'error' => 0, 'found' => 0, 'skipped' => 0),
		self::DAM_FE_PI_2 => array('success' => 0, 'error' => 0, 'found' => 0, 'skipped' => 0)
	);

	protected static $output = array();

	/**
	 * list all dam_frontend_piX plugins (deleted and hidden too)
	 *
	 * @return array
	 */
	public function getDamFrontendPlugins() {
		return array(
			self::DAM_FE_PI_1 => $this->getTtContentElements("list_type='". self::DAM_FE_PI_1 . "'"),
			self::DAM_FE_PI_2 => $this->getTtContentElements("list_type='". self::DAM_FE_PI_2 . "'"),
			//@TODO: convert dam_frontend_pi3
			self::DAM_FE_PI_3 . ' (currently not supported)'
				=> $this->getTtContentElements("list_type='". self::DAM_FE_PI_3 . "'"),
		);
	}

	/**
	 * convert dam_frontend_pi1 and pi2 plugins to filelink plugins
	 */
	public function convertDamFeToFileLinks() {
		$this->preSetup();

		$this->info('search for dam_frontend_pi1 plugins');
		$this->convertDamFePi(self::DAM_FE_PI_1, function($row) {
			return $this->convertPi1Item($row);
		});

		$this->info('search for dam_frontend_pi2 plugins');
		$this->convertDamFePi(self::DAM_FE_PI_2, function($row) {
			return $this->convertPi2Item($row);
		});

		$this->printStats();

		$this->cleanUp();

		$fileFolderRead = \tx_rnbase::makeInstance('DMK\\Mkdam2fal\\ServiceHelper\\FileFolderRead');
		$fileFolderRead->writeCsvLog($this->migratedElements, 'damfrontend');
	}

	private function preSetup() {
		//@TODO: auf templavoila prüfen
		//disable insert of tt_content ref, we'll do this later at correct position by yourself
		$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tx_templavoila_tcemain']['doNotInsertElementRefsToPage'] = TRUE;
	}

	public function getOutput() {
		return self::$output;
	}

	private function cleanUp() {
		//remove temp settings
		unset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tx_templavoila_tcemain']['doNotInsertElementRefsToPage']);
	}

	/**
	 * checks if the filelink plugin is registert in templavoila flexform on specific page
	 */
	private function checkFilelinkPluginShown() {
		$damFrontendPi1s = $this->getTtContentElements("list_type='dam_frontend_pi1'");
		self::$progress[self::DAM_FE_PI_1]['found'] += sizeof($damFrontendPi1s);
		foreach ($damFrontendPi1s as $row) {
			try {
				$fileLinksUids = $this->getFilelinkPluginUid($row);

				if ($fileLinksUids !== false) {
					foreach ($fileLinksUids as $fileLinksUid) {
						$affected = $this->fixTemplaVoilaPageEntry($row, $fileLinksUid);
						if ($affected > 0) {
							self::$progress[self::DAM_FE_PI_1]['success'] += $affected;
						}
					}
				} else {
						self::$progress[self::DAM_FE_PI_1]['skipped'] += 1;
				}
			} catch (\Exception $e) {
				$this->error($e->getMessage());
				self::$progress[self::DAM_FE_PI_1]['error'] += 1;
			}
		}
	}

	/**
	 * @param $filter
	 * @param $convertFunc
	 */
	private function convertDamFePi($filter, $convertFunc) {
		$damFrontendPi1s = $this->getTtContentElements("list_type='$filter'");
		self::$progress[$filter]['found'] += sizeof($damFrontendPi1s);
		foreach ($damFrontendPi1s As $row) {
			try {
				if ($convertFunc($row)) {
					self::$progress[$filter]['success'] += 1;
				} else {
					self::$progress[$filter]['skipped'] += 1;
				}
			} catch (\Exception $e) {
				$this->error($e->getMessage());
				self::$progress[$filter]['error'] += 1;
			}
		}
	}

	/**
	 * query for tt_content elements with given conditions
	 *
	 * @param $where
	 * @param null $callback performed on each item
	 * @return array
	 */
	private function getTtContentElements($where, $callback=NULL) {

		$options = array(
			'enablefieldsoff' => 1,
			'limit' => self::DB_ITEM_LIMIT,
			'where' => '(' . $where . ') AND deleted=0 AND pid > 0',
		);

		if ($callback) {
			$options['callback'] = array($this, $callback);
		}

		return Tx_Rnbase_Database_Connection::getInstance()->doSelect('*','tt_content', $options);
	}

	/**
	 * return uid of
	 * @param $row
	 * @return array uids
	 */
	private function getFilelinkPluginUid($row) {
		$options = array(
			'enablefieldsoff' => 1,
			'limit' => self::DB_ITEM_LIMIT,
			'where' => 'CType=\'uploads\' AND deleted=0 and pid=' . $row['pid'],
		);

		$result = Tx_Rnbase_Database_Connection::getInstance()->doSelect('uid','tt_content', $options);
		if (sizeof($result) > 0) {
			$return = array();

			return array_reduce($result, function($carry, $row) {
				$carry[] = $row['uid'];
				return $carry;
			}, $return);
		}
		return false;
	}

	/**
	 * converts a dam_frontend_pi1 to a file links list
	 *
	 * @param array $item tt_content
	 * @return bool
	 * @throws \Exception
	 */
	private function convertPi1Item($item) {
		if ($item['dam_fe_converted'] == '1' || empty($item['pi_flexform'])) {
			return FALSE;
		}

		$this->info('convert pi1 items');
		$xmlArr = \t3lib_div::xml2array($item['pi_flexform']);

		$this->info('tt_content uid: ' . $item['uid'] . ' "' . $item['header'] . '" on pid ' . $item['pid'], 2);

		//Anzeige von Dateiliste
		if ($xmlArr['data']['sDEF']['lDEF']['viewID']['vDEF'] != '1') {
			throw new \Exception('only viewID is supported');
		}

		if (!isset($xmlArr['data']['sSelection']['lDEF']['catMounts']['vDEF'])) {
			throw new \Exception('no DAM category found');
		}

		if (empty($xmlArr['data']['sSelection']['lDEF']['catMounts']['vDEF'])) {
			throw new \Exception('no DAM category selected');
		}

		//find FAL categories and create file collections for each one
		$damCatId = $xmlArr['data']['sSelection']['lDEF']['catMounts']['vDEF'];
		$falCategories = $this->getFalCategory(explode(',', $damCatId));

		$fileCollectionUids = array();
		foreach ($falCategories as $falCategory) {
			$fileCollectionUids[] = $this->findOrCreateFileCollection($falCategory);
		}

		//TODO: sorting muss in der Tabelle pages neu gesetzt werden (tx_templavoila_flex)
		$fileLinksUid = $this->createNewFileLinks($item, $fileCollectionUids);
		$this->fixTemplaVoilaPageEntry($item, $fileLinksUid);

		Tx_Rnbase_Database_Connection::getInstance()->doUpdate(
			'tt_content', 'uid='.$item['uid'],
			array('dam_fe_converted' => '1', 'hidden' => '1')
		);

		return TRUE;
	}

	/**
	 * converts a dam_frontend_pi2 to a file links list and creates relation to sys_file
	 *
	 * @param $item
	 * @return bool
	 * @throws \Exception
	 */
	private function convertPi2Item($item) {

		if (
			$item['dam_fe_converted'] == '1' ||
			$item['dam_fe_new_created'] == '1' ||
			$item['tx_damdownloadlist_records'] == ''

		) {
			return FALSE;
		}

		$this->info('convert pi2 items');

		//records from tx_dam fitting for item (only uids)
		$filteredDamRecordUids = $this->filterActiveDamUids($item['tx_damdownloadlist_records']);

		//new 'File Link' element
		$fileLinksUid = $this->createNewFileLinks($item);

		//$this->fixTemplaVoilaPageEntry($item, $fileLinksUid);
		$this->fixTemplaVoilaFileLinkEntryInFce($item, $fileLinksUid);

		$this->info('tt_content uid: ' . $item['uid'] . ' ' . $item['header'] . ' on pid ' . $item['pid']
				. ' Anzahl Files:' . sizeof($filteredDamRecordUids), 2);

		$elementCounter = 0;
		//relation new element 'File Link' to FAL for all files from dam
		foreach ($filteredDamRecordUids as $damRecordUid) {
			$elementCounter++;
			$falUid = $this->getFalUidFromDam($damRecordUid);
			if (!$falUid) {
				//throw new \RuntimeException('no FAL file found for damUid ' . $damRecordUid);
				$this->error('no FAL file found for damUid: ' . $damRecordUid);
			} else {
				//set references if new elemente 'File Link' is created
				if ($fileLinksUid !== FALSE) {
					$refid = \tx_rnbase_util_TSFAL::addReference('tt_content', 'media', $fileLinksUid, $falUid, $item['pid']);

					// sorting of elements
					Tx_Rnbase_Database_Connection::getInstance()->doUpdate(
						'sys_file_reference', 'uid='.$refid,
						array('sorting_foreign' => $elementCounter)
					);
					//add title to reference
					if($refTitle = $this->getDamTitle($damRecordUid)) {
						Tx_Rnbase_Database_Connection::getInstance()->doUpdate(
							'sys_file_reference', 'uid='.$refid,
							array('title' => $refTitle)
						);
					}
					//add description to reference
					if ($refDescription = $this->getDamDescription($damRecordUid)) {
						Tx_Rnbase_Database_Connection::getInstance()->doUpdate(
							'sys_file_reference', 'uid='.$refid,
							array('description' => $refDescription)
						);
					}

					$this->debug('Reference created for sys_file ' . $falUid, 2);
					//sign old element as converted
					Tx_Rnbase_Database_Connection::getInstance()->doUpdate(
							'tt_content', 'uid='.$item['uid'],
							array('dam_fe_converted' => '1', 'hidden' => '1')
					);
				}
			}
		}

		// Save as array with old and new content element id for further usage
		$this->migratedElements[] = array($item['uid'], $fileLinksUid);
		return TRUE;
	}

	/**
	 * @param $damRecordUids
	 * @return mixed
	 */
	private function filterActiveDamUids($damRecordUids) {
		$options = array(
			'enablefieldsoff' => 1,
			'where' => 'uid IN ('.$damRecordUids.') AND deleted=0',
			'orderby' => 'FIELD(uid, ' . $damRecordUids . ')'
		);
		$rows = Tx_Rnbase_Database_Connection::getInstance()->doSelect('uid', 'tx_dam', $options);
		$result = array_reduce($rows, function($res, $item) {
			$res[] = $item['uid'];
			return $res;
		}, array());
		return $result;
	}

	/**
	 * @param $damId
	 * @return mixed
	 */
	private function getDamTitle($damId) {
		$options = array(
				'enablefieldsoff' => 1,
				'where' => 'uid = '.$damId.' AND deleted=0'
		);
		$result = reset(Tx_Rnbase_Database_Connection::getInstance()->doSelect('title', 'tx_dam', $options));
		return ($result && array_key_exists('title', $result)) ? $result['title'] : FALSE;
	}

	/**
	 * @param $damId
	 * @return mixed
	 */
	private function getDamDescription($damId) {
		$options = array(
				'enablefieldsoff' => 1,
				'where' => 'uid = '.$damId.' AND deleted=0'
		);
		$result = reset(Tx_Rnbase_Database_Connection::getInstance()->doSelect('description', 'tx_dam', $options));
		return ($result && array_key_exists('description', $result)) ? $result['description'] : FALSE;
	}

	/**
	 * search FAL category for given array dam uid
	 *
	 * @param array $damCatUids
	 * @return array
	 */
	protected function getFalCategory($damCatUids) {
		$options = array(
			'enablefieldsoff' => 1,
			'where' => 'damCatUid IN (' . implode(',', $damCatUids) . ')',
		);
		return Tx_Rnbase_Database_Connection::getInstance()->doSelect('uid,title,description', 'sys_category', $options);
	}

	/**
	 * @param $damUid
	 * @@return int|false
	 */
	protected function getFalUidFromDam($damUid) {
		$options_a = array(
			'enablefieldsoff' => 1,
			'where' => 'damUid=' . $damUid,
		);
		$options_b = array(
				'enablefieldsoff' => 1,
				'where' => 'uid=' . $damUid,
		);
		$result = reset(Tx_Rnbase_Database_Connection::getInstance()->doSelect('uid', 'sys_file', $options_a));
		$result = ($result && array_key_exists('uid', $result)) ? $result['uid'] : FALSE;
		//if no result try to get from tx_dam table
		if (!$result) {
			$result = reset(Tx_Rnbase_Database_Connection::getInstance()->doSelect('falUid', 'tx_dam', $options_b));
			$result = ($result && array_key_exists('falUid', $result)) ? $result['falUid'] : FALSE;
		}
		return $result;
	}

	/**
	 * search all sys_files
	 * @param $damCatUid
	 * @return array
	 */
	private function getFalFileForDamCategory($damCatUid) {
		$options = array(
			'enablefieldsoff' => 1,
			'where' => 'c.damCatUid=' . $damCatUid,
		);
		$rows = Tx_Rnbase_Database_Connection::getInstance()->doSelect('c.title, c.uid as catUid, f.uid', 'sys_category c
			LEFT JOIN sys_category_record_mm cmm on (c.uid = cmm.uid_local)
			LEFT JOIN sys_file_metadata m ON (cmm.uid_foreign = m.uid and cmm.tablenames = \'sys_file_metadata\')
			LEFT JOIN sys_file f ON (m.file = f.uid)
		', $options);


		/** @var FileRepository $fileRepository */
		$fileRepository = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Resource\\FileRepository');
		$refs = array();
		foreach ($rows as $row) {
			//find by FAL cats *******************
			$refs[] = $fileRepository->findByIdentifier($row['uid']);
		}
		return $refs;
	}

	/**
	 * @param $falCategory
	 * @return int id of the collection
	 */
	private function findOrCreateFileCollection($falCategory) {
//		if (sizeof($files) <= 0) {
//			return false;
//		}
//
		//find existing FileCollection
		$foundCollectionUid = $this->findFileCollectionByCategory($falCategory['uid']);
		if ($foundCollectionUid) {
			$this->info('use existing collection: ' . $foundCollectionUid['uid'], 2);
			return $foundCollectionUid['uid'];
		}

//		/** @var CategoryBasedFileCollection $categoryCol */
//		//$categoryCol = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Resource\\Collection\\CategoryBasedFileCollection');
//		$col = CategoryBasedFileCollection::create(array(
//			'uid' => 'NEW123',
//			'pid' => \tx_mktest_util_MiscTools::getStoragePid(),
//			'title' => $falCategory['title'],
//			'category' => $falCategory['uid'],
//			'description' => $falCategory['description']
//		));
//
//		foreach($files as $file) {
//			$col->add($file);
//		}

		$data = array(
			'pid' => 1, //@TODO: config PID
			'title' => $falCategory['title'],
			'type' => 'category',
			'category' => $falCategory['uid'],
			'description' => $falCategory['description']
		);

		$colId = Tx_Rnbase_Database_Connection::getInstance()->doInsert('sys_file_collection', $data);

		$this->info('new collection created: ' . $colId, 2);

		return $colId;
	}

	/**
	 * @param int $categoryUid
	 * @return mixed
	 */
	private function findFileCollectionByCategory($categoryUid) {
		$options = array(
			'enablefieldsoff' => 1,
			'where' => 'category = ' . $categoryUid,
		);

		return reset(Tx_Rnbase_Database_Connection::getInstance()->doSelect('uid', 'sys_file_collection', $options));
	}

	/**
	 * @param $oldItem
	 * @param array $fileCollectionUids
	 * @return false|int Uid of new filelinks
	 */
	private function createNewFileLinks($oldItem, $fileCollectionUids=array()) {
		//$this->debug('$oldItem: ' . var_export($oldItem, TRUE));

		//not nice but needed
		$GLOBALS['BE_USER']->user['admin'] = 1;

		$item['pid'] = $oldItem['pid'];
		$item['header'] = $oldItem['header'];
		$item['sorting'] = $oldItem['sorting'];
		$item['cruser_id'] = $oldItem['cruser_id'];
		$item['bodytext'] = $oldItem['bodytext'];
		$item['imagecols'] = $oldItem['imagecols'];
		$item['subheader'] = $oldItem['subheader'];
		$item['spaceBefore'] = $oldItem['spaceBefore'];
		$item['spaceAfter'] = $oldItem['spaceAfter'];
		$item['header_layout'] = $oldItem['header_layout'];
		$item['records'] = $oldItem['records'];
		$item['pages'] = $oldItem['pages'];
		$item['hidden'] = $oldItem['hidden'];
		$item['fe_group'] = $oldItem['fe_group'];
		$item['CType'] = 'uploads';
		$item['list_type'] = '';
		$item['filelink_size'] = '1';
		$item['file_collections'] = implode(',', $fileCollectionUids);

		$data = array(
			'tt_content' => array(
				'NEW' => $item
			)
		);

		/** @var \TYPO3\CMS\Core\DataHandling\DataHandler $tce */
		$tce = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\DataHandling\\DataHandler');
		$tce->stripslashes_values = 0;
		$tce->start($data, array());
		$tce->process_datamap();

		if (array_key_exists('NEW', $tce->substNEWwithIDs) && $tce->substNEWwithIDs['NEW'] > 0) {
			$this->info('added new file link ' . $tce->substNEWwithIDs['NEW'], 2);

			//adjust sorting cause tce does it wrong
			Tx_Rnbase_Database_Connection::getInstance()->doUpdate(
					'tt_content', 'uid='.$tce->substNEWwithIDs['NEW'],
					array('sorting' => $oldItem['sorting'])
			);
			//info to old element than new is created
			Tx_Rnbase_Database_Connection::getInstance()->doUpdate(
					'tt_content', 'uid='.$oldItem['uid'],
					array('dam_fe_new_created' => '1')
			);
			return $tce->substNEWwithIDs['NEW'];
		}

		return FALSE;
	}

	/**
	 * insert the new FileLink uid before the old dam-plugin in TemplaVoila FlexForm
	 *
	 * @param array $row
	 * @param int $fileLinksUid
	 * @return bool
	 */
	private function fixTemplaVoilaPageEntry($row, $fileLinksUid) {
		$options = array(
			'enablefieldsoff' => 1,
			'where' => 'uid = ' . $row['pid'],
		);
		$page = reset(Tx_Rnbase_Database_Connection::getInstance()->doSelect('tx_templavoila_flex', 'pages', $options));

		if (strstr($page['tx_templavoila_flex'], $fileLinksUid) !== FALSE)
			return FALSE;

		$templavoilaFlex = StringUtility::addUidStr($page['tx_templavoila_flex'], $row['uid'], $fileLinksUid);

		$this->debug("\nflexVOR: " . $page['tx_templavoila_flex'] . "\nfelxNACH: $templavoilaFlex\n\n");

		return Tx_Rnbase_Database_Connection::getInstance()
				->doUpdate('pages', $options['where'], array('tx_templavoila_flex' => $templavoilaFlex));
	}

	/**
	 * fix Position of new FileLink in TemplaVoila FCE
	 *
	 * @param array $row oldItem
	 * @param int $fileLinksUid
	 * @return bool
	 */
	private function fixTemplaVoilaFileLinkEntryInFce($row, $fileLinksUid) {

		if ($row['pid'] < 0) {
			return TRUE;
		}
		$options = array(
				'enablefieldsoff' => 1,
				'where' => 'pid = ' . $row['pid'] . ' AND deleted=0 AND CType=\'templavoila_pi1\' AND ( tx_templavoila_flex LIKE \'%field_content_left%\' OR  tx_templavoila_flex LIKE \'%field_sidebar%\') ',
		);
		$fces = Tx_Rnbase_Database_Connection::getInstance()->doSelect('*', 'tt_content', $options);

		foreach ($fces AS $fce) {
			if (strstr($fce['tx_templavoila_flex'], $fileLinksUid) !== FALSE) { // do not insert same id twice
			} else {
				$templavoilaFlex = StringUtility::addUidStr($fce['tx_templavoila_flex'], $row['uid'], $fileLinksUid);
				$this->debug("\ntt_conttent flexVOR: " . $fce['tx_templavoila_flex'] . "\ntt_content felxNACH: $templavoilaFlex\n\n");
				$options_a = array(
						'enablefieldsoff' => 1,
						'where' => 'uid = ' . $fce['uid'],
				);
				Tx_Rnbase_Database_Connection::getInstance()
				->doUpdate('tt_content', $options_a['where'], array('tx_templavoila_flex' => $templavoilaFlex));
			}
		}
		return TRUE;
	}

	/**
	 * @param $msg
	 * @param int $intent
	 * @param array $debug
	 */
	private function info($msg, $intent=0, $debug = array()) {
		$intent = str_repeat(' ', $intent);
		self::$output[] = "$intent$msg\n";
	}

	/**
	 * @param $msg
	 * @param int $intent
	 */
	private function debug($msg, $intent=0) {
		if (self::DEBUG === TRUE) {
			$intent = str_repeat(' ', $intent);
			self::$output[] = "$intent$msg\n";
		}
	}

	/**
	 * @param $msg
	 * @param array $debug
	 */
	private function error($msg, $debug = array()) {
		self::$output[] = "[ERROR] $msg\n";
	}

	/**
	 * print out status of all transactions
	 */
	private function printStats() {
		$this->info("Status:\n");
		foreach (self::$progress as $key => $progres) {
			$this->info($key, 6);
			$this->info(print_r($progres, true));
		}
	}

	/**
	 * Convert FlexForm data array to XML
	 *
	 * @param array $array Array to output in <T3FlexForms> XML
	 * @param boolean $addPrologue If set, the XML prologue is returned as well.
	 * @return string XML content.
	 */
	private function flexArray2Xml($array, $addPrologue = FALSE) {
		$flexArray2Xml_options = array();
		$flexArray2Xml_options['useCDATA'] = 1;
//		$flexArray2Xml_options['disableTypeAttrib'] = 2;

		$options = $GLOBALS['TYPO3_CONF_VARS']['BE']['niceFlexFormXMLtags'] ? $flexArray2Xml_options : array();
		$spaceInd = $GLOBALS['TYPO3_CONF_VARS']['BE']['compactFlexFormXML'] ? -1 : 4;
		$output = \TYPO3\CMS\Core\Utility\GeneralUtility::array2xml($array, '', 0, 'T3FlexForms', $spaceInd, $options);
		if ($addPrologue) {
			$output = '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>' . LF . $output;
		}
		return $output;
	}
}
