<?php

use TRITUM\RepeatableFormElements\Configuration\Extension;

defined('TYPO3') or die();

call_user_func(function () {
    Extension::addTypoScriptSetup();
    Extension::registerHooks();

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['repeatableFormElements.copyVariants'] ??= true;
});
