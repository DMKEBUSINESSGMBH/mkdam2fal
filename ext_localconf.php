<?php
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
	'DMK.' . $_EXTKEY,
	'Pi1',
	array(
		'Damfalfile' => 'list,referenceUpdate,updateCategory,updateDamFrontend',

	),
	// non-cacheable actions
	array(
		'Damfalfile' => 'list,referenceUpdate,updateCategory,updateDamFrontend',

	)
);
