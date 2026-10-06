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
 * @class View.Views.Base.CasessummaryView
 * @alias SUGAR.App.view.views.BaseCasessummaryView
 * @extends View.View
 */
({
    events: {
        'shown.bs.tab li[data-bs-toggle="tab"]': 'resize',
    },

    plugins: ['Dashlet', 'Chart'],
    className: 'cases-summary-wrapper',

    tabData: null,
    tabClass: '',

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);

        this.tooltipTemplate = app.template.getField('chart', 'singletooltiptemplate', this.module);
        this.locale = SUGAR.charts.getSystemLocale();

        this.chartDefaults = this.getDonutChartDefaultOptions();
        const {sideLineLength, sideLabelFontSize} = this.chartDefaults;
        this.chartDefaults.bottomPadding = (sideLineLength + sideLabelFontSize) / 2;
        this.chartConfig = this.getDonutChartConfig(this.chartDefaults);
    },

    /**
     * Generic method to render chart with check for visibility and data.
     * Called by _renderHtml and loadData.
     */
    renderChart: function() {
        if (!this.isChartJSReady()) {
            if (this.$el.parents().length > 0 && this.$el.is(':hidden') && !this.reRenderFlag) {
                // Call this function one more time for cases
                // if a chart should be rendered, but $el is still not displayed
                _.debounce(() => {
                    this.reRenderFlag = true;
                    this.renderChart();
                }, 3000);
            }

            return;
        }

        const canvas = _.first(this.$('canvas#' + this.cid));
        const ctx = canvas.getContext('2d');
        const chartData = this.getChartData();
        this.chartConfig.data = chartData;

        this.chart = new Chart(ctx, this.chartConfig);

        this.chart_loaded = _.isFunction(this.chart.update);
        this.displayNoData(!this.chart_loaded);
    },

    /**
     * Return formatted data for chartjs
    */
    getChartData: function() {
        if (!this.chartCollection || !this.chartCollection.data) {
            return [];
        }

        const chartDefaults = this.chartDefaults;
        const chartData = [];
        const labels = [];

        _.each(this.chartCollection.data, data => {
            chartData.push(data.value);
            labels.push(data.key);
        });

        return {
            labels,
            datasets: [{
                data: chartData,
                backgroundColor: chartDefaults.dataBackgroundColor,
                borderColor: chartDefaults.borderColor,
            }]
        };
    },

    /**
     * Create, display and position the tooltip for the chart
     *
     * @param {Object} context
     */
    generateExternalTooltip: function(context) {
        // Tooltip Element
        let tooltipEl = $('.chartjs-tooltip');
        const tooltipModel = context.tooltip;

        // Create element on first render
        if (!tooltipEl.length) {
            tooltipEl = this.createTooltipElement();
        }

        // Hide if no tooltip
        if (tooltipModel.opacity === 0) {
            tooltipEl.css({
                opacity: 0
            });
            return;
        }

        if (tooltipModel.body) {
            const point = tooltipModel.dataPoints[0];
            const value = point.raw || 0;
            const total = this.total || 0;

            point.value = value;
            point.key = point.label;
            point.label = app.lang.get('LBL_CHART_COUNT');
            point.percent = app.utils.charts.numberFormatPercent(value, total, this.locale);

            const template = this.tooltipTemplate(point).replace(/(\r\n|\n|\r)/gm, '');

            tooltipEl.html(template);
        }

        const tooltipPosition = this.getTooltipPosition(context, tooltipEl);

        tooltipEl.css({
            opacity: 1,
            left: tooltipPosition.left + 'px',
            top: tooltipPosition.top + 'px',
        });
    },

    /**
     * Build content with favorite fields for content tabs
     */
    addFavs: function() {
        var self = this;
        //loop over metricsCollection
        _.each(this.tabData, function(tabGroup) {
            if (tabGroup.models && tabGroup.models.length > 0) {
                _.each(tabGroup.models, function(model) {
                    var field = app.view.createField({
                            def: {type: 'favorite'},
                            model: model,
                            meta: {view: 'detail'},
                            viewName: 'detail',
                            view: self
                        });
                    field.setElement(self.$('[data-model-id="' + model.id + '"]'));
                    field.render();
                });
            }
        });
    },

    /* Process data loaded from REST endpoint so that d3 chart can consume
     * and set general chart properties
     */
    evaluateResult: function(data) {
        this.total = data.models.length;

        var countClosedCases = data.where({status: 'Closed'})
                .concat(data.where({status: 'Rejected'}))
                .concat(data.where({status: 'Duplicate'})).length,
            countOpenCases = this.total - countClosedCases;

        this.chartCollection = {
            data: [],
            properties: {
                title: app.lang.get('LBL_CASE_SUMMARY_CHART'),
                value: 3,
                label: this.total
            }
        };
        this.chartCollection.data.push({
            key: app.lang.get('LBL_DASHLET_CASESSUMMARY_CLOSE_CASES'),
            classes: 'sc-fill-green',
            value: countClosedCases
        });
        this.chartCollection.data.push({
            key: app.lang.get('LBL_DASHLET_CASESSUMMARY_OPEN_CASES'),
            classes: 'sc-fill-red',
            value: countOpenCases
        });

        if (!_.isEmpty(data.models)) {
            this.processCases(data);
        }
    },

    /**
     * Build tab related data and set tab class name based on number of tabs
     * @param {data} object The chart related data.
     */
    processCases: function(data) {
        this.tabData = [];

        var status2css = {
                'Rejected': 'label-success',
                'Closed': 'label-success',
                'Duplicate': 'label-success'
            },
            stati = _.uniq(data.pluck('status')),
            statusOptions = app.metadata.getModule('Cases', 'fields').status.options || 'case_status_dom';

        _.each(stati, function(status, index) {
            if (!status2css[status]) {
                this.tabData.push({
                    index: index,
                    status: status,
                    statusLabel: app.lang.getAppListStrings(statusOptions)[status],
                    models: data.where({'status': status}),
                    cssClass: status2css[status] ? status2css[status] : 'label-important'
                });
            }
        }, this);

        this.tabClass = ['one', 'two', 'three', 'four', 'five'][this.tabData.length] || 'four';
    },

    /**
     * @inheritdoc
     */
    loadData: function(options) {
        var self = this,
            oppID,
            accountBean,
            relatedCollection;
        if (this.meta.config) {
            return;
        }
        oppID = this.model.get('account_id');
        if (oppID) {
            accountBean = app.data.createBean('Accounts', {id: oppID});
        }
        relatedCollection = app.data.createRelatedCollection(accountBean || this.model, 'cases');
        relatedCollection.fetch({
            relate: true,
            success: function(data) {
                self.evaluateResult(data);
                if (!self.disposed) {
                    // we have to rerender the entire dashlet, not just the chart,
                    // because the HBS file is dependant on processCases completion
                    self.render();
                    self.addFavs();
                }
            },
            error: _.bind(function() {
                this.displayNoData(true);
            }, this),
            complete: options ? options.complete : null,
            limit: -1,
            fields: this.getFieldNames()
        });
    },

    /**
     * Get the list of field names to render the dashlet correctly
     * @return {string[]} The list of fields we need to fetch
     * @override
     */
    getFieldNames: function() {
        // FIXME TY-920: we shouldn't have to override this per-dashlet
        return this.dashletConfig && this.dashletConfig.dashlets[0].fields || [];
    }
})
