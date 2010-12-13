<?php
/**
 * Netresearch easy workspace extension for Typo3
 *
 * PHP version 5
 *
 * @category Typo3
 * @package  nr_easy_workspace
 * @author   Alexander Opitz <alexander.opitz@netresearch.de>
 * @license  http://www.gnu.org/licenses/gpl.txt GPL v3
 * @link     http://www.netresearch.de
 */
if (!defined ('TYPO3_MODE')) {
    die ('Access denied.');
}

require_once 'conf.php';
//require_once $BACK_PATH . 'mod/user/ws/class.wslib_gui.php';

/**
 * The class which extends normal workspace gui.
 *
 * @category Typo3
 * @package  nr_easy_workspace
 * @author   Alexander Opitz <alexander.opitz@netresearch.de>
 * @license  http://www.gnu.org/licenses/gpl.txt GPL v3
 * @link     http://www.netresearch.de
 * @see      wslib_gui
 */
class ux_wslib_gui extends wslib_gui
{
    /**
     * Depending on the user TSConfig "workspace_showall" the original Overview
     * function or getWorkspaceOverviewEasy() will be called.
     *
     * @param object $doc    Document (to use for formatting)
     * @param int    $wsid   Workspace ID, if NULL, the value is obtained
     *                       from current BE user.
     * @param int    $filter If 0, than no filtering, if 10 than select for
     *                       publishing, otherwise stage value.
     * @param int    $pageId If greater than zero, than it is UID of page in LIVE
     *                       workspaces to select records for.
     *
     * @return string Generated HTML
     */
    function getWorkspaceOverview($doc, $wsid = null, $filter = 0, $pageId = -1)
    {
        global $BE_USER;
        if ($BE_USER->getTSConfigVal('workspace_showall') == 1) {
            $content = parent::getWorkspaceOverview(
                $doc, $wsid, $filter, $pageId
            );
        } else {
            $content = $this->getWorkspaceOverviewEasy(
                $doc, $wsid, $filter, $pageId
            );
        }
        return $content;
    }

    /**
     * Generates the output for the easy workspace.
     *
     * @param object $doc    Document (to use for formatting)
     * @param int    $wsid   Workspace ID, if NULL, the value is obtained
     *                       from current BE user.
     * @param int    $filter If 0, than no filtering, if 10 than select for
     *                       publishing, otherwise stage value.
     * @param int    $pageId If greater than zero, than it is UID of page in LIVE
     *                       workspaces to select records for.
     *
     * @return string Generated HTML
     */
    function getWorkspaceOverviewEasy($doc, $wsid = null, $filter = 0, $pageId = -1)
    {
        global $LANG, $BE_USER;

        // Setup
        $this->workspaceId = (!is_null($wsid) ? $wsid : $BE_USER->workspace);
        $this->doc = $doc;
        $this->initVars();

        // Initialize workspace object and request all pending versions:
        $wslibObj = t3lib_div::makeInstance('wslib');

        // Selecting ALL versions belonging to the workspace:
        $versions = $wslibObj->selectVersionsInWorkspace(
            $this->workspaceId, $filter, -99, $pageId
        );

        // Traverse versions and build page-display array:
        $pArray = array();
        $wmArray = array(); // is page in web mount?
        $rlArray = array(); // root line of page
        $pagePermsClause = $BE_USER->getPagePermsClause(1);
        foreach ($versions as $table => $records) {
            if (is_array($records)) {
                foreach ($records as $rec) {
                    $pageIdField = $table==='pages' ? 't3ver_oid' : 'realpid';
                    $recPageId = $rec[$pageIdField];
                    if (!isset($wmArray[$recPageId])) {
                        $wmArray[$recPageId] = $BE_USER->isInWebMount(
                            $recPageId, $pagePermsClause
                        );
                    }
                    if ($wmArray[$recPageId]) {
                        if (!isset($rlArray[$recPageId])) {
                            $rlArray[$recPageId] = t3lib_BEfunc::BEgetRootLine(
                                $recPageId, 'AND 1=1'
                            );
                        }
                        $this->displayWorkspaceOverview_setInPageArray(
                            $pArray,
                            $rlArray[$recPageId],
                            $table,
                            $rec
                        );
                    }
                }
            }
        }

            // Page-browser:
        $pointer = t3lib_div::_GP('browsePointer');
        /* We wont to show all */
        $browseStat = $this->cropWorkspaceOverview_list($pArray, $pointer, 1000000);
        $browse = $LANG->getLL('label_showing');
        $browse = sprintf(
            $browse,
            $browseStat['begin'],
            (
                $browseStat['end']
                ? $browseStat['end'].' out of '.$browseStat['allItems']
                : $browseStat['allItems']
            )
        );
        $browse .= '<br/>' . '<br/>';

        $workspaceOverviewList = $this->displayWorkspaceOverviewEasy_list(
            $pArray, $this->workspaceId
        );

        if ($workspaceOverviewList || $this->alwaysDisplayHeader) {
            $strTable = $this->getTableContent($workspaceOverviewList);

            return $browse . $strTable . $this->markupNewOriginals();
        }
        return '';
    }

    /**
     * Returns the table from template with content
     *
     * @param array $arTableRows The rows in HTML to include in Table
     *
     * @return string HTML generated output
     */
    function getTableContent($arTableRows) {
        $strTable = t3lib_parsehtml::getSubpart(
            $this->doc->moduleTemplate,
            '###WORKSPACE_OVERVIEW_TABLE###'
        );

        $strTable = $this->substituteTableHeader($strTable);

        $strTable = t3lib_parsehtml::substituteMarker(
            $strTable,
            '###WORKSPACE_OVERVIEW_CONTENT###',
            implode('',$arTableRows)
        );
        return $strTable;
    }

    /**
     * Returns the headline from table part of template with content
     *
     * @param string $strTable The Table template part
     *
     * @return string HTML generated output of table with headline.
     */
    function substituteTableHeader($strTable) {
        global $LANG;

        $arHeadline = array(
            '###LLL_LABEL_PAGETREE###' => $LANG->getLL('label_pagetree'),
            '###LLL_LABEL_ACTION###'   => $LANG->getLL('label_action'),
        );

        $strTable = t3lib_parsehtml::substituteMarkerArray(
            $strTable,
            $arHeadline
        );

        return $strTable;
    }

    /**
     * Sets the realName or username of a ID given user into the User array if
     * the user has an email adress.
     *
     * @param integer $id The   id of the user which should be set into array.
     * @param array   &$arUsers The users array to set the user into.
     *
     * @return void
     */
    function getUserIntoArray($id, &$arUsers)
    {
        if (isset($this->be_user_Array_full[$id]['email'])) {
            $arUsers[$id] = (
                $this->be_user_Array_full[$id]['realName'] !=  ''
                ? $this->be_user_Array_full[$id]['realName']
                : $this->be_user_Array_full[$id]['username']
            );
        }
    }

    /**
     * Rendering the table lines for the publish / review overview:
     * (Made for internal recursive calling)
     *
     * @param array   $arElements  Hierarchical storage of the elements to display
     *                             (see displayWorkspaceOverview() /
     *                             displayWorkspaceOverview_setInPageArray())
     * @param integer $nWsid       The id of the workspace which is in use.
     * @param array   $arTableRows Existing array of table rows to add to
     * @param integer $nDepth      Depth counter
     * @param boolean $bCanShow    If in user filemount and can be shown
     *
     * @return array  Table rows, see displayWorkspaceOverview()
     */
    function displayWorkspaceOverviewEasy_list(
        $arElements,
        $nWsid,
        $arTableRows = array(),
        $nDepth = 0,
        $bCanShow = false
    ) {
        global $TCA, $LANG;

        // Traverse $pArray
        if (is_array($arElements)) {
            foreach($arElements as $nElementId => $v) {
                if (t3lib_div::testInt($nElementId)) {
                    // Parent was not in tree but maybe this child?
                    if (!$bCanShow) {
                        $bCanShow = $this->isInWebMount($nElementId);
                    }
                    if ($bCanShow) {
                        $this->displayWorkspaceOverview_insertSubElements (
                            $arElements[$nElementId.'_'],
                            $nElementId,
                            $nWsid,
                            $arTableRows,
                            $nDepth
                        );
                    }
                    // Call recursively for sub-rows:
                    $arTableRows = $this->displayWorkspaceOverviewEasy_list(
                        $arElements[$nElementId.'.'],
                        $nWsid,
                        $arTableRows,
                        ($bCanShow ? $nDepth + 1 : $nDepth),
                        $bCanShow
                    );
                }
            }
        }
        return $arTableRows;
    }


    /**
     * Rendering the table lines for the publish / review overview:
     * Draws for one Subliment
     *
     * @param array   $arSubElements Hierarchical storage of the subelements
     * @param integer $nElementId    The Id of the Element for this subelements
     * @param integer $nWsid         The id of the workspace which is in use.
     * @param array   &$arTableRows  Existing array of table rows to add to
     * @param integer $nDepth      Depth counter
     *
     * @return void
     */
    function displayWorkspaceOverview_insertSubElements(
        $arSubElements,
        $nElementId,
        $nWsid,
        &$arTableRows,
        $nDepth
    ) {
        // Show as Tree if it has no Elements or show as changed
        // if elements are content. Or show with its elements.
        if (!is_array($arSubElements) || !is_array($arSubElements['pages'])) {
            $table = 'pages';
            $rec_off = false;
            if (is_array($arSubElements) && isset($arSubElements['tt_content'])) {
                $rec_off = true;
            }
            $rec_on = t3lib_BEfunc::getRecord($table, $nElementId);

            $arTableRows[] = $this->displayWorkspaceOverview_insertLine(
                $table, $rec_on, $rec_off, false, $nDepth
            );
        }

        // If there ARE elements on this level, print them:
        if (is_array($arSubElements)) {
            foreach($arSubElements as $table => $oidArray) {
            if ($table === 'tt_content') continue;
                foreach($oidArray as $oid => $recs) {
                    // Get CURRENT online record and icon based on "t3ver_oid":
                    $rec_on = t3lib_BEfunc::getRecord($table,$oid);
                    $rec_off = t3lib_BEfunc::getWorkspaceVersionOfRecord(
                        $nWsid, $table,$oid
                    );

                    $arTableRows[] = $this->displayWorkspaceOverview_insertLine(
                        $table, $rec_on, $rec_off, true, $nDepth
                    );
                }
            }
        }
    }

    function displayWorkspaceOverview_insertLine(
        $table, $rec_on, $rec_off, $bIsOffline, $nDepth
    ) {
        global $LANG;

        switch(($bIsOffline?(int)$rec_off['t3ver_stage']:$rec_on['t3ver_stage'])) {
            case 0:
                $sLabel = $this->icons('edit', $LANG->getLL('stage_editing'));
                $color = '#666666'; // TODO Use CSS?
                $lightcolor = '';
                break;
            case 1:
                $sLabel = $this->icons('review', $LANG->getLL('label_review'));
                $color = '#6666cc'; // TODO Use CSS?
                $lightcolor = '#a098c4';
                break;
            case 10:
                $sLabel = $this->icons('publish', $LANG->getLL('label_publish'));
                $color = '#66cc66'; // TODO Use CSS?
                $lightcolor = '#98c498';
                break;
            case -1:
                $sLabel = $this->icons('rejected', $LANG->getLL('label_rejected'));
                $color = '#ff0000'; // TODO Use CSS?
                $lightcolor = '#d68283';
                break;
            default:
                $sLabel = $this->icons('undefined', $LANG->getLL('label_undefined'));
                $color = '';
                $lightcolor = '';
                break;
        }

        $arIconRecord = ($bIsOffline ? $rec_off : $rec_on);
        // Compile table row:
        $this->line += 1;
        $strRow = '
            <tr class="' .  ($this->line % 2 ? 'bgColor4' : 'bgColor4-20') . '"'
            . (
                $lightcolor != ''
                ? 'style="background-color:' . $lightcolor . '"'
                : ''
            )
            . '>
                <td style="background-color:'.$color.'; text-align:center;">'.$sLabel.'</td>
                <td nowrap="nowrap">'.
                    $this->displayWorkspaceOverview_treeIconTitle($table, $arIconRecord, $nDepth).
                    '</td>
                <td nowrap="nowrap">'.
                    $this->displayWorkspaceOverview_commandLinks($table, $rec_on, $rec_off).
                    '</td>
            </tr>';
        return $strRow;
    }

    function icons($type, $strTitle='') {
        switch($type)   {
            case 'edit':
                $icon   = 'gfx/edit2.gif';
                $width  = 11;
                $height = 12;
            break;
            case 'review':
                $icon = 'gfx/lightning.png';
                $width  = 16;
                $height = 16;
            break;
            case 'publish':
                $icon = 'gfx/icon_ok2.gif';
                $width  = 18;
                $height = 16;
            break;
            case 'rejected':
                $icon = 'gfx/icon_warning.gif';
                $width  = 16;
                $height = 16;
            break;
            case 'undefined':
                $icon = 'gfx/icon_fatalerror.gif';
                $width  = 18;
                $height = 16;
            break;
        }
        if ($icon)  {
            return '<img'.
                t3lib_iconWorks::skinImg(
                    $this->doc->backPath,
                    $icon,
                    'width="'.$width.'" height="'.$height.'"'
                )
                .' class="absmiddle"'
                .' title="'.$strTitle.'"'
                .' alt="'.$strTitle.'"'
                .' />';
        }
    }

    /**
     * Create indentation, icon and title for the page tree.
     *
     * @param string  $table    Name of table
     * @param integer $arRecord Record to use for icon
     * @param integer $nDepth   Depth counter from displayWorkspaceOverview_list()
     *                          used to indent the icon and title
     *
     * @return string  HTML content
     */
    function displayWorkspaceOverview_treeIconTitle(
        $table, $arRecord, $nDepth
    ) {
        return '<img src="clear.gif" width="1" height="1" hspace="'
            .($nDepth * $this->pageTreeIndent)// Indenting page tree
            .'" align="top" alt="" />'
            .t3lib_iconWorks::getIconImage(
                $table,
                $arRecord,
                $this->doc->backPath,
                ' align="top" title="'
                .t3lib_BEfunc::getRecordIconAltText($arRecord, $table)
                .'"'
            )
            .htmlspecialchars(
                t3lib_div::fixed_lgd_cs(
                    $arRecord['title'],
                    $this->pageTreeIndent_titleLgd
                )
            );
    }

    /**
     * Links to publishing etc of a version
     *
     * @param string $table    Table name
     * @param array  &$rec_on  Online record
     * @param array  &$rec_off Offline record (version)
     *
     * @return string HTML content, mainly link tags and images.
     */
    function displayWorkspaceOverview_commandLinks($table, &$rec_on, &$rec_off) {
        global $LANG, $BE_USER;

        if ($this->publishAccess
            && (!($BE_USER->workspaceRec['publish_access']&1)
                || (int)$rec_off['t3ver_stage']===10
            )
        ) {
            if ($rec_off) {
                $actionLinks =
                    '<input type="checkbox" value="live_page"'
                    . ' name="cmd[' . $table . '][' . $rec_on['uid'] . '][version][action]"'
                    . ' onclick="testSendButtons()"/>';
                $actionLinks.=
                    '<a href="'.htmlspecialchars($this->doc->issueCommand(
                            '&cmd[' . $table . '][' . $rec_on['uid'] . '][version][action]=live_page'
                        ))
                    . '">'
                    . '<img ' . t3lib_iconWorks::skinImg(
                        $this->doc->backPath,
                        'gfx/insert1.gif',
                        'width="14" height="14"'
                    )
                    . ' alt="" align="top" title="' . $LANG->getLL('img_title_publish_page') . '" />'
                    . '</a>';
            } else {
                $actionLinks.=
                    '<img' . t3lib_iconWorks::skinImg(
                        $this->doc->backPath,
                        'gfx/clear.gif',
                        'width="28" height="14"'
                    )
                    . ' alt="" align="top" title="" />';
            }
            if ($table==='pages') {
                $actionLinks.=
                    '<a href="'.htmlspecialchars($this->doc->issueCommand(
                            '&cmd['.$table.']['.$rec_on['uid'].'][version][action]=live_page_down'
                        ))
                    . '">'
                    . '<img'.t3lib_iconWorks::skinImg(
                        $this->doc->backPath,
                            'gfx/insert2.gif',
                            'width="14" height="14"'
                        )
                    . ' alt="" align="top" title="' . $LANG->getLL('img_title_publish_tree') . '" />'
                    . '</a>';
            } else {
                $actionLinks.=
                    '<img' . t3lib_iconWorks::skinImg(
                        $this->doc->backPath,
                        'gfx/clear.gif',
                        'width="14" height="14"'
                    )
                    . ' alt="" align="top" title="" />';
            }
        } else {
            if ($rec_off) {
                $actionLinks =
                    '<input type="checkbox" value="live_page"'
                    . ' name="cmd['.$table.']['.$rec_on['uid'].'][version][action]"'
                    . ' onClick="testSendButtons()">';
            } else {
                $actionLinks.=
                    '<img' . t3lib_iconWorks::skinImg(
                        $this->doc->backPath,
                        'gfx/clear.gif',
                        'width="14" height="14"')
                    . ' alt="" align="top" title="" />';
            }
        }
        if ($rec_off) {
            $addGetVars = '';
            if ('pages_language_overlay' === $table) {
                $tempUid = $rec_on['pid'];
                $addGetVars .= '&L=' . $rec_off['sys_language_uid'];
            } elseif ('pages' === $table) {
                $tempUid = $rec_on['uid'];
            }
            if ($tempUid > 0) {
                $actionLinks.=
                    '<a href="#" onclick="' . htmlspecialchars(
                        t3lib_BEfunc::viewOnClick(
                            $tempUid,
                            $this->doc->backPath,
                            t3lib_BEfunc::BEgetRootLine($tempUid),
                            '',
                            '',
                            $addGetVars
                        )
                    )
                    . '">'
                    . '<img' . t3lib_iconWorks::skinImg(
                        $this->doc->backPath,
                        'gfx/zoom.gif',
                        'width="12" height="12"')
                    . ' title="" alt="" />'
                    . '</a>';
            }
        }
        return $actionLinks;
    }

    function isInWebMount($id,$readPerms='',$exitOnError=0)
    {
        global $TYPO3_CONF_VARS, $BE_USER;

        if (!$TYPO3_CONF_VARS['BE']['lockBeUserToDBmounts']
            || $BE_USER->isAdmin()
        ) {
            return 1;
        }

        $id = intval($id);

        // Check if input id is an offline version page in which case we will
        // map id to the online version:
        $checkRec = t3lib_beFUnc::getRecord('pages',$id,'pid,t3ver_oid');
        if ($checkRec['pid']==-1) {
            $id = intval($checkRec['t3ver_oid']);
        }

        if (!$readPerms) $readPerms = $BE_USER->getPagePermsClause(1);
        if ($id>0) {
            $wM = $GLOBALS['BE_USER']->returnWebmounts();
            $rL = t3lib_BEfunc::BEgetRootLine($id,' AND '.$readPerms);

            foreach($rL as $v) {
                if ($v['uid'] && in_array($v['uid'],$wM)) {
                    return $v['uid'];
                }
            }
        }
        if ($exitOnError) {
            t3lib_BEfunc::typo3PrintError(
                'Access Error',
                'This page is not within your DB-mounts',
                0
            );
            exit;
        }
    }
}