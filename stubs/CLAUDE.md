# Launchpad

Local PHP development environment at ~/.config/launchpad/

## Commands

```bash
launchpad start         # Start all services
launchpad stop          # Stop all services
launchpad restart       # Restart everything
launchpad status        # Check service status
launchpad sites         # List all sites
launchpad php <site> <version>  # Set PHP version
```

## Horizon (Queue Worker)

Launchpad includes a web app with Horizon for background job processing. Horizon is managed by **supervisord** for automatic startup and restart on failure.

```bash
# Check Horizon status
launchpad horizon:status

# View supervisor status
sudo supervisorctl status launchpad-horizon

# Manual control via supervisor
sudo supervisorctl restart launchpad-horizon
sudo supervisorctl stop launchpad-horizon
sudo supervisorctl start launchpad-horizon

# View logs
tail -f ~/.config/launchpad/logs/horizon.log

# Access dashboard (when running)
open https://launchpad.test/horizon
```

Supervisord configuration is at `/etc/supervisor/conf.d/launchpad-horizon.conf`.

## Direct Docker Access

```bash
# Start/stop individual services
docker compose -f ~/.config/launchpad/php/docker-compose.yml up -d
docker compose -f ~/.config/launchpad/postgres/docker-compose.yml down

# View logs
docker logs -f launchpad-php-83
docker logs -f launchpad-caddy
docker logs -f launchpad-redis
```

## Sites

Paths in config.json are served as {folder}.test (flat namespace, first match wins).

Set PHP version per project:

```bash
echo "8.1" > ~/Projects/mysite/.php-version
```

Or edit config.json:

```json
{ "sites": { "mysite": { "php_version": "8.1" } } }
```

Then restart: `launchpad restart`

## Add a New Path

1. Edit ~/.config/launchpad/config.json, add path to "paths" array
2. Edit ~/.config/launchpad/php/docker-compose.yml, add volume mount
3. Run: `launchpad restart`

## Config Locations

- PHP: ~/.config/launchpad/php/php.ini
- Caddy: ~/.config/launchpad/caddy/Caddyfile
- Sites: ~/.config/launchpad/config.json
- DNS: ~/.config/launchpad/dns/Dockerfile
- Horizon logs: ~/.config/launchpad/logs/horizon.log
- Web app: ~/.config/launchpad/web/

## Troubleshooting

```bash
# Check all services
launchpad status --json | jq .

# Check Horizon specifically
launchpad horizon:status
sudo supervisorctl status launchpad-horizon

# Restart everything
launchpad restart
sudo supervisorctl restart launchpad-horizon

# View Horizon logs for errors
tail -100 ~/.config/launchpad/logs/horizon.log
```
