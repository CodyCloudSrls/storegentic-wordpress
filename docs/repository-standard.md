# The repository standard

CodyCloud Srls makes one Storegentic connector for each platform. Each
connector gets the same repository shape. A person who knows one repository
knows all of them.

This page is the standard. Copy it into the next repository and change the
part that the platform decides.

## The shape

```
<plugin files at the root>     The platform decides these
LICENSE                        AGPL-3.0-or-later, or the licence of the platform
README.md                      The front door
CHANGELOG.md                   Keep a Changelog, one section for each version
CONTRIBUTING.md                The house rules, and how to make a version
SECURITY.md                    How to report a problem, and what the code protects
.editorconfig                  The shape of the source
.gitattributes                 The one list of what stays out of the package
.gitignore                     What stays out of the repository
.github/workflows/check.yml    Syntax, versions, package
.github/workflows/release.yml  A tag makes a release
bin/build-zip.sh               Make the package
bin/run-tests.sh               Run the tests
docs/architecture.md           How is it built, and why?
docs/contract.md               What does the service declare?
docs/hooks.md                  Which extension points does it give?
docs/installation.md           How do I put it on a site?
docs/testing.md                How do I run the tests?
docs/translations.md           How do I change or add a language?
docs/repository-standard.md    This page
```

**The plugin files stay at the root.** A person can then clone the repository
directly into the plugin directory of the platform. A `plugin/` directory
looks tidy, but it stops that, and it also breaks `git log` for each file
after the move.

## The rules

### One branch

`main`, and no other branch. There is no development branch and no release
branch. A connector has one live version.

### One tag for each version

An annotated tag, `v<version>`. The tag is the only thing that makes a
release: `release.yml` builds the package and publishes it. Nobody uploads a
file by hand.

`release.yml` stops if the tag and the version of the plugin are not the
same.

### The package comes from `git archive`

`bin/build-zip.sh` uses `git archive`, and `git archive` reads the
`export-ignore` lines of `.gitattributes`. Two results come from this:

- Only a file that git holds can get into a package. A stray file in the
  working directory cannot.
- There is one list of the development files, in one place.

The script also stops if the version is not the same in each file that holds
it, and if a translation catalogue is not compiled.

### The source language is Italian

The identifiers and the comments are Italian. The documentation is English.
The interface has five languages.

This is not a mixed decision. The comments hold the reason for each line:
which defect caused which strangeness. They are for the person who changes
the code, and that person speaks Italian. The documentation is for the person
who installs the connector or who integrates with it, and that person can be
anywhere.

### A commit message says why

In Italian. The first line says what changed, in the present tense, with no
prefix and no tag. The body says why: which defect, and what it caused. No
trailer, and no name of a tool.

## What each platform decides

| Item | WordPress | Another platform |
|---|---|---|
| The plugin files at the root | `storegentic.php`, `src/`, `assets/`, `languages/`, `readme.txt`, `uninstall.php` | The layout that the platform needs |
| The package format | A `.zip` with one directory | The format that the platform installs |
| The test runner | WP-CLI, `wp eval-file` | The runner of the platform |
| The catalogue format | `.pot`, `.po`, `.mo`, `.l10n.php` | The format of the platform |
| The extension points | Filters and actions | The mechanism of the platform |
| The version, in more than one file | The header, the constant `VERSIONE`, `Stable tag` | Each file that holds the version |

## What no platform changes

These come from the service, not from the platform. Keep them the same in
each connector.

1. **One address in the code: the handshake.** The contract declares the
   others. See [contract.md](contract.md).
2. **Show only what the contract declares.** A command that answers "not
   authorized" is worse than a command that is not there.
3. **The key stays on the server.** The browser speaks to the platform, and
   the platform speaks to the service.
4. **The sync has two phases**, and the second phase starts only after each
   page passed. A guard stops a large prune.
5. **The service says which item, the shop says what it is.** Join the two
   with the SKU. Do not put a price in a cache.
6. **A silent service is not an error page.** Fall back to the database of
   the site, and say so on the screen.
7. **Each public route has a rate limit**, for each IP address.
8. **The names of the events come from the service.** Map them in one place.

## To start a new connector

1. Copy `LICENSE`, `.editorconfig`, `.gitattributes`, `.github/` and `bin/`.
2. Copy `docs/contract.md` and this page. Change the part that the platform
   decides.
3. Write `README.md`, `CONTRIBUTING.md` and `SECURITY.md` from the versions
   here. The house rules do not change.
4. Change `bin/build-zip.sh`: the version fields, and the format of the
   package.
5. First commit, then the tag `v0.1.0`.
