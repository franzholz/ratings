<?php
if (!defined ('TYPO3_MODE')) die('Access denied.');

if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('tt_news')) {
	// New columns
	$tempColumns = array (
		'tx_ratings_enable' => Array (
			'exclude' => 1,
			'label' => 'LLL:EXT:ratings/locallang_db.xml:tt_news.tx_ratings_enable',
			'config' => array (
				'type'     => 'check',
				'items'    => array(
					array('', '')
				),
				'default'  => '1'
			)
		),
	);

	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('tt_news', $tempColumns, 1);
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('tt_news', 'tx_ratings_enable;;;;1-1-1');
}

$tx_ratings_sysconf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['ratings']);
$tx_ratings_debug_mode_disabled = is_array($tx_ratings_sysconf) && !intval($tx_ratings_sysconf['debugMode']);




\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_ratings_data');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_ratings_iplog');

unset($tx_ratings_debug_mode_disabled);
unset($tx_ratings_sysconf);

$TCA['tt_content']['types']['list']['subtypes_excludelist']['ratings' . '_pi1'] = 'layout,select_key,pages';
$TCA['tt_content']['types']['list']['subtypes_addlist']['ratings' . '_pi1'] = 'pi_flexform';
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue('ratings' .'_pi1', 'FILE:EXT:ratings/pi1/flexform_ds.xml');

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(array('LLL:EXT:ratings/locallang_db.xml:tt_content.list_type_pi1', 'ratings' . '_pi1'),'list_type');

if (TYPO3_MODE=='BE') {
	$TBE_MODULES_EXT['xMOD_db_new_content_el']['addElClasses']['tx_ratings_pi1_wizicon'] = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('ratings') . 'pi1/class.tx_ratings_pi1_wizicon.php';
}

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('ratings', 'static/Ratings/', 'Ratings');

