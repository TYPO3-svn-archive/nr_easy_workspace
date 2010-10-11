<?php
declare(encoding='UTF-8');
/**
 * Netresearch easy workspace extension for Typo3
 *
 * PHP version 5
 *
 * @category Typo3
 * @package  nr_easy_workspace
 * @author   Alexander Opitz <alexander.opitz@netresearch.de>
 * @author   Christian Weiske <christian.weiske@netresearch.de>
 * @license  http://www.gnu.org/licenses/gpl.txt GPL v3
 * @link     http://mantis.nr/view.php?id=4424
 */

/**
 * Hooks for Typo3 TCE and pagetree
 *
 * @category Typo3
 * @package  nr_easy_workspace
 * @author   Alexander Opitz <alexander.opitz@netresearch.de>
 * @author   Christian Weiske <christian.weiske@netresearch.de>
 * @license  http://www.gnu.org/licenses/gpl.txt GPL v3
 * @link     http://mantis.nr/view.php?id=4424
 */
class tx_nreasyworkspace_tcemain
{
    /**
    * Debugging on or off.
    * When activated, logging to devlog is active.
    *
    * @var boolean
    */
    private $debug = false;



    /**
     * This method is called by a hook in the TYPO3 Core Engine (TCEmain).
     *
     * @param string $command    ???
     * @param string $table      The table TCEmain is currently processing
     * @param string $id         The records id (if any)
     * @param mixed  $value      ???
     * @param object &$reference Reference to the parent object (TCEmain)
     *
     * @return void
     */
    public function processCmdmap_postProcess(
        $command, $table, $id, $value, &$reference
    ) {
        if ($this->debug) {
            t3lib_div::devLog(
                'processCmdmap_postProcess', 'nr_easy_workspace', 0,
                array($command, $table, $id, $value)
            );
        }

        switch ($command) {
        case 'version':
            switch ((string)$value['action']) {
            case 'live_page':
                $this->live_page($table, $id, $reference->cmdmap, $reference);
                break;
            case 'live_page_down':
                $this->live_page($table, $id, $reference->cmdmap, $reference, true);
                break;
            }
            break;
        }
    }//function processCmdmap_postProcess(..)



    /**
    * Push the given record into live workspace
    *
    * @param string  $table      Name of table that is being processed
    * @param integer $id         uid of record being processed
    * @param string  $strCmd     Command map from &$reference
    * @param object  &$reference Reference to parent object (TCEmain)
    * @param boolean $recursive  Process child records, too
    *
    * @return void
    */
    protected function live_page(
        $table, $id, $strCmd, &$reference, $recursive = false
    ) {
        global $TCA, $BE_USER;

        $wsid = $BE_USER->workspace;
        // Nicht live schalten sonder reviewn?
        if (isset($strCmd['pages'][0]['version']['review'])) {
            $this->review_page($table, $id, $strCmd, $reference);
            return;
        }

        $curVersion = t3lib_BEfunc::getRecord($table, $id, '*');
        $offVersion = t3lib_BEfunc::getWorkspaceVersionOfRecord(
            $wsid, $table, $id, '*'
        );

        if (is_array($offVersion) && $id !== $offVersion['uid']) {
            $reference->version_swap($table, $id, $offVersion['uid']);
        } else {
            $this->setT3verTo(0, $table, $id);
        }

        if ($table != 'pages') {
            return;
        }

        $arInPage = $this->selectVersionsInWorkspace(
            $wsid, $filter = 0, $stage = -99, $id
        );
        foreach ($arInPage as $onTable => $arElements) {
            if ($onTable == 'pages') {
                if ($recursive) {
                    foreach ($arElements as $arElement) {
                        $this->live_page(
                            'pages', $arElement['uid'], $strCmd, $reference,
                            $recursive
                        );
                    }
                } else {
                    continue;
                }
            } else {
                foreach ($arElements as $arElement) {
                    $reference->version_swap(
                        $onTable, $arElement['t3ver_oid'], $arElement['uid']
                    );
                }
            }
        }
        if ($recursive) {
            $arPages = t3lib_BEfunc::getRecordsByField('pages', 'pid', $id);
            if (is_array($arPages)) {
                foreach ($arPages as $arPage) {
                    $this->live_page(
                        'pages', $arPage['uid'], $strCmd, $reference, $recursive
                    );
                }
            }
        }
    }//protected function live_page(..)



    /**
    * Send page to reviewer
    * "Setzen der Seiten auf Reviewstatus, sowie eintragen in zu sendende
    *  Mail Liste-"
    *
    * @param string  $table      Name of table that is being processed
    * @param integer $id         uid of record being processed
    * @param string  $strCmd     Command map from &$reference
    * @param object  &$reference Reference to parent object (TCEmain)
    * @param boolean $recursive  Process child records, too
    *
    * @return void
    */
    protected function review_page(
        $table, $id, $strCmd, &$reference, $recursive = false
    ) {
        global $BE_USER, $TYPO3_CONF_VARS;

        $arMessages = array(
            'Bitte Seiten freigeben.',
            'Bitte Seiten überarbeiten und freigeben',
            'Bitte Seiten freigeben oder Kontakt aufnehmen, nicht überarbeiten',
            'Please publish pages',
            'Please revise and publish pages',
            'Please publish pages or contact me, do not revise'
        );

        //kick out people without edit access
        if (!$reference->checkRecordUpdateAccess($table, $id)) {
            $reference->newlog(
                'Attempt to set stage for record failed'
                . ' because you do not have edit access',
                1
            );
            return;
        }



        $wsid = $BE_USER->workspace;

        $curVersion = t3lib_BEfunc::getRecord($table, $id, '*');
        $offVersion = t3lib_BEfunc::getWorkspaceVersionOfRecord(
            $wsid, $table, $id, '*'
        );

        $stat = $reference->BE_USER->checkWorkspaceCurrent();

        $bHasAccess = t3lib_div::inList(
            'admin,online,offline,reviewer,owner',
            $stat['_ACCESS']
        );
        if (!$bHasAccess && !($stageId <= 1 && $stat['_ACCESS']==='member')) {
            $reference->newlog(
                'The member user tried to set a stage value "'
                . $stageId . '" which was not allowed',
                1
            );
            return;
        }

        $arConfVars
            =& $TYPO3_CONF_VARS['SC_OPTIONS']['tx_nreasyworkspace_tcemain'];

        $arSendMails = $arConfVars['sendMails'];
        // Set stage of record:

        if (is_array($offVersion)) {
            $this->setT3verTo(10, $table, $offVersion['uid']);
        } else {
            $this->setT3verTo(10, $table, $id);
        }

        // Set the elements inside the page to review
        $arInPage = $this->selectVersionsInWorkspace(
            $wsid, $filter = 0, $stage = -99, $id
        );

        foreach ($arInPage as $table => $arElements) {
            foreach ($arElements as $arElement) {
                $this->setT3verTo(10, $table, $arElement['uid']);
            }
        }

        // Set change into logfile
        $reference->newlog(
            'Stage for record was changed to ' . $stageId
            . '. Comment was: "' . substr($comment, 0, 100) . '"'
        );
        //TEMPORARY, except 6-30 as action/detail number which is
        // observed elsewhere!
        $reference->log(
            $table, $id, 6, 0, 0, 'Stage raised...',
            30, array('comment' => $comment, 'stage' => $stageId)
        );

        $emails = $reference->notifyStageChange_getEmails(
            $strCmd['pages'][0]['version']['reviewer'], true
        );
        $emails = array_unique($emails);

            // Send email:
        if (count($emails)) {
            $message = sprintf(
                '
%s

%s

                ',
                ($strCmd['pages'][0]['version']['msg_ind'] != ''
                    ? $strCmd['pages'][0]['version']['msg_ind']
                    : $arMessages[$strCmd['pages'][0]['version']['msg']]
                ),
                "%s" // Späteres ersetzen durch alle zu reviewenden Seiten
            );
            $arSendMails['to']      = $emails;
            $arSendMails['message'] = $message;
            $arSendMails['title']   = ($strCmd['pages'][0]['version']['msg'] != ''
                ? $arMessages[$strCmd['pages'][0]['version']['msg']]
                : 'TYPO3 Workspace Note: Please review pages'
            );
            $arSendMails['toreplace'] .= "\n"
                . $this->getPreviewUrl($id, $addGetVars, $anchor);
        }

        $arConfVars['sendMails'] = $arSendMails;
    }//protected function review_page(..)

    /**
     * Generates preview url with domain record for page.
     *
     * @param integer $id         Id of page to link
     * @param string  $addGetVars Possible get vars to add to url.
     * @param string  $anchor     Possible anchor to add to url.
     *
     * @return string Link to preview of page.
     */
    public function getPreviewUrl($id, $addGetVars, $anchor)
    {
        global $BE_USER;
        $rootLine = t3lib_BEfunc::BEgetRootLine($id);

        $strPreview  = '/' . TYPO3_mainDir
            . 'mod/user/ws/wsol_preview.php?id=' . $id
            . '&wsid=' . $BE_USER->workspace;

        if ($rootLine)  {
            $parts = parse_url(t3lib_div::getIndpEnv('TYPO3_SITE_URL'));
            if (t3lib_BEfunc::getDomainStartPage($parts['host'], $parts['path'])) {
                $preUrl_temp = t3lib_BEfunc::firstDomainRecord($rootLine);
            }
        }
        $preUrl = $preUrl_temp
            ? (t3lib_div::getIndpEnv('TYPO3_SSL') ? 'https://' : 'http://') . $preUrl_temp
            : t3lib_div::getIndpEnv('TYPO3_SITE_URL');

        return $preUrl . $strPreview . $addGetVars . $anchor;
    } // public function getPreviewUrl(..)

    /**
    * Changes the stage version (t3ver_stage) of elements in database.
    *
    * Stages:
    * -1 rejected
    *  0 editing
    *  1 review
    * 10 publish
    *
    * @param integer $nStage Staging version (t3ver_stage)
    * @param string  $table  Table to do that on
    * @param integer $id     uid to change stage on
    *
    * @return void
    */
    protected function setT3verTo($nStage, $table, $id) {
        $sArray = array(
            't3ver_stage' => $nStage
        );
        $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
            $table, 'uid=' . intval($id), $sArray
        );
    }

    /**
    * Selectieren aller Elemente in einem Workspace, von einer
    * ID aus gehend über alle Tabellen
    *
    * @param integer $wsid   ID of current workspace, see $BE_USER->workspace
    * @param integer $filter ???
    * @param integer $stage  ???
    * @param integer $pageId ID of page to get element versions from
    *
    * @return array Array of elements in the given workspace and the given
    *               page id
    */
    function selectVersionsInWorkspace(
        $wsid, $filter = 0, $stage = -99, $pageId = -1
    ) {
        global $TCA;

        $wsid   = intval($wsid);
        $filter = intval($filter);
        $output = array();

        // Traversing all tables supporting versioning:
        foreach ($TCA as $table => $cfg) {
            if ($TCA[$table]['ctrl']['versioningWS']) {
                //Select all records from this table in the database
                // from the workspace
                //This joins the online version with the offline version
                // as tables A and B
                $output[$table] = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
                    'A.uid, A.t3ver_oid,'
                    . ($table === 'pages'
                        ? ' A.t3ver_swapmode,'
                        :''
                    )
                    . ' B.pid AS realpid',
                    $table . ' A,' . $table.' B',
                    //Table A is the offline version and pid=-1 defines offline
                    'A.pid=-1'
                    . ($pageId!=-1
                        ? ($table==='pages'
                            ? ' AND B.uid='.intval($pageId)
                            : ' AND B.pid='.intval($pageId)
                        )
                        : ''
                    )
                    //For "real" workspace numbers, select by that.
                    //If = -98, select all that are NOT online (zero).
                    //Anything else below -1 will not select on the wsid
                    // and therefore select all!
                    . ($wsid > -98
                        ? ' AND A.t3ver_wsid=' . $wsid
                        : ($wsid === -98
                            ? ' AND A.t3ver_wsid!=0'
                            : ''
                        )
                    )
                    //lifecycle filter:
                    // 1 = select all drafts (never-published),
                    // 2 = select all published one or more times (archive/multiple)
                    . ($filter===1
                        ? ' AND A.t3ver_count=0'
                        : ($filter === 2
                            ? ' AND A.t3ver_count>0'
                            : ''
                        )
                    )
                    . ($stage!=-99
                        ? ' AND A.t3ver_stage=' . intval($stage)
                        : ''
                    )
                    //Table B (online) must have PID >= 0 to signify being online.
                    . ' AND B.pid>=0'
                    //... and finally the join between the two tables.
                    . ' AND A.t3ver_oid=B.uid'
                    . t3lib_BEfunc::deleteClause($table, 'A')
                    . t3lib_BEfunc::deleteClause($table, 'B'),
                    '',
                    //Order by UID, mostly to have a sorting in the
                    // backend overview module which doesn't "jump around"
                    // when swapping.
                    'B.uid'
                );
            }
        }

        return $output;
    }//function selectVersionsInWorkspace(..)



    /**
    * Add an extra symbol to the page in the page tree in the backend
    * when the page is under review.
    *
    * @param array       $arRecordInfo Array with table name and record uid,
    *                                  e.g. array('pages', 12)
    * @param webPageTree &$tree        Tree object that is being generated
    *
    * @return string Additional data that shall be displayed in the tree item
    */
    public function lockedIcon($arRecordInfo, &$tree)
    {
        global $BE_USER, $LANG;

        if ($BE_USER->getTSConfigVal('treeview_showreviewstate') === 'none') {
            return '';
        }
        list($strTable, $nUid) = $arRecordInfo;
        if ($strTable != 'pages') {
            return '';
        }
        //t3lib_BEfunc::getRecordWSOL($table, $uid, $fields = '*', $where = '',)
        $arRecord = t3lib_BEfunc::getRecordWSOL(
            $strTable, $nUid,
            'uid,t3ver_stage,t3ver_wsid'
        );

        if ($arRecord['t3ver_wsid'] == 0) {
            return '';
        }
        
        switch((int)$arRecord['t3ver_stage']) {
            case 0:
                $strImage = $this->icons('edit', $LANG->getLL('stage_editing'));
                break;
            case 1:
                $strImage = $this->icons('review', $LANG->getLL('label_review'));
                break;
            case 10:
                $strImage = $this->icons('publish', $LANG->getLL('label_publish'));
                break;
            case -1:
                $strImage = $this->icons('rejected', $LANG->getLL('label_rejected'));
                break;
            default:
                $strImage = $this->icons('undefined', $LANG->getLL('label_undefined'));
                break;
        }
        //add icon to tell the page is under review
        return $strImage;
//            '<img src="/typo3/gfx/lightning.png" alt="Review" title="In Review"/>';
    }//public function lockedIcon(..)

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
                $width  = 18;
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
}//class tx_nreasyworkspace_tcemain


$file = 'ext/nr_easy_workspace/class.tx_nreasyworkspace_tcemain.php';
if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS'][$file]) {
    include_once $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS'][$file];
}

?>