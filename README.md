# Bootstrap Shortcodes Development Workflow

Welcome to the development repository for the Bootstrap Shortcodes WordPress Plugin. 

## Git Flow & Contributing
This repository strictly adheres to **Git Flow**:
1. **No direct commits to `master`.** The `master` branch is protected.
2. For all changes, checkout a new branch from `master` (e.g., `feature/awesome-new-button` or `fix/button-styling`).
3. Commit your changes logically and atomically. Do not include unrelated files or business/personal notes.
4. Push your branch and open a Pull Request against `master`.
5. Automated CI/CD pipelines will run against your Pull Request. Once CI passes and the PR is reviewed, it may be merged.

## Repository Hygiene
Only development, testing, and project-related files are permitted in this repository. Never commit business communications, credentials, or unrelated documents.

## Local QA Testing
For local testing, we use `@wordpress/env`. See the [detailed workflow](.agents/workflows/local-qa.md) for instructions on spinning up the local test site.
