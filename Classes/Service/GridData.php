<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2011 Workspaces Team (http://forge.typo3.org/projects/show/typo3v4-workspaces)
 *  (c) 2010-2011 Netresearch GmbH & Co. KG (http://www.netresearch.de)
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
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * @author Workspaces Team (http://forge.typo3.org/projects/show/typo3v4-workspaces)
 * @author Alexander Opitz <alexander.opitz@netresearch.de>
 * @package nr_easy_workspace
 * @subpackage Service
 */
class ux_tx_Workspaces_Service_GridData extends tx_Workspaces_Service_GridData
{
	/**
	 * Generates grid list array from given versions.
	 *
	 * @param array $versions
	 * @param string $filterTxt
	 * @return void
	 */
	protected function generateDataArray(array $versions, $filterTxt) {
		/** @var $stagesObj Tx_Workspaces_Service_Stages */
		$stagesObj = t3lib_div::makeInstance('Tx_Workspaces_Service_Stages');

		/** @var $workspacesObj Tx_Workspaces_Service_Workspaces */
		$workspacesObj = t3lib_div::makeInstance('Tx_Workspaces_Service_Workspaces');
		$availableWorkspaces = $workspacesObj->getAvailableWorkspaces();

		$workspaceAccess = $GLOBALS['BE_USER']->checkWorkspace($GLOBALS['BE_USER']->workspace);
		$swapStage = ($workspaceAccess['publish_access'] & 1) ? Tx_Workspaces_Service_Stages::STAGE_PUBLISH_ID : 0;
		$swapAccess =  $GLOBALS['BE_USER']->workspacePublishAccess($GLOBALS['BE_USER']->workspace) &&
					   $GLOBALS['BE_USER']->workspaceSwapAccess();

		$this->initializeWorkspacesCachingFramework();

		// check for dataArray in cache
		if ($this->getDataArrayFromCache($versions, $filterTxt) == FALSE) {
			$stagesObj = t3lib_div::makeInstance('Tx_Workspaces_Service_Stages');

			foreach ($versions as $table => $records) {
                if ('tt_content' === $table) {
                    continue;
                }
				$versionArray = array('table' => $table);

				foreach ($records as $record) {

					$origRecord = t3lib_BEFunc::getRecord($table, $record['t3ver_oid']);
					$versionRecord = t3lib_BEFunc::getRecord($table, $record['uid']);

					if (isset($GLOBALS['TCA'][$table]['columns']['hidden'])) {
						$recordState = $this->workspaceState($versionRecord['t3ver_state'], $origRecord['hidden'], $versionRecord['hidden']);
					} else {
						$recordState = $this->workspaceState($versionRecord['t3ver_state']);
					}
					$isDeletedPage = ($table == 'pages' && $recordState == 'deleted');
					$viewUrl =  tx_Workspaces_Service_Workspaces::viewSingleRecord($table, $record['t3ver_oid'], $origRecord);

					$pctChange = $this->calculateChangePercentage($table, $origRecord, $versionRecord);
					$versionArray['uid'] = $record['uid'];
					$versionArray['workspace'] = $versionRecord['t3ver_id'];
					$versionArray['label_Workspace'] = htmlspecialchars($versionRecord[$GLOBALS['TCA'][$table]['ctrl']['label']]);
					$versionArray['label_Live'] = htmlspecialchars($origRecord[$GLOBALS['TCA'][$table]['ctrl']['label']]);
					$versionArray['label_Stage'] = htmlspecialchars($stagesObj->getStageTitle($versionRecord['t3ver_stage']));
					$versionArray['change'] = $pctChange;
					$versionArray['path_Live'] = htmlspecialchars(t3lib_BEfunc::getRecordPath($record['livepid'], '', 999));
					$versionArray['path_Workspace'] = htmlspecialchars(t3lib_BEfunc::getRecordPath($record['wspid'], '', 999));
					$versionArray['workspace_Title'] = htmlspecialchars(tx_Workspaces_Service_Workspaces::getWorkspaceTitle($versionRecord['t3ver_wsid']));

					$versionArray['workspace_Tstamp'] = $versionRecord['tstamp'];
					$versionArray['workspace_Formated_Tstamp'] = t3lib_BEfunc::datetime($versionRecord['tstamp']);
					$versionArray['t3ver_oid'] = $record['t3ver_oid'];
					$versionArray['livepid'] = $record['livepid'];
					$versionArray['stage'] = $versionRecord['t3ver_stage'];
					$versionArray['icon_Live'] = t3lib_iconWorks::mapRecordTypeToSpriteIconClass($table, $origRecord);
					$versionArray['icon_Workspace'] = t3lib_iconWorks::mapRecordTypeToSpriteIconClass($table, $versionRecord);

					$versionArray['allowedAction_nextStage'] = $stagesObj->isNextStageAllowedForUser($versionRecord['t3ver_stage']);
					$versionArray['allowedAction_prevStage'] = $stagesObj->isPrevStageAllowedForUser($versionRecord['t3ver_stage']);

					if ($swapAccess && $swapStage != 0 && $versionRecord['t3ver_stage'] == $swapStage) {
						$versionArray['allowedAction_swap'] = $stagesObj->isNextStageAllowedForUser($swapStage);
					} else if ($swapAccess && $swapStage == 0) {
						$versionArray['allowedAction_swap'] = TRUE;
					} else {
						$versionArray['allowedAction_swap'] = FALSE;
					}
					$versionArray['allowedAction_delete'] = TRUE;
						// preview and editing of a deleted page won't work ;)
					$versionArray['allowedAction_view'] = !$isDeletedPage && $viewUrl;
					$versionArray['allowedAction_edit'] = !$isDeletedPage;
					$versionArray['allowedAction_editVersionedPage'] = !$isDeletedPage;

					$versionArray['state_Workspace'] = $recordState;

					if ($filterTxt == '' || $this->isFilterTextInVisibleColumns($filterTxt, $versionArray)) {
						$this->dataArray[$table . '.' . $origRecord['uid']] = $versionArray;
					}
				}
			}
			$this->sortDataArray();

			$this->setDataArrayIntoCache($versions, $filterTxt);
		}
		$this->sortDataArray();
	}
}


if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/nr_easy_workspace/Classes/Service/GridData.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/nr_easy_workspace/Classes/Service/GridData.php']);
}
?>