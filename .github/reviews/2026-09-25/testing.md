# Testing Review

The CI workflow now has a separate Dusk job for the browser suite, with Node 22, asset build, ChromeDriver setup, a local server, and a dedicated MariaDB service.

The isolated non-browser suite passed: 71 tests, 198 assertions. The Dusk job still needs confirmation on GitHub Actions because it was not run in the local container during the audit.
