<?php
/*
 * Your installation or use of this SugarCRM file is subject to the applicable
 * terms available at
 * http://support.sugarcrm.com/Resources/Master_Subscription_Agreements/.
 * If you do not agree to all of the applicable terms or do not have the
 * authority to bind the entity as an authorized representative, then do not
 * install or use this SugarCRM file.
 *
 * Copyright (C) SugarCRM Inc. All rights reserved.
 */

use Sugarcrm\Sugarcrm\Entitlements\SubscriptionManager;

$connector_strings = [
    'LBL_LICENSING_INFO' => '<table border="0" cellspacing="1"><tr><td valign="top" width="35%" class="dataLabel">' .
        'يُرجى إدخال بيانات الاعتماد الخاصة بالإضافة الذكية، المقدّمة من دعم Sugar. بمجرد الحفظ، سيتم إخفاء المفتاح السري ولن يكون بالإمكان عرضه مرة أخرى في إعدادات الموصل.</td></tr></table>',
    'oauth2_client_id' => 'معرّف العميل',
    'oauth2_client_secret' => 'مفتاح المصادقة الخاص بالعميل',
];
