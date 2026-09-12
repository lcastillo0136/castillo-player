# Changelog

All notable changes to Castillo Player will be documented in this file.

The format is inspired by Keep a Changelog, and this project uses
semantic versioning for public releases.


## [0.1.0] - 2026-09-11

Initial public release of Castillo Player.


### Added

- Alternative responsive Web interface for moOde Audio.
- Desktop and mobile layouts.
- Music library browsing by songs, artists and albums.
- Global library search.
- Favorites.
- Playlists.
- Playback queue management.
- Full-library playback.
- Persistent desktop player.
- Mobile mini player.
- Now Playing views for desktop and mobile.
- Real-time player state using Server-Sent Events and MPD idle.
- Synchronized LRC lyrics.
- Clickable lyric lines for seeking.
- Mobile lyric navigation.
- LRC editor with timestamp support and multiline paste.
- Audio metadata editor.
- Embedded artwork editor.
- Current-track indicators in library views.
- Audio-output selector for local HiFi DAC and Bluetooth sinks.
- Playback-state restoration when changing audio outputs.
- Waveform generation and display.
- Hashtag indexing and browsing.
- Progressive Web App support.
- Integration with selected moOde configuration interfaces.
- Central Castillo Player configuration file.
- Automated core installer.
- Automated uninstaller.
- moOde version compatibility detection.
- Optional NAS synchronization module.
- NAS-to-local one-way library mirroring.
- Protection of locally edited files during NAS-to-local synchronization.
- Pending-change tracking for metadata, artwork and lyrics.
- Conflict detection before local-to-NAS synchronization.
- Local-to-NAS dry-run support.
- Global NAS synchronization status.
- GPL-3.0-only licensing and project notices.
- Installation, configuration, uninstallation and compatibility
  documentation.
- Desktop and mobile screenshots.


### Compatibility

This release was tested with:

- moOde Audio 10.3.3
- Debian GNU/Linux 13 (Trixie)
- Linux aarch64
- MPD 0.24.13
- PHP 8.4
- Python 3.13
- Node.js 20
- nginx 1.26


### Notes

Castillo Player is an independent community project and is not
affiliated with or officially supported by the moOde Audio project.

The optional NAS synchronization module is not required for normal
Castillo Player operation.