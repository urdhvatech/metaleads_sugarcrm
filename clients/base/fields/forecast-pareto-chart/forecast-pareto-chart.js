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
 * @class View.Fields.Base.ForecastParetoChartField
 * @alias SUGAR.App.view.fields.BaseForecastParetoChartField
 * @extends View.Fields.Base.BaseField
 */
({
    plugins: ['Chart'],

    /**
     * The data from the server
     */
    _serverData: undefined,

    /**
     * The open state of the sidepanel
     */
    state: "open",

    /**
     * Visible state of the preview window
     */
    preview_open: false,

    /**
     * Is the dashlet collapsed or not
     */
    collapsed: false,

    /**
     * Throttled Set Server Data call to prevent it from firing multiple times right in a row.
     */
    throttledSetServerData: false,

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._beforeInit(options);

        this.once('render', function() {
            this.renderChart();
        }, this);

        this._super('initialize', [options]);

        // we need this if because Jasmine breaks with out as you can't define a view with a layout in Jasmine Test
        // @see BR-1217
        if (this.view.layout) {
            // we need to listen to the context on the layout for this view for when it collapses
            this.view.layout.on('dashlet:collapse', this.handleDashletCollapse, this);
            this.view.layout.context.on('dashboard:collapse:fire', this.handleDashletCollapse, this);
            // We listen to this event to call the chart resize method
            // because the size of the dashlet can change in the dashboard.
            this.view.layout.context.on('dashlet:draggable:stop', this.handleDashletCollapse, this);
        }

        // Localization parameters for the system
        this.locale = SUGAR.charts.getSystemLocale();
        this.throttledSetServerData = _.throttle(this._setServerData, 1000);
        this.barTooltipTemplate = app.template.getField(this.type, 'bartooltiptemplate', this.module);
        this.lineTooltipTemplate = app.template.getField(this.type, 'linetooltiptemplate', this.module);
        this.quotaTooltipTemplate = app.template.getField(this.type, 'quotatooltiptemplate', this.module);

        this.chartDefaults = {
            line: {
                pointRadius: 5,
                pointStyle: 'circle',
                clip: false,
                order: 0
            },
            quota: {
                borderColor: '#4d5154',
                borderWidth: 5,
                borderDash: [8, 8],
                clip: false,
            },
            targetQuota: {
                borderColor: '#6f777b',
                borderWidth: 5,
                borderDash: [8, 8],
                clip: false,
            }
        };
    },

    /**
     * Before init
     *
     * @param {Object} options
     */
    _beforeInit: function(options) {
        this.skipLifecycleMethods = true;
    },

    /**
     * @inheritdoc
     */
    bindDataChange: function() {
        app.events.on('preview:open', function() {
            this.preview_open = true;
        }, this);
        app.events.on('preview:close', function() {
            this.preview_open = false;
            this.renderDashletContents();
        }, this);

        var defaultLayout = this.closestComponent('sidebar');
        if (defaultLayout) {
            this.listenTo(defaultLayout, 'sidebar:state:changed', function(state) {
                this.state = state;
                this.renderDashletContents();
            });
        }

        this.model.on('change', function(model) {
            var changed = _.keys(model.changed);
            if (!_.isEmpty(_.intersection(['user_id', 'display_manager', 'timeperiod_id'], changed))) {
                this.renderChart();
            }
        }, this);

        this.model.on('change:group_by change:dataset change:ranges', this.renderDashletContents, this);
    },

    /**
     * Utility method to check is the dashlet is visible
     *
     * @return {boolean}
     */
    isDashletVisible: function() {
        return (!this.disposed && this.state === 'open' &&
                !this.preview_open && !this.collapsed && !_.isUndefined(this._serverData));
    },

    /**
     * Utility method to resize dashlet with check for visibility
     *
     * @return {boolean}
     */
    resize: function() {
        if (this.isDashletVisible() && this.paretoChart && _.isFunction(this.paretoChart.update)) {
            this.paretoChart.update();
        }
    },

    /**
     * Utility method to render the chart if the dashlet is visible
     *
     * @return {boolean}
     */
    renderDashletContents: function() {
        if (this.isDashletVisible()) {
            this.convertDataToChartData();
            this.generateChart();

            return true;
        }

        return false;
    },

    /**
     * Utility method since there are two event listeners
     *
     * @param {Boolean} collapsed       Is this dashlet collapsed or not
     */
    handleDashletCollapse: function(collapsed) {
        this.collapsed = collapsed;

        this.renderDashletContents();
    },

    /**
     * Attach and detach a resize method to the print event
     * @param {string} The state of print handling.
     */
    handlePrinting: function(state) {
        var self = this,
            mediaQueryList = window.matchMedia && window.matchMedia('print'),
            pausecomp = function(millis) {
                // www.sean.co.uk
                var date = new Date(),
                    curDate = null;
                do {
                    curDate = new Date();
                } while (curDate - date < millis);
            },
            printResize = function(mql) {
                if (mql.matches) {
                    self.paretoChart.width(640).height(320).update();
                    // Pause for a second to let chart finish rendering
                    pausecomp(200);
                } else {
                    browserResize();
                }
            },
            browserResize = function() {
                self.paretoChart.width(null).height(null).update();
            };

        if (state === 'on') {
            if (window.matchMedia) {
                mediaQueryList.addListener(printResize);
            } else if (window.attachEvent) {
                window.attachEvent('onbeforeprint', printResize);
                window.attachEvent('onafterprint', printResize);
            } else {
                window.onbeforeprint = printResize;
                window.onafterprint = browserResize;
            }
        } else {
            if (window.matchMedia) {
                mediaQueryList.removeListener(printResize);
            } else if (window.detachEvent) {
                window.detachEvent('onbeforeprint', printResize);
                window.detachEvent('onafterprint', printResize);
            } else {
                window.onbeforeprint = null;
                window.onafterprint = null;
            }
        }
    },

    /**
     * @inheritdoc
     * Clean up!
     */
    unbindData: function() {
        // we need this if because Jasmine breaks with out as you can't define a view with a layout in Jasmine Test
        // @see BR-1217
        if (this.view.layout) {
            this.view.layout.off('dashlet:collapse', null, this);
            this.view.layout.context.off('dashboard:collapse:fire', null, this);
            this.view.layout.context.off('dashlet:draggable:stop', null, this);
        }
        app.events.off(null, null, this);
        this._super('unbindData');
    },

    /**
     * Render the chart for the first time
     *
     * @param {Object} [options]        Options from the dashlet loaddata call
     */
    renderChart: function(options) {
        if (this.disposed || !this.triggerBefore('chart:pareto:render') ||
            _.isUndefined(this.model.get('timeperiod_id')) ||
            _.isUndefined(this.model.get('user_id'))
        ) {
            return;
        }

        this._serverData = undefined;

        // just on the off chance that no options param is passed in
        options = options || {};
        options.success = _.bind(function(data) {
            if(this.model) {
                this.model.set({
                    title: data.title
                });
                this._serverData = data;
                if (data.error) {
                    app.alert.show('chart_error', {
                        level: 'error',
                        messages: data.error
                    });

                    this.trigger('chart:pareto:rendered');
                } else {
                    this.convertDataToChartData();
                    this.generateChart();
                }
            }
        }, this);

        var read_options = {};
        if (this.model.has('no_data') && this.model.get('no_data') === true) {
            read_options['no_data'] = 1;
        }

        // if this is a manager view, send the target_quota param to the endpoint
        if(this.model.get('display_manager')) {
            read_options['target_quota'] = (this.model.get('show_target_quota')) ? 1 : 0;
        }

        var url = app.api.buildURL(this.buildChartUrl(), null, null, read_options);

        app.api.call('read', url, {}, options);
    },

    /**
     * Generate the Chart Object
     */
    generateChart: function() {
        const displayManager = this.model.get('display_manager');
        const chartData = this.chartData;

        // clear out the current chart before a re-render
        if (!_.isEmpty(this.paretoChart)) {
            $(window).off('resize.' + this.sfId);
            this.paretoChart.destroy();
        }

        if (chartData.data.length > 0) {
            // if the chart element is hidden by a previous render, but has data now, show it
            this.displayNoData(false);

            const canvas = this.$('canvas#' + this.cid);
            const ctx = canvas[0].getContext('2d');

            const data = {
                datasets: chartData.data,
            };
            const linePosition = {
                id: 'linePosition',
                beforeDatasetsDraw: function(chart) {
                    const datasets = chart.data.datasets;
                    const bars = _.filter(datasets, dataset => dataset.type === 'bar');
                    const pairs = [];

                    _.each(bars, bar => {
                        const barSerie = bar.series;
                        const line = _.find(datasets, dataset => dataset.series === barSerie &&
                            dataset.type === 'line') || {};

                        pairs.push([datasets.indexOf(bar), datasets.indexOf(line)]);
                    });

                    _.each(pairs, pair => {
                        const barData = chart.getDatasetMeta(pair[0]).data;
                        const lineData = chart.getDatasetMeta(pair[1]).data;

                        lineData[0].x = barData[0].x;
                        lineData[1].x = barData[1].x;
                    });
                }
            };
            const config = {
                data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    onClick: (event, elements) => this.handleChartClick(event, elements),
                    layout: {
                        padding: {
                            right: 10,
                            left: 10
                        }
                    },
                    plugins: {
                        tooltip: {
                            enabled: false,
                            external: context => this.generateExternalTooltip(context),
                        },
                        legend: {
                            display: false,
                        },
                        annotation: {
                            annotations: this.getAnnotations(),
                        },
                    },
                    scales: {
                        x: {
                            stacked: !displayManager,
                            grid: {
                                display: false,
                            },
                            border: this.getScaleStyle(),
                        },
                        y: this.getYScaleConfig(),
                    }
                },
            };

            if (displayManager) {
                this.applyCategoryScaleConfig(config);
                config.plugins = [linePosition];
            } else {
                this.applyTimeScaleConfig(config);
                config.plugins = [];
            }

            this.paretoChart = new Chart(ctx, config);

            this.updateLinePosition(this.paretoChart);
            this.generateLegend();

            $(window).on('resize.' + this.sfId, _.debounce(_.bind(this.resize, this), 100));
            this.handlePrinting('on');
        } else {
            this.displayNoData(true);
        }

        this.trigger('chart:pareto:rendered');
    },

    /**
     * Get the annotations for the chart
     */
    getAnnotations: function() {
        const displayManager = this.model.get('display_manager');
        const chartData = this.chartData;
        const chartProperties = chartData.properties;
        const quota = chartProperties.quota;
        const targetQuota = chartProperties.targetQuota;
        const annotations = {};

        if (quota) {
            annotations.quota = {
                type: 'line',
                yMin: quota,
                yMax: quota,
                enter: (context, event) => this.generateAnnotationTooltip(context, event),
                leave: context => this.hideAnnotationTooltip(context),
            };
        }

        if (displayManager && targetQuota) {
            annotations.targetQuota = {
                type: 'line',
                yMin: targetQuota,
                yMax: targetQuota,
                enter: (context, event) => this.generateAnnotationTooltip(context, event, true),
                leave: context => this.hideAnnotationTooltip(context),
            };
        }

        _.each(annotations, (annotation, key) => {
            if (this.chartDefaults && this.chartDefaults[key]) {
                _.each(this.chartDefaults[key], (value, subKey) => {
                    annotation[subKey] = value;
                });
            }
        });

        return annotations;
    },

    /**
     * Generate the legend for the chart
     */
    generateLegend: function() {
        if (!this.paretoChart) {
            return;
        }

        const chart = this.paretoChart;
        const chartData = chart.data || {};
        const chartProperties = this.chartData.properties;
        const legendMeta = [];
        const disabledKeys = this.getDisabledChartKeys();
        const displayManager = this.model.get('display_manager');
        const chartDefaults = this.chartDefaults;

        _.each(chartData.datasets, dataset => {
            const lineItem = dataset.type === 'line';
            const label = dataset.label;
            const color = dataset.backgroundColor;
            const legendItem = {
                id: app.utils.generateUUID(),
                series: dataset.series,
                visible: !(_.contains(disabledKeys, label)),
                label,
                color,
                type: dataset.type,
                lineItem,
                displayManager,
                callback: () => this.toggleChartElementVisibility(dataset, chart),
            };
            legendMeta.push(legendItem);
        });

        legendMeta.push({
            id: app.utils.generateUUID(),
            visible: true,
            label: chartProperties.quotaLabel,
            type: 'line',
            dottedItem: true,
            color: chartDefaults.quota.borderColor,
        });

        if (displayManager && chartProperties.targetQuota) {
            legendMeta.push({
                id: app.utils.generateUUID(),
                visible: true,
                label: chartProperties.targetQuotaLabel,
                type: 'line',
                dottedItem: true,
                color: chartDefaults.targetQuota.borderColor,
            });
        }

        this._customLegend = app.view.createView({
            type: 'pareto-legend',
            context: this.context,
            model: this.model,
            layout: this,
            legendMeta,
        });

        this._customLegend.render();

        const legendContainer = this.$('#custom-legend');

        legendContainer.empty();
        legendContainer.append(this._customLegend.$el);
    },

    /**
     * Toggle chart element visibility
     *
     * @param {Object} dataset
     * @param {Object} chart
     */
    toggleChartElementVisibility: function(dataset, chart) {
        if (!chart || !chart.data) {
            return;
        }

        const chartBars = _.filter(chart.data.datasets, dataset => dataset.type === 'bar');
        const chartLines = _.filter(chart.data.datasets, dataset => dataset.type === 'line');
        const hiddenBars = _.filter(chartBars, bar => bar.hidden);
        const displayManager = this.model.get('display_manager');

        if (hiddenBars.length === chartBars.length - 1) {
            _.each(chartBars, bar => bar.hidden = false);
        } else {
            dataset.hidden = !dataset.hidden;
        }

        this.updateLinePosition(chart);

        if (displayManager) {
            _.each(chartLines, line => line.hidden = false);

            const lineData = _.find(chartLines, line => dataset.series === line.series) || {};
            lineData.hidden = dataset.hidden;

            chart.update();
        }
    },

    /**
     * Update the line position
     *
     * @param {Object} chart
     */
    updateLinePosition: function(chart) {
        if (!chart || !chart.data) {
            return;
        }

        const displayManager = this.model.get('display_manager');

        if (displayManager) {
            return;
        }
        const datasets = chart.data.datasets;
        const visibleBars = _.filter(datasets, dataset => dataset.type === 'bar' && !dataset.hidden);
        const lineData = _.find(datasets, dataset => dataset.type === 'line' && !dataset.hidden) || {};

        _.each(lineData.data, data => {
            data.y = 0;
        });

        _.each(visibleBars, bar => {
            _.each(bar.data, (point, index) => {
                lineData.data[index].y += point.y;
            });
        });

        _.each(lineData.data, (point, index) => {
            if (index > 0) {
                point.y += lineData.data[index - 1].y;
            }
        });

        chart.update();
    },

    /**
     * Chart click event handler
     *
     * @param {Object} event
     * @param {Array} elements
     */
    handleChartClick: function(event, elements) {
        if (elements.length > 0) {
            const datasetIndex = elements[0].datasetIndex;
            const legend = this._customLegend;
            const legendItem = legend._barLegend[datasetIndex];

            legend.updateLegendStyle(legendItem);
        }
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
        const chartProp = this.chartData.properties;

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
            const pointY = point.raw.y;
            let template = '';

            point.key = point.dataset.label;
            point.y = app.currency.formatAmountLocale(pointY, app.currency.getBaseCurrencyId());

            if (point.dataset.type === 'bar') {
                point.label = this.model.get('group_by') === 'probability' ?
                    (app.lang.get('LBL_OW_PROBABILITY', 'Forecasts') + ' (%)') :
                    app.lang.get('LBL_SALES_STAGE', 'Forecasts');
                point.x = (pointY * 100 / chartProp.groupData[point.dataIndex].t).toFixed(1);

                template = this.barTooltipTemplate(point).replace(/(\r\n|\n|\r)/gm, '');
            } else {
                template = this.lineTooltipTemplate(point).replace(/(\r\n|\n|\r)/gm, '');
            }

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
     * Create the tooltip element for annotations
     *
     * @param {Object} context
     * @param {Object} event
     * @param {boolean} targetQuota
     */
    generateAnnotationTooltip: function(context, event, targetQuota = false) {
        let tooltipEl = $('.chartjs-tooltip');

        // Create element on first render
        if (!tooltipEl.length) {
            tooltipEl = this.createTooltipElement();
        }

        const chartProp = this.chartData.properties;
        const key = targetQuota ? chartProp.targetQuotaLabel : chartProp.quotaLabel;
        const value = targetQuota ? chartProp.targetQuota : chartProp.quota;
        const point = {
            key,
            y: app.currency.formatAmountLocale(value, app.currency.getBaseCurrencyId()),
        };

        tooltipEl.html(this.quotaTooltipTemplate(point).replace(/(\r\n|\n|\r)/gm, ''));

        const tooltipPosition = this.getTooltipPosition(context, tooltipEl, event.x, event.y);

        tooltipEl.css({
            opacity: 1,
            left: tooltipPosition.left + 'px',
            top: tooltipPosition.top + 'px',
        });
    },

    /**
     * Hide the annotation tooltip
     *
     * @param {Object} context
     */
    hideAnnotationTooltip: function(context) {
        let tooltipEl = $('.chartjs-tooltip');

        tooltipEl.css({
            opacity: 0
        });
    },

    /**
     * Apply the time scale configuration to the chart
     *
     * @param {Object} config
     */
    applyTimeScaleConfig: function(config) {
        const xScaleLimits = this.getXScaleLimits();
        Object.assign(config.options.scales.x, {
            type: 'time',
            time: {unit: 'month'},
            min: xScaleLimits[0],
            max: xScaleLimits[1],
            ticks: {
                color: this.getTextColor(),
                callback: (value, index) => this.formatXTick(value, index)
            },
        });
    },

    /**
     * Apply the category scale configuration to the chart
     *
     * @param {Object} config
     */
    applyCategoryScaleConfig: function(config) {
        const labels = _.map(this.chartData.properties.groupData, group => group.label);

        Object.assign(config.options.scales.x, {
            type: 'category',
            labels,
            ticks: {
                color: this.getTextColor(),
            },
        });
    },

    /**
     * Get y scale configuration for the chart
     */
    getYScaleConfig: function() {
        const displayManager = this.model.get('display_manager');
        const scaleStyle = this.getScaleStyle();

        return {
            stacked: !displayManager,
            border: {
                display: false
            },
            grid: {
                color: (context) => {
                    return context.tick && context.tick.major ? 'transparent' : scaleStyle.color;
                },
            },
            max: this.getYScaleMax(),
            afterDataLimits: (scale) => {
                scale.max *= 1.1;
            },
            ticks: {
                color: this.getTextColor(),
                font: {
                    weight: (context) => {
                        if (context.tick && context.tick.major) {
                            return 'bold';
                        }

                        return 'normal';
                    },
                },
                callback: (value, index, values) => this.formatYTick(value, index, values),
            },
            afterBuildTicks: (scale) => {
                const chartProperties = this.chartData.properties;
                const quota = chartProperties.quota;
                const displayManager = this.model.get('display_manager');
                const ticks = scale.ticks;
                ticks[0].major = true;
                ticks[ticks.length - 1].major = true;

                this.markQuotaTicks(scale, quota);

                if (displayManager && chartProperties.targetQuota) {
                    const targetQuota = chartProperties.targetQuota;

                    this.markQuotaTicks(scale, targetQuota);
                }
            },
        };
    },

    /**
     * Replace scale ticks that are close to quota and add quota ticks
     *
     * @param {Object} scale
     * @param {number} quotaValue
     */
    markQuotaTicks: function(scale, quotaValue) {
        const threshold = 0.4;
        const ticks = scale.ticks;

        if (!ticks.length || ticks.length < 2) {
            return;
        }

        const tickInterval = ticks[1].value - ticks[0].value;

        _.each(ticks, tick => {
            const isCloseToQuota = Math.abs(tick.value - quotaValue) <= threshold * tickInterval;

            if (isCloseToQuota) {
                tick.major = true;
                tick.value = quotaValue;
            }
        });

        ticks.push({
                value: quotaValue,
                major: true,
                label: this.getFormattedQuotaValue(quotaValue)
            }
        );
    },

    /**
     * Format the time scale x-axis ticks
     *
     * @param {number} value
     * @param {number} index
     */
    formatXTick: function(value, index) {
        const chartProperties = this.chartData.properties;

        if (!chartProperties.groupData[index]) {
            return '';
        }

        const originalLabel = chartProperties.groupData[index].label;
        const date = new Date(originalLabel);

        if (!isNaN(date)) {
            const year = date.getFullYear();
            const month = (date.getMonth() + 1).toString().padStart(2, '0');
            const day = date.getDate().toString().padStart(2, '0');

            return `${month}/${day}/${year}`;
        } else {
            const parsedDate = this.parseMonthYear(originalLabel);

            if (parsedDate) {
                const {month, year} = parsedDate;
                const monthNumber = month.toString().padStart(2, '0');

                return `${monthNumber}/01/${year}`;
            }
        }

        return originalLabel;
    },

    /**
     * Parse the month and year from the label
     *
     * @param {string} label
     */
    parseMonthYear: function(label) {
        if (_.isString(label)) {
            const match = label.match(/^([a-zA-Z]+) (\d{4})$/);

            if (match) {
                const monthIndex = 1;
                const yearIndex = 2;
                const monthName = match[monthIndex].toLowerCase();
                const year = match[yearIndex];

                const months = {
                    january: 1, february: 2, march: 3, april: 4, may: 5, june: 6,
                    july: 7, august: 8, september: 9, october: 10, november: 11, december: 12
                };

                const monthNumber = months[monthName];

                if (monthNumber) {
                    return {month: monthNumber, year};
                }
            }
        }

        return null;
    },

    /**
     * Format the y-axis ticks
     *
     * @param {number} value
     * @param {number} index
     * @param {Array} values
     */
    formatYTick: function(value, index, values) {
        if (!values[index].label) {
            return SUGAR.charts.sugarApp.utils.charts.numberFormatSI(value, 2, true);
        }
    },

    /**
     * Get the x scale limits for the chart
     */
    getXScaleLimits: function() {
        const xValues = this.chartData.properties.groupData.map(group => group.label);
        const minDate = new Date(xValues[0]);
        const maxDate = new Date(xValues[xValues.length - 1]);

        minDate.setMonth(minDate.getMonth() - 1);

        const midDay = Math.floor(this.daysInMonth(minDate) / 2);

        minDate.setDate(midDay);
        maxDate.setMonth(maxDate.getMonth() + 1);

        return [minDate.getTime(), maxDate.getTime()];
    },

    /**
     * Format the quota value
     *
     * @param {number} value
     */
    getFormattedQuotaValue: function(value) {
        if (value < 1000) {
            value = value / 1000;
        }

        return SUGAR.charts.sugarApp.utils.charts.numberFormatSI(value, 2, true);
    },

    /**
     * Get the y values for the chart
     */
    getYValues: function() {
        const yValues = [];

        _.each(this.chartData.data, group => {
            _.each(group.data, data => {
                yValues.push(data.y);
            });
        });

        return yValues;
    },

    /**
     * Get the max value for the y scale
     */
    getYScaleMax: function() {
        if (!this.chartData || !this.chartData.properties) {
            return 0;
        }

        const yValues = this.getYValues();
        const maxY = Math.max(...yValues);
        const quota = this.chartData.properties.quota;
        const max = Math.max(maxY, quota);

        return Math.round(max / 1000) * 1000;
    },

    /**
     * Utility method to determine which data we need to parse,
     */
    convertDataToChartData: function() {
        if (this.state === 'closed' || this.preview_open || this.collapsed || _.isUndefined(this._serverData)) {
            return -1;
        }

        if (this.model.get('display_manager')) {
            this.convertManagerDataToChartData();
        } else {
            this.convertRepDataToChartData(this.model.get('group_by'));
        }
    },

    /**
     * Parse the Manager Data and set the chartData object
     */
    convertManagerDataToChartData: function() {
        const serverData = this._serverData;
        const colors = SUGAR.charts.getColorPalette();
        const dataset = this.model.get('dataset');
        const records = serverData.data;
        const quotaLabel = app.lang.get((this.model.get('show_target_quota')) ?
            'LBL_QUOTA_ADJUSTED' : 'LBL_QUOTA', 'Forecasts');
        const chartData = {
            'properties': {
                'name': serverData.title,
                'quota': parseFloat(serverData.quota),
                'yDataType': 'currency',
                'xDataType': 'category',
                'quotaLabel': quotaLabel,
                'groupData': _.map(records, (record, i) => {
                    return {
                        group: i,
                        label: record.name,
                        t: parseFloat(record[dataset]) + parseFloat(record[dataset + '_adjusted'])
                    };
                }),
            },
            'data': []
        };
        const disabledKeys = this.getDisabledChartKeys();
        const barData = [dataset, dataset + '_adjusted'].map(function(ds, seriesIdx) {
                const vals = records.map(function(rec, recIdx) {
                        return {
                            series: seriesIdx,
                            x: recIdx + 1,
                            y: parseFloat(rec[ds]),
                            y0: 0
                        };
                    });
                const label = serverData.labels.dataset[ds];

                return {
                    hidden: (_.contains(disabledKeys, label)),
                    label,
                    series: seriesIdx,
                    type: 'bar',
                    data: vals,
                    valuesOrig: vals,
                    order: 1
                };
            }, this);
        const lineData = [dataset, dataset + '_adjusted'].map(function(ds, seriesIdx) {
                const vals = _.map(records, (rec, recIdx) => {
                    return {
                        series: seriesIdx,
                        x: recIdx + 1,
                        y: parseFloat(rec[ds])
                    };
                });
                let addToLine = 0;
                const label = serverData.labels.dataset[ds];

                _.each(vals, function(val, i, list) {
                    list[i].y += addToLine;
                    addToLine = list[i].y;
                });

                return {
                    hidden: (_.contains(disabledKeys, label)),
                    label,
                    series: seriesIdx,
                    type: 'line',
                    data: vals,
                    valuesOrig: vals,
                };
            }, this);

        if(this.model.get('show_target_quota')) {
            // add target quota to chart data
            chartData.properties.targetQuota = +serverData.target_quota;
            chartData.properties.targetQuotaLabel = app.lang.get('LBL_QUOTA', 'Forecasts');
        }

        _.each(this.chartDefaults.line, (value, key) => {
            lineData.forEach(line => {
                line[key] = value;
            });
        });
        chartData.data = barData.concat(lineData);

        _.each(chartData.data, (group, index) => {
            group.backgroundColor = colors[index];
            group.borderColor = colors[index];
        });
        this.chartData = chartData;
    },

    /**
     * Convert the Rep Data and set the chartData Object
     *
     * @param {string} type     What we are dispaying
     */
    convertRepDataToChartData: function(type) {
        const serverData = this._serverData;
        // clear any NaNs
        _.each(serverData.data, function(point) {
            if (_.has(point, 'likely') && isNaN(point.likely)) {
                point.likely = 0;
            }
            if (_.has(point, 'best') && isNaN(point.best)) {
                point.best = 0;
            }
            if (_.has(point, 'worst') && isNaN(point.worst)) {
                point.worst = 0;
            }
        });

        const xAxisData = serverData['x-axis'];
        const colors = SUGAR.charts.getColorPalette();
        let dataset = this.model.get('dataset');
        let ranges = this.model.get('ranges');
        let seriesIdx = 0;
        let barData = [];
        let lineVals = xAxisData ?
            _.map(xAxisData, axis => {
                const time = this.getTimestamp(axis.label);

                return {series: seriesIdx, x: time, y: 0};
            }) : {};
        const line = {
            'label': serverData.labels.dataset[dataset],
            'type': 'line',
            'series': seriesIdx,
            'data': [],
            'valuesOrig': [],
        };
        const chartData = {
            'properties': {
                'name': serverData.title,
                'quota': parseFloat(serverData.quota),
                'yDataType': 'currency',
                'xDataType': 'time',
                'quotaLabel': app.lang.get('LBL_QUOTA', 'Forecasts'),
                'groupData': xAxisData.map(function(item, i) {
                    return {
                        'group': i,
                        'label': item.label,
                        't': 0
                    };
                }),
            },
            'data': []
        };
        let records = serverData.data;
        let data = (!_.isEmpty(ranges)) ? records.filter(function(rec) {
            return _.contains(ranges, rec.forecast);
        }) : records;
        let disabledKeys = this.getDisabledChartKeys();

        _.each(serverData.labels[type], function(label, value) {
            const td = data.filter(d => {
                const convertedValue = type === 'probability' ? Number(value) : value;

                return d[type] === convertedValue;
            });

            if (!_.isEmpty(td)) {
                const barVal = _.map(xAxisData, axis => {
                    const time = this.getTimestamp(axis.label);

                    return {series: seriesIdx, x: time, y: 0, y0: 0};
                });
                const axis = xAxisData;

                // loop though all the data and map it to the correct x series
                _.each(td, function(record) {
                    for (let y = 0; y < axis.length; y++) {
                        if (record.date_closed_timestamp >= axis[y].start_timestamp &&
                            record.date_closed_timestamp <= axis[y].end_timestamp) {
                            // add the value
                            const val = parseFloat(record[dataset]);
                            barVal[y].y += val;
                            chartData.properties.groupData[y].t += val;
                            lineVals[y].y += val;
                            break;
                        }
                    }
                }, this);

                barData.push({
                    hidden: (_.contains(disabledKeys, label)),
                    label: label,
                    series: seriesIdx,
                    type: 'bar',
                    data: barVal,
                    valuesOrig: app.utils.deepCopy(barVal),
                    order: 1,
                });

                // increase the series
                seriesIdx++;
            }
        }, this);

        if (!_.isEmpty(barData)) {
            // fix the line
            var addToLine = 0;
            _.each(lineVals, function(val, i, list) {
                list[i].y += addToLine;
                addToLine = list[i].y;
            });

            line.data = lineVals;
            line.valuesOrig = app.utils.deepCopy(lineVals);

            _.each(this.chartDefaults.line, (value, key) => {
                line[key] = value;
            });

            barData.push(line);

            _.each(barData, (group, index) => {
                group.backgroundColor = colors[index];
                group.borderColor = colors[index];
            });
            chartData.data = barData;
        }

        this.chartData = chartData;
    },

    /**
     * Convert string date to time
     *
     * @param {string} dateLabel
     */
    getTimestamp: function(dateLabel) {
        let time = new Date(dateLabel).getTime();

        if (isNaN(time)) {
            const firstDayOfMonth = 1;
            const parsedDate = this.parseMonthYear(dateLabel);
            time = parsedDate ? new Date(parsedDate.year, parsedDate.month - 1, firstDayOfMonth).getTime() : 0;
        }

        return time;
    },

    /**
     * Look at the current chart if it exists and return the keys that are currently
     * hidden they can still be hidden when the chart is re-rendered
     *
     * @return {Array}
     */
    getDisabledChartKeys: function() {
        if (_.isUndefined(this.paretoChart) || _.isUndefined(this.paretoChart.data)) {
            return [];
        }

        const currentChartData = this.paretoChart.data.datasets;
        const disabledBars = _.filter(currentChartData, group => !_.isUndefined(group.hidden) &&
            group.hidden === true);

        return (!_.isEmpty(disabledBars)) ? _.map(disabledBars, group => group.label) : [];
    },

    /**
     * Accepts params object and builds the proper endpoint url for charts
     *
     * @return {String} has the proper structure for the chart url.
     */
    buildChartUrl: function() {
        const baseUrl = this.model.get('display_manager') ? 'ForecastManagerWorksheets' : 'ForecastWorksheets';
        return baseUrl + '/chart/' + this.model.get('timeperiod_id') + '/' + this.model.get('user_id');
    },

    /**
     * Do we have serverData yet?
     * @return {boolean}
     */
    hasServerData: function() {
        return !_.isUndefined(this._serverData);
    },

    /**
     * Return the data that was passed back from the server
     * @return {Object}
     */
    getServerData: function() {
        return this._serverData;
    },

    /**
     *
     * @param {Object} data
     * @param {Boolean} [adjustLabels]
     */
    setServerData: function(data, adjustLabels) {
        this.throttledSetServerData(data, adjustLabels);
    },

    /**
     * This method is called by the _.throttle call in initialize
     *
     * @param {Object} data
     * @param {Boolean} [adjustLabels]
     * @private
     */
    _setServerData: function(data, adjustLabels) {
        this._serverData = data;

        if (adjustLabels === true) {
            this.adjustProbabilityLabels();
        }
        this.renderDashletContents();
    },

    /**
     * When the Probability Changes on the Rep Worksheet, The labels in the chart data need to be updated
     * to Account for the potentially new label.
     */
    adjustProbabilityLabels: function() {
        const probabilities = _.unique(_.map(this._serverData.data, function(item) {
            return item.probability;
        })).sort();

        this._serverData.labels.probability = _.object(probabilities, probabilities);
    },

    /**
     * @inheritdoc
     */
    _dispose: function() {
        this.handlePrinting('off');
        $(window).off('resize.' + this.sfId);
        this.$('.sc-chart').off('click');
        this._super('_dispose');
    }

})
