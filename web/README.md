# Launchpad Web App

A stateless Laravel application that provides API endpoints for the Launchpad ecosystem. This web app is bundled with the Launchpad CLI and deployed to `~/.config/launchpad/web/`.

## Architecture

The web app is **stateless by design**:

- **No database** - `DB_CONNECTION=null`
- **Redis for everything** - Cache, sessions, and queues use Redis
- **Horizon for queues** - Managed by supervisord on the host

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
│                           │  Managed by supervisord │           │
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

Horizon is managed by **supervisord** for automatic startup and crash recovery.

### Supervisor Configuration

Located at `/etc/supervisor/conf.d/launchpad-horizon.conf`:

```ini
[program:launchpad-horizon]
process_name=%(program_name)s
command=php ~/.config/launchpad/web/artisan horizon
autostart=true
autorestart=true
user=launchpad
environment=HOME="/home/launchpad",REDIS_HOST="127.0.0.1",PATH="..."
redirect_stderr=true
stdout_logfile=~/.config/launchpad/web/storage/logs/supervisor-horizon.log
stopwaitsecs=10
```

### Managing Horizon

```bash
# Check status
sudo supervisorctl status launchpad-horizon

# Restart Horizon
sudo supervisorctl restart launchpad-horizon

# View logs
tail -f ~/.config/launchpad/web/storage/logs/supervisor-horizon.log

# Access dashboard
open https://launchpad.test/horizon
```

## Configuration

### Environment (.env)

Key settings for stateless operation:

```bash
DB_CONNECTION=null          # No database
QUEUE_CONNECTION=redis      # Queues via Redis
CACHE_STORE=redis          # Cache via Redis
SESSION_DRIVER=redis       # Sessions via Redis
REDIS_HOST=launchpad-redis  # Docker network name
```

### Redis Connections

The web app uses different Redis hosts depending on context:

| Context | REDIS_HOST | Why |
|---------|------------|-----|
| Web App (Docker) | `launchpad-redis` | Docker network name resolution |
| Horizon (Host) | `127.0.0.1` | Direct connection from host |

## Troubleshooting

### Jobs Not Processing

```bash
# Check Horizon status
sudo supervisorctl status launchpad-horizon

# Check Redis connectivity
redis-cli -h 127.0.0.1 ping

# Check pending jobs
redis-cli -h 127.0.0.1 LLEN launchpad_horizon:default
```

### Horizon Not Starting

```bash
# View supervisor logs
tail -f ~/.config/launchpad/web/storage/logs/supervisor-horizon.log

# Reload supervisor config
sudo supervisorctl reread
sudo supervisorctl update
```

### Job Fails with Exit Code 1

```bash
# Check Laravel logs
tail -f ~/.config/launchpad/web/storage/logs/laravel.log

# Run provision command directly
launchpad provision my-project --template=user/repo --db-driver=pgsql --visibility=private
```
