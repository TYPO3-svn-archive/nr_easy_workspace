<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009, 2010 Netresearch GmbH & Co KG <info@netresearch.de>
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
 * @author Alexander Opitz <alexander.opitz@netresearch.de>
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

class ux_wsol_preview extends wsol_preview
{
    var $workspace = 0;  // Which workspace to preview!

    /**
     * Main function of class
     *
     * @return void
     */
    function main()
    {
        global $LANG, $BE_USER;

        if ($this->isBeLogin()) {
            $this->workspace = $BE_USER->workspace;
            $this->arWorkspace = $BE_USER->workspaceRec;
            $wsid = (int) t3lib_div::_GP('wsid');

            if ($wsid > 0 && $wsid != $this->workspace) {
                $arWorkspace = $BE_USER->checkWorkspace($wsid);
                if (is_array($arWorkspace)) {
                    $this->workspace = $wsid;
                    $this->arWorkspace = $arWorkspace;
                } else {
                    $arOutputParts = array(
                        'title' => 'Error',
                        'text' => 'You have no rights on this workspace',
                        'color' => 'red',
                        'buttons' => '',
                    );
                    echo $this->output($arOutputParts);
                    exit(0);
                }
            }
        }

        switch (t3lib_div::_GP('show')) {
            case 'both':
                $this->strShow   = 'both';
                $this->strToggle = 'single';
            case 'single':
            default:
                $this->strShow   = 'single';
                $this->strToggle = 'both';
            break;
        }
        
        $this->pageId = intval(t3lib_div::_GP('id'));
        $this->language = intval(t3lib_div::_GP('L'));


        if ($strHeader = t3lib_div::_GP('header')) {
            $output = $this->outputHeaders($strHeader);
        } elseif ($strMessage = t3lib_div::_GP('msg')) {
            $output = $this->outputMessages($strMessage);
        } else {
            $this->generateUrls();
            $output = $this->printFrameset();
        }

        echo $output;
    }

    /**
     * Generates small Message output inside the frames.
     *
     * @param array $arOutputParts The parts to output
     *   title   => Title of page
     *   color   => Background color
     *   text    => Text to output
     *   buttons => Extra buttons inside the table
     *
     * @return string the HTML to output to user
     */
    function output($arOutputParts)
    {
        $output = '
            <html>
                <head>
                    <title>' . $arOutputParts['title'] . '</title>
                </head>
                <body bgcolor="'.$arOutputParts['color'].'">
                    <table width="100%" border="0" cellpadding="0" cellspacing="0">
                        <tr><td valign="top">
                        <font face="verdana,arial" size="2" color="white"><b>
                            ' . $arOutputParts['text'] . '
                        </b></font>
                        </td>'.$arOutputParts['buttons'].'</tr>
                    </table>
                </body>
            </html>';
        return $output;
    }


    /**
     * Returns the HTML for header output
     *
     * @param string $strHeader Header to view
     *
     * @return string the HTML to output to user
     */
    function outputHeaders($strHeader)
    {
        global $LANG, $BE_USER;

        if ($strHeader!=='live') {
            $setLiveButton = '';
            if ($this->isBeLogin() && 0 != $this->pageId) {
                if ($BE_USER->workspacePublishAccess($this->workspace)
                    && (!($this->arWorkspace['publish_access']&1))
                ) {
                    $setLiveButton = '<input type="submit" value="'.$LANG->getLL('set_live').'">';
                }
            }
            $button = '<td align="right">
                        <form
                            method="post"
                            action="../../../tce_db.php?&cmd[pages]['.$this->pageId.'][version][action]=live_page&redirect='
                                . urlencode('mod/user/ws/wsol_preview.php?id='.$this->pageId.'&L='.$this->language.'&show='.$this->strShow.'&wsid='.$this->workspace)
                            .'"
                            style="margin: 0, padding: 0" target="_parent">
                            <input type="button" value="'.$LANG->getLL('switch_previewmode').'" onclick="toggleFrame();">
                            '.$setLiveButton.'
                        </form>
                        <script type="text/javascript">
                            function toggleFrame() {
                                parent.location.href="wsol_preview.php?id='.$this->pageId.'&L='.$this->language.'&show='.$this->strToggle.'&wsid='.$this->workspace.'";
                            }
                        </script>
                    </td>';
            $arOutputParts = array(
                'title' => 'Workspace Version',
                'text' => 'Workspace Version ('.$this->workspace.'):',
                'color' => 'green',
                'buttons' => $button,
            );
        } else {
            $arOutputParts = array(
                'title' => 'Live Version',
                'text' => 'Live Version:',
                'color' => 'red',
                'buttons' => '',
            );
        }
        return $this->output($arOutputParts);
    }

    /**
     * Returns the HTML for message output
     *
     * @param string $strMessage Message to view
     *
     * @return string the HTML to output to user
     */
    function outputMessages($strMessage)
    {
        $arOutputParts = array(
            'title' => 'Message',
            'text' => '',
            'color' => '#888888',
            'buttons' => '',
        );
        switch($strMessage) {
            case 'branchpoint':
                $arOutputParts['text'] = '<b>No live page available!</b><br/><br/>
                The previewed page was inside a "Branch" type version and has no traceable counterpart in the live workspace.';
            break;
            case 'newpage':
                $arOutputParts['text'] = '<b>New page!</b><br/><br/>
                The previewed page is created in the workspace and has no counterpart in the live workspace.';
            break;
            default:
                $arOutputParts['text'] = 'Unknown message code "'.htmlspecialchars($strMessage).'"';
            break;
        }
        return $this->output($arOutputParts);
    }

    /**
     * URLs generated in $this->URL array
     *
     * @return void
     */
    function generateUrls()
    {
        $strPageUrl = t3lib_div::getIndpEnv('TYPO3_SITE_URL')
            . 'index.php?id=' . $this->pageId . '&L=' . $this->language;

        $this->URL = array(
            'liveHeader' => 'wsol_preview.php?header=live',
            'draftHeader' => 'wsol_preview.php?header=draft&id='.$this->pageId.'&L='.$this->language.'&show='.$this->strShow.'&wsid='.$this->workspace,
            'live' => $strPageUrl . '&ADMCMD_noBeUser=1',
            'draft' => $strPageUrl . '&ADMCMD_previewWS=' . $this->workspace,
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
    function printFrameset()
    {
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
                    <title>Preview workspace version</title>
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
     * Checks if a backend user is logged in. Due to the line
     * "define('TYPO3_PROCEED_IF_NO_USER', '1');" the backend is initialized even
     * if no backend user was authenticated. This is in order to allow previews
     * through this module of yet not-logged in users.
     *
     * @return boolean  True, if there is a logged in backend user.
     */
    function isBeLogin()
    {
        return is_array($GLOBALS['BE_USER']->user);
    }
}


$file = 'ext/nr_easy_workspace/mod1/class.ux_wsol_preview.php';
if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS'][$file]) {
    include_once $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS'][$file];
}
?>