<?php
defined('TYPO3_MODE') or die();

(function () {
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
        'repeatable_form_elements',
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
