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

namespace Sugarcrm\Sugarcrm\InboundEmail\Polyfill;

use Webklex\PHPIMAP\Query\WhereQuery;

class ImapSearchHelper
{
    public static function applyCriteria(WhereQuery $query, array $criteria): void
    {
        for ($i = 0; $i < count($criteria); $i++) {
            $criterion = strtoupper($criteria[$i]);

            switch ($criterion) {
                case 'ALL':
                    break;

                case 'ANSWERED':
                    $query->answered();
                    break;

                case 'BCC':
                    if (isset($criteria[$i + 1])) {
                        $query->bcc($criteria[++$i]);
                    }
                    break;

                case 'BEFORE':
                    if (isset($criteria[$i + 1])) {
                        $query->whereBefore($criteria[++$i]);
                    }
                    break;

                case 'BODY':
                    if (isset($criteria[$i + 1])) {
                        $query->whereBody($criteria[++$i]);
                    }
                    break;

                case 'CC':
                    if (isset($criteria[$i + 1])) {
                        $query->cc($criteria[++$i]);
                    }
                    break;

                case 'DELETED':
                    $query->deleted();
                    break;

                case 'FLAGGED':
                    $query->flagged();
                    break;

                case 'FROM':
                    if (isset($criteria[$i + 1])) {
                        $query->from($criteria[++$i]);
                    }
                    break;

                case 'KEYWORD':
                    if (isset($criteria[$i + 1])) {
                        $query->whereKeyword($criteria[++$i]);
                    }
                    break;

                case 'NEW':
                    $query->whereNew();
                    break;

                case 'OLD':
                    $query->whereOld();
                    break;

                case 'ON':
                    if (isset($criteria[$i + 1])) {
                        $query->whereOn($criteria[++$i]);
                    }
                    break;

                case 'RECENT':
                    $query->recent();
                    break;

                case 'SEEN':
                    $query->seen();
                    break;

                case 'SINCE':
                    if (isset($criteria[$i + 1])) {
                        $query->whereSince($criteria[++$i]);
                    }
                    break;

                case 'SUBJECT':
                    if (isset($criteria[$i + 1])) {
                        $query->whereSubject($criteria[++$i]);
                    }
                    break;

                case 'TEXT':
                    if (isset($criteria[$i + 1])) {
                        $query->whereText($criteria[++$i]);
                    }
                    break;

                case 'TO':
                    if (isset($criteria[$i + 1])) {
                        $query->to($criteria[++$i]);
                    }
                    break;

                case 'UNANSWERED':
                    $query->unanswered();
                    break;

                case 'UNDELETED':
                    $query->undeleted();
                    break;

                case 'UNFLAGGED':
                    $query->unflagged();
                    break;

                case 'UNKEYWORD':
                    if (isset($criteria[$i + 1])) {
                        $query->whereUnkeyword($criteria[++$i]);
                    }
                    break;

                case 'UNSEEN':
                    $query->unseen();
                    break;
            }
        }
    }
}
