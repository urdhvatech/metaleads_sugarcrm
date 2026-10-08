<?php
/**
 * This file is part of the "Meta Leads" package.
 *
 * @package Meta Leads
 * @author Urdhva Tech <contact@urdhva-tech.com>
 * @link https://www.urdhva-tech.com
 * @copyright Urdhva Tech
 * @license As specified in the License Agreement supplied with this package.
 */

/*
$viewdefs['ut_sm']['base']['layout']['settings'] = array(
    'components' => array(
        array(
            'layout' => array(
                'type' => 'default',
                'name' => 'sidebar',
                'components' => array(
                    array(
                        'layout' => array(
                            'type' => 'base',
                            'name' => 'main-pane',
                            'css_class' => 'main-pane span12',
                            'components' => array(
                                array(
                                    'view' => 'settings',
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        ),
    ),
);
*/


$viewdefs['ut_sm']['base']['layout']['settings'] = array(
    'type' => 'simple',
    'components' =>
    array(
        array(
            'view' => 'settings',
        ),
    ),
);
