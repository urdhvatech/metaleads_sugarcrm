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

import globals from "globals";
import { defineConfig } from "eslint/config";
import eslintPluginImport from "eslint-plugin-import";
import eslintPluginPromise from "eslint-plugin-promise";
import eslintConfigGoogle from "eslint-config-google";
import jsdoc from "eslint-plugin-jsdoc";

delete eslintConfigGoogle.rules['valid-jsdoc']; // not supported in eslint 9+
delete eslintConfigGoogle.rules['require-jsdoc']; // not supported in eslint 9+

export default defineConfig([
  {
    ignores: [
      "node_modules/**",
      "**/*.min.js",
      "include/javascript/clipboardjs/**",
      "include/javascript/fuse/**",
      "include/javascript/gridstack/**",
      "include/javascript/jquery/**",
      "include/javascript/mousetrap/**",
      "include/javascript/nprogress/**",
      "include/javascript/d3-sugar/**",
      "include/javascript/sucrose/**",
      "include/javascript/chartjs/**",
      "include/javascript/phpjs/**",
      "include/javascript/select2/**",
      "include/javascript/tinymce/**",
      "include/javascript/twitterbootstrap/**",
      "include/javascript/yui/**",
      "include/javascript/yui3/**",
      "include/javascript/canvg.js",
      "include/javascript/favicon.js",
      "include/javascript/iscroll.js",
      "include/javascript/jquery.js",
      "include/javascript/modernizr.js",
      "include/javascript/StackBlur.js",
      "include/javascript/pmse/lib/**",
      "include/javascript/amazon-connect/**",
      "include/javascript/dom-purify/**",
      "include/javascript/kendo/**",
      "include/javascript/filesaver/**",
      "include/javascript/jszip/**",
      "jssource/src/build/**",
      "tests/**",
      "sidecar/**",
      "styleguide/tests/**",
      "vendor/**",
      "themes/**",
      "tailwind.config.js",
      "upgrader.tailwind.config.js",
      "gulpfile.js"
    ]
  },
  {
    files: ["**/*.js"],
    languageOptions: {
      sourceType: "script",
      globals: {
        // defense against weird values that global may have which result in
        // TypeError: Key "languageOptions": Key "globals": Global "AudioWorkletGlobalScope " has leading or trailing whitespace.
        ...Object.fromEntries(
          Object.entries(globals.browser).map(([key, value]) => [
            key.trim(),
            typeof value === 'string' ? value.trim() : value
          ])
        ),
        // SugarCRM Framework Globals
        $: "readonly",
        jQuery: "readonly",
        _: "readonly",
        Backbone: "readonly",
        Handlebars: "readonly",
        moment: "readonly",
        async: "readonly",
        SUGAR: "readonly",
        SUGARCRM: "readonly",
        SUGAR_URL: "readonly",
        SUGAR_REST: "readonly",
        SUGAR_FLAVOR: "readonly",
        SUGAR_callsInProgress: "readonly",
        SUGAR_AJAX_URL: "readonly",
        SUGAR_GRID: "readonly",
        App: "readonly",
        ModuleBuilder: "readonly",
        Studio: "readonly",
        DCMenu: "readonly",
        ajaxStatus: "readonly",
        app: "readonly",
        YAHOO: "readonly",
        YAHOO_config: "readonly",
        collection: "readonly",
        current_user: "readonly",
        request_map: "readonly",
        filter_defs: "readonly",
        isDate: "readonly",
        convertReportDateTimeToDB: "readonly",
        SugarTest: "readonly",
        sinon: "readonly",
        expect: "readonly",
      },
      ecmaVersion: 2021
    },
    plugins: {
      import: eslintPluginImport,
      promise: eslintPluginPromise,
      jsdoc: jsdoc,
    },
    rules: {
      ...eslintConfigGoogle.rules,
      "indent": ["error", 4],
      "max-len": ["error", { code: 120 }],
      "consistent-this": ["error", "self"],
      "camelcase": ["error", { "properties": "never" }],
      "jsdoc/check-tag-names": "error",
      "jsdoc/check-types": "error",
      "jsdoc/require-returns": "warn",
      "jsdoc/require-description": "off",
      "semi": "off",
      "no-var": "off",
    }
  }
]);
