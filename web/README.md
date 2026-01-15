# Orbit Web App

A stateless Laravel application that provides API endpoints for the Orbit ecosystem. This web app is bundled with the Orbit CLI and deployed to `~/.config/orbit/web/`.

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
│  │ Orbit Web App   │     │  orbit-redis    │            │
│  │ (FrankenPHP/Docker) │────▶│  (Docker container) │            │
│  │ REDIS_HOST=         │     │                     │            │
│  │ orbit-redis     │     └──────────┬──────────┘            │
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
| `/api/status` | GET | Get orbit status |
| `/horizon` | GET | Horizon dashboard (web UI) |

### Create Project

```bash
curl -sk https://orbit.ccc/api/projects \
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

Horizon runs as a Docker container (`orbit-horizon`) managed by the CLI.

### Docker Configuration

Located at `~/.config/orbit/horizon/docker-compose.yml`:
- Uses the same PHP image as web containers
- Mounts the web app at `/app`
- Connects to Redis and Reverb via Docker network
- Auto-restarts on failure

### Managing Horizon

```bash
# Check status
orbit horizon:status
docker ps | grep orbit-horizon

# Start/Stop/Restart
orbit horizon:start
orbit horizon:stop
docker restart orbit-horizon

# View logs
docker logs orbit-horizon --tail 100 -f

# Access dashboard
open https://orbit.{tld}/horizon
```

## Configuration

### Environment (.env)

Key settings for stateless operation:

```bash
DB_CONNECTION=null           # No database
QUEUE_CONNECTION=redis       # Queues via Redis
CACHE_STORE=redis           # Cache via Redis
SESSION_DRIVER=redis        # Sessions via Redis
REDIS_HOST=orbit-redis   # Docker network name
REVERB_HOST=orbit-reverb # Reverb container
REVERB_PORT=6001            # Reverb port
```

### Redis Connections

Both the web app and Horizon run in Docker, using Docker network hostnames:

| Container | REDIS_HOST | Notes |
|-----------|------------|-------|
| orbit-php-* | `orbit-redis` | PHP containers for web requests |
| orbit-horizon | `orbit-redis` | Queue worker container |

## Troubleshooting

### Jobs Not Processing

```bash
# Check Horizon container is running
docker ps | grep orbit-horizon

# Check container logs for errors
docker logs orbit-horizon --tail 50

# Check Redis connectivity from Horizon container
docker exec orbit-horizon redis-cli -h orbit-redis ping

# Check pending jobs
docker exec orbit-redis redis-cli LLEN orbit_horizon:default
```

### Horizon Not Starting

```bash
# View container logs
docker logs orbit-horizon

# Restart the container
docker restart orbit-horizon

# Check health status
docker inspect orbit-horizon --format "{{.State.Health.Status}}"
```

### Config Changes Not Taking Effect

After changing `.env`, clear config cache:
```bash
docker exec orbit-horizon php artisan config:clear
docker restart orbit-horizon
```

### Job Fails with Exit Code 1

```bash
### Job Fails with Exit Code 1

```bash
# Check Laravel logs
tail -f ~/.config/orbit/web/storage/logs/laravel.log

# Run provision command directly
orbit provision my-project --template=user/repo --db-driver=pgsql --visibility=private
```
