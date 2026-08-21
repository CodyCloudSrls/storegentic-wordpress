# How to work on this code

Read [docs/architecture.md](docs/architecture.md) first. It gives the reason
for each decision.

## The house rules

These rules are not style preferences. Each one comes from a defect.

1. **No address of the service in the code**, except the handshake. The
   service declares the addresses. See [docs/contract.md](docs/contract.md).
2. **Show only what the contract declares.** A command that answers "not
   authorized" is worse than a command that is not there.
3. **A comment gives the reason, not the action.** If a line looks strange,
   the comment above it says which defect caused the strangeness. Do not
   write a comment that repeats the code.
4. **Simplified Italian in the code**: one idea for each sentence, an active
   verb, the same word for the same thing.
5. **Each string goes through a translation function.** The JavaScript holds
   no text. The text comes from PHP, through `wp_localize_script`.
6. **One question, one place.** If you must ask the same question in two
   files, make a method.

## The shape of the code

| Item | Rule |
|---|---|
| PHP | 8.0. `declare( strict_types = 1 );` in each file. |
| Indent | Tabs in PHP, two spaces in CSS and JavaScript. |
| Standard | WordPress Coding Standards. |
| Classes | `final`, with static methods, one directory for each area. |
| Autoload | `Storegentic\Sub\Name` is in `src/Sub/Name.php`. |
| Dependencies | None. No Composer, no npm, no build step. |
| JavaScript | Plain JavaScript. No jQuery, no framework. |
| CSS | Each class starts with `sg-`. Each value comes from a variable with a fallback. |

Each file starts with a block that says what the file does and why it exists.
Look at `src/Api/Contratto.php` for the shape.

## Escape and permissions

- Escape at the output: `esc_html`, `esc_attr`, `esc_url`.
- The markup of a card comes from `Frontend\Scheda` only. Do not build markup
  in JavaScript.
- Each admin action needs `current_user_can( Negozio::permesso() )` and a
  nonce for that action. One nonce for the whole page would let a user with a
  nonce for a check start a sync.
- Each public REST route needs a rate limit. The routes are public because a
  customer is not a logged-in user.
- Do not put the site key in a page. Do not put the site key in a log.

## Before you commit

```
bin/run-tests.sh
```

If you change a string, make the template again and compile the catalogues.
See [docs/translations.md](docs/translations.md).

If you change the CSS or the JavaScript, look at the panel and at the shop at
390, 768, 1280 and 1600 pixels. There must be no horizontal scroll and no
error in the console.

## Commit messages

Write the message in Italian, in the same style as the history. The first
line says what changed, in the present tense, with no prefix and no tag. The
body says **why**. Half of the value of this code is in the commit messages.

```
Il pannello dice quando il piano e' finito, e la ricerca non si ferma

Il contratto dichiara plan, usage e rateLimits da sempre, e il plugin non li
guardava: il pannello diceva "attivo" leggendo solo se l'handshake era
riuscito, cosa che riesce anche a quota finita.
```

Do not add a trailer to a commit message. Do not name a tool in a commit
message.

## Branches and versions

The repository has one branch: `main`. There is no development branch and no
release branch.

Each version gets an annotated tag: `v0.3.0`.

To make a version:

1. Change the version in `storegentic.php` (the header and the constant
   `VERSIONE`) and in `readme.txt` (`Stable tag`).
2. Add the section to `CHANGELOG.md` and to the changelog in `readme.txt`.
3. Commit.
4. `git tag -a v0.3.1 -m "Storegentic 0.3.1"`
5. `git push origin main --follow-tags`

The tag makes the release. `.github/workflows/release.yml` builds the
package, makes the checksum, and publishes both with the notes from
`CHANGELOG.md`. Nobody uploads a file by hand.

The workflow stops if the tag and the version of the plugin are not the same.

To see the package before the tag:

```
bin/build-zip.sh
```

The repository shape is a standard for each Storegentic connector. See
[docs/repository-standard.md](docs/repository-standard.md).

## A new test

Write the restore code first. A test must keep the data that it changes and
put it back, also if it stops in the middle. See
[docs/testing.md](docs/testing.md).
