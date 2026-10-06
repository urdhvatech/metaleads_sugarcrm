import baseThemeCss from '../../../build/theme/sugar-base.css' with { type: 'css' };
import borderRadius from '../../../build/size/tailwind/borderRadius';

// All rounded values defined in tokens.
const borderRadii = {
  'rounded-none': '0px',
  'rounded-sm': '0.25rem',
  'rounded': '0.5rem',
  'rounded-md': '0.5rem',
  'rounded-lg': '1rem',
  'rounded-xl': '1.5rem',
  'rounded-full': '9999px'
};

describe('Base theme sizing properties', () => {
  test('all values of border radius exist', () => {
    const rules = baseThemeCss['rules'];
	Object.keys(borderRadii).forEach(e => {
	  expect(borderRadii[e]).toEqual(rules[`--${e}`]);
	});
  });
});

describe('Tailwind CSS format', () => {
  test('all values of border radius exist', () => {
	Object.keys(borderRadii).forEach(e => {
	  if (e.includes('-')) {
		let minimizedName = e.split('-')[1];
		expect(borderRadii[e]).toEqual(borderRadius[minimizedName]);
	  } else {
		expect(borderRadii[e]).toEqual(borderRadius['DEFAULT']);
	  }
	});
  });
});
