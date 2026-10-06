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
({
    results: {},
    chart: {},
    plugins: ['Dashlet', 'Chart'],

    /**
     * Is the forecast Module setup??
     */
    forecastSetup: 0,

    /**
     * Track if current user is manager.
     */
    isManager: false,

    /**
     * @inheritDoc
     */
    initialize: function(options) {
        this.isManager = app.user.get('is_manager');
        this._initPlugins();
        this._super('initialize', [options]);

        // check to make sure that forecast is configured
        var forecastConfig = app.metadata.getModule('Forecasts', 'config') || {};
        this.forecastSetup = forecastConfig['is_setup'] || 0;
        this.userCurrencyPreference = app.user.getPreference('currency_id');
        this.locale = SUGAR.charts.getUserLocale();
        this.tooltipTemplate = app.template.getView(this.name + '.tooltiptemplate');
        this._customLegend = false;
    },

    /**
     * {@inheritDoc}
     */
    initDashlet: function(view) {
        var salesStageLabels = app.lang.getAppListStrings('sales_stage_dom');

        if (!this.isManager && this.meta.config) {
            // FIXME: Dashlet's config page is rendered from meta.panels directly.
            // See the "dashletconfiguration-edit.hbs" file.
            this.meta.panels = _.chain(this.meta.panels).filter(function(panel) {
                panel.fields = _.without(panel.fields, _.findWhere(panel.fields, {name: 'visibility'}));
                return panel;
            }).value();
        }

        // get the current timeperiod
        if (this.forecastSetup) {
            app.api.call('GET', app.api.buildURL('TimePeriods/current'), null, {
                success: _.bind(function(currentTP) {
                    this.settings.set({'selectedTimePeriod': currentTP.id}, {silent: true});
                    this.layout.loadData();
                }, this),
                error: _.bind(function() {
                    // Needed to catch the 404 in case there isnt a current timeperiod
                }, this),
                complete: view.options ? view.options.complete : null
            });
        } else {
            this.settings.set({'selectedTimePeriod': 'current'}, {silent: true});
        }
    },

    /**
     * This method is called by the chart model in initDashlet
     *
     * @param {number} d  The numeric value to be formatted
     * @param {number} precision  The level of precision to apply
     * @return {string}  A number formatted with current user settings
     * @private
     */
    _formatValue: function(d, precision) {
        return app.currency.formatAmountLocale(d, this.userCurrencyPreference, precision);
    },

    /**
     * Initialize plugins.
     * Only manager can toggle visibility.
     *
     * @return {View.Views.BaseForecastPipeline} Instance of this view.
     * @protected
     */
    _initPlugins: function() {
        if (this.isManager) {
            this.plugins = _.union(this.plugins, [
                'ToggleVisibility'
            ]);
        }
        return this;
    },

    /**
     * {@inheritDoc}
     */
    bindDataChange: function() {
        this.settings.on('change', function(model) {
            // reload the chart
            if (this.$el && this.$el.is(':visible')) {
                this.loadData({});
            }
        }, this);
    },

    /**
     * Generic method to render chart with check for visibility and data.
     * Called by _renderHtml and loadData.
     */
    renderChart: function() {
        if (!this.isChartJSReady()) {
            this.disposeLegend();
            return;
        }

        // Clear out the current chart before a re-render
        if (this.chart && typeof this.chart.destroy === 'function') {
            this.chart.destroy();
        }

        const data = this.getFormattedData(this.results);
        const chartOptions = {
            chartType: 'funnelChart',
            chartElementId: this.cid,
            show_legend: false,
            maintainAspectRatio: false,
            isCompactChart: true,
            disableChartClick: true,
            datalabels: {
                textAlign: 'center',
            },
            tooltip: {
                label: this.getTooltipLabel,
                backgroundColor: '#50575b',
            },
            plugins: {
                datalabels: {
                    formatter: _.bind(this.datalabelsFormatter, this),
                }
            },
            display: _.bind(this.display, this),
            customPlugins: [this.getCustomPlugin()],
        };

        this.chart = SUGAR.charts.getChartInstance(data, chartOptions);
        this.chart = this.chart.createChart();

        this.generateLegend();
    },

    /**
     * Calculate if the label should be displayed on the chart
     *
     * @param {Object} context
     */
    display: function(context) {
        const datasetIndex = context.datasetIndex || 0;
        const dataIndex = context.dataIndex || 0;
        const chart = context.chart;
        const ctx = chart.ctx;
        const chartData = chart.data || {};

        const value = chartData && chartData.datasets ? chartData.datasets[datasetIndex].data[dataIndex] : 0;
        const label = value ? this.datalabelsFormatter(value, context) : '';

        const targetSet = chart.getDatasetMeta(datasetIndex);
        const targetElement = targetSet && targetSet.data ? targetSet.data[dataIndex] : false;
        const targetCorners = targetElement ? targetElement._cornersCache : false;

        const topLeftCornerIndex = 0;
        const topRightCornerIndex = 3;
        const bottomLeftCornerIndex = 1;
        const bottomRightCornerIndex = 2;
        const xPosIndex = 0;
        const yPosIndex = 1;
        const minHeightAllowed = 35;

        let barHeight = 0;
        let displayLabel = false;

        if (targetCorners &&
            targetCorners[topLeftCornerIndex] &&
            targetCorners[bottomLeftCornerIndex]) {
            const topLeftCorner = targetCorners[topLeftCornerIndex] || [];
            const bottomLeftCorner = targetCorners[bottomLeftCornerIndex] || [];
            const topRightCorner = targetCorners[topRightCornerIndex] || [];
            const bottomRightCorner = targetCorners[bottomRightCornerIndex] || [];
            const topY = topLeftCorner[yPosIndex] || 0;
            const bottomY = bottomLeftCorner[yPosIndex] || 0;
            const topWidth = topRightCorner[xPosIndex] - topLeftCorner[xPosIndex];
            const bottomWidth = bottomRightCorner[xPosIndex] - bottomLeftCorner[xPosIndex];

            if (label && label.length) {
                const bottomLabelWidth = ctx.measureText(label[0]).width;
                const topLabelWidth = ctx.measureText(label[label.length - 1]).width;
                displayLabel = topWidth >= topLabelWidth && bottomWidth >= bottomLabelWidth;
            }

            barHeight = topY - bottomY;
        }

        return barHeight >= minHeightAllowed && displayLabel;
    },

    /**
     * Formatter for datalabels
     *
     * @param {number} value
     * @param {Object} context
     *
     * @return {Array|string}
     */
    datalabelsFormatter: function(value, context) {
        const chart = context.chart;
        const element = chart.getDatasetMeta(context.datasetIndex).data[context.dataIndex];
        const cornersCache = element._cornersCache || [];

        if (cornersCache && cornersCache.length) {
            const label = chart.config.data.labels[context.dataIndex];
            const text = this.getFormattedLabel(label, value);
            const ctx = chart.ctx;
            const labelWidth = ctx.measureText(label).width;
            const rightTopIndex = 3;
            const leftTopIndex = 0;
            const xPosIndex = 0;
            const topWidth = cornersCache[rightTopIndex][xPosIndex] - cornersCache[leftTopIndex][xPosIndex];

            if (topWidth < labelWidth) {
                const words = label.split(/\s+/);

                if (words.length === 1) {
                    return '';
                }
                text.splice(0, 1, ...words);
            }

            return text;
        }

        return '';
    },

    /**
     * Get the formatted label to be displayed
     *
     * @param {string|Array} label
     * @param {number} value
     * @return {Array}
     */
    getFormattedLabel: function(label, value) {
        const targetData = this.results.data.find(data => {
            return data.key === label;
        });
        const formattedValue = SUGAR.charts.sugarApp.utils.charts.numberFormatSI(value, 3, true);

        return [label, formattedValue + ' (' + targetData.count + ')'];
    },

    /**
     * Format data for chart
     *
     * @param {Object} results
     * @return formattedData
     */
    getFormattedData: function(results) {
        const formattedData = {
            values: [],
        };

        _.each(results.data, dataBlock => {
            const values = _.map(dataBlock.values, function(values) {
                return values.value;
            });

            formattedData.values.push({
                count: dataBlock.count,
                key: dataBlock.key,
                label: dataBlock.key,
                values
            });
        });

        formattedData.values.reverse();
        formattedData.properties = results.properties;

        return formattedData;
    },

    /**
     * Get tooltip label
     *
     * @param {Object} chart
     * @param {Object} tooltip
     * @param {Object} data
     */
    getTooltipLabel: function(chart, tooltip, data) {
        let labels = [];

        let total = chart.sumValues(data.datasets[tooltip.datasetIndex].data);
        let value = data.datasets[tooltip.datasetIndex].data[tooltip.index];
        const dataTarget = chart.rawData.values.find(function(record) {
            return record.key === data.labels[tooltip.index];
        });
        labels.push(
            {
                text: chart.labels.tooltip.amount,
                value: chart.app.currency.formatAmountLocale(value, chart.locale.currency_id),
            },
            {
                text: chart.labels.tooltip.percent,
                value: chart.app.utils.charts.numberFormatPercent(value, total, chart.locale),
            },
            {
                text: chart.labels.tooltip.count,
                value: dataTarget.count,
            }
        );

        return labels.map(label => `${label.text}: ${label.value}`);
    },

    /**
     * Get custom plugin for chart
     */
    getCustomPlugin: function() {
        return {
            id: 'lineEffect',
            beforeDraw: chart => {
                const ctx = chart.ctx;
                const chartData = chart.data || {};
                const chartArea = chart.chartArea;
                const topLeftCornerIndex = 0;
                const yPosIndex = 1;
                const xPosIndex = 0;
                const paddingX = 5;
                const ctxLineColor = this.getTextColor();
                const ctxLineWidth = 0.3;
                const textAlign = 'left';
                const textBaseline = 'bottom';
                const defaultLineWidth = 240;
                const maxVerticalSpace = 64;
                const labelFontSize = 12;
                let oneLineLabel = true;
                const ctxOptions = {
                    strokeStyle: ctxLineColor,
                    lineWidth: ctxLineWidth,
                    textAlign,
                    textBaseline,
                    fillStyle: ctxLineColor,
                };
                chart.sideLineConfig = {
                    defaultLineWidth,
                    maxVerticalSpace,
                    labelFontSize,
                    oneLineLabel,
                };

                ctx.save();
                chart.changeCtxFontSize(chart.sideLineConfig.labelFontSize);

                _.each(chartData.datasets, (dataset, index) => {
                    const visibleElements = chart.getVisibleElements(index);

                    if (!chart.visibleElementsLastState ||
                        !_.isEqual(chart.visibleElementsLastState, visibleElements) ||
                        _.isUndefined(chart.chartOffset)) {
                        chart.visibleElementsLastState = visibleElements;
                        chart.needUpdate = true;
                    } else if (chart.chartOffset !== chart.chartOffsetLastState) {
                        chart.needUpdate = true;
                        chart.chartOffsetLastState = chart.chartOffset;
                    } else {
                        chart.needUpdate = false;
                    }

                    if (visibleElements && visibleElements.length) {
                        const sideLabelElements = this.getSideLabelElements(visibleElements);
                        chart.sideLineConfig.lineWidth = chart.getSideLineWidth(sideLabelElements);
                        const lineVerticalSpace = chart.getSideLineVerticalSpace(sideLabelElements);
                        let y = _.first(visibleElements)._view.y;

                        _.each(sideLabelElements, (element, index) => {
                            const cornersCache = element._cornersCache || [];

                            if (this.areCornersCalculated(cornersCache)) {
                                const topY = cornersCache[topLeftCornerIndex][yPosIndex];
                                const x = cornersCache[topLeftCornerIndex][xPosIndex];
                                const label = element._view.label;
                                const value = element._chart.data.datasets[element._datasetIndex].data[element._index];
                                const formattedLabel = this.getFormattedLabel(label, value);
                                const text = chart.sideLineConfig.oneLineLabel ?
                                    [formattedLabel.join(' ')] : formattedLabel;
                                const {startingX, endingLabelX} = chart.getSideLineXCoords(visibleElements, text);

                                // Reset y if labels exceed chart area
                                if (y - lineVerticalSpace * sideLabelElements.length < chartArea.top && index === 0) {
                                    y = chartArea.bottom;
                                }

                                chart.drawLine(startingX, endingLabelX, y, null, ctxOptions);
                                chart.drawLine(endingLabelX, endingLabelX + paddingX, y, topY);
                                chart.drawLine(endingLabelX + paddingX, x - paddingX, topY);
                                chart.drawTextLine(text, startingX, y);

                                y -= lineVerticalSpace;
                            }
                        });
                    }
                });

                ctx.restore();
            },
            afterDraw: chart => {
                if (chart.needUpdate) {
                    chart.update();
                }
            },
        };
    },

    /**
     * Check if corners are calculated
     *
     * @param {Array} corners
     */
    areCornersCalculated: function(corners) {
        return corners &&
            corners.length &&
            !_.every(corners, corner => _.every(corner, coord => !coord));
    },

    /**
     * Get elements that do not have displayed label
     *
     * @param {Array} elements
     */
    getSideLabelElements: function(elements) {
        return _.filter(elements, element => {
            return !this.isLabelDisplayed(element);
        });
    },

    /**
     * Check if label is displayed
     *
     * @param {Object} element
     */
    isLabelDisplayed: function(element) {
        const dataLabels = element.$datalabels || [];

        return dataLabels.length && _.first(dataLabels)._model;
    },

    /**
     * Generate legend for chart
     */
    generateLegend: function() {
        if (!this.chart) {
            return;
        }

        const chart = this.chart;
        const chartData = chart.data || {};
        const legendMeta = [];

        _.each(chartData.datasets, (dataset, index) => {
            const meta = chart.getDatasetMeta(index);
            const chartElements = meta.data;

            _.each(chartElements, element => {
                const elementView = element._view;
                const label = elementView.label;
                const color = elementView.backgroundColor;
                const legendItem = {
                    id: app.utils.generateUUID(),
                    visible: true,
                    label,
                    color,
                    callback: () => {
                        this.toggleChartElementVisibility(element, chart);
                    },
                };
                legendMeta.push(legendItem);
            });
        });

        this._customLegend = app.view.createView({
            type: 'legend',
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
     * Dispose legend element
     */
    disposeLegend: function() {
        if (this._customLegend) {
            this._customLegend.dispose();

            this._customLegend = false;
        }
    },

    /**
     * Toggle chart element visibility
     *
     * @param {Object} element
     * @param {Object} chart
     */
    toggleChartElementVisibility: function(element, chart) {
        element.hidden = !element.hidden;
        chart.update();
    },

    hasChartData: function() {
        return !_.isEmpty(this.results) && this.results.data && this.results.data.length > 0;
    },

    /**
     * @inheritDoc
     */
    loadData: function(options) {
        var timeperiod = this.settings.get('selectedTimePeriod');
        if (timeperiod) {
            var oppsConfig = app.metadata.getModule('Opportunities', 'config');

            if (oppsConfig) {
                var oppsViewBy = oppsConfig['opps_view_by'];
            } else {
                this.results = {};
                this.renderChart();

                return false;
            }

            var url_base = oppsViewBy + '/chart/pipeline/' + timeperiod + '/';

            if (this.isManager) {
                url_base += this.getVisibility() + '/';
            }
            var url = app.api.buildURL(url_base);
            app.api.call('GET', url, null, {
                success: _.bind(function(o) {
                    if (o && o.data) {
                        var salesStageLabels = app.lang.getAppListStrings('sales_stage_dom');

                        // update sales stage labels to translated strings
                        _.each(o.data, function(dataBlock) {
                            if (dataBlock && dataBlock.key && salesStageLabels && salesStageLabels[dataBlock.key]) {
                                dataBlock.key = salesStageLabels[dataBlock.key];
                            }

                        });
                    }
                    this.results = {};
                    this.results = o;
                    this.renderChart();
                }, this),
                error: _.bind(function(o) {
                    this.results = {};
                    this.renderChart();
                }, this),
                complete: options ? options.complete : null
            });
        }
    },

    /**
     * @inheritDoc
     */
    unbind: function() {
        this.settings.off('change');
        this._super('unbind');
    }
})
