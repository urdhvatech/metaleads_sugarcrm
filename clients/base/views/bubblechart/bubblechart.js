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
 * @class View.Views.Base.BubblechartView
 * @alias SUGAR.App.view.views.BaseBubblechartView
 * @extends View.View
 */
({
    plugins: ['Dashlet', 'Chart'],

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        // Track if current user is manager
        this.isManager = app.user.get('is_manager');
        this._initPlugins();

        var config = app.metadata.getModule('Forecasts', 'config');
        // What module are we forecasting by?
        this.forecastBy = config && config.forecast_by || 'Opportunities';

        // set the title label in meta the same way the dashlet title is set on render
        options.meta.label = app.lang.get(options.meta.label, this.forecastBy);

        this._super('initialize', [options]);

        var fields = [
            'id',
            'name',
            'account_name',
            'base_rate',
            'currency_id',
            'assigned_user_name',
            'date_closed',
            'probability',
            'account_id',
            'sales_stage',
            'commit_stage'
        ];

        var orderBy = '';
        // Which field holds the likely case value?
        if (this.forecastBy === 'Opportunities') {
            fields.push('amount');
            orderBy = 'amount:desc';
            this.likelyField = 'amount';
        } else {
            fields.push('likely_case');
            orderBy = 'likely_case:desc';
            this.likelyField = 'likely_case';
        }

        this.params = {
            'fields': fields.join(','),
            'max_num': 10,
            'order_by': orderBy
        };

        // get the locale settings for the active user
        // this.locale is stored by reference in the chart model
        this.locale = SUGAR.charts.getUserLocale();

        this.tooltipTemplate = app.template.getView(this.name + '.tooltiptemplate');
    },

    /**
     * @inheritdoc
     */
    initDashlet: function(view) {
        var self = this;

        if (this.settings.get('filter_duration') == 0) {
            this.settings.set({'filter_duration': 'current'}, {'silent': true});
        }

        this.setDateRange();

        if (!this.isManager && this.meta.config) {
            // FIXME: Dashlet's config page is loaded from meta.panels directly.
            // See the "dashletconfiguration-edit.hbs" file.
            this.meta.panels = _.chain(this.meta.panels).filter(function(panel) {
                panel.fields = _.without(panel.fields, _.findWhere(panel.fields, {name: 'visibility'}));
                return panel;
            }).value();
        }

        this.setChartConfig();

        this.on('data-changed', function() {
            this.renderChart();
        }, this);
        this.settings.on('change:filter_duration', this.changeFilter, this);

        this.layout.on('render', function() {
            if (!this.disposed && !this.settings.get('config')) {
                this.layout.setTitle(app.lang.get(this.meta.label, this.forecastBy));
            }
        }, this);
    },

    /**
     * Initialize plugins.
     * Only manager can toggle visibility.
     *
     * @return {View.Views.BaseBubbleChart} Instance of this view.
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
     * Generic method to render chart with check for visibility and data.
     * Called by _renderHtml and loadData.
     */
    renderChart: function() {
        if (!this.isChartJSReady()) {
            return;
        }

        // Clear out the current chart before a re-render
        if (this.chart) {
            this.chart.destroy();
        }

        const canvas = this.$('canvas#' + this.cid);
        const ctx = canvas[0].getContext('2d');

        //clear the canvas before rendering the chart
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        const groupedData = this.getChartDatasets();
        this.chartConfig.data.datasets = groupedData;

        this.chart = new Chart(ctx, this.chartConfig);
    },

    /**
     * Calculate the time domain
     *
     * @return {Array} Array of time domain
     */
    getTimeDomain: function() {
        if (this.timeDomain.length === 0) {
            return [];
        }

        const dates = _.map(this.timeDomain, function(date) {
            return new Date(date);
        });
        const sortedDates = _.sortBy(dates, function(date) {
            return date.getTime();
        });

        const start = sortedDates[0];
        const end = sortedDates[sortedDates.length - 1];
        const startDate = new Date(start.getFullYear(), start.getMonth(), 1);
        const endDate = new Date(end.getFullYear(), end.getMonth() + 1, 0);

        return [startDate, endDate];
    },

    /**
     * Calculate and set the points values for y axis
     *
     * @return {Array} Array of datasets
    */
    scaleYValues: function(groupedData) {
        const self = this;
        const groupLength = groupedData.length;
        const yLabelValues = [];
        let gDomain = [0, 1];
        let gRange = [0, 1];
        let total = 0;

        const sorttedGroupedData = groupedData
            .map(function(group) {
                group.total = 0;

                group.values = group.values.sort(function(a, b) {
                    return b.y < a.y ? -1 : b.y > a.y ? 1 : 0;
                })
                .map(function(point) {
                    group.total += point.y;
                    return point;
                });

                return group;
            })
            .sort(function(a, b) {
                return a.total < b.total ? -1 : a.total > b.total ? 1 : 0;
            });

        const convertedData = _.map(sorttedGroupedData, function(group, index) {
            total += group.total;
            const originalYValues = _.map(group.values, function(point) { return point.y; });

            gDomain = self.getDomainValues(originalYValues);
            gRange = self.getRangeValues(index, groupLength);

            const deinterpolate = self.deinterpolateLinear(gDomain[0], gDomain[1]);
            const interpolate = self.interpolateNumber(gRange[0], gRange[1]);

            group.values = group.values.map(function(point) {
                point.opportunity = point.y;
                const t = deinterpolate(point.y);
                point.y = interpolate(t);

                return point;
            });

            let yValues = _.map(group.values, function(point) { return point.y; });
            yLabelValues.push({
                key: group.key,
                y: Math.min(...yValues)
            });

            return group;
        });

        self.yLabelValues = yLabelValues;

        return convertedData;
    },

    /**
     * Calculate and set the radius of the bubble points
     *
     * @return {Array} Array of datasets
    */
    setCircleRadius: function(groupedData) {
        const self = this;
        const radiusRange = [256, 1024];

        const yVAlues = this.getYValues(groupedData);
        let gDomain = this.getDomainValues(yVAlues);

        return _.map(groupedData, function(group) {
            const deinterpolate = self.deinterpolateLinear(gDomain[0], gDomain[1]);
            const interpolate = self.interpolateNumber(radiusRange[0], radiusRange[1]);

            group.data = group.data.map(function(point) {
                const t = deinterpolate(point.y);
                point.r = Math.sqrt(interpolate(t) / Math.PI);

                return point;
            });

            return group;
        });
    },

    /**
     * Get the y values of the datasets
     *
     * @return {Array} Array of y values
    */
    getYValues: function(groupedData) {
        const yValues = [];

        _.each(groupedData, function(group) {
            _.each(group.data, function(point) {
                yValues.push(point.y);
            });
        });

        return yValues;
    },

    /**
     * Reset the x scale range
     */
    resetXScaleRange: function() {
        const timeDomain = this.getTimeDomain();

        if (timeDomain.length > 0) {
            const startSpace = -1;

            this.chartConfig.options.scales.x.min = this.addDays(timeDomain[0], startSpace);
            this.chartConfig.options.scales.x.max = timeDomain[1];
        }
    },

    /**
     * Set chart configuration
    */
    setChartConfig: function() {
        const self = this;
        const hoverEffect = {
            id: 'hoverEffect',
            afterDatasetsDraw: _.bind(function(chart) {
                const ctx = chart.ctx;
                const activeElements = chart.getActiveElements();

                if (activeElements.length) {
                    const activeElement = activeElements[0].element;
                    const elementOptions = activeElement.options;
                    const {x, y} = activeElement;
                    const addedRadius = 5;

                    // Draw hover effect
                    ctx.save();
                    ctx.beginPath();
                    ctx.arc(x, y, elementOptions.radius + addedRadius, 0, 2 * Math.PI);
                    ctx.fillStyle = this.hexToRgba(elementOptions.backgroundColor, 0.2);
                    ctx.fill();
                    ctx.restore();
                }
            }, this)
        };

        const paddingBelowLegends = {
            id: 'paddingBelowLegends',
            beforeInit: function(chart) {
                const legendPadding = 15;
                const originalFit = chart.legend.fit;

                chart.legend.fit = function fit() {
                    originalFit.bind(chart.legend)();
                    this.height += legendPadding;
                };
            }
        };
        const updateXScaleRange = {
            id: 'updateXScaleRange',
            beforeDraw: function(chart) {
                const datasets = chart.data.datasets;
                const xScale = chart.scales.x;

                if (!xScale) {
                    return;
                }

                const xEndPixel = xScale._endPixel;
                const xStartPixel = xScale._startPixel;
                const oneDayPx = 2;
                let maxRangeDays = 0;
                let minRangeDays = 0;

                function isBubbleOverflowing(dataPoint) {
                    const pointPixel = xScale.getPixelForValue(dataPoint.x);
                    const pointRadius = dataPoint.r;

                    if (pointPixel + pointRadius > xEndPixel) {
                        maxRangeDays = Math.ceil((pointPixel + pointRadius - xEndPixel) / oneDayPx);
                    }
                    if (pointPixel - pointRadius < xStartPixel) {
                        minRangeDays = Math.ceil((xStartPixel - (pointPixel - pointRadius)) / oneDayPx);
                    }
                }

                datasets.forEach(dataset => {
                    dataset.data.forEach(isBubbleOverflowing);
                });

                if (maxRangeDays || minRangeDays) {
                    if (maxRangeDays) {
                        chart.options.scales.x.max = self.addDays(new Date(xScale.max), maxRangeDays);
                    }
                    if (minRangeDays) {
                        chart.options.scales.x.min = self.addDays(new Date(xScale.min), -minRangeDays);
                    }
                    chart.update();
                }
            },
            resize: function(chart) {
                self.resetXScaleRange();
            }
        };

        this.chartConfig = {
            type: 'bubble',
            data: {
                datasets: [],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                onClick: function(event, elements) {
                    if (elements.length > 0) {
                        const elementIndex = elements[0].index;
                        const datasetIndex = elements[0].datasetIndex;
                        const dataPoint = this.data.datasets[datasetIndex].data[elementIndex];

                        $('.chartjs-tooltip').css({
                            opacity: 0
                        });

                        app.router.navigate(app.router.buildRoute(self.forecastBy, dataPoint.id), {trigger: true});
                    }
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'month',
                            displayFormats: {
                                month: 'MMMM'
                            },
                        },
                        grid: {
                            display: false,
                        },
                        ticks: {
                            color: this.getTextColor(),
                            font: this.getFontStyle(),
                        },
                        border: this.getScaleStyle(),
                        afterBuildTicks: _.bind(function(scale) {
                            const timeDomain = this.getTimeDomain();

                            if (timeDomain.length > 0) {
                                const start = timeDomain[0];
                                const end = timeDomain[1];
                                scale.ticks = [];

                                const timeRange = this.monthRange(start, end);

                                _.each(timeRange, _.bind(function(date) {
                                    let midDay = Math.floor(this.daysInMonth(date) / 2);
                                    const time = new Date(date.getFullYear(), date.getMonth(), midDay + 1).getTime();

                                    scale.ticks.push({value: time});
                                }, this));
                            }
                        }, this),
                    },
                    y: {
                        type: 'linear',
                        beginAtZero: true,
                        offset: true,
                        grid: {
                            drawTicks: false,
                            lineWidth: 1,
                            color: 'rgba(111, 119, 123, 0.3)',
                        },
                        afterBuildTicks: _.bind(function(scale) {
                            if (this.yLabelValues && this.yLabelValues.length > 0) {
                                const yValues = _.map(this.yLabelValues, function(label) {
                                    return label.y;
                                });

                                scale.ticks = [];

                                _.each(yValues, function(value) {
                                    scale.ticks.push({value});
                                });
                            }
                        }, this),
                        ticks: {
                            padding: 5,
                            font: this.getFontStyle(),
                            color: this.getTextColor(),
                            callback: _.bind(function(value, index) {
                                const yLabelValues = this.yLabelValues;

                                if (yLabelValues && yLabelValues.length > 0) {
                                    return yLabelValues[index].key;
                                }
                            }, this)
                        },
                        border: this.getScaleStyle(),
                    },
                },
                plugins: {
                    tooltip: {
                        enabled: false,
                        external: _.bind(function(context) {
                            this.generateExternalTooltip(context);
                        }, this)
                    },
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            font: this.getFontStyle(),
                            generateLabels: function(chart) {
                                const datasets = chart.data.datasets;
                                const legend = [];

                                _.each(datasets, function(dataset) {
                                    _.each(dataset.data, function(data) {
                                        const items = legend.find(function(item) {
                                            return item.text === data.probability + '%';
                                        });

                                        if (!items) {
                                            legend.push({
                                                text: data.probability + '%',
                                                fillStyle: data.backgroundColor,
                                                lineWidth: data.borderWidth,
                                                strokeStyle: data.borderColor,
                                                pointStyle: 'circle',
                                                fontColor: self.getTextColor(),
                                            });
                                        }
                                    });
                                });

                                return legend;
                            },
                            sort: function(first, second) {
                                let labels = [first.text, second.text];
                                labels = _.map(labels, function(label) {
                                    return parseInt(label.replace('%', ''), 10);
                                });

                                return labels[0] - labels[1];
                            },
                        },
                    },
                },
            },
            plugins: [hoverEffect, paddingBelowLegends, updateXScaleRange]
        };
    },

    /**
     * Get the text font style for the chart
     */
    getFontStyle: function() {
        return {
            size: 11,
        };
    },

    /**
     * Return formatted data for chartjs
     *
     * @return {Array} Array of datasets
    */
    getChartDatasets: function() {
        const self = this;
        const state = this.isManager && this.getVisibility() === 'user';
        const groupKey = state ? 'sales_stage_short' : 'assigned_user_name';

        if (this.chartCollection && this.chartCollection.data) {
            const groupedData = _.reduce(this.chartCollection.data, function(memo, point) {
                let group = _.find(memo, function(group) {
                    return group.key === point[groupKey];
                });

                if (!group) {
                    group = {
                        key: point[groupKey],
                        values: []
                    };

                    memo.push(group);
                }

                group.values.push(point);

                return memo;
            }, []);

            const generatedData = this.scaleYValues(groupedData);
            const colorMap = this.getColorMap();

            const formattedData = _.map(generatedData, function(data) {
                return {
                    data: _.map(data.values, function(point) {
                        return {
                            x: new Date(point.x).getTime(),
                            y: point.y,
                            account_name: point.account_name,
                            assigned_user_name: point.assigned_user_name,
                            base_amount: point.base_amount,
                            currency_symbol: point.currency_symbol,
                            probability: point.probability,
                            sales_stage_short: point.sales_stage_short,
                            key: point[groupKey],
                            id: point.id,
                            backgroundColor: colorMap[point.probability],
                            borderColor: colorMap[point.probability],
                            borderWidth: 1
                        };
                    }),
                    pointBackgroundColor: self.getColors(data.values),
                    pointBorderColor: self.getColors(data.values),
                };
            });

            return this.setCircleRadius(formattedData);
        }

        return [];
    },

    /**
     * Get the color map for the chart
     *
     * @return {Array}
     */
    getColorMap: function() {
        const colors = SUGAR.charts.getColorPalette();
        const colorMap = [];

        const probabilityOptions = app.lang.getAppListStrings('sales_probability_dom');
        _.each(Object.values(probabilityOptions), function(probability, index) {
            colorMap[probability] = colors[index];
            index++;
        });

        return colorMap;
    },

    /**
     * Get the colors for each data point
     *
     * @param {Array} values
     * @return {Array}
     */
    getColors: function(values) {
        const colorMap = this.getColorMap();

        return _.map(values, function(point) {
            return colorMap[point.probability];
        });
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
            const point = tooltipModel.dataPoints[0].raw;
            const dateFormat = app.date.convertFormat(app.user.getPreference('datepref'));

            point.close_date = moment(point.x).format(dateFormat);
            point.likely = app.currency.formatAmountLocale(point.base_amount, point.currency_id);

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
     * Filter out records that don't meet date criteria
     * and convert into format convenient for d3
     */
    evaluateResult: function(data) {
        this.total = data.records.length;
        this.timeDomain = [];

        let statusOptions = 'sales_stage_dom';
        const fieldMeta = app.metadata.getModule(this.forecastBy, 'fields');

        if (fieldMeta) {
            statusOptions = fieldMeta.sales_stage.options || statusOptions;
        }

        this.chartCollection = {
            data: data.records.map(function(d) {
                const salesStage = app.lang.getAppListStrings(statusOptions)[d.sales_stage] || d.sales_stage;

                // if probability is null or empty set to 0
                if(_.isNull(d.probability) || d.probability === '') {
                    d.probability = 0;
                }

                // if likely is null or empty set to 0, for customers that do not require likely
                if(_.isNull(d[this.likelyField]) || d[this.likelyField] === '') {
                    d[this.likelyField] = 0;
                }

                this.timeDomain.push(d.date_closed);

                return {
                    id: d.id,
                    x: d.date_closed,
                    y: Math.round(parseInt(d[this.likelyField], 10) / parseFloat(d.base_rate)),
                    shape: 'circle',
                    account_name: d.account_name,
                    assigned_user_name: d.assigned_user_name,
                    sales_stage: salesStage,
                    sales_stage_short: salesStage,
                    probability: parseInt(d.probability, 10),
                    base_amount: d[this.likelyField],
                    currency_symbol: app.currency.getCurrencySymbol(d.currency_id),
                    currency_id: d.currency_id
                };
            }, this),
            properties: {
                title: app.lang.get('LBL_DASHLET_TOP10_SALES_OPPORTUNITIES_NAME'),
                value: data.records.length,
                xDataType: 'datetime',
                yDataType: 'numeric'
            }
        };
    },

    /**
     * @inheritdoc
     */
    loadData: function(options) {
        var self = this,
            _filter = [
                {
                    'date_closed': {
                        '$gte': self.dateRange.begin
                    }
                },
                {
                    'date_closed': {
                        '$lte': self.dateRange.end
                    }
                }
            ];

        if (!this.isManager || this.getVisibility() === 'user') {
            _filter.push({'$owner': ''});
        }

        var _local = _.extend({'filter': _filter}, this.params);
        var url = app.api.buildURL(this.forecastBy, null, null, _local, this.params);

        // Request data from REST endpoint, evaluate result and trigger data change event
        app.api.call('read', url, null, {
            success: function(data) {
                self.evaluateResult(data);
                if (!self.disposed) {
                    self.trigger('data-changed');
                }
            },
            error: _.bind(function() {
                this.displayNoData(true);
            }, this),
            complete: options ? options.complete : null
        });
    },

    /**
     * Calculate date range based on date range dropdown control
     */
    setDateRange: function() {
        var now = new Date(),
            mapping = {
                'current' : 0,
                'next' : 3,
                'year' : 12
            },
            duration = mapping[this.settings.get('filter_duration')],
            startMonth = Math.floor(now.getMonth() / 3) * 3,
            startDate = new Date(now.getFullYear(), (duration === 12 ? 0 : startMonth + duration), 1),
            addYear = 0,
            addMonth = duration === 12 ? 12 : 3,
            endDate;

        // if "Next Quarter" is selected and the current month is Oct/Nov/Dec, add 1 to the year
        if(duration === 3 && now.getMonth() >= 9) {
            addYear = 1;
        }
        endDate = new Date(now.getFullYear() + addYear, startDate.getMonth() + addMonth, 0);

        this.dateRange = {
            'begin': app.date.format(startDate, 'Y-m-d'),
            'end': app.date.format(endDate, 'Y-m-d')
        };
    },

    /**
     * Trigger data load event based when date range dropdown changes
     */
    changeFilter: function() {
        this.setDateRange();
        this.loadData();
    },

    /**
     * @inheritdoc
     */
    _dispose: function() {
        this.off('data-changed');
        this.settings.off('change:filter_duration');
        this._super('_dispose');
    }
})
