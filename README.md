# pkgstats.archlinux.de
This project contains the code powering [https://pkgstats.archlinux.de/](https://pkgstats.archlinux.de/),
the Arch Linux package statistics website.

# Dependencies
- [just](https://github.com/casey/just)
- [go](https://go.dev/)
- [pnpm](https://pnpm.io/)

## Optional
- [air](https://github.com/air-verse/air)

# Setup
1. Run `just init` to install dependencies, build and generate fixtures
2. Run `just run` to start the application locally or `just dev` to watch for template and Go changes and rebuild automatically (requires [air](https://github.com/air-verse/air))
3. Run `just` for a full list of available commands

# Contributions
For contributing you'll probably want to test your changes at least once
before submitting a pull request. Run `just test` to run all tests.

## Fun Statistics
If you want one or more categories added to the fun statistics, you can either
do so by
1. submitting a pull request or
2. by providing the category and the packages
it should include via the package comparison feature.

Navigate to https://pkgstats.archlinux.de/packages, select the packages and click
"compare". Open an issue and add the link of the comparison page.
