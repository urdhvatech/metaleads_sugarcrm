module.exports = {
  // parse rules from `fileContent`
  process(fileContent, filename, config, options) {
    const baseFileName = filename.split('/').at(-1);

     const exportObj = {
       type: 'css',
       filename: baseFileName,
     };

    // assumes only parsing theme css (not icons.css at the moment)
    // assumes each css file has only ONE block
    // assumes every variable is declared on new line
    // assumes each rule ends with ';'

    const linesInFile = fileContent.split('\n');

    // adding selectors to export object
    const firstSelectorLine = linesInFile.find(line => line.indexOf('{') > 0);
    const parsedSelector = firstSelectorLine.split('{').at(0).trim();
    const cssSelectors = parsedSelector.split(',').map(Function.prototype.call, String.prototype.trim);
    exportObj['selectors'] = cssSelectors;

    // adding rules to export object
    const rules = {};
    const ruleStrings = linesInFile.filter(line => line.trim().indexOf('--') == 0);

    ruleStrings.forEach(ruleString => {
      const withoutSemiColon = ruleString.split(';').at(0);
      const [key, value] = withoutSemiColon.split(':').map(Function.prototype.call, String.prototype.trim);
      rules[key] = value; // values are all stringified
    });

    exportObj['rules'] = rules;

    // prepare transform's outpus
    const exportContent = JSON.stringify(exportObj);

    return {
      code: `module.exports = ${exportContent};`,
    };
  },
  // Recommended to implement getCacheKey for performance
  getCacheKey() {
    return 'maple-syrup-css-transformer';
  },
};