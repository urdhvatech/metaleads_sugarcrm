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

const StyleDictionary = require('style-dictionary');

const {
  fileHeader,
} = StyleDictionary.formatHelpers;

module.exports = {
  format: {
    twColors: ({dictionary, file}) => {
      const palette = {}

      dictionary.allProperties.map(prop => {
        // Determine if the token is a single-value token, or a variant
        if (prop.path.length === 2) {
          palette[prop.name] = prop.value;
        } else if (prop.path.length === 3) {
          let variantGroup = prop.path[1];
          if (!palette[variantGroup]) {
            palette[variantGroup] = {};
          }

          palette[variantGroup][prop.name] = prop.value
        }
      });

      return `${fileHeader({file})} module.exports = ${JSON.stringify(palette, null, 2)};`
    },
  },
  // This will match any files ending in json or json5.
  // json5 is being used here, so comments can be added in the token files for reference.
  source: ['tokens/**/**/*.@(json|json5)'],
  transform: {
    'color/css': Object.assign({}, StyleDictionary.transform[`color/css`], {
      transitive: true,
    }),
  },
  platforms: {
    js: {
      transformGroup: 'js',
      buildPath: 'build/javascript/',
      files: [
        {
          destination: 'sugarColorPalette.js',
          format: 'javascript/es6',
          filter: token => {
            return token.filePath === 'tokens/color/palette.json';
          },
        }
      ]
    },
    less: {
      transformGroup: 'less',
      transforms: [
        'attribute/cti',
        'name/cti/kebab',
        'time/seconds',
        'size/rem',
        'color/css',
      ],
      buildPath: 'build/less/',
      files: [
        {
          destination: 'sugar-color-palette.less',
          format: 'less/variables',
          filter: token => {
            return token.filePath === 'tokens/color/palette.json';
          }
        },
        {
          destination: 'sugar-theme-variables.less',
          format: 'less/variables',
          filter: token => {
            let isColor = token.attributes.category === 'color';
            let isNotPalette = token.filePath !== 'tokens/color/palette.json';
            return isColor && isNotPalette;
          }
        },
      ],
    },
    tailwind: {
      transformGroup: 'js',
      transforms: [
        'attribute/cti',
        'color/hex'
      ],
      buildPath: 'build/tailwind/',
      files: [
        {
          destination: 'sugar-tw-color-palette.js',
          format: 'twColors',
          filter: token => {
            return token.filePath === 'tokens/color/palette.json';
          },
        }
      ]
    },
  },
};
