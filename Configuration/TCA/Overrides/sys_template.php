<?php

defined('TYPO3') or die();

(function () {
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
        \TRITUM\RepeatableFormElements\Configuration\Extension::KEY,
        'Configuration/TypoScript',
        'Form setup'
    );
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
        'repeatable_form_elements',
        'Configuration/TypoScript/Js/Global',
        'JavaScript (global)'
    );
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
        'repeatable_form_elements',
        'Configuration/TypoScript/Js/Bundle',
        'JavaScript (bundle)'
    );
})();
