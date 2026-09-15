<p align="center">
<a href="https://github.com/ugarit/chisel/actions"><img src="https://github.com/ugarit/chisel/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/ugarit/chisel"><img src="https://img.shields.io/packagist/dt/ugarit/chisel" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/ugarit/chisel"><img src="https://img.shields.io/packagist/v/ugarit/chisel" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/ugarit/chisel"><img src="https://img.shields.io/packagist/l/ugarit/chisel" alt="License"></a>
</p>

## Introduction

Ugarit Chisel provides primitives for building post-install scripts that remove unwanted features from Ugarit starter kits. Compatible starter kits include a `chisel.php` script that defines the optional features and the file mutations needed to remove them.

## Installation

```bash
composer require ugarit/chisel
```

## Usage

An example `chisel.php` script for optional email verification might look like this:

```php
<?php

require getenv('UGARIT_INSTALLER_AUTOLOADER');

use Ugarit\Chisel\Chisel;
use Ugarit\Chisel\Question;

return Chisel::script(dirname(__DIR__))
    ->questions([
        Question::multiselect(
            name: 'auth_features',
            label: 'Which authentication features would you like to enable?',
            options: [
                'email-verification' => 'Email verification',
            ],
            hint: 'Use space to select, enter to confirm.',
        ),
    ])
    ->selected('auth_features', 'email-verification',
        then: function (Chisel $c) {
            $c->files(
                'resources/js/pages/settings/profile.tsx',
                'app/Providers/FortifyServiceProvider.php',
            )->removeSectionMarkers('email-verification');
        },
        else: function (Chisel $c) {
            $c->php('app/Models/User.php')
                ->removeImport('Heritage\Contracts\Auth\MustVerifyEmail')
                ->removeInterface('MustVerifyEmail');

            $c->file('config/fortify.php')->removeLinesContaining('Features::emailVerification()');

            $c->files(
                'app/Providers/FortifyServiceProvider.php',
                'resources/js/pages/settings/profile.tsx',
            )->removeSection('email-verification');

            $c->files(
                'resources/js/components/email-verification-notice.tsx',
                'resources/js/pages/auth/verify-email.tsx',
                'tests/Feature/Auth/EmailVerificationTest.php',
                'tests/Feature/Auth/VerificationNotificationTest.php',
            )->delete();
        },
    );
```

Although the questions are defined in the `chisel.php` file, an external process such as an Scribe command is responsible for rendering them and passing the answers to Chisel's `chisel()` method. An example Scribe command using [Ugarit Prompts](https://ugarit.com/docs/prompts) might look like this:

```php
use Heritage\Console\Command;
use Ugarit\Chisel\Chisel;
use Ugarit\Chisel\Question;
use RuntimeException;

use function Ugarit\Prompts\multiselect;

class InstallFeatures extends Command
{
    protected $signature = 'install:features
        {--answers= : JSON string of answers to skip interactive prompts}';

    public function handle(): void
    {
        $script = require base_path('chisel.php');

        $providedAnswers = $this->option('answers') === null
            ? []
            : json_decode((string) $this->option('answers'), true, 512, JSON_THROW_ON_ERROR);

        $answers = $script
            ->collectAnswers()
            ->onQuestion(fn (Question $question) => match ($question->type) {
                'multiselect' => multiselect(
                    label: $question->label,
                    options: $question->options,
                    default: $question->default ?? [],
                    required: $question->required,
                    hint: $question->hint,
                ),
                default => throw new RuntimeException("Unsupported question type [{$question->type}]."),
            })
            ->interactive($this->input->isInteractive())
            ->withAnswers($providedAnswers);

        $script->chisel($answers);

        $chisel = Chisel::in(base_path());

        $chisel->npm()->install();
        $chisel->npm()->run('build');
    }
}
```

## Script Definitions

| Method                                     | Purpose                                          |
| ------------------------------------------ | ------------------------------------------------ |
| `Chisel::script($directory)`               | Create a script definition                       |
| `Question::multiselect(...)`               | Define a multiselect question                    |
| `questions([...])`                         | Set the script's questions                       |
| `questions()`                              | Retrieve the registered questions                |
| `collectAnswers()`                         | Begin collecting answers for registered questions |
| `apply($callback)`                         | Register an unconditional mutation step           |
| `selected($key, $value, then:, else:)`     | Branch on a multiselect answer                    |
| `selectedAny($key, $values, then:, else:)` | Branch when any of the given values are selected  |
| `selectedAll($key, $values, then:, else:)` | Branch when all of the given values are selected  |
| `chisel($answers)`                         | Execute the registered mutations                  |

## Answer Collection

`collectAnswers()` returns a pending answer collector. All methods are fluent and can be called in any order. Answers are resolved when the object is used as an array or `toArray()` is called. When non-interactive, defaults are used automatically.

| Method                         | Purpose                                                    |
| ------------------------------ | ---------------------------------------------------------- |
| `onQuestion($callback)`        | Handle question prompts                                    |
| `interactive($interactive)`    | Configure whether missing answers are prompted interactively |
| `withAnswers($answers)`        | Provide pre-collected answers to skip prompting            |

## File Mutations

`file($path)` targets a single file. `files(...$paths)` targets multiple files.

| Method                            | Purpose                                            |
| --------------------------------- | -------------------------------------------------- |
| `replace($search, $replace)`      | Replace a string                                   |
| `removeLinesContaining($content)` | Remove lines containing a string                   |
| `removeSectionMarkers($tag)`      | Strip section markers, keep the content            |
| `removeSection($tag)`             | Remove section markers and the content inside them |
| `delete()`                        | Delete the targeted files                          |

## PHP File Mutations

`php($path)` provides AST-based edits. Changes are saved automatically when the object is destroyed.

| Method                        | Purpose                             |
| ----------------------------- | ----------------------------------- |
| `removeImport($class)`        | Remove a `use` statement            |
| `removeTrait($trait)`         | Remove a trait usage from the class |
| `removeInterface($interface)` | Remove an implemented interface     |

## npm

| Method                               | Purpose                                            |
| ------------------------------------ | -------------------------------------------------- |
| `npm()->install()`                   | Install dependencies using the detected package manager |
| `npm()->run($script, ...$arguments)` | Run a package manager script                       |
| `npm()->remove(...$packages)`        | Remove packages using the detected package manager |

The `npm()` method detects `npm`, `yarn`, `pnpm`, and `bun` automatically.

## Section Markers

Wrap optional code in comment pairs:

```php
/* @chisel-passkeys */
Fortify::authenticateUsingPasskeys();
/* @end-chisel-passkeys */
```

JSX files may use block comments with braces:

```tsx
{
    /* @chisel-passkeys */
}
<PasskeyButton />;
{
    /* @end-chisel-passkeys */
}
```

`removeSectionMarkers('passkeys')` keeps the code and removes the markers. `removeSection('passkeys')` removes both. The `chisel-` marker prefix is added automatically.

## Contributing

Thank you for considering contributing to Chisel! The contribution guide can be found in the [Ugarit documentation](https://ugarit.com/docs/contributions).

## Code of Conduct

In order to ensure that the Ugarit community is welcoming to all, please review and abide by the [Code of Conduct](https://ugarit.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

Please review [our security policy](https://github.com/ugarit/chisel/security/policy) on how to report security vulnerabilities.

## License

Ugarit Chisel is open-sourced software licensed under the [MIT license](LICENSE.md).
