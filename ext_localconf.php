<?php

defined('TYPO3') || die();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update']['nsGoogledocsPrefixedBeUsersColumns']
    = \NITSAN\NsGoogledocs\Updates\PrefixedBeUsersColumnsUpgradeWizard::class;
