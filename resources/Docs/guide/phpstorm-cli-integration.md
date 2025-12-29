# PhpStorm: Ishmael CLI integration (2025-ready) and aliases

This guide explains, step by step, how to wire the Ishmael CLI into modern PhpStorm builds and how to create handy aliases. It reflects UI changes in recent PhpStorm versions and clarifies common pitfalls.

What you’ll get
- Command completion and inline help for Ish commands in PhpStorm’s Command Line Tools Console and Run Anything
- Optional Tools → External Tools menu item (if you want a fixed menu entry)
- Optional per‑project aliases for very quick execution

Prerequisites
- Open your application root in PhpStorm (the directory that contains composer.json)
- Ishmael framework installed so the CLI exists
    - Windows: vendor\bin\ish.bat
    - macOS/Linux: vendor/bin/ish (make it executable if needed)

Where the definition XML lives
- In this repository (source): vendor/ishmael/framework/extras/phpstorm/ish-cli.xml
- Distributed via Composer: vendor/ishmael/framework/extras/phpstorm/ish-cli.xml

Important: Tool path vs. XML file
- The XML only describes commands; it is NOT the executable.
- The “Tool path” (or “Program”) must point to the actual CLI launcher:
    - Windows: $ProjectFileDir$\vendor\bin\ish.bat
    - macOS/Linux: $ProjectFileDir$/vendor/bin/ish


Option A — Use PHP Command Line Tool Support (recommended)

Recent PhpStorm builds show “PHP Command Line Tool Support” and may not display a separate generic “Command Line Tool Support” plugin. That’s OK — use the PHP one.

1) Import the Ish command definitions
    - Settings/Preferences → Tools → PHP Command Line Tool Support
    - Click + → Custom tool → Import
    - Select: $ProjectFileDir$/vendor/ishmael/framework/extras/phpstorm/ish-cli.xml

2) Configure the tool
    - After import, select the created “Ishmael CLI” row and click the pencil icon.
    - Alias: ish
    - Tool path (the executable):
        - Windows: $ProjectFileDir$\vendor\bin\ish.bat
        - macOS/Linux: $ProjectFileDir$/vendor/bin/ish
    - Working directory: $ProjectFileDir$
    - Visibility: Current project
    - Leave “Interpreter” empty (ish.bat/ish calls PHP internally).
    - Apply / OK

3) How to run it in newer PhpStorm
    - Tools → Command Line Tools Console → type: ish help (completion comes from the XML)
    - Or press Ctrl twice (Run Anything) and run: ish help

Notes about older instructions
- In older screenshots you may see a Tools → Ishmael CLI submenu. Newer PhpStorm versions no longer auto‑create a per‑tool submenu here. Use the Command Line Tools Console or Run Anything instead (see above). If you want a persistent menu item, use Option B below.


Option B — Add a Tools menu item via External Tools (optional)

If you prefer a fixed Tools menu entry and/or a keyboard shortcut:
- Settings/Preferences → Tools → External Tools → +
    - Name: Ishmael CLI (ish)
    - Program:
        - Windows: $ProjectFileDir$\vendor\bin\ish.bat
        - macOS/Linux: $ProjectFileDir$/vendor/bin/ish
    - Arguments: $Prompt$
    - Working directory: $ProjectFileDir$
      This will appear under Tools → External Tools → Ishmael CLI (ish). You can assign a shortcut in Keymap.


Creating handy aliases (within PHP Command Line Tool Support)
1) Settings/Preferences → Tools → PHP Command Line Tool Support
2) Select “Ishmael CLI” → Open alias editor
3) Suggested aliases:
    - mkm → make:module (Args: <Name>)
    - mkr → make:resource (Args: <Module> <Name> [--api])
    - mkc → make:controller (Args: <Module> <Name> [--invokable])
    - mks → make:service (Args: <Module> <Name>)
    - mig → migrate (Args: [--module=<Name>] [--steps=<N>] [--pretend])
    - rb → migrate:rollback (Args: [--module=<Name>] [--steps=<N>])
    - status → status (Args: [--module=<Name>])
    - seed → seed (Args: [--module=<Name>] [--class=<FQCN>] [--force] [--env=<ENV>])
    - routes → make:routes (Args: [<Module>] [--api])
    - mods:cache → modules:cache
    - mods:clear → modules:clear
    - pack → pack (Args: [--target=<dir>] [--out=<dir>] [--env=<env>] [--include-dev] [--dry-run])

Tips for aliases
- Keep them short and memorable.
- Use placeholders in Arguments so PhpStorm will prompt you.


Verification checklist
- From PhpStorm: Tools → Command Line Tools Console → ish help shows the CLI help.
- From Run Anything (press Ctrl twice): ish help runs successfully.
- From the built‑in Terminal at project root:
    - Windows: .\vendor\bin\ish.bat help
    - macOS/Linux: ./vendor/bin/ish help


Troubleshooting
- No “Command Line Tools Console” menu item:
    - Ensure you’re on a recent PhpStorm build. Use Run Anything (Ctrl twice) as an alternative.
- It runs the wrong thing / nothing happens:
    - Double‑check “Tool path” points to the executable (ish.bat or ish), not to the XML.
- Program not found:
    - Confirm the file exists at vendor/bin/ish.bat (Windows) or vendor/bin/ish (macOS/Linux).
- Permission denied (macOS/Linux):
    - Run: chmod +x vendor/bin/ish
- Wrong working directory:
    - Set Working directory to $ProjectFileDir$.
- Still stuck?
    - Tell us your PhpStorm build (Help → About) and what you see when you run ish help from Terminal.


Updating the command list
- Re‑import the XML after upgrading the framework to get newly added commands, or add custom aliases manually for new commands.

Related
- See guide/cli.md for command semantics and examples.
