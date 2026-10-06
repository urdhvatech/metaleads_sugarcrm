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

namespace Sugarcrm\Sugarcrm\HistoricallyDelta\Decorators\Types;

use Sugarcrm\Sugarcrm\HistoricallyDelta\Decorators\FieldTypeDecorator;
use Sugarcrm\Sugarcrm\HistoricallyDelta\Decorators\FieldDelta;

class DefaultFieldDecorator extends FieldTypeDecorator
{
    /**
     * Decorates the field delta.
     *
     * @param mixed $past
     * @param mixed $current
     *
     * @return FieldDelta
     */
    public function decorate($past, $current): FieldDelta
    {
        if ($past === $current) {
            return $this->emptyResult();
        }

        return $this->formatDelta((string)$past, 1);
    }
}
