import lightThemeCss from '../../../build/theme/sugar-light.css' with { type: 'css' };

const lightComponent = {
  'background-base': 'var(--gray-100)',
  'foreground-base': 'var(--white)',
  'border-base': 'var(--gray-300)',
  'button-primary-background': 'var(--blue-600)',
  'button-primary-text': 'var(--white)',
  'button-secondary-background': 'var(--transparent)',
  'button-secondary-text': 'var(--blue-600)',
  // 'sicon': 'var(--gray-400)',
  'text-base': 'var(--gray-800)',
  'text-action': 'var(--blue-600)',
};

describe('Light theme component properties', () => {
  test('exported under class name', () => {
    expect(lightThemeCss['selectors'].includes('.sugar-light-theme')).toEqual(true);
  });

  test('is backwards compatible', () => {
    const rules = lightThemeCss['rules'];
    const newRuleKeySet = new Set(Object.keys(rules).map((key) => key.replace(/^--/, "")));
    const oldRuleKeySet = new Set(Object.keys(lightComponent));

    expect(oldRuleKeySet.isSubsetOf(newRuleKeySet)).toEqual(true);
  });

  test('all properties exist', () => {
    const rules = lightThemeCss['rules'];
    Object.keys(lightComponent).forEach(e => {
      expect(lightComponent[e]).toEqual(rules[`--${e}`]);
    });
  });
});
