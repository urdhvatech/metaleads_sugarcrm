import SugarColorPalette from '../../../build/color/tailwind/sugarPalette';
import * as ES6Colors from '../../../build/color/js/sugarColorPalette';

const monochromePalette = ['black', 'white', 'transparent'];
const colorPalette = [
  'red', 
  'orange', 
  'amber', 
  'yellow', 
  'lime', 
  'green', 
  'emerald', 
  'cyan', 
  'blue', 
  'cobalt', 
  'indigo', 
  'violet', 
  'purple',
  'fuschia',
  'pink',
  'rose',
  'gray',
];
const shadeVariants = ['50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950'];

describe('Tailwind CSS format', () => {
  test('all values of monochrome palette exists', () => {
	for (let i = 0; i < monochromePalette.length; i++) {
	  let currMono = monochromePalette[i];
	  expect(SugarColorPalette[currMono]).toBeDefined();
	}
  });

  test('all colors exported in Tailwind CSS format have 12 defined variants', () => {
	for (let i = 0; i < colorPalette.length; i++) {
	  let currCol = colorPalette[i];

	  for (let j = 0; j < shadeVariants.length; j++) {
		let currVar = shadeVariants[j];
		expect(SugarColorPalette[currCol][currVar]).toBeDefined();
	  }
	}
  });
});

describe('ES6 format', () => {
  test('all values from monochrome palette exist', () => {
	for (let i = 0; i < monochromePalette.length; i++) {
	  let currMono = `ColorBase${monochromePalette[i].charAt(0).toUpperCase()}${monochromePalette[i].slice(1)}`;
	  expect(ES6Colors[currMono]).toBeDefined();
	}
  });

  test('all colors exported in ES6 JavaScript format have 12 defined variants', () => {
	for (let i = 0; i < colorPalette.length; i++) {
	  let currColor = `ColorBase${colorPalette[i].charAt(0).toUpperCase()}${colorPalette[i].slice(1)}`;

	  for (let j = 0; j < shadeVariants.length; j++) {
		let currVar = shadeVariants[j];
		expect(ES6Colors[`${currColor}${currVar}`]).toBeDefined();
	  }
	}
  });
});

describe('CSS variables format', () => {
  test('all colors exported in css/variables format have 12 defined variants', () => {
	for (let i = 0; i < colorPalette.length; i++) {
	  for (let j = 0; j < shadeVariants.length; j++) {
		expect(`--${colorPalette[i]}-${shadeVariants[j]}`).toBeDefined();
	  }
	}
  });
});
