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

use Sugarcrm\Sugarcrm\Bean\ModuleDependency;

class SugarACLActivities extends SugarACLStatic
{
    public function checkAccess($module, $view, $context): bool
    {
        if ($view === 'field') {
            return true;
        }

        if (!Activity::isEnabled()) {
            // Allow metadata load even if Activities are disabled
            if ($view === 'access') {
                return true;
            }
            return false;
        }

        $user = $this->getCurrentUser($context);

        if ($user->isAdmin()) {
            return true;
        }

        /** @var SugarBean | null $bean */
        $bean = $context['bean'] ?? null;

        if (!($bean instanceof ModuleDependency && $bean->getParentType())) {
            return true;
        }

        $parentBean = $bean->getParentBean();
        if ($parentBean === null) {
            return true;
        }
        $action = $this->extractAction($parentBean);

        $hasReadAccess = parent::checkAccess(
            $parentBean->getModuleName(),
            $action,
            array_merge($context, ['bean' => $parentBean]),
        );

        if (!$hasReadAccess) {
            return false;
        }

        if (self::isWriteOperation($view, $context)) {
            //for subscriptions we need to check ownership for the parent bean
            if ($bean->getModuleName() === 'Subscriptions') {
                $actions = ACLAction::getUserActions($user->id);
                $listAccess = $actions[$parentBean->getModuleName()]['module']['list']['aclaccess'] ?? false;

                //Some modules (Quotas and etc.) cannot be controlled by Role Management. We need to skip them
                if ($listAccess === ACL_ALLOW_ALL || !isset($actions[$parentBean->getModuleName()])) {
                    return true;
                }

                return $parentBean->isOwner($user->id);
            }

            return $bean->isOwner($user->id);
        }

        return true;
    }

    /**
     * @param SugarBean|null $bean
     * @return string
     *
     * For activities, comments and subscriptions we need
     * to check the parent bean to determine the action.
     * If the parent bean don't have ID it means that
     * we are in list context. Else we are in view context.
     */
    private function extractAction(SugarBean|null $bean): string
    {
        if (!empty($bean) && $bean->id) {
            return 'view';
        }

        return 'list';
    }
}
