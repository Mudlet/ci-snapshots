# Mudlet CI Snapshots - Development Container

This directory contains the development container configuration for local development of the Mudlet CI Snapshots application.

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop) or [Docker Engine](https://docs.docker.com/engine/install/)
- [Visual Studio Code](https://code.visualstudio.com/)
- [Dev Containers extension](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers) for VS Code

## Quick Start

1. Open this repository in Visual Studio Code
2. When prompted, click "Reopen in Container" (or use Command Palette: `Dev Containers: Reopen in Container`)
3. Wait for the container to build and start (first time may take a few minutes)
4. Once the container is ready, open your browser and navigate to `http://localhost:8080`
5. The database tables will be automatically created on first access

## What's Included

The development container provides:

- **PHP 8.3** with Apache web server
- **MySQL 8.0** database server
- Required PHP extensions:
  - mysqli (for database connectivity)
  - zip (for archive handling)
  - intl (for internationalization)
  - gettext (for translations)
- Apache modules:
  - mod_rewrite (for URL rewriting)
  - mod_headers (for CORS headers)
- Pre-configured development files:
  - `config.php` (auto-generated from `config.example.php`)
  - `.htaccess` (auto-generated from `.htaccess.example`)
  - `ip_list` (auto-generated from `ip_list.example`)

## Configuration

### Default Settings

The development environment is pre-configured with:

- **Web Server**: `http://localhost:8080`
- **MySQL Host**: `db`
- **Database Name**: `ci_snapshots`
- **Database User**: `snapshots`
- **Database Password**: `snapshots123`

### Customizing Configuration

If you need to modify the configuration:

1. Edit `config.php` in the workspace root
2. Changes are immediately reflected (no container rebuild needed)

## File Upload Testing

To test file uploads locally:

```bash
# From within the dev container terminal
curl --upload-file ./test-file.zip http://localhost:8080/test-file.zip
```

Or with authentication (if enabled in config.php):

```bash
curl -u user:pass --upload-file ./test-file.zip http://localhost:8080/test-file.zip
```

## Database Access

You can connect to the MySQL database using:

- **Host**: `localhost` (from your host machine) or `db` (from within container)
- **Port**: `3306`
- **Database**: `ci_snapshots`
- **Username**: `snapshots`
- **Password**: `snapshots123`

## Running Cron Jobs

To manually run the cron job for cleanup:

```bash
php /workspace/cron.php
```

To add a user for authenticated uploads:

```bash
php /workspace/cron.php adduser
```

## Debugging

The development container includes PHP debugging support. PHP extensions for VS Code are automatically installed.

## Ports

- **8080**: Apache web server
- **3306**: MySQL database server

## Volumes

Data is persisted in Docker volumes:

- `mysql-data`: MySQL database files

## Troubleshooting

### Database connection errors

If you see database connection errors:

1. Ensure the database container is running: `docker-compose ps`
2. Check database logs: `docker-compose logs db`
3. Restart the containers: Reopen the dev container or use `docker-compose restart`

### Permission errors on file uploads

If you encounter permission errors when uploading files:

```bash
# From within the dev container terminal
sudo chown -R www-data:www-data /workspace/files /workspace/tmp
sudo chmod -R 775 /workspace/files /workspace/tmp
```

### Rebuilding the container

If you need to rebuild the container (e.g., after modifying Dockerfile):

1. Use Command Palette: `Dev Containers: Rebuild Container`
2. Or manually: `docker-compose down && docker-compose build --no-cache`

## Architecture

The development environment consists of two containers:

1. **app**: PHP 8.3 + Apache web server
   - Serves the application
   - Mounts the workspace directory
   
2. **db**: MySQL 8.0 database
   - Stores snapshot metadata
   - Persists data in a Docker volume

## Notes

- The wp-plugin directory is included in the workspace but is not required for core functionality
- File uploads are stored in the `files/` directory
- Temporary files during processing are stored in the `tmp/` directory
- Both directories are created automatically with proper permissions

## Stopping the Development Environment

When you close VS Code or switch to a different workspace, the containers continue running in the background. To stop them:

```bash
docker-compose -f .devcontainer/docker-compose.yml down
```

To stop and remove all data:

```bash
docker-compose -f .devcontainer/docker-compose.yml down -v
```
