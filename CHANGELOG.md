# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Added configurable process drivers via `solo.process_driver` (`screen` or `native`) with backward-compatible fallback to `solo.use_screen`.
- Added regression coverage for dashboard rendering behavior, diff-render cursor anchoring, renderer reuse, process-driver command wrapping, and shutdown signaling paths.

### Changed

- Updated dashboard rendering to prefer screen-diff output when available while preserving string-renderer fallback behavior.
- Upgraded `soloterm/screen` to include the upstream `toCellBuffer()` ANSI decode fix and removed Solo's temporary local shim.

### Fixed

- Fixed a frame composition bug that could scroll away the first row, causing the tab strip to disappear or appear incomplete.
- Fixed non-interactive dashboard boot crashes caused by uninitialized Laravel Prompts typed properties.
- Fixed differential rendering artifacts by re-homing the cursor at the first changed cell in each diff frame.
- Fixed shutdown signaling in non-screen drivers so the root process receives graceful termination.

### Removed

- Removed `EnhancedTailCommand` - it has been extracted to the standalone `soloterm/vtail` package. The default Logs command now uses a simple `tail -f` command.

## [0.3.0]

### Breaking Changes

There are a lot of breaking changes from 0.2.x to 0.3.x. I am sorry about that, but this is a fundamental rewrite! And it's so much better. This should be the last 0.x release. Provided nothing major comes up, we'll move on to 1.0.0 pretty quickly.

### Changed

- Changed the package name from `aaronfrancis/solo` to `soloterm/solo`. I've marked `aaronfrancis/solo` abandoned on Packagist.
- Changed the namespace from `AaronFrancis\Solo` to `SoloTerm\Solo`
- Completely rewrote the rendering pipeline
- Changed how hotkeys work. They are now configurable.

### Added

- Interactive commands!
- A new quick nav popup
- Hotkeys per command
- A new solo:dumps command that you can use to intercept `dump` calls in your application.
- A new solo:make command that lets you quickly access all Laravel make:* commands
- An enhanced tail command that collapses vendor frames and lets you truncate the logs via hotkey

### Removed

- Removed the need for you to have a service provider in favor of a much simpler config file. You'll need to migrate to the config file approach and delete your service provider.
- Removed all the `allowRegistrationFrom` hoops you had to jump through to register commands. This means that any third-party package could register a command. Any malicious third-party package can _already_ run `shell_exec` without Solo. Running a malicious script via Solo just makes it totally obvious that it's happening. So the security that `allowRegistrationFrom` added was performative at best.
