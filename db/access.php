<?php

defined('MOODLE_INTERNAL') || die();

$capabilities = array(

    'gradereport/strathblindusers:view' => array(
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        )
    ),
);


