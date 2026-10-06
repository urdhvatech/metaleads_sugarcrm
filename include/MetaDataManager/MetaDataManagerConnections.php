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

class MetaDataManagerConnections extends MetaDataManager
{
    /**
     * View=full includes all fields, even links and collections. View=detail
     * excludes links and collections unless they are included via view
     * metadata. View metadata may be used to control display parameters for any
     * fields. Any other view name falls back to the standard behavior as
     * defined by the base platform.
     *
     * @param string $moduleName The module name.
     * @param string $view The view name.
     * @param array $displayParams Associative array of field names and their
     *                             display params on the given view.
     *
     * @return array Flat list of fields for the given module and view.
     */
    public function getModuleViewFields($moduleName, $view, &$displayParams = [])
    {
        $seed = BeanFactory::newBean($moduleName);
        $allFields = $seed->getFieldDefinitions();

        // Only the field names are needed.
        $allFields = array_keys($allFields);

        switch (strtolower($view)) {
            case 'full':
                // Include everything.
                break;
            case 'detail':
                // Exclude links, collections, and other expensive fields.
                $excludedFields = $this->getFieldsExcludedFromDetailView($moduleName);
                $allFields = array_diff($allFields, $excludedFields);
                break;
            default:
                // Fall back to standard behavior.
                $allFields = [];
        }

        // Get the fields specifically named in the viewdef.
        $viewFields = parent::getModuleViewFields($moduleName, $view, $displayParams);

        // Include fields from the viewdef that would be excluded by default.
        $fields = array_merge($allFields, $viewFields);

        return array_unique($fields);
    }

    /**
     * Returns a list of fields that should be excluded from the detail view
     * because they can kill performance. These fields can be added back by
     * defining a custom view metadata for the module. See the detail views
     * for the connections platform in the Calls and Meetings modules.
     *
     * @param string $moduleName The module name.
     *
     * @return array Flat list of field names to exclude from the detail view.
     */
    protected function getFieldsExcludedFromDetailView($moduleName): array
    {
        $seed = BeanFactory::newBean($moduleName);
        $allFields = $seed->getFieldDefinitions();

        $excludedFields = array_filter(
            $allFields,
            function ($fieldDef) use ($seed) {
                if (in_array($fieldDef['name'], ['locked_fields'])) {
                    // `locked_fields` are a `relate_collection`, which requires
                    // a separate SQL query per record while formatting the
                    // bean. Let's exclude `locked_fields` to enhance
                    // performance. After all, `locked_fields` represent
                    // operational state that is needed to avoid conflicts
                    // between processes, but offer nothing in terms of
                    // describing the state of a record besides a suggestion
                    // that an automated process may soon update the locked
                    // fields.
                    return true;
                }

                if (!empty($fieldDef['relate_collection'])) {
                    // Include other relate collections, like `tag`.
                    return false;
                }

                if (in_array($fieldDef['type'], ['collection', 'link'])) {
                    // Exclude fields that are links or collections.
                    return true;
                }

                if (!array_key_exists('link', $fieldDef)) {
                    // Include primary fields (i.e., columns belonging to the
                    // module's database table). These are fields that are not
                    // relate (i.e., non-db) fields.
                    return false;
                }

                // Let's further inspect the relationship to determine whether
                // or not to include the relate field...
                $linkName = $fieldDef['link'];

                if (!$seed->load_relationship($linkName)) {
                    // The relationship couldn't be loaded. Don't risk the field
                    // causing problems. Exclude it.
                    return true;
                }

                if ($seed->$linkName->getType() === REL_TYPE_ONE) {
                    // Allow a relate field to be filled by a one-to-one or
                    // one-to-many link.
                    return false;
                }

                if ($seed->$linkName->getRelationshipObject()->primaryOnly) {
                    // Allow a relate field to be filled by a many-to-many link
                    // that yields only a primary row.
                    // Ex.: *_email_addresses_primary.
                    return false;
                }

                // Exclude relate fields that use a many-to-many link.
                return true;
            }
        );

        // Only the field names are needed.
        $excludedFields = array_keys($excludedFields);

        return $excludedFields;
    }
}
