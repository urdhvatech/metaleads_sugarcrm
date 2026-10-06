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

class TeamNameHelper
{
    /**
     * Splits a private team's name into name and name_2 based on the associated user's last name.
     * @param SugarBean $focus The Team SugarBean object being saved.
     */
    public function splitName($focus)
    {
        $tokens = explode(' ', $focus->name);

        if (safeCount($tokens) == 2) {
            $focus->name = trim($tokens[0]);
            $focus->name_2 = trim($tokens[1]);
        } elseif (safeCount($tokens) > 2) {
            $user = $this->getAssociatedUser($focus->associated_user_id);

            $focus->name_2 = '';
            $index = safeCount($tokens);
            for ($i = (safeCount($tokens) - 1); $i > 0; $i--) {
                $lastNameTokens = array_slice($tokens, $i);
                $lastName = implode(' ', $lastNameTokens);

                if (strcmp($lastName, $user->last_name) == 0) {
                    $focus->name_2 = $lastName;
                    $index = $i;
                    break;
                }
            }

            $newTokens = array_slice($tokens, 0, $index);
            $focus->name = implode(' ', $newTokens);
        } else {
            $focus->name_2 = '';
        }
    }

    /**
     * Helper method to fetch the associated user bean.
     * @param string $userId The ID of the user to retrieve.
     * @return SugarBean|null
     */
    protected function getAssociatedUser(string $userId)
    {
        return BeanFactory::getBean('Users', $userId);
    }
}
