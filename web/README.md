# Launchpad Web App

A stateless Laravel application that provides API endpoints for the Launchpad ecosystem. This web app is bundled with the Launchpad CLI and deployed to `~/.config/launchpad/web/`.

## Architecture

The web app is **stateless by design**:

- **No database** - `DB_CONNECTION=null`
- **Redis for everything** - Cache, sessions, and queues use Redis
- **Horizon for queues** - Runs in Docker container

```
┌─────────────────────────────────────────────────────────────────┐
│  Desktop App / API Client                                       │
│           │                                                     │
│           ▼                                                     │
│  ┌─────────────────────┐     ┌─────────────────────┐            │
│  │ Launchpad Web App   │     │  launchpad-redis    │            │
│  │ (FrankenPHP/Docker) │────▶│  (Docker container) │            │
│  │ REDIS_HOST=         │     │                     │            │
│  │ launchpad-redis     │     └──────────┬──────────┘            │
│  └─────────────────────┘                │                       │
│                                         │ 127.0.0.1:6379        │
│                                         ▼                       │
│                           ┌─────────────────────────┐           │
│                           │  Horizon (on host)      │           │
│                           │  REDIS_HOST=127.0.0.1   │           │
│                           │  Docker container │           │
│                           └─────────────────────────┘           │
└─────────────────────────────────────────────────────────────────┘
```

## API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/projects` | POST | Create new project (dispatches CreateProjectJob) |
| `/api/projects` | GET | List all projects |
| `/api/status` | GET | Get launchpad status |
| `/horizon` | GET | Horizon dashboard (web UI) |

### Create Project

```bash
curl -sk https://launchpad.ccc/api/projects \
  -H "Content-Type: application/json" \
  -X POST \
  -d '{"name":"my-project","template":"user/repo","db_driver":"pgsql","visibility":"private"}'
```

Response:
```json
{
  "success": true,
  "status": "provisioning",
  "slug": "my-project",
  "message": "Project provisioning started."
}
```

## Horizon Queue Worker

Horizon runs as a Docker container (`launchpad-horizon`) managed by the CLI.

### Docker Configuration

Located at `~/.config/launchpad/horizon/docker-compose.yml`:
- Uses the same PHP image as web containers
- Mounts the web app at `/app`
- Connects to Redis and Reverb via Docker network
- Auto-restarts on failure

### Managing Horizon

```bash
# Check status
launchpad horizon:status
docker ps | grep launchpad-horizon

# Start/Stop/Restart
launchpad horizon:start
launchpad horizon:stop
docker restart launchpad-horizon

# View logs
docker logs launchpad-horizon --tail 100 -f

# Access dashboard
open https://launchpad.{tld}/horizon
```

## Configuration

### Environment (.env)

Key settings for stateless operation:

```bash
DB_CONNECTION=null           # No database
QUEUE_CONNECTION=redis       # Queues via Redis
CACHE_STORE=redis           # Cache via Redis
SESSION_DRIVER=redis        # Sessions via Redis
REDIS_HOST=launchpad-redis   # Docker network name
REVERB_HOST=launchpad-reverb # Reverb container
REVERB_PORT=6001            # Reverb port
```

### Redis Connections

Both the web app and Horizon run in Docker, using Docker network hostnames:

| Container | REDIS_HOST | Notes |
|-----------|------------|-------|
| launchpad-php-* | `launchpad-redis` | PHP containers for web requests |
| launchpad-horizon | `launchpad-redis` | Queue worker container |

## Troubleshooting

### Jobs Not Processing

```bash
# Check Horizon container is running
docker ps | grep launchpad-horizon

# Check container logs for errors
docker logs launchpad-horizon --tail 50

# Check Redis connectivity from Horizon container
docker exec launchpad-horizon redis-cli -h launchpad-redis ping

# Check pending jobs
docker exec launchpad-redis redis-cli LLEN launchpad_horizon:default
```

### Horizon Not Starting

```bash
# View container logs
docker logs launchpad-horizon

# Restart the container
docker restart launchpad-horizon

# Check health status
docker inspect launchpad-horizon --format "{{.State.Health.Status}}"
```

### Config Changes Not Taking Effect

After changing `.env`, clear config cache:
```bash
docker exec launchpad-horizon php artisan config:clear
docker restart launchpad-horizon
```

### Job Fails with Exit Code 1

```bash
### Job Fails with Exit Code 1

```bash
# Check Laravel logs
tail -f ~/.config/launchpad/web/storage/logs/laravel.log

# Run provision command directly
launchpad provision my-project --template=user/repo --db-driver=pgsql --visibility=private
```
