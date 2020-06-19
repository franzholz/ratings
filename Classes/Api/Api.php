<?php

namespace Netcreators\Ratings\Api;

/***************************************************************
*  Copyright notice
*
*  (c) 2008 Dmitry Dulepov [netcreators] <dmitry@typo3.org>
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
***************************************************************/

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;


/**
 * This class contains API for ratings. There are two ways to use this API:
 * <ul>
 * <li>Call {@link getRatingValue} to obtain rating value and process it yourself</li>
 * <li>Call {@link getRatingDisplay} to format and display rating value along with a control to change rating</li>
 * </ul>
 *
 * @author	Dmitry Dulepov [netcreators] <dmitry@typo3.org>
 * @package	TYPO3
 * @subpackage	tx_ratings
 */
class Api {

    /**
    *
    */
    protected $cObj;

    protected $databaseHandle;

    /**
    * Creates an instance of this class
    *
    */
    public function __construct() {
        $this->cObj = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class);
        $this->cObj->start('', '');
        $this->databaseHandle = $this->getDatabaseConnection();
    }

    /**
    * Fetches data and calculates rating value for $ref. Rating values are from
    * 0 to 100.
    *
    * @param	string		$ref	Reference to item in TYPO3 "datagroup" format (like tt_content_10)
    * @param	array		$conf	Configuration array
    * @return	float		Rating value (from 0 to 100)
    */
    public function getRatingValue($ref, $conf = null) {
        if (is_null($conf)) {
            $conf = $this->getDefaultConfig();
        }
        $rating = $this->getRatingInfo($ref);
        $result = max(0, 100 * (floatval($rating['rating']) - intval($conf['minValue'])) / (intval($conf['maxValue']) - intval($conf['minValue'])));
        return $result;
    }

    /**
    * Retrieves default configuration of ratings.
    * Uses plugin.tx_ratings from page TypoScript template
    *
    * @return	array		TypoScript configuration for ratings
    */
    public function getDefaultConfig() {
        $result = [];
        $tsfe = $this->getTypoScriptFrontendController();
        if ($tsfe) {
            $result = $tsfe->tmpl->setup['plugin.']['tx_ratings.'];
        }
        return $result;
    }

    /**
    * Generates HTML code for displaying ratings.
    *
    * @param	string		$ref	Reference
    * @param	array		$conf	Configuration array
    * @return	string		HTML content
    */
    public function getRatingDisplay($ref, $conf = null) {
        $tsfe = $this->getTypoScriptFrontendController();
        if (is_null($conf)) {
            $conf = $this->getDefaultConfig();
        }

        // Get template
        if ($tsfe) {
            // Normal call
            $pathFilename = $tsfe->tmpl->getFileName($conf['templateFile']);
            $template = file_get_contents($pathFilename);
        }
        else {
            // Called from ajax
            $template = @file_get_contents(PATH_site . $conf['templateFile']);
        }

        if (!$template) {
            $errorContent = 'Unable to load template code from "' . $conf['templateFile'] . '"';
            GeneralUtility::devLog($errorContent, 'ratings', 3);
            return $errorContent;
        }
        return $this->generateRatingContent($ref, $template, $conf);
    }

    /**
    * Retrieves current IP address
    *
    * @return	string		Current IP address
    */
    public function getCurrentIp() {
        if (preg_match('/^\d{2,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
    * Checks if item was already voted by current user
    *
    * @param	string		$ref	Reference
    * @return	boolean		true if item was voted
    */
    public function isVoted($ref) {
        list($rec) =
            $this->databaseHandle->exec_SELECTgetRows(
                'COUNT(*) AS t',
                'tx_ratings_iplog',
                ' reference=' .
                    $this->databaseHandle->fullQuoteStr($ref, 'tx_ratings_iplog') .
                    ' AND ip=' .
                    $this->databaseHandle->fullQuoteStr($this->getCurrentIp(), 'tx_ratings_iplog') .
                $this->enableFields('tx_ratings_iplog')
            );
        return ($rec['t'] > 0);
    }


    /**
    * Calculates image bar width
    *
    * @param    int    $rating            Rating value
    * @param    int    $ratingImageWidth  width of the rating image
    * @return   int
    */
    protected function getBarWidth($rating, $ratingImageWidth) {
        $result = 0;
        if (
            MathUtility::canBeInterpretedAsInteger($ratingImageWidth) &&
            MathUtility::canBeInterpretedAsInteger($rating)
        ) {
            $result = intval($ratingImageWidth * $rating);
        }
        return $result;
    }

    /**
    * Fetches rating information for $ref
    *
    * @param string $ref	Reference in TYPO3 "datagroup" format (i.e. tt_content_10)
    * @return array Array with two values: rating and count, which is calculated rating value and number of votes respectively
    */
    protected function getRatingInfo($ref) {
        $recs =
            $this->databaseHandle->exec_SELECTgetRows(
                'rating,vote_count',
                'tx_ratings_data',
                ' reference=' .
                    $this->databaseHandle->fullQuoteStr($ref, 'tx_ratings_data') .
                    $this->enableFields('tx_ratings_data')
            );
        return (count($recs) ? $recs[0] : array('rating' => 0, 'vote_count' => 0));
    }

    /**
    * Generates rating content for given $ref using $template HTML template
    *
    * @param	string		$ref	Reference in TYPO3 "datagroup" format (i.e. tt_content_10)
    * @param	string		$template	HTML template to use
    * @param	array		$conf	Configuration array
    * @return	string		Generated content
    */
    protected function generateRatingContent($ref, $template, array $conf) {
        $tsfe = $this->getTypoScriptFrontendController();
        if (!$tsfe) {
            return '';
        }
        $templateService = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Service\MarkerBasedTemplateService::class);
        $siteRelPath = ExtensionManagementUtility::siteRelPath('ratings');
        $rating = $this->getRatingInfo($ref);
        if ($rating['vote_count'] > 0) {
            $ratingValue = $rating['rating'] / $rating['vote_count'];
            $rating_str = sprintf($tsfe->sL('LLL:EXT:ratings/Resources/Private/Language/locallang.xlf:api_rating'), $ratingValue, $conf['maxValue'], $rating['vote_count']);
        } else {
            $ratingValue = 0;
            $rating_str = $tsfe->sL('LLL:EXT:ratings/Resources/Private/Language/locallang.xlf:api_not_rated');
        }

        if (
            $conf['mode'] == 'static' ||
            (!$conf['disableIpCheck'] && $this->isVoted($ref))
        ) {
            $subTemplate = $templateService->getSubpart($template, '###TEMPLATE_RATING_STATIC###');
            $links = '';
        } else {
            $subTemplate = $templateService->getSubpart($template, '###TEMPLATE_RATING###');
            $voteSub = $templateService->getSubpart($template, '###VOTE_LINK_SUB###');
            // Make ajaxData
            $confCopy = $conf;
            unset($confCopy['userFunc']);
            $confCopy['templateFile'] = $tsfe->tmpl->getFileName($conf['templateFile']);
            $data = serialize(array(
                'pid' => $tsfe->id,
                'conf' => $confCopy,
                'lang' => $tsfe->lang,
            ));
            $ajaxData = base64_encode($data);
            // Create links
            $links = '';
            for ($i = $conf['minValue']; $i <= $conf['maxValue']; $i++) {
                $check = md5($ref . $i . $ajaxData . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']);
                $links .= $templateService->substituteMarkerArray($voteSub, array(
                    '###VALUE###' => $i,
                    '###REF###' => $ref,
                    '###PID###' => $tsfe->id,
                    '###CHECK###' => $check,
                    '###SITE_REL_PATH###' => $siteRelPath,
                    '###AJAX_DATA###' => rawurlencode($ajaxData),
                ));
            }
        }

        $markers = array(
            '###PID###' => $tsfe->id,
            '###REF###' => htmlspecialchars($ref),
            '###TEXT_SUBMITTING###' => $tsfe->sL('LLL:EXT:ratings/Resources/Private/Language/locallang.xlf:api_submitting'),
            '###TEXT_ALREADY_RATED###' => $tsfe->sL('LLL:EXT:ratings/Resources/Private/Language/locallang.xlf:api_already_rated'),
            '###BAR_WIDTH###' => $this->getBarWidth($ratingValue, $conf['ratingImageWidth']),
            '###RATING###' => $rating_str,
            '###RATING_VALUE###' => $ratingValue,
            '###TEXT_RATING_TIP###' => $tsfe->sL('LLL:EXT:ratings/Resources/Private/Language/locallang.xlf:api_tip'),
            '###SITE_REL_PATH###' => $siteRelPath,
            '###VOTE_LINKS###' => $links,
            '###RAW_COUNT###' => $this->cObj->stdWrap($rating['vote_count'], $conf['voteCountStdWrap.']),
            '###REVIEW_COUNT###' => $this->cObj->stdWrap($rating['vote_count'], $conf['reviewCountStdWrap.']),
            '###RAW_VOTE###' => $this->cObj->stdWrap($rating['rating'], $conf['ratingVoteStdWrap.']),
            '###RAW_VOTE_MAX###' => $this->cObj->stdWrap($conf['maxValue'], $conf['ratingMaxValueStdWrap.']),
            '###RAW_VOTE_MIN###' => $this->cObj->stdWrap($conf['minValue'], $conf['ratingMinValueStdWrap.']),
        );

        $result = $templateService->substituteMarkerArray($subTemplate, $markers);
        return $result;
    }

    /**
    * Implements enableFields call that can be used from regular FE and eID
    *
    * @param	string		$tableName	Table name
    * @return	string		SQL
    */
    public function enableFields($tableName) {
        if ($this->getTypoScriptFrontendController()) {
            return $this->cObj->enableFields($tableName);
        }
        /* @var $sys_page \TYPO3\CMS\Frontend\Page\PageRepository */
        $sys_page = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\Page\PageRepository::class);

        return $sys_page->enableFields($tableName);
    }

    /**
     * @return \TYPO3\CMS\Core\Database\DatabaseConnection
     */
    protected function getDatabaseConnection()
    {
        return $GLOBALS['TYPO3_DB'];
    }

    /**
     * @return TypoScriptFrontendController
     */
    protected function getTypoScriptFrontendController()
    {
        return $GLOBALS['TSFE'];
    }
}


