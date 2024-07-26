<?php

use TRITUM\RepeatableFormElements\Configuration\Extension;

defined('TYPO3') or die();

call_user_func(function () {
    Extension::addTypoScriptSetup();
    Extension::registerIcons();
    Extension::registerHooks();
});
