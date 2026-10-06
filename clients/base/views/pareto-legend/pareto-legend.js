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
 * @class View.Views.Base.ParetoLegendView
 * @alias SUGAR.App.view.views.BaseParetoLegendView
 * @extends View.Views.Base.LegendView
 */
({
    extendsFrom: 'LegendView',
    className: 'w-full h-full',

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);

        _.each(this._legend, function(legendItem) {
            if (legendItem.type === 'bar') {
                this._barLegend.push(legendItem);
            } else {
                this._lineLegend.push(legendItem);
            }
        }, this);
    },

    /**
     * Init properties
     */
    _initProperties: function() {
        this._super('_initProperties');

        this._barLegend = [];
        this._lineLegend = [];
        this._circleConf = {
            radius: 6,
            space: 1,
            startAngle: 0,
            endAngle: 2 * Math.PI,
        };
        this._circleConf.x = this._circleConf.radius + this._circleConf.space;
    },

    /**
     * Register events
     */
    _registerEvents: function() {
        // arrange elements when the container resizes
        this.listenTo(this.context, 'container-resizing', () => {
            _.debounce(_.bind(this._arrangeElements,this),this._resizeTiming)();
        });

        this.listenTo(this.context, 'animation-completed', () => {
            _.debounce(_.bind(this._arrangeElements,this),this._resizeTiming)();
        });

        this.listenTo(this.context, 'data-changed', () => {
            _.debounce(_.bind(this._arrangeElements,this),this._resizeTiming)();
        });

        $('.dashlets').off(`scroll.${this.cid}`).on(`scroll.${this.cid}`, (el, targetId) => {
            this.tryHideElements(el, targetId);
        });

        $('.forecasts-chart-wrapper').off(`scroll.${this.cid}`).on(`scroll.${this.cid}`, (el, targetId) => {
            this.tryHideElements(el, targetId);
        });

        // arrange elements when the window resizes
        $(window).off(`resize.${this.cid}`).on(`resize.${this.cid}`, ()=> {
            _.debounce(_.bind(this._arrangeElements,this),this._resizeTiming)();
        });

        // close the dropdown when the user clicks outside of it
        $(document).off(`click.${this.cid}`).on(`click.${this.cid}`, (el, targetId) => {
            this.tryHideElements(el, targetId);
        });

        _.each(this._barLegend, (meta) => {
            const legendItem = this.$(`[data-id="${meta.id}"]`);

            legendItem.off('click').on('click', () => {
                this.updateLegendStyle(meta);
            });
        });
    },

    /**
     * Apply style to each legend item
     */
    _applyStyle: function() {
        this._applyStyleLineLegend();
        this._applyStyleBarLegend();
    },

    /**
     * Update legend style
     *
     * @param {Object} meta
     */
    updateLegendStyle: function(meta) {
        if (meta.callback && _.isFunction(meta.callback)) {
            meta.callback();
        }

        const hiddenBars = _.filter(this._barLegend, (legendItem) => !legendItem.visible);

        if (hiddenBars.length === this._barLegend.length - 1) {
            _.each(this._barLegend, legendItem => legendItem.visible = true);
        } else {
            meta.visible = !meta.visible;
        }

        this._applyStyleBarLegend();

        if (meta.displayManager) {
            _.each(this._lineLegend, legendItem => legendItem.visible = true);

            const lineLegendItem = _.find(this._lineLegend, {series: meta.series}) || {};
            lineLegendItem.visible = meta.visible;

            this._applyStyleLineLegend();
        }
    },

    /**
     * Render line legend
     */
    _applyStyleLineLegend: function() {
        _.each(this._lineLegend, (legendItem) => {
            if (legendItem.dottedItem) {
                this.drawDottedLine(legendItem);
            } else {
                this.drawLine(legendItem);
            }
        });
    },

    /**
     * Render bar legend
     */
    _applyStyleBarLegend: function() {
        _.each(this._barLegend, (legendItem) => {
            let canvasParent = this.$(`[data-id="${legendItem.id}"]`);

            if (!canvasParent.length) {
                const dropdownElements = $(`#dropdown-elements${this._id}`);
                canvasParent = dropdownElements.find(`[data-id="${legendItem.id}"]`);
            }

            const canvases = canvasParent.find('canvas');

            _.each(canvases, (canvas) => {
                const context = canvas.getContext('2d');
                const canvasWidth = canvas.width;
                const canvasHeight = canvas.height;
                const filledCircle = legendItem.visible;
                const circleConf = this._circleConf;

                context.clearRect(0, 0, canvasWidth, canvasHeight);

                this.drawCircle(context,
                    circleConf.x,
                    canvasHeight / 2,
                    circleConf.radius,
                    legendItem.color,
                    filledCircle
                );
            });
        });
    },

    /**
     * Draw dotted legend line
     *
     * @param {string} legendItem
     */
    drawDottedLine: function(legendItem) {
        const legendItemId = legendItem.id;
        const canvasParent = this.$(`#${legendItemId}`);
        const canvas = _.first(canvasParent.find('canvas'));
        const context = canvas.getContext('2d');
        const canvasWidth = canvas.width;
        const canvasHeight = canvas.height;
        const y = canvasHeight / 2;
        const dashWidth = 8;
        const gapWidth = 8;
        const space = 5;

        // Clear the canvas
        context.clearRect(0, 0, canvasWidth, canvasHeight);

        context.strokeStyle = legendItem.color;
        context.setLineDash([dashWidth, gapWidth]);
        context.beginPath();
        context.moveTo(space, y);
        context.lineTo(canvasWidth - space, y);
        context.lineWidth = 4;
        context.stroke();
    },

    /**
     * Draw legend line
     *
     * @param {Object} legendItem
     */
    drawLine: function(legendItem) {
        const legendItemId = legendItem.id;
        const canvasParent = this.$(`#${legendItemId}`);
        const canvas = _.first(canvasParent.find('canvas'));
        const context = canvas.getContext('2d');
        const canvasWidth = canvas.width;
        const canvasHeight = canvas.height;
        const y = canvasHeight / 2;

        const circleConf = this._circleConf;
        const radius = circleConf.radius;
        const color = legendItem.color;
        const startXCircle = circleConf.x;
        const endXCircle = canvasWidth - radius - circleConf.space;
        const filledCircle = legendItem.visible;
        const startXLine = startXCircle + radius;
        const endXLine = endXCircle - radius;

        // Clear the canvas
        context.clearRect(0, 0, canvasWidth, canvasHeight);

        context.lineWidth = 4;

        context.beginPath();
        context.moveTo(startXLine, y);
        context.lineTo(endXLine, y);
        context.strokeStyle = legendItem.color;
        context.stroke();

        this.drawCircle(context, startXCircle, y, radius, color, filledCircle);
        this.drawCircle(context, endXCircle, y, radius, color, filledCircle);
    },

    /**
     * Draw circle
     *
     * @param {Object} context
     * @param {number} x
     * @param {number} y
     * @param {number} radius
     * @param {string} color
     * @param {boolean} filledCircle
     */
    drawCircle: function(context, x, y, radius, color, filledCircle = false) {
        const circleConf = this._circleConf;

        context.fillStyle = color;
        context.strokeStyle = color;
        context.lineWidth = 2;
        context.beginPath();
        context.arc(x, y, radius, circleConf.startAngle, circleConf.endAngle);
        context.stroke();

        if (filledCircle) {
            context.fill();
        }
    },

    /**
     * Check if the dropdown should be displayed
     */
    _needDropdown: function() {
        // remove the overflow hidden class so that we get the full width
        this._resetLegendVisibility();

        const inlineElements = this.$('#inline-elements');
        const elementsWidth = inlineElements.outerWidth(true);
        const legendContainerWidth = this.$('#legend-container').width();
        const lineLegendContainerWidth = this.$('#line-legend-container').outerWidth(true);
        const dropdownWidth = this.$('#dropdown-toggle').outerWidth(true);

        // as we now have the full width, we can bring the overflow-hidden class back
        inlineElements.addClass('overflow-hidden');

        return elementsWidth > legendContainerWidth - lineLegendContainerWidth - dropdownWidth;
    },

    /**
     * Get the max width available for the legend inline items
     */
    _getMaxWidthAvailable: function() {
        const legendContainerWidth = this.$('#legend-container').width();
        const lineLegendContainerWidth = this.$('#line-legend-container').outerWidth(true);
        const dropdownWidth = this.$('#dropdown-toggle').outerWidth(true);
        const minDisplayWidthOffset = 50;

        return legendContainerWidth - dropdownWidth - minDisplayWidthOffset - lineLegendContainerWidth;
    },

    /**
     * @inheritdoc
     */
    _dispose: function() {
        $(window).off(`resize.${this.cid}`);
        $('.dashlets').off(`scroll.${this.cid}`);

        this._super('_dispose');
    },
})
