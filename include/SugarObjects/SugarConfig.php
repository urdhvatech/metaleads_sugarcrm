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

/**
 * Config manager
 * @api
 */
class SugarConfig implements SplSubject
{
    // @codingStandardsIgnoreLine PSR2.Classes.PropertyDeclaration.Underscore
    public $_cached_values = [];

    /**
     * Observers of the configuration changes
     *
     * @var SplObjectStorage|SplObserver[]
     */
    private $observers;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->observers = new SplObjectStorage();
    }

    public static function getInstance()
    {
        static $instance = null;
        if (is_null($instance)) {
            $instance = new SugarConfig();
        }
        return $instance;
    }

    public function get($key, $default = null)
    {
        if (!isset($this->_cached_values[$key])) {
            $config = $this->all();
            $this->_cached_values[$key] = !empty($config) ?
                SugarArray::staticGet($config, $key, $default) :
                $default;
        }
        return $this->_cached_values[$key];
    }

    public function all(): array
    {
        return is_array($GLOBALS['sugar_config'] ?? null) ? $GLOBALS['sugar_config'] : [];
    }

    public function clearCache($key = null)
    {
        if (is_null($key)) {
            $this->_cached_values = [];
        } else {
            unset($this->_cached_values[$key]);
        }

        $this->notify();
    }

    /**
     * {@inheritDoc}
     */
    public function attach(SplObserver $observer): void
    {
        $this->observers->offsetSet($observer);
    }

    /**
     * {@inheritDoc}
     */
    public function detach(SplObserver $observer): void
    {
        $this->observers->detach($observer);
    }

    /**
     * {@inheritDoc}
     */
    public function notify(): void
    {
        foreach ($this->observers as $observer) {
            $observer->update($this);
        }
    }
}
