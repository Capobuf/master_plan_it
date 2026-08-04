#!/usr/bin/env bash

set -eu

mysql --protocol=socket --user=root --password="${MYSQL_ROOT_PASSWORD}" <<-SQL
    CREATE DATABASE IF NOT EXISTS \`master_plan_it_test\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    GRANT ALL PRIVILEGES ON \`master_plan_it_test\`.* TO '${MYSQL_USER}'@'%';
    FLUSH PRIVILEGES;
SQL
