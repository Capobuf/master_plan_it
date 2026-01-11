`bench start` uses [honcho](http://honcho.readthedocs.org) to manage multiple processes in **developer mode**.

### Processes

The various process that are needed to run frappe are:

1. `bench start` - the web server
2. `redis_cache` for caching (general)
3. `redis_queue` for managing queue for background workers
4. `redis_socketio` as a message broker for real-time updates / updates from background workers
5. `web` for the frappe web server.
6. `socketio` for real-time messaging.
7. `schedule` to trigger periodic tasks
8. `worker_*` redis workers to handle async jobs

Optionally if you are developing for frappe you can add:

`bench watch` to automatically build the desk javascript app.

### Sample

redis\_cache: redis-server config/redis\_cache.conf
redis\_socketio: redis-server config/redis\_socketio.conf
redis\_queue: redis-server config/redis\_queue.conf
web: bench serve --port 8000
socketio: /usr/bin/node apps/frappe/socketio.js
watch: bench watch
schedule: bench schedule
worker\_short: bench worker --queue short
worker\_long: bench worker --queue long
worker\_default: bench worker --queue default
