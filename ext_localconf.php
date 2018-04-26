<?php
defined('TYPO3_MODE') or die();

/*
 * @author Ralf Zimmermann TRITUM GmbH <ralf.zimmermann@tritum.de>
 */
call_user_func(function () {
    if (TYPO3_MODE === 'BE') {
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScriptSetup(trim('
            module.tx_form {
                settings {
                    yamlConfigurations {
                        1511193633 = EXT:repeatable_form_elements/Configuration/Yaml/FormSetup.yaml
                        1511193634 = EXT:repeatable_form_elements/Configuration/Yaml/FormSetupBackend.yaml
                    }
                }
            }
        '));

        $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
        $iconRegistry->registerIcon(
            't3-form-icon-repeatable-container',
            \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
            ['source' => 'EXT:repeatable_form_elements/Resources/Public/Icons/t3-form-icon-repeatable-container.svg']
        );
    } elseif (TYPO3_MODE === 'FE') {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['afterInitializeCurrentPage'][1511196413]
            = \TRITUM\RepeatableFormElements\Hooks\FormHooks::class;

            $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['beforeRendering'][1511196413]
                = \TRITUM\RepeatableFormElements\Hooks\FormHooks::class;
    }
});
