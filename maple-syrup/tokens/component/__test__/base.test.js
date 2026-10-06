import baseThemeCss from '../../../build/theme/sugar-base.css' with { type: 'css' };

const baseComponent = {
  'text-secondary': 'var(--gray-500)',
};

const genericTailwindColors = [
  'rose',
  'pink',
  'fuschia',
  'purple',
  'violet',
  'indigo',
  'cobalt',
  'blue',
  'cyan',
  'teal',
  'emerald',
  'green',
  'lime',
  'yellow',
  'amber',
  'orange',
  'red',
  'gray',
];

const tailwindShadeModifiers = [
  '950',
  '900',
  '800',
  '700',
  '600',
  '500',
  '400',
  '300',
  '200',
  '100',
  '50',
];

describe('Base theme component properties', () => {
  test('all properties exist', () => {
    const rules = baseThemeCss['rules'];
    Object.keys(baseComponent).forEach(e => {
      expect(baseComponent[e]).toEqual(rules[`--${e}`]);
    });
  });

  test('supports base Tailwind colors', () => {
    const rules = baseThemeCss['rules'];

    const baseTailwindSelectors = genericTailwindColors.flatMap(color =>
        tailwindShadeModifiers.map(shade => `--${color}-${shade}`)
    );

    baseTailwindSelectors.forEach(selector => {
      expect(rules[selector]).toBeDefined();
    });
  });
});
