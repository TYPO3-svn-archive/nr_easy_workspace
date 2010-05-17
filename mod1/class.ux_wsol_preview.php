<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Netresearch GmbH & Co KG <info@netresearch.de>
*  All rights reserved
*
*  This script is free software; you can redistribute it and/or modify
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
/**
 * Implements the Workspace Preview screens
 *
 * @author Alexander Opitz <ao@netresearch.de>
 * @see wslib_gui
 * @package nr_easy_workspace
 */
define('TYPO3_PROCEED_IF_NO_USER', '1');

unset($MCONF);
require_once('conf.php');
require_once($BACK_PATH.'init.php');
require_once($BACK_PATH.'template.php');
require_once('class.wslib.php');

$LANG->includeLLFile('EXT:nr_easy_workspace/mod1/locallang.xml');

class ux_wsol_preview extends wsol_preview {

    var $workspace = 0;  // Which workspace to preview!

    /**
        * Main function of class
        *
        * @return void
        */
    function main() {
        global $LANG, $BE_USER;

        if ($this->isBeLogin()) {
            $this->workspace = $BE_USER->workspace;
        }

        // @NETRESEARCH Kompletter Umbau, single/multiview, button zur Livestellung, da rechte Seite weg gefallen ist.
        if ($header = t3lib_div::_GP('header')) {

        if (t3lib_div::_GP('show')=='both') {
                $toggle='single';
            } else {
                $toggle='both';
            }

            if ($header!=='live') {
                $headerText = 'Workspace Version ('.$this->workspace.'):';
                $color = 'green';
                $setLiveButton = '';
                if ($this->isBeLogin() && t3lib_div::_GP('id')!='') {
                    if ($BE_USER->workspacePublishAccess($BE_USER->workspace) && (!($BE_USER->workspaceRec['publish_access']&1) || (int)$rec_off['t3ver_stage']===10)) {
                        $setLiveButton = '<input type="submit" value="'.$LANG->getLL('set_live').'">';
                    }
                }
                $button = '<td align="right">
                            <form method="post" action="../../../tce_db.php?&cmd[pages]['.t3lib_div::_GP('id').'][version][action]=live_page&redirect='.urlencode('mod/user/ws/wsol_preview.php?id='.t3lib_div::_GP('id').'&L='.intval(t3lib_div::_GP('L')).'&show='.t3lib_div::_GP('show')).'" style="margin: 0, padding: 0" target="_parent">
                                <input type="button" value="'.$LANG->getLL('switch_previewmode').'" onclick="toggleFrame();">
                                '.$setLiveButton.'
                            </form>
                            <script type="text/javascript">
                                function toggleFrame() {
                                    parent.location.href="wsol_preview.php?id='.t3lib_div::_GP('id').'&L='.intval(t3lib_div::_GP('L')).'&show='.$toggle.'";
                                }
                            </script>
                        </td>';
            } else {
                $headerText = 'Live Version:';
                $color = 'red';
                $button = '';
            }

            $output =  '
                <html>
                    <head>
                        <title>Header</title>
                    </head>
                    <body bgcolor="'.$color.'">
                        <table width="100%" border="0" cellpadding="0" cellspacing="0"><tr><td valign="top">
                            <font face="verdana,arial" size="2" color="white"><b>'.$headerText.'</b></font>
                        </td>'.$button.'</tr></table>
                    </body>
                </html>';
        } elseif ($msg = t3lib_div::_GP('msg')) {
            switch($msg) {
                case 'branchpoint':
                    $message = '<b>No live page available!</b><br/><br/>
                    The previewed page was inside a "Branch" type version and has no traceable counterpart in the live workspace.';
                break;
                case 'newpage':
                    $message = '<b>New page!</b><br/><br/>
                    The previewed page is created in the workspace and has no counterpart in the live workspace.';
                break;
                default:
                    $message = 'Unknown message code "'.$msg.'"';
                break;
            }

            $output =  '
                <html>
                    <head>
                        <title>Message</title>
                    </head>
                    <body bgcolor="#eeeeee">
                        <div width="100%" height="100%" style="text-align: center; align: center;"><br/><br/><br/><br/><font face="verdana,arial" size="2" color="#666666">'.$message.'</font></div>
                    </body>
                </html>';

        } else {
            $this->generateUrls();
            $output = $this->printFrameset();
        }

        echo $output;
    }

    /**
        * URLs generated in $this->URL array
        *
        * @return void
        */
    function generateUrls() {
            // Live URL:
        $pageId = intval(t3lib_div::_GP('id'));
        $language = intval(t3lib_div::_GP('L'));

        // @NETRESEARCH URLs um Parameter für single/multiview erweitert
        $this->URL = array(
            'liveHeader' => 'wsol_preview.php?header=live',
            'draftHeader' => 'wsol_preview.php?header=draft&id='.$pageId.'&L='.$language.'&show='.t3lib_div::_GP('show'),
            'live' => t3lib_div::getIndpEnv('TYPO3_SITE_URL').'index.php?id='.$pageId.'&L='.$language.'&ADMCMD_noBeUser=1',
            'draft' => t3lib_div::getIndpEnv('TYPO3_SITE_URL').'index.php?id='.$pageId.'&L='.$language.'&ADMCMD_view=1&ADMCMD_editIcons=1&ADMCMD_previewWS='.$this->workspace,
            'versionMod' => '../../../sysext/version/cm1/index.php?id='.intval(t3lib_div::_GP('id')).'&diffOnly=1'
        );

        if ($this->isBeLogin()) {
                // Branchpoint; display error message then:
            if (t3lib_BEfunc::isPidInVersionizedBranch($pageId)=='branchpoint') {
                $this->URL['live'] = 'wsol_preview.php?msg=branchpoint';
            }

            $rec = t3lib_BEfunc::getRecord('pages',$pageId,'t3ver_state');
            if ((int)$rec['t3ver_state']===1) {
                $this->URL['live'] = 'wsol_preview.php?msg=newpage';
            }
        }
    }

    /**
        * Outputting frameset HTML code
        *
        * @return void
        */
    function printFrameset() {
        // @NETRESEARCH Add singleview, rechten Frame entfernt => Dadurch keine Unterscheidung für Reviewer nötig
        if (t3lib_div::_GP('show')=='both') {
            return '
            <html>
                <head>
                    <title>Preview and compare workspace version with live version</title>
                </head>
                <frameset cols="*" framespacing="3" frameborder="3" border="3">
                    <frameset rows="22,60%,20,40%" framespacing="3" frameborder="3" border="3">
                        <frame name="frame_drafth" src="'.htmlspecialchars($this->URL['draftHeader']).'" marginwidth="0" marginheight="0" frameborder="1" scrolling="no">
                        <frame name="frame_draft" src="'.htmlspecialchars($this->URL['draft']).'" marginwidth="0" marginheight="0" frameborder="1" scrolling="auto">
                        <frame name="frame_liveh" src="'.htmlspecialchars($this->URL['liveHeader']).'" marginwidth="0" marginheight="0" frameborder="1" scrolling="no">
                        <frame name="frame_live" src="'.htmlspecialchars($this->URL['live']).'" marginwidth="0" marginheight="0" frameborder="1" scrolling="auto">
                    </frameset>
                </frameset>
            </html>';
        } else {
            return '
            <html>
                <head>
                    <title>Preview and compare workspace version with live version</title>
                </head>
                <frameset cols="*" framespacing="3" frameborder="3" border="3">
                    <frameset rows="22,*" framespacing="3" frameborder="3" border="3">
                        <frame name="frame_drafth" src="'.htmlspecialchars($this->URL['draftHeader']).'" marginwidth="0" marginheight="0" frameborder="1" scrolling="no">
                        <frame name="frame_draft" src="'.htmlspecialchars($this->URL['draft']).'" marginwidth="0" marginheight="0" frameborder="1" scrolling="auto">
                    </frameset>
                </frameset>
            </html>';
        }
    }

    /**
        * Checks if a backend user is logged in. Due to the line "define('TYPO3_PROCEED_IF_NO_USER', '1');" the backend is initialized even if no backend user was authenticated. This is in order to allow previews through this module of yet not-logged in users.
        *
        * @return boolean  True, if there is a logged in backend user.
        */
    function isBeLogin() {
        return is_array($GLOBALS['BE_USER']->user);
    }
}


$file = 'ext/nr_easy_workspace/mod1/class.ux_wsol_preview.php';
if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS'][$file]) {
    include_once $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS'][$file];
}

$previewObject = t3lib_div::makeInstance('ux_wsol_preview');
$previewObject->main();
?>