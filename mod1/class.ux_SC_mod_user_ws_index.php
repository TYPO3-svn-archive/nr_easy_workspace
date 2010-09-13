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
if(!defined ('TYPO3_MODE')) {
    die ('Access denied.');
}

require_once 'conf.php' ;
require_once $BACK_PATH . 'mod/user/ws/index.php';
require_once PATH_typo3 . 'sysext/lang/lang.php';

$LANG = t3lib_div::makeInstance('language');
$LANG->init($BE_USER->uc['lang']);

$LANG->includeLLFile('EXT:nr_easy_workspace/mod1/locallang.xml');

/**
 * The class which extends normal workspace gui.
 *
 * @category Typo3
 * @package  nr_easy_workspace
 * @author   Alexander Opitz <alexander.opitz@netresearch.de>
 * @license  http://www.gnu.org/licenses/gpl.txt GPL v3
 * @link     http://www.netresearch.de
 * @see      SC_mod_user_ws_index
 */
class ux_SC_mod_user_ws_index extends SC_mod_user_ws_index
{
    /**
     * Executes action for selected elements, if any is sent:
     *
     * @return void
     */
    function execute()
    {
        $post = t3lib_div::_POST();
        if (is_array($post) && isset($post['cmd'])) {
            $tce = t3lib_div::makeInstance('t3lib_TCEmain');
            $tce->stripslashes_values = 0;
            $tce->start(array(), $post['cmd']);
            $tce->process_cmdmap();
            $this->sendEmails();
        } else {
            parent::execute();
        }
    }

    /**
     * Initialize output with JS an header
     *
     * @return void
     */
    function init()
    {
        global $TYPO3_CONF_VARS, $LANG;

        parent::init();

        $filename = 'EXT:nr_easy_workspace/templates/ws.html';

        $strLLFile = 'fileadmin/workspaceMessages/locallang.xml';

        if (is_file(PATH_site . $strLLFile)) {
            $this->messageLL = $LANG->includeLLFile($strLLFile, 0);
        } else {
            $this->messageLL = $GLOBALS['LOCAL_LANG'];
        }

        // If you symlink your typo3, then php resolves this symlinks
        // and so you can't change from typo3 to typo3conf via ../ path changes.
        $strBackPathSave = $this->doc->backPath;
        $this->doc->backPath = '';
        //Typo3 before 4.3 don't handle extension path
        $filename = t3lib_extMgm::extPath('nr_easy_workspace') .'templates/ws.html';
        $this->doc->setModuleTemplate($filename);
        $this->doc->backPath = $strBackPathSave;

        $arText = array();
        for ($i = 0; $i < 100; $i++) {
            $text = $LANG->getLLL('mailtext_' . $i, $this->messageLL);
            if (empty($text)) {
                break;
            }
            $arText[] = sprintf($text, $TYPO3_CONF_VARS['SYS']['sitename']);
        }

        $this->doc->JScode.= $this->doc->wrapScriptTags(
            'arTexte = ' . json_encode($arText) . ';
            function testSendButtons() {
                arElements = document.getElementsByTagName("input");
                bSomething = false;
                for(var i = 0; i < arElements.length; i++) {
                    if (arElements[i].type == "checkbox" && arElements[i].checked) {
                        bSomething = true;
                        break;
                    }
                }
                if (bSomething) {
                    setButton("submit_marked", true);
                    reviewer = document.getElementById("reviewer");
                    message_ind = document.getElementById("message_ind");
                    if (reviewer.value!="" && message_ind.value!="") {
                        setButton("submit_marked_to_review", true);
                    } else {
                        setButton("submit_marked_to_review", false);
                    }
                } else {
                    setButton("submit_marked_to_review", false);
                    setButton("submit_marked_to_review", false);
                }
            }

            function setButton(name, enable) {
                button = document.getElementById(name);
                if (button) {
                    if (enable) {
                        button.disabled = false;
                        button.style.opacity = 1;
                    } else {
                        button.disabled = true;
                        button.style.opacity = 0.3;
                    }
                }
            }

            function updateText() {
                message = document.getElementById("message");
                message_ind = document.getElementById("message_ind");
                if (message.value != "") {
                    message_ind.value = arTexte[message.value];
                }
            }'
        );
    }

    /**
     * Main output function
     *
     * @return void
     */
    function main()
    {
        global $LANG, $BE_USER, $BACK_PATH;

        // See if we need to switch workspace
        $changeWorkspace = t3lib_div::_GET('changeWorkspace');
        if ($changeWorkspace != '') {
            $BE_USER->setWorkspace($changeWorkspace);
            $this->content .= $this->doc->wrapScriptTags(
                'top.location.href="' . $BACK_PATH . t3lib_BEfunc::getBackendScript() . '";'
            );
        } else {
            // Starting page:
            $this->content .= $this->doc->header($LANG->getLL('title'));
            $this->content .= $this->doc->spacer(5);

            // Get usernames and groupnames
            $be_group_Array = t3lib_BEfunc::getListGroupNames('title,uid');
            $groupArray = array_keys($be_group_Array);

            // Need 'admin' field for t3lib_iconWorks::getIconImage()
            $this->be_user_Array_full = $this->be_user_Array
                = t3lib_BEfunc::getUserNames(
                    'username,usergroup,usergroup_cached_list,uid,admin'
                    . ',workspace_perms,email,realName'
                );
            if (!$BE_USER->isAdmin()) {
                $this->be_user_Array = t3lib_BEfunc::blindUserNames(
                    $this->be_user_Array, $groupArray, 1
                );
            }

            if ($BE_USER->getTSConfigVal('workspace_showall') == 1) {
                    // Build top menu:
                $menuItems = array();
                $menuItems[] = array(
                    'label' => $LANG->getLL('menuitem_review'),
                    'content' => $this->moduleContent_publish()
                );
                $menuItems[] = array(
                    'label' => $LANG->getLL('menuitem_workspaces'),
                    'content' => $this->moduleContent_workspaceList()
                );

                    // Add hidden fields and create tabs:
                $content = $this->doc->getDynTabMenu($menuItems,'user_ws');
            } else {
                if ($BE_USER->workspace != 0) {
                    // if workspace is not live
                    $content = $this->displayWorkspaceOverview();
                } else {
                    $content = $this->doc->section(
                        $LANG->getLL('live_not_useable'),
                        '', true, false, 3
                    );
                }
                $strWorkspace = (
                    $BE_USER->workspace == 0
                    ? 'LIVE'
                    : $BE_USER->workspaceRec['title']
                );
                $content = $this->doc->section(
                    $LANG->getLL('label_workspace') . ' ' . $strWorkspace,
                    $content, 1, 1
                );
            }
            $this->content.=$this->doc->section('', $content, 0, 1);
        }
            // Setting up the buttons and markers for docheader
        $docHeaderButtons = $this->getButtons();
        $markers['CONTENT'] = $this->content;

            // Build the <body> for the module
        $this->content = $this->doc->startPage($LANG->getLL('title'));
        $this->content.= $this->doc->moduleBody(
            $this->pageinfo, $docHeaderButtons, $markers
        );
        $this->content.= $this->doc->endPage();

        $this->content = $this->doc->insertStylesAndJS($this->content);
    }

    /**
     * Depending on TSconfig, it returns the Workspace
     *
     * @return string HTML rendered output
     */
    function displayWorkspaceOverview()
    {
        global $BE_USER;
        $content = parent::displayWorkspaceOverview();
        if ($BE_USER->getTSConfigVal('workspace_showall') != 1) {
            $content.= $this->getReviewersOverviewEasy();
        }
        return $content;
    }

    /**
     * Gets list of available reviewers for this workspace
     *
     * @return array Array of BE_Users which are reviewer
     */
    function getReviewers()
    {
        global $BE_USER;
        $list = $BE_USER->workspaceRec['reviewers'];
        $content_array = array();
        if ($list != '') {
            $userIDs = explode(',', $list);

                // get user names and sort
            $regExp = '/^(be_[^_]+)_(\d+)$/';
            $groups = false;
            foreach ($userIDs as $userUID) {
                $id = $userUID;

                if (preg_match($regExp, $userUID)) {
                    $table = preg_replace($regExp, '\1', $userUID);
                    $id = intval(preg_replace($regExp, '\2', $userUID));
                    if ($table == 'be_users') {
                        // user
                        $this->getUserIntoArray($id, $content_array);
                    } else {
                        // group
                        if (false === $groups) {
                            $groups = t3lib_BEfunc::getGroupNames();
                        }
                        foreach ($this->be_user_Array_full as $user) {
                            $arGroups = split(',', $user['usergroup_cached_list']);
                            if (in_array($id, $arGroups)) {
                                $this->getUserIntoArray(
                                    $user['uid'], $content_array
                                );
                            }
                        }
                    }
                } else {
                    $this->getUserIntoArray($userUID, $content_array);
                }
            }
            asort($content_array);
        }
        return $content_array;
    }

    /**
     * Puts the user with the id into the array with realname or username, if
     * an email is defined for the user.
     *
     * @param integer $id       The user id.
     * @param array   &$arUsers The array with users.
     *
     * @return void
     */
    function getUserIntoArray($id, &$arUsers)
    {
        if (isset($this->be_user_Array_full[$id]['email'])) {
            $arUsers[$id] = (
                $this->be_user_Array_full[$id]['realName'] != ''
                ? $this->be_user_Array_full[$id]['realName']
                : $this->be_user_Array_full[$id]['username']
            );
        }
    }

    /**
     * Builds the reviewer part of the workspace overview.
     *
     * @return string HTML generated from template
     */
    function getReviewersOverviewEasy()
    {
        global $LANG, $BE_USER;
        // @NETRESEARCH Die Mitteilungsbox an die Reviewer
        $arMessages = array();
        for ($i = 0; $i < 100; $i++) {
            $text = $LANG->getLLL('mailtext_' . $i, $this->messageLL);
            if (empty($text)) {
                break;
            }
            $arMessages[] = $LANG->getLLL('mailtext_' . $i . '_header', $this->messageLL);
        }
        $arReviewers = $this->getReviewers();
        $strRevOption = '<option value="">'
            . $LANG->getLL('select_reviewer')
            . '</option>';
        $strMsgOption = '<option value="">'
            . $LANG->getLL('select_message')
            . '</option>';

        $strTable = t3lib_parsehtml::getSubpart(
            $this->doc->moduleTemplate,
            '###WORKSPACE_REVIEWER_TABLE###'
        );

        $arLLL = array(
            '###LLL_INFORM_REVIEWER###'      => $LANG->getLL('inform_reviewer'),
            '###LLL_REVIEWER###'             => $LANG->getLL('reviewer'),
            '###LLL_REVIEWER_MESSAGE###'     => $LANG->getLL('reviewer_message'),
            '###LLL_REVIEWER_MESSAGE_IND###' => $LANG->getLL('reviewer_message_ind'),
            '###LLL_SUBMIT_MARKED_TO_REVIEW###'
                => $LANG->getLL('submit_marked_to_review'),
            '###LLL_NO_REVIEWERS###'         => $LANG->getLL('no_reviewers'),
        );

        $strTable = t3lib_parsehtml::substituteMarkerArray(
            $strTable,
            $arLLL
        );

        if (count($arReviewers)) {
            foreach ($arReviewers as $uid => $name) {
                $strRevOption.= '<option value="'.$uid.'">'.$name.'</option>';
            }
            foreach ($arMessages as $uid => $name) {
                $strMsgOption.= '<option value="'.$uid.'">'.$name.'</option>';
            }
            $arData = array(
                '###OPTIONS_REVIEWER###' => $strRevOption,
                '###OPTIONS_MESSAGES###' => $strMsgOption,
            );

            $strTable = t3lib_parsehtml::substituteMarkerArray(
                $strTable,
                $arData
            );

            $strTable = t3lib_parsehtml::substituteSubpart(
                $strTable,
                '###WORKSPACE_NO_REVIEWER_AVAILABLE###',
                ''
            );
        } else {
            $strTable = t3lib_parsehtml::substituteSubpart(
                $strTable,
                '###WORKSPACE_REVIEWER_AVAILABLE###',
                ''
            );
        }

        return $strTable;
    }

    /**
     * Sends out the reviewer mails if set in TYPO3_CONF_VARS
     *
     * @return void
     */
    function sendEmails()
    {
        global $TYPO3_CONF_VARS, $BE_USER, $LANG;
        $arSendMails
            = $TYPO3_CONF_VARS['SC_OPTIONS']['tx_nreasyworkspace_tcemain']['sendMails'];

        if (isset($arSendMails['to']) && $arSendMails['to'] != '') {
            $emails = $arSendMails['to'];
            $message = sprintf($arSendMails['message'], $arSendMails['toreplace']);
            $message .= "\n" . $LANG->getLL('message_notice');
            $message .= "\n\n" . $BE_USER->user['realName'];
            $message .= "\n" .  (
                $BE_USER->user['email'] != ''
                ? $BE_USER->user['email'] : ''
            );

            $returnPath = (
                $BE_USER->user['email'] != ''
                ? $BE_USER->user['realName'] . ' <' . $BE_USER->user['email'] . '>'
                : (
                    $TYPO3_CONF_VARS['SYS']['siteemail'] != ''
                    ? $TYPO3_CONF_VARS['SYS']['siteemail'] :
                    'YourTypo3@installation.org'
                )
            );
            t3lib_div::plainMailEncoded(
                implode(',',$emails),
                $arSendMails['title'],
                trim($message),
                'From: '.$returnPath."\n"
            );
        }
    }
}