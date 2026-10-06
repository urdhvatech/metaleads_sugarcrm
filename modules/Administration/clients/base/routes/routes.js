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
(function(app) {
    app.events.on('router:init', function(router) {
        var routes = [
            {
                name: 'administration',
                route: 'Administration',
                callback: function() {
                    app.controller.loadView({
                        layout: 'administration',
                        module: 'Administration'
                    });
                }
            },
            {
                name: 'relate-denormalization',
                route: ':Administration/denormalization',
                callback: function(module) {
                    app.controller.loadView({
                        layout: 'config-drawer',
                        module: module
                    });
                }
            },
            {
                name: 'module-names-and-icons',
                route: ':Administration/module-names-and-icons',
                callback: function(module) {
                    let prevLayout = app.controller.context.get('layout');
                    if (prevLayout && !_.contains(['login', 'bwc'], prevLayout)) {
                        app.drawer.open({
                            layout: 'module-names-and-icons-drawer',
                            context: {
                                module: module,
                                fromRouter: true
                            }
                        });
                        return;
                    }
                    app.controller.loadView({
                        layout: 'module-names-and-icons-drawer',
                        module: module
                    });
                }
            },
            {
                // route for Timeline config
                name: 'timeline-config',
                route: ':Administration/config/timeline/:target',
                callback: function(module, target) {
                    app.controller.loadView({
                        layout: 'timeline-config',
                        category: 'timeline',
                        target: target,
                        module: module
                    });
                }
            },
            {
                name: 'historically-delta-config',
                route: ':Administration/config/historically-delta/:target',
                callback: function(module, target) {
                    app.controller.loadView({
                        layout: 'historically-delta-config',
                        category: 'delta',
                        target: target,
                        module: module
                    });
                }
            },
            {
                // route for Config Framework
                name: 'admin-config',
                route: ':Administration/config/:category',
                callback: function(module, category) {
                    var layout = app.metadata.getLayout(module, category + '-config') ? category + '-config' : 'config';
                    app.controller.loadView({
                        layout: layout,
                        category: category,
                        module: module
                    });
                }
            },
            {
                name: 'drive-path',
                route: ':Administration/drive-path/:type',
                callback: function(module, type) {
                    app.controller.loadView({
                        layout: 'drive-path',
                        module: module,
                        driveType: type,
                    });
                }
            },
            {
                name: 'package-builder',
                route: ':Administration/package-builder',
                callback: function(module) {
                    app.controller.loadView({
                        layout: 'package-builder',
                        module: module
                    });
                }
            },
            {
                name: 'dropdown-editor',
                route: 'Administration/dropdown-editor/:dropdownName',
                callback: function(dropdownName) {
                    let prevLayout = app.controller.context.get('layout');
                    if (prevLayout) {
                        let drawerOptions = {
                            layout: 'dropdown-editor-drawer',
                            context: {
                                module: 'Administration',
                                dropdownName: dropdownName,
                                fromRouter: true
                            }
                        };
                        let drawer = !_.contains(['bwc'], prevLayout) ? app.drawer : parent.App.drawer;

                        if (!app.drawer.count()) {
                            drawer.open(drawerOptions);
                        } else {
                            drawer.load(drawerOptions);
                        }

                        return;
                    }
                    app.controller.loadView({
                        layout: 'dropdown-editor-drawer',
                        module: 'Administration',
                        dropdownName: dropdownName
                    });
                }
            },
            {
                name: 'dropdown-editor-blank',
                route: 'Administration/dropdown-editor',
                callback: function() {
                    let prevLayout = app.controller.context.get('layout');
                    if (prevLayout) {
                        let drawerOptions = {
                            layout: 'dropdown-editor-drawer',
                            context: {
                                module: 'Administration',
                                fromRouter: true
                            }
                        };
                        let drawer = !_.contains(['bwc'], prevLayout) ? app.drawer : parent.App.drawer;

                        if (!app.drawer.count()) {
                            drawer.open(drawerOptions);
                        } else {
                            drawer.load(drawerOptions);
                        }

                        return;
                    }
                    app.controller.loadView({
                        layout: 'dropdown-editor-drawer',
                        module: 'Administration'
                    });
                }
            },
        ];
        app.router.addRoutes(routes);
    });
})(SUGAR.App);
