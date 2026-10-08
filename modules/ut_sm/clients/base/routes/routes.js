/*
 * Meta Leads sidecar routes for Administration settings.
 */
(function(app) {
    app.events.on('router:init', function() {
        var loadAdminLayout = function(layout, extra) {
            if (!app.acl.hasAccess('admin', 'Administration')) {
                app.controller.loadView({
                    layout: 'access-denied',
                    module: 'Administration'
                });
                return;
            }
            app.controller.loadView(_.extend({
                module: 'ut_sm',
                layout: layout,
                // ut_sm is an admin screen, not a record module. Without this,
                // sidecar fetches the module list and builds SELECT FROM with
                // an empty table and empty field list.
                skipFetch: true
            }, extra || {}));
        };

        app.router.addRoutes([
            {
                name: 'ut-sm-home',
                route: 'ut_sm',
                callback: function() {
                    app.router.navigate('ut_sm/settings', {trigger: true, replace: true});
                }
            },
            {
                name: 'ut-sm-settings',
                route: 'ut_sm/settings',
                callback: function() {
                    loadAdminLayout('settings');
                }
            },
            {
                name: 'ut-sm-license',
                route: 'ut_sm/license',
                callback: function() {
                    loadAdminLayout('license');
                }
            },
            {
                name: 'ut-sm-account',
                route: 'ut_sm/account/:id',
                callback: function(id) {
                    loadAdminLayout('account-settings', {
                        accountId: id
                    });
                }
            }
        ]);
    });
})(SUGAR.App);
