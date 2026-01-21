---
description: Update the master_plan_it app in the container (pull, migrate, clear-cache)
---

# Update App Workflow

This workflow updates the `master_plan_it` application within the running container by pulling the latest code, running migrations, and clearing the cache. It is designed to be idempotent and implementation-agnostic.

// turbo-all
1. Update the application code in the backend container:
   ```bash
   # Find the backend container dynamically
   CONTAINER=$(docker ps --format "{{.Names}}" | grep "mpit.*backend" | head -n 1)
   
   if [ -z "$CONTAINER" ]; then
       echo "Error: Could not find mpit backend container."
       exit 1
   fi
   
   echo "Targeting container: $CONTAINER"
   
   # Execute update script inside the container
   docker exec -u 1000:1000 "$CONTAINER" bash -c '
   set -e
   
   # --- Configuration ---
   APP_NAME="master_plan_it"
   APPS_DIR="/home/frappe/frappe-bench/apps"
   APP_PATH="$APPS_DIR/$APP_NAME"
   BENCH_DIR="/home/frappe/frappe-bench"
   
   echo "--- Starting Update for $APP_NAME ---"
   
   # 1. Update Code
   if [ -d "$APP_PATH" ]; then
       echo "Updating git repository in $APP_PATH..."
       cd "$APP_PATH"
       
       # Fetch updates
       git fetch --all
       
       # Determine remote (upstream or origin) and branch (develop)
       # Prefer upstream if it exists, otherwise origin
       if git remote | grep -q "^upstream$"; then
           REMOTE="upstream"
       else
           REMOTE="origin"
       fi
       
       BRANCH="develop" # Default to develop, could be dynamic
       
       echo "Pulling latest changes from $REMOTE/$BRANCH..."
       git reset --hard "$REMOTE/$BRANCH"
   else
       echo "Error: App directory $APP_PATH not found."
       exit 1
   fi
   
   # 2. Detect Site
   cd "$BENCH_DIR"
   # Find the first site that is not a system directory/file
   SITE_NAME=$(ls -1 sites | grep -vE "^(assets|apps|common_site_config.json|currentsite.txt|\.)$" | head -n 1)
   
   if [ -z "$SITE_NAME" ]; then
       echo "Error: Could not detect any site in $BENCH_DIR/sites"
       exit 1
   fi
   
   echo "Detected site: $SITE_NAME"
   
   # 3. Apply Changes
   echo "Running bench migrate..."
   bench --site "$SITE_NAME" migrate
   
   echo "Clearing cache..."
   bench --site "$SITE_NAME" clear-cache
   
   echo "--- Update Completed Successfully ---"
   '
   ```