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

const webpack = require('webpack');
const path = require('path');

module.exports = function(config) {
    config.set({
        basePath: '.',
        client: {
            jasmine: {
                random: false,
            }
        },
        browsers: ['ChromeHeadless'],
        files: [
            {
                pattern: 'tests/fixtures/*',
                included: false,
                served: true,
                watched: false,
            },

            {
                pattern: 'tests/fixtures/metadata/*.json',
                included: false,
                served: true,
                watched: false
            },

            'node_modules/sinon/pkg/sinon.js',
            'node_modules/nise/nise.js',

            {pattern: 'tests/index.js', watched: false},
        ],

        preprocessors: {
            'tests/index.js': ['webpack', 'babel', 'sourcemap'],
        },

        frameworks: [
            'jasmine',
            'webpack',
        ],
        plugins: [
            'karma-webpack',
            'karma-sourcemap-loader',
            'karma-jasmine',
            'karma-chrome-launcher',
            'karma-firefox-launcher',
            'karma-safari-launcher',
            'karma-sauce-launcher',
            'karma-coverage',
            'karma-junit-reporter',
            'karma-babel-preprocessor',
        ],
        concurrency: 1, //Set the number of browsers to run in parallel. 1 means sequential.
        reportSlowerThan: 500,
        browserNoActivityTimeout: 120000,
        captureTimeout: 120000,
        browserDisconnectTimeout: 10000,
        browserDisconnectTolerance: 10,
        sauceLabs: {
            testName: 'Sidecar Karma Tests',
        },
        customLaunchers: {
            dockerChromeHeadless: {
                base: 'ChromeHeadless',
                flags: ['--no-sandbox', '--disable-gpu', '--disable-software-rasterizer'],
            },
            docker_chrome: {
                base: 'Chrome',
                flags: [
                    '--no-sandbox',
                    '--disable-gpu',
                    '--disable-dev-shm-usage'
                ],
            },
            sl_safari: {
                base: 'SauceLabs',
                browserName: 'safari',
                platform: 'OS X 10.11',
                version: '9.0',
            },
            sl_firefox: {
                base: 'SauceLabs',
                browserName: 'firefox',
                platform: 'Linux',
                version: 54.0,
            },
        },
        webpack: {
            devtool: 'inline-source-map',
            optimization: {
                minimize: false,
            },
            module: {
                rules: [
                    {
                        test: /\.js$/,
                        exclude: /(node_modules|lib)/,
                        use: ['babel-loader'],
                    },
                    {
                        test: /\.js$/,
                        include: [
                            path.resolve('src'),
                            /lib\/sugar.*/,
                        ],
                    },
                ],
            },
            plugins: [
                new webpack.DefinePlugin({
                    ZEPTO: JSON.stringify(process.env.ZEPTO),
                }),
            ],
            resolve: {
                modules: [
                    path.resolve(__dirname, './src'),
                    path.resolve(__dirname, './lib'),
                    path.resolve(__dirname, './node_modules'),
                ],
            },
        },
        webpackMiddleware: {
            stats: 'errors-only',
        },
    });
};
