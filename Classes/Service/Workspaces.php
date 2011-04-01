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
class ux_tx_Workspaces_Service_Workspaces extends tx_Workspaces_Service_Workspaces
{
	/**
	 * Select all records from workspace pending for publishing
	 * Used from backend to display workspace overview
	 * User for auto-publishing for selecting versions for publication
	 *
	 * @param	integer		Workspace ID. If -99, will select ALL versions from ANY workspace. If -98 will select all but ONLINE. >=-1 will select from the actual workspace
	 * @param	integer		Lifecycle filter: 1 = select all drafts (never-published), 2 = select all published one or more times (archive/multiple), anything else selects all.
	 * @param	integer		Stage filter: -99 means no filtering, otherwise it will be used to select only elements with that stage. For publishing, that would be "10"
	 * @param	integer		Page id: Live page for which to find versions in workspace!
	 * @param	integer		Recursion Level - select versions recursive - parameter is only relevant if $pageId != -1
	 * @return	array		Array of all records uids etc. First key is table name, second key incremental integer. Records are associative arrays with uid, t3ver_oid and t3ver_swapmode fields. The pid of the online record is found as "livepid" the pid of the offline record is found in "wspid"
	 */
	public function selectVersionsInWorkspace($wsid, $filter = 0, $stage = -99, $pageId = -1, $recursionLevel = 0) {

		$wsid = intval($wsid);
		$filter = intval($filter);
		$output = array();

			// Contains either nothing or a list with live-uids
		if ($pageId != -1 && $recursionLevel > 0) {
			$pageList = $this->getTreeUids($pageId, $wsid, $recursionLevel);
		} else if ($pageId != -1) {
			$pageList = $pageId;
		} else {
			$pageList = '';
		}

			// Traversing all tables supporting versioning:
		foreach ($GLOBALS['TCA'] as $table => $cfg) {
			if ($GLOBALS['TCA'][$table]['ctrl']['versioningWS']) {

				$recs = $this->selectAllVersionsFromPages($table, $pageList, $wsid, $filter, $stage);
				if (intval($GLOBALS['TCA'][$table]['ctrl']['versioningWS']) === 2) {
					$moveRecs = $this->getMoveToPlaceHolderFromPages($table, $pageList, $wsid, $filter, $stage);
					$recs = array_merge($recs, $moveRecs);
				}
				$recs = $this->filterPermittedElements($recs, $table);
				if (count($recs)) {
                    if ('tt_content' === $table) {
                        $output['pages'] = array_merge($output['pages'], $recs);
                    }
					$output[$table] = $recs;
				}
			}
		}
		return $output;
	}


	/**
	 * Remove all records which are not permitted for the user
	 *
	 * @param array $recs
	 * @param string $table
	 * @return array
	 */
	protected function filterPermittedElements($recs, &$table) {
		$checkField = ('pages' === $table) ? 'uid' : 'wspid';
		$permittedElements = array();
		if (is_array($recs)) {
			foreach ($recs as $rec) {
                if ('tt_content' === $table) {
                    $page = t3lib_beFunc::getWorkspaceVersionOfRecord($GLOBALS['BE_USER']->workspace, 'pages', $rec[$checkField]);
                    $pageLive = t3lib_beFunc::getRecord('pages', $rec[$checkField], 'uid,pid,perms_userid,perms_user,perms_groupid,perms_group,perms_everybody,t3ver_oid');
                    if (false === $page) {
                        $page = $pageLive;
                    }
                } else {
                    $page = t3lib_beFunc::getRecord('pages', $rec[$checkField], 'uid,pid,perms_userid,perms_user,perms_groupid,perms_group,perms_everybody,t3ver_oid');
                }
				if ($GLOBALS['BE_USER']->doesUserHaveAccess($page, 1)) {
                    if ('tt_content' === $table) {
                        if ('0' === $page['t3ver_oid']) {
                            $page['t3ver_oid'] = $page['uid'];
                            $page['livepid'] = $page['pid'];
                            $page['wspid'] = $page['pid'];
                        } else {
                            $page['livepid'] = $pageLive['pid'];
                            $page['wspid'] = $pageLive['pid'];
                        }
                        $permittedElements[] = $page;
                    } else {
                        $permittedElements[] = $rec;
                    }
				}
			}
		}
		return $permittedElements;
	}
}


if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/nr_easy_workspace/Classes/Service/Workspaces.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/nr_easy_workspace/Classes/Service/Workspaces.php']);
}
?>
