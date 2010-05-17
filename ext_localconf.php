<?php
if (!defined ('TYPO3_MODE')) {
    die ('Access denied.');
}

$TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['typo3/mod/user/ws/index.php']
    = t3lib_extMgm::extPath($_EXTKEY).'mod1/class.ux_SC_mod_user_ws_index.php';
$TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['typo3/mod/user/ws/class.wslib_gui.php']
    = t3lib_extMgm::extPath($_EXTKEY).'mod1/class.ux_wslib_gui.php';
$TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['typo3/mod/user/ws/wsol_preview.php']
    = t3lib_extMgm::extPath($_EXTKEY).'mod1/class.ux_wsol_preview.php';

$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['nr_easy_workspace']
    = 'EXT:nr_easy_workspace/class.tx_nreasyworkspace_tcemain.php:tx_nreasyworkspace_tcemain';

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['recStatInfoHooks'][]
    = 'EXT:nr_easy_workspace/class.tx_nreasyworkspace_tcemain.php:&tx_nreasyworkspace_tcemain->lockedIcon';
?>