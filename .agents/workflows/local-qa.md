---
description: How to set up and run local QA for bootstrap-shortcodes
---

# Local QA Setup for Bootstrap Shortcodes

This workflow sets up a local WordPress environment to test the `bootstrap-shortcodes` plugin manually. It relies on the official `@wordpress/env` tool, which spins up a Docker container with WordPress and automatically mounts and activates the plugin in the current directory.

## Prerequisites
- **Node.js**: Requires `npm` and `npx`.
- **Docker**: Requires the Docker engine to be running on your machine.

## Steps

1. **Start the local WordPress environment**
   Run the following command in the root directory of the plugin:
   ```bash
   npx @wordpress/env start
   ```
   *Note: This might take a few minutes the first time it runs as it downloads the necessary Docker images.*

2. **Access the WordPress Dashboard**
   Once the environment is running, you will see output with the URLs for the site.
   - **Site URL**: `http://localhost:8888`
   - **Dashboard URL**: `http://localhost:8888/wp-admin`
   - **Username**: `admin`
   - **Password**: `password`

3. **Perform Manual QA**
   - Log into the dashboard.
   - Go to **Posts > Add New** or **Pages > Add New**.
   - Note: Since this plugin relies on adding buttons to the TinyMCE "Visual" editor, make sure you are using the Classic Editor block or install the Classic Editor plugin if testing on modern WordPress, OR test it within a Classic block in Gutenberg.
   - Enable the "Toolbar Toggle" (kitchen sink) to see the Bootstrap Shortcodes button if it's hidden.
   - Insert some shortcodes (like Buttons, Alerts, Grid) and publish the post.
   - View the post on the frontend to ensure the shortcodes render correctly.

4. **Stop the environment**
   When you are done testing, run:
   ```bash
   npx @wordpress/env stop
   ```
   To completely remove the containers and reset the database:
   ```bash
   npx @wordpress/env clean all
   ```
