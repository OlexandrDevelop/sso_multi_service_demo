SHELL := /usr/bin/bash
.ONESHELL:

.PHONY: dev install seed run stop

install:
	cd services/service-auth && COMPOSER_MEMORY_LIMIT=-1 composer install
	cd services/service-b && COMPOSER_MEMORY_LIMIT=-1 composer install
	cd services/service-c && COMPOSER_MEMORY_LIMIT=-1 composer install
	cd web/app-b && npm i || true
	cd web/app-c && npm i || true

seed:
	cd services/service-auth && php artisan migrate --graceful --ansi || true
	cd services/service-auth && php artisan passport:install --force || true
	cd services/service-auth && php artisan db:seed --class=Database\\Seeders\\DefaultUserSeeder --ansi || true

run:
	# A: 8001, B: 8002, C: 8003, FE-B: 5173, FE-C: 5174
	: > .pids
	( cd services/service-auth && php -S 0.0.0.0:8001 -t public ) & echo $$! >> .pids
	( cd services/service-b && php -S 0.0.0.0:8002 -t public ) & echo $$! >> .pids
	( cd services/service-c && php -S 0.0.0.0:8003 -t public ) & echo $$! >> .pids
	( cd web/app-b && npm run dev ) & echo $$! >> .pids
	( cd web/app-c && npm run dev ) & echo $$! >> .pids
	wait

stop:
	@if [ -f .pids ]; then \
		echo "Stopping processes from .pids..."; \
		xargs -r kill < .pids || true; \
		rm -f .pids; \
	fi
	# Fallbacks in case some PIDs were missed
	-pkill -f "php -S 0.0.0.0:8001 -t public" || true
	-pkill -f "php -S 0.0.0.0:8002 -t public" || true
	-pkill -f "php -S 0.0.0.0:8003 -t public" || true
	-pkill -f "vite" || true

# One-shot for fresh machine
 dev: install seed run 