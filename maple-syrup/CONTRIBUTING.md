# Contributing

When contributing to Maple Syrup, please follow the guidelines below.

## Getting started

Clone your fork of this repo and start building away. If you need to build the tokens locally, use the following command:

```shell
$  yarn install
$  yarn build
```

## Feature branches

Start by making a [fork](https://github.com/sugarcrm/maple-syrup/fork) on this repo. At this time, we do not have a naming
convention for feature branches, so be sure to make a short & descriptive feature branch name. Please keep feature branches
narrow in scope.

## Pull requests

When you push your branch to create a PR, please only include the updated tokens files, and not the `build` directory.
There is a build step to generate tokens.

The approval process currently requires a separate contributor to approve before merging.

### Workflows (GitHub Actions)

There is a small test suite that runs to verify the existence, and general structure of
the tokens. [This workflow](.github/workflows/node.js.yml) is based on the official "Node.js CI" workflow found on GitHub.
It simply installs dependencies, and runs the test suite. Purposely, it does not run a build to ensure that the changes
in the PR match what is being used to run the test suite.

