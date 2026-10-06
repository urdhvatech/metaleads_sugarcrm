import darkThemeCss from '../../../build/theme/sugar-dark.css' with { type: 'css' };

const darkComponent = {
  'background-base': 'var(--gray-800)',
  'foreground-base': 'var(--gray-900)',
  'border-base': 'var(--gray-700)',
  'button-primary-background': 'var(--blue-400)',
  'button-primary-text': 'var(--gray-800)',
  'button-secondary-background': 'var(--transparent)',
  'button-secondary-text': 'var(--blue-300)',
  // 'sicon': 'var(--gray-500)',
  'text-base': 'var(--gray-300)',
  'text-action': 'var(--blue-300)',
};

describe('Dark theme component properties', () => {
  test('exported under class name', () => {
    expect(darkThemeCss['selectors'].includes('.sugar-dark-theme')).toEqual(true);
  });

  test('is backwards compatible', () => {
    const rules = darkThemeCss['rules'];
    const newRuleKeySet = new Set(Object.keys(rules).map((key) => key.replace(/^--/, "")));
    const oldRuleKeySet = new Set(Object.keys(darkComponent));

    expect(oldRuleKeySet.isSubsetOf(newRuleKeySet)).toEqual(true);
  });

  test('all properties exist', () => {
    const rules = darkThemeCss['rules'];
    Object.keys(darkComponent).forEach(e => {
      expect(darkComponent[e]).toEqual(rules[`--${e}`]);
    });
  });
});
