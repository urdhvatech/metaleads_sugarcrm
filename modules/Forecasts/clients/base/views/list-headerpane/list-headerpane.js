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
 * @class View.Views.Base.ForecastsListHeaderpaneView
 * @alias SUGAR.App.view.layouts.BaseForecastsListHeaderpaneView
 * @extends View.Views.Base.ListHeaderpaneView
 */
({
    extendsFrom: 'HeaderpaneView',

    plugins: ['FieldErrorCollection'],

    /**
     * If Forecasts' data sync is complete and we can render buttons
     * @type Boolean
     */
    forecastSyncComplete: false,

    /**
     * Holds the prefix string that is rendered before the same of the user
     * @type String
     */
    forecastWorksheetLabel: '',

    /**
     * Timeperiod model
     */
    tpModel: undefined,

    /**
     * Current quarter label id
     *
     * @type String
     */
    currentTimePeriodId: undefined,

    /**
     * Current default quarter
     *
     */
    lastQuarter: 4,

    /**
     * Current default timezone (always GMT)
     *
     */
    defaultTimeZone: 'Etc/GMT',

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this.tpModel = new Backbone.Model();
        this._super('initialize', [options]);
        this.currentTimePeriodId = this.context.get('selectedTimePeriod');
        this.resetSelection(this.currentTimePeriodId);

        // Update label for worksheet
        let selectedUser = this.context.get('selectedUser');
        if (selectedUser) {
            this._title = this._getForecastWorksheetLabel(selectedUser);
        }
    },

    /**
     * @inheritdoc
     */
    bindDataChange: function() {
        this.tpModel.on('change', function(model) {
            let selectedTimePeriodId = model.get('selectedTimePeriod');
            this.context.trigger(
                'forecasts:timeperiod:changed',
                model,
                this.getField('selectedTimePeriod').tpTooltipMap[selectedTimePeriodId]);
        }, this);

        this.context.on('forecasts:timeperiod:canceled', function() {
            this.resetSelection(this.tpModel.previous('selectedTimePeriod'));
        }, this);

        this.layout.context.on('forecasts:sync:start', function() {
            this.forecastSyncComplete = false;
        }, this);

        this.layout.context.on('forecasts:sync:complete', function() {
            this.forecastSyncComplete = true;
        }, this);

        this.context.on('change:selectedUser', function(model, changed) {
            app.user.lastState.set('Forecasts:selected-user', changed);
            this._title = this._getForecastWorksheetLabel(changed);
            if (!this.disposed) {
                this.render();
            }
        }, this);

        this.context.on('plugin:fieldErrorCollection:hasFieldErrors', function(collection, hasErrors) {
            if(this.fieldHasErrorState !== hasErrors) {
                this.fieldHasErrorState = hasErrors;
            }
        }, this);

        this.context.on('button:print_button:click', function() {
            window.print();
        }, this);

        this._super('bindDataChange');
    },

    /**
     * Gets the current worksheet type
     * @return {string} Either "Rollup" or "Direct". Returns empty string if current user could not be found
     * @private
     */
    _getWorksheetType: function() {
        let selectedUser = this.context.get('selectedUser');
        if (!selectedUser) {
            return '';
        }
        return app.utils.getForecastType(selectedUser.is_manager, selectedUser.showOpps);
    },

    /**
     * Gets the correct language label dependent on "Rollup" vs "Direct" worksheet
     * @param {*} selectedUser The current user whose worksheet is being viewed, stored in this.context
     * @return {string}
     * @private
     */
    _getForecastWorksheetLabel: function(selectedUser) {
        return this._getWorksheetType() === 'Rollup' ?
            app.lang.get('LBL_RU_TEAM_FORECAST_HEADER', this.module, {name: selectedUser.full_name}) :
            app.lang.get('LBL_FDR_FORECAST_HEADER',
                this.module,
                {name: selectedUser.full_name}
            );
    },

    /**
     * @inheritdoc
     */
    _renderHtml: function() {
        if(!this._title) {
            var user = this.context.get('selectedUser') || app.user.toJSON();
            this._title = user.full_name;
        }

        this._super('_renderHtml');

        this.listenTo(this.getField('selectedTimePeriod'), 'render', function() {
            this.markCurrentTimePeriod(this.tpModel.get('selectedTimePeriod'));
        }, this);
    },

    /**
     * @inheritdoc
     */
    _dispose: function() {
        if(this.layout.context) {
            this.layout.context.off('forecasts:sync:start', null, this);
            this.layout.context.off('forecasts:sync:complete', null, this);
        }
        this.stopListening();
        this._super('_dispose');
    },

    /**
     * Sets the timeperiod to the selected timeperiod, used primarily for resetting
     * the dropdown on nav cancel
     *
     * @param String timeperiodId
     */
    resetSelection: function(timeperiodId) {
        this.tpModel.set({selectedTimePeriod: timeperiodId}, {silent: true});
        _.find(this.fields, function(field) {
            if (_.isEqual(field.name, 'selectedTimePeriod')) {
                field.render();
                return true;
            }
        });
    },

    /**
     * Get year and quarter
     *
     * @param {Object} currentDate timeperiodId
     * @return array
     */
    getQuarter: function(currentDate) {
        currentDate = currentDate instanceof app.date ? currentDate : app.date();

        const forecastCnf = app.metadata.getModule('Forecasts', 'config') || {};
        const forecastStartDateStr = forecastCnf.timeperiod_start_date || app.date().startOf('year').formatServer(true);
        const forecastStartDate = app.date(forecastStartDateStr);
        const forecastsTimeperiod = forecastCnf.timeperiod_fiscal_year || null;

        //extract fiscal start date components
        const fiscalStartMonth = forecastStartDate.month();
        const fiscalStartDay = forecastStartDate.date();

        let fiscalYear = currentDate.year();
        let fiscalYearStart = app.date(currentDate).set({month: fiscalStartMonth, date: fiscalStartDay});

        //adjust year based on forecast time settings
        if (forecastsTimeperiod === 'current_year' && currentDate.isBefore(fiscalYearStart)) {
            fiscalYear -= 1;
        } else if (forecastsTimeperiod === 'next_year' && currentDate.isSameOrAfter(fiscalYearStart)) {
            fiscalYear += 1;
        }

        const quarter = this.determineQuarter(currentDate, fiscalYearStart);

        return [fiscalYear, quarter];
    },

    /**
     * Determine the fiscal quarter for the given date
     * @param {Object} presentDate
     * @param {Object} yearStart
     *
     * @return {number} fiscal quarter
     */
    determineQuarter: function(presentDate, yearStart) {
        let currentDate = new Date(presentDate.toLocaleString());
        let fiscalYearStart = new Date(yearStart.toLocaleString());

        //extracting the fiscal year start month and day
        //0-based (Jan = 0, Dec = 11)
        const fiscalStartMonth = fiscalYearStart.getUTCMonth();
        const fiscalStartDay = fiscalYearStart.getUTCDate();

        const currentYear = currentDate.getUTCFullYear();

        //determine the fiscal year start date for the given year
        let fiscalYearStartDate = new Date(Date.UTC(currentYear, fiscalStartMonth, fiscalStartDay));

        //if the current date is before the fiscal start date of the current year, adjust to the previous fiscal year
        if (currentDate < fiscalYearStartDate) {
            fiscalYearStartDate = new Date(Date.UTC(currentYear - 1, fiscalStartMonth, fiscalStartDay));
        }

        //define the fiscal quarter start dates properly
        const fiscalQuarterStartDates = [
            fiscalYearStartDate,                               // Q1 Start
            this.addMonths(fiscalYearStartDate, 3),            // Q2 Start
            this.addMonths(fiscalYearStartDate, 6),            // Q3 Start
            this.addMonths(fiscalYearStartDate, 9)             // Q4 Start
        ];

        //get the timezone from the user preferences if not use GMT
        let timezone = app.user.getPreference('timezone') || this.defaultTimeZone;
        const options = {timeZone: timezone};

        let currentDateToCompare = new Date(currentDate.toLocaleString('en-US', options));

        //identify which quarter the current date falls into using UTC-based comparison
        for (let i = 0; i < 4; i++) {
            let fiscalQuarterStart = new Date(fiscalQuarterStartDates[i].toLocaleString('en-US', options));
            //compare currentDate and fiscalQuarterStartDates[i] using UTC timestamps
            if (currentDateToCompare.getTime() < fiscalQuarterStart.getTime()) {
                //return correct quarter (1-based index)
                return i;
            }
        }

        return this.lastQuarter;
    },

    /**
     * add months taking in consideration varying month lengths
     *
     * @param {Object} date
     * @param {number} months
     *
     * @return {Object} startOftheQuarter
     */
    addMonths: function(date, months) {
        let startOftheQuarter = new Date(date);
        startOftheQuarter.setUTCMonth(startOftheQuarter.getUTCMonth() + months);
        return startOftheQuarter;
    },

    /**
     * Get month and year
     *
     * @param {Object} d  timeperiodId
     * @return string
     */
    getMonth: function(d) {
        d = d || app.date();
        return d.format('MMMM YYYY');
    },

    /**
     * Mark the current time period with 'Current' label
     *
     * @param String selectedTimePeriodId
     */
    markCurrentTimePeriod: function(selectedTimePeriodId) {
        let listTimePeriods = this.getField('selectedTimePeriod') ? this.getField('selectedTimePeriod').items : null;
        if (!listTimePeriods) {
            return;
        }

        let timePeriodInterval = app.metadata.getModule('Forecasts', 'config').timeperiod_leaf_interval;
        let currentTimePeriod = timePeriodInterval === 'Quarter' ? this.getQuarter().join(' Q') : this.getMonth();
        let currentTimePeriodId = _.findKey(listTimePeriods, item => item === currentTimePeriod);
        if (!currentTimePeriodId) {
            return;
        }

        let currentTimePeriodText = app.lang.get('LBL_CURRENT', this.module) +
            ' (' + listTimePeriods[currentTimePeriodId] + ')';
        listTimePeriods[currentTimePeriodId] = currentTimePeriodText;
        if (selectedTimePeriodId === currentTimePeriodId) {
            this.$('.quarter-picker .forecastsTimeperiod .select2-chosen').text(currentTimePeriodText);
        }
    }
})
