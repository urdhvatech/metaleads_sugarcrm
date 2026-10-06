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

namespace Sugarcrm\Sugarcrm\Maps;

use SugarBean;
use BeanFactory;
use SugarQuery;
use Sugarcrm\Sugarcrm\Maps\Logger;
use LoggerManager;
use DBManager;

class FilterUtils
{
    /**
     * Get coords by zipcode from GCS database/Bing
     *
     * @param string $zipCode
     * @param string $country
     *
     * @return mixed
     */
    public static function getCoordsFromZip(string $zipCode, string $country)
    {
        $logger = new Logger(LoggerManager::getLogger());

        try {
            $coords = self::getDbCoordsFromZip($zipCode, $country);

            if (!$coords) {
                $data = self::getGCSCoordsFromZip($zipCode, $country);

                if (!is_array($data) || $data['geocoded'] !== true) {
                    return false;
                }

                $coords = [
                    'latitude' => $data['lat'],
                    'longitude' => $data['long'],
                ];

                return $coords;
            }

            return $coords;
        } catch (\Exception|\Throwable $e) {
            $logger->error("Maps: Failed to get coords from zip for zipCode: {$zipCode} country: {$country}");
        }

        return false;
    }

    /**
     * Get coords by record from database
     *
     * @param string $module
     * @param string $id
     *
     * @return mixed
     */
    public static function getDbCoordsFromRecord(string $module, string $id)
    {
        $geocodeBean = BeanFactory::newBean(Constants::GEOCODE_MODULE);

        $sq = new SugarQuery();
        $sq->select('latitude', 'longitude');
        $sq->from($geocodeBean)
            ->where()
            ->equals('parent_type', $module)
            ->equals('parent_id', $id)
            ->equals('geocoded', true)
            ->equals('status', Constants::GEOCODE_SCHEDULER_STATUS_COMPLETED);
        $sq->limit(1);

        $result = $geocodeBean->fetchFromQuery($sq, ['latitude', 'longitude']);

        if (empty($result)) {
            return false;
        }

        $beanId = array_keys($result)[0];

        $geocodeBeanResult = $result[$beanId];

        $coords = [
            'latitude' => $geocodeBeanResult->latitude,
            'longitude' => $geocodeBeanResult->longitude,
        ];

        return $coords;
    }

    /**
     * Get coords by zipcode from database
     *
     * @param string $zipCode
     * @param string $country
     *
     * @return mixed
     */
    public static function getDbCoordsFromZip(string $zipCode, string $country)
    {
        $geocodeBean = BeanFactory::newBean(Constants::GEOCODE_MODULE);

        $sq = new SugarQuery();
        $sq->select('latitude', 'longitude');
        $sq->from($geocodeBean)
            ->where()
            ->equals('postalcode', $zipCode)
            ->equals('country', $country)
            ->equals('geocoded', true)
            ->equals('status', Constants::GEOCODE_SCHEDULER_STATUS_COMPLETED);
        $sq->limit(1);

        $result = $geocodeBean->fetchFromQuery($sq, ['latitude', 'longitude']);

        if (empty($result)) {
            return false;
        }

        $beanId = array_keys($result)[0];

        $geocodeBeanResult = $result[$beanId];

        $coords = [
            'latitude' => $geocodeBeanResult->latitude,
            'longitude' => $geocodeBeanResult->longitude,
        ];

        return $coords;
    }


    /**
     * Get coords by zipcode from GCS
     *
     * @param string $zipCode
     * @param string $country
     *
     * @return array
     */
    public static function getGCSCoordsFromZip(string $zipCode, string $country): array
    {
        $gcsClient = new GCSClient();

        $resp = $gcsClient->getCoordsByZip($zipCode, $country);

        return $resp;
    }

    /**
     * Get the name of the coords table
     *
     * @return void
     */
    public static function getCoordsTableName(): string
    {
        $geocodeBean = BeanFactory::newBean(Constants::GEOCODE_MODULE);
        return $geocodeBean->table_name;
    }

    /**
     * Update the geocode status of the parent record
     *
     * @param array $geocodeBeansIds
     * @param string $status
     */
    public static function updateGeocodeStatuses(array $geocodeBeansIds, string $status)
    {
        foreach ($geocodeBeansIds as $geocodeBeanId) {
            try {
                $geocodeBean = BeanFactory::retrieveBean(Constants::GEOCODE_MODULE, $geocodeBeanId);

                if (!$geocodeBean) {
                    continue;
                }

                $parentRecord = BeanFactory::retrieveBean($geocodeBean->parent_type, $geocodeBean->parent_id);

                if (!$parentRecord) {
                    continue;
                }

                foreach ($parentRecord->field_defs as $fieldName => $fieldMeta) {
                    if ($fieldMeta['type'] === 'geocodestatus') {
                        $parentRecord->{$fieldName} = $status;
                    }
                }

                $parentRecord->processed = true;
                $parentRecord->save();
            } catch (\Exception|\Throwable $e) {
                $logger = new Logger(LoggerManager::getLogger());
                $logger->error("Maps: Failed to update geocode status for geocodeBeanId: {$geocodeBeanId}");
            }
        }
    }

    /**
     * Limits the search area by adding geocode boundary conditions.
     *
     * Modifies the given to restrict the search area to a specific radius
     * around a central latitude and longitude. It does this by adding latitude and longitude boundary
     * conditions that approximate the search area as a bounding box. This optimization helps to
     * pre-filter results before applying more precise geospatial calculations.
     *
     * @param SugarQuery $sq The SugarQuery object to be modified.
     * @param float $latitude The central latitude for the bounding box.
     * @param float $longitude The central longitude for the bounding box.
     * @param float $radius The radius of the search area in kilometers.
     * @param float $pi The value of pi used for calculations.
     * @param int $earthRadiusKm The radius of the Earth in kilometers.
     * @param string $geocodeTable The name of the table containing geocode data.
     * @return void
     */
    public static function limitSearchArea(
        SugarQuery $sq,
        float $latitude,
        float $longitude,
        float $radius,
        float $pi,
        int $earthRadiusKm,
        string $geocodeTable
    ) {
        $isDb2 = DBManager::isDb2($sq->getDBManager());

        if ($isDb2) {
            $latitudeBoundry = <<<EOQ
                (
                        $geocodeTable.latitude >= (CAST($latitude as DOUBLE) - (CAST($radius as DOUBLE) / CAST($earthRadiusKm as DOUBLE)) * (180 / $pi))
                    AND
                        $geocodeTable.latitude <= (CAST($latitude as DOUBLE) + (CAST($radius as DOUBLE) / CAST($earthRadiusKm as DOUBLE)) * (180 / $pi))
                )
EOQ;

            $longitudeBoundry = <<<EOQ
                (
                    $geocodeTable.longitude >=
                            (
                                CAST($longitude as DOUBLE) - (CAST($radius as DOUBLE) / (CAST($earthRadiusKm as DOUBLE) * COS(CAST($latitude as DOUBLE) * $pi / 180))) * (180 / $pi)
                            )
                    AND
                        $geocodeTable.longitude <=
                            (
                                CAST($longitude as DOUBLE) + (CAST($radius as DOUBLE) / (CAST($earthRadiusKm as DOUBLE) * COS(CAST($latitude as DOUBLE) * $pi / 180))) * (180 / $pi)
                            )
                )
EOQ;
        } else {
            $latitudeBoundry = <<<EOQ
                (
                        $geocodeTable.latitude >= ($latitude - ($radius / $earthRadiusKm) * (180 / $pi))
                    AND
                        $geocodeTable.latitude <= ($latitude + ($radius / $earthRadiusKm) * (180 / $pi))
                )
EOQ;

            $longitudeBoundry = <<<EOQ
                (
                    $geocodeTable.longitude >=
                            (
                                $longitude - ($radius / ($earthRadiusKm * COS($latitude * $pi / 180))) * (180 / $pi)
                            )
                    AND
                        $geocodeTable.longitude <=
                            (
                                $longitude + ($radius / ($earthRadiusKm * COS($latitude * $pi / 180))) * (180 / $pi)
                            )
                )
EOQ;
        }
        $sq->whereRaw($latitudeBoundry);
        $sq->whereRaw($longitudeBoundry);
    }
}
