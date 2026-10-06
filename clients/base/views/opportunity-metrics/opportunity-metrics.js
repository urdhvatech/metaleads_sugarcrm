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
 * @class View.Views.Base.OpportunityMetricsView
 * @alias SUGAR.App.view.views.BaseOpportunityMetricsView
 * @extends View.View
 */
({
    plugins: ['Dashlet', 'Chart'],
    className: 'opportunity-metrics-wrapper',

    metricsCollection: null,

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);

        this.tooltipTemplate = app.template.getField('chart', 'singletooltiptemplate', this.module);
        this.locale = SUGAR.charts.getSystemLocale();
        const activeColor = '#517bf8';
        this.chartDefaults = this.getDonutChartDefaultOptions();
        const {sideLineLength, sideLabelFontSize} = this.chartDefaults;
        this.chartDefaults.bottomPadding = (sideLineLength + sideLabelFontSize) / 2;
        this.chartDefaults.dataBackgroundColor.push(activeColor);
        this.chartConfig = this.getDonutChartConfig(this.chartDefaults);
    },

    /**
     * Generic method to render chart with check for visibility and data.
     * Called by _renderHtml and loadData.
     */
    renderChart: function() {
        if (!this.isChartJSReady()) {
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

    /* Process data loaded from REST endpoint so that d3 chart can consume
     * and set general chart properties
     */
    evaluateResult: function(data) {
        var total = 0,
            userConversionRate = 1 / app.metadata.getCurrency(app.user.getPreference('currency_id')).conversion_rate,
            userCurrencyPreference = app.user.getPreference('currency_id'),
            stageLabels = app.lang.getAppListStrings('opportunity_metrics_dom'),
            convertedAmount;

        _.each(data, function(value, key) {
            convertedAmount = app.currency.convertWithRate(value.amount_usdollar, userConversionRate);
            // parse currencies, format to user preference and attach the correct delimiters/symbols etc
            data[key].formattedAmount = app.currency.formatAmountLocale(convertedAmount, userCurrencyPreference, 0);
            data[key].icon = key === 'won' ? 'caret-up' : (key === 'lost' ? 'caret-down' : 'minus');
            data[key].cssClass = key === 'won' ? 'won' : (key === 'lost' ? 'lost' : 'active');
            data[key].dealLabel = key;
            data[key].stageLabel = stageLabels[key] || key;
            total += value.count;
        });

        this.total = total;
        this.metricsCollection = data;

        this.chartCollection = {
            data: _.map(this.metricsCollection, function(value, key) {
                return {
                    key: value.stageLabel,
                    value: value.count,
                    classes: key
                };
            }),
            properties: {
                title: app.lang.get('LBL_DASHLET_OPPORTUNITY_NAME'),
                value: 3,
                label: total,
                yDataType: 'numeric',
                xDataType: 'string'
            }
        };
    },

    /**
     * @inheritdoc
     */
    loadData: function(options) {
        var self = this,
            url;
        if (this.meta.config) {
            return;
        }
        url = app.api.buildURL(this.model.module, 'opportunity_stats', {
            id: this.model.get('id')
        });
        app.api.call('read', url, null, {
            success: function(data) {
                self.evaluateResult(data);
                if (!self.disposed) {
                    // we have to rerender the entire dashlet, not just the chart,
                    // because the HBS file is dependant on metricsCollection
                    self.render();
                }
            },
            error: _.bind(function() {
                this.displayNoData(true);
            }, this),
            complete: options ? options.complete : null
        });
    },
})
