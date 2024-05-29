# pkstats.archlinux.de
This project contains the code powering [https://pkgstats.archlinux.de/](https://pkgstats.archlinux.de/),
the Arch Linux package statistics website.

# Dependencies
- [just](https://github.com/casey/just)

# Setup
1. Run `just install` to install dependencies
2. Run `just init` to perform the initial setup & start the application locally
3. Use `just start` and `just stop` to start and stop the app.
4. Run `just` for a full list of available commands

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
