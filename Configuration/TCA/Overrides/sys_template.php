<?php
defined('TYPO3_MODE') or die();

call_user_func(function () {
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
        \TRITUM\RepeatableFormElements\Configuration\Extension::KEY,
        'Configuration/TypoScript',
        'Repeatable form configuration'
    );
});
