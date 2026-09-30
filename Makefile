SHELL := /bin/bash
MAKE_PHP ?= php -d zend.assertions=1 -d memory_limit=1G
MAKE_COMPOSER ?= composer
MAKE_ARTISAN ?= ${MAKE_PHP} ./artisan
.DEFAULT_GOAL := check

.PHONY: check audit stan frontend test-unit lint fix test e2e test-coverage clean setup local testing demo development staging production serve
check: stan lint audit frontend test-unit test

audit: vendor node_modules
	${MAKE_COMPOSER} audit
	${MAKE_COMPOSER} check-platform-reqs
	${MAKE_COMPOSER} validate --strict --no-check-all
	npm audit --audit-level=info

stan: vendor
	${MAKE_PHP} vendor/bin/phpstan analyse --memory-limit=1G

frontend: node_modules
	npm run type-check
	npm run build

test-unit: node_modules
	npm run test:unit

lint: node_modules vendor
	npx prettier -c .
	${MAKE_PHP} vendor/bin/pint --test

fix: node_modules vendor
	npx prettier -w .
	${MAKE_PHP} vendor/bin/pint

test: vendor
	${MAKE_PHP} vendor/bin/pest --no-coverage --fail-on-warning --fail-on-risky

e2e: node_modules vendor frontend
	npx playwright test

test-coverage: vendor
	@mkdir -p build/coverage
	XDEBUG_MODE=coverage ${MAKE_PHP} -dxdebug.mode=coverage vendor/bin/pest --coverage --coverage-clover=build/coverage/clover.xml --coverage-html=build/coverage/html

# Lockfiles are source files. Cleaning dependencies must preserve them.
clean:
	rm -rf node_modules vendor

.env:
	cp .env.example .env

# Existing configuration and application keys are preserved.
setup: .env
	${MAKE_COMPOSER} install --no-interaction --prefer-dist
	npm ci --include=dev
	@if ${MAKE_PHP} -r 'require "vendor/autoload.php"; $$values = Dotenv\Dotenv::parse(file_get_contents(".env")); exit(($$values["APP_KEY"] ?? "") === "" ? 0 : 1);'; then ${MAKE_ARTISAN} key:generate --no-interaction --force; fi

local: setup frontend
	${MAKE_ARTISAN} migrate --force --no-interaction
	@if [ ! -L public/storage ]; then ${MAKE_ARTISAN} storage:link --no-interaction; fi

testing: test

# Demo data is opt-in and forbidden outside a local environment by the seeder.
demo: vendor .env
	${MAKE_ARTISAN} db:seed --no-interaction

# Build before pruning development tools. Deployments supply their own .env.
development: setup frontend
	${MAKE_ARTISAN} config:clear
	${MAKE_ARTISAN} migrate --force --no-interaction
	${MAKE_COMPOSER} install --no-dev --classmap-authoritative --no-interaction --prefer-dist
	npm ci --omit=dev
	${MAKE_ARTISAN} optimize
	@if [ ! -L public/storage ]; then ${MAKE_ARTISAN} storage:link --no-interaction; fi
	${MAKE_ARTISAN} queue:restart

staging production: development

serve: vendor .env
	${MAKE_ARTISAN} serve --host=127.0.0.1 --port=8000

vendor:
	${MAKE_COMPOSER} install --no-interaction --prefer-dist

node_modules:
	npm ci --include=dev
