# Default shell
SHELL := /bin/bash

# Variables
MAKE_PHP ?= php -d zend.assertions=1 -d memory_limit=1G
MAKE_COMPOSER ?= composer
MAKE_ARTISAN ?= ${MAKE_PHP} ./artisan

# Default goal
.DEFAULT_GOAL := panic

# Goals
.PHONY: check
check: stan lint audit frontend test-unit test

.PHONY: audit
audit: ./vendor ./composer.lock ./node_modules ./package-lock.json
	${MAKE_COMPOSER} audit
	${MAKE_COMPOSER} check-platform-reqs
	${MAKE_COMPOSER} validate --strict --no-check-all
	npm audit --audit-level info --omit dev

.PHONY: stan
stan: ./vendor/bin/phpstan
	${MAKE_PHP} ./vendor/bin/phpstan analyse --memory-limit=1G

.PHONY: frontend
frontend: ./node_modules
	npm run type-check
	npm run build

.PHONY: test-unit
test-unit: ./node_modules/.bin/vitest
	npm run test:unit

.PHONY: lint
lint: ./node_modules/.bin/prettier ./vendor/bin/pint
	"./node_modules/.bin/prettier" -c .
	${MAKE_PHP} ./vendor/bin/pint --test

.PHONY: fix
fix: ./node_modules/.bin/prettier ./vendor/bin/pint
	"./node_modules/.bin/prettier" -w .
	${MAKE_PHP} ./vendor/bin/pint

.PHONY: test
test: ./vendor/bin/pest ./.env
	${MAKE_ARTISAN} optimize:clear
	${MAKE_ARTISAN} cache:clear
	${MAKE_ARTISAN} config:clear
	${MAKE_ARTISAN} event:clear
	${MAKE_ARTISAN} route:clear
	${MAKE_ARTISAN} view:clear
	${MAKE_ARTISAN} clear-compiled
	APP_ENV=testing ${MAKE_PHP} ./vendor/bin/pest --no-coverage

.PHONY: e2e
e2e: ./node_modules/.bin/playwright ./.env
	./node_modules/.bin/playwright test

.PHONY: test-coverage
test-coverage: ./vendor/bin/phpunit ./.env
	${MAKE_ARTISAN} optimize:clear
	${MAKE_ARTISAN} cache:clear
	${MAKE_ARTISAN} config:clear
	@mkdir -p build/coverage
	XDEBUG_MODE=coverage php -dxdebug.mode=coverage ./vendor/bin/phpunit --coverage-text --coverage-clover=build/coverage/clover.xml --coverage-html=build/coverage/html
	@echo "Coverage report: build/coverage/html/index.html"

.PHONY: clean
clean:
	rm -rf ./node_modules
	rm -rf ./package-lock.json
	rm -rf ./vendor
	rm -rf ./composer.lock

# Deploy / Release
.PHONY: local
local: ./.env
	${MAKE_COMPOSER} install
	npm install --include dev --install-links
	${MAKE_ARTISAN} optimize:clear
	${MAKE_ARTISAN} cache:clear
	${MAKE_ARTISAN} config:clear
	${MAKE_ARTISAN} event:clear
	${MAKE_ARTISAN} route:clear
	${MAKE_ARTISAN} view:clear
	${MAKE_ARTISAN} clear-compiled
	${MAKE_ARTISAN} migrate --force
	${MAKE_ARTISAN} db:seed --force
	${MAKE_ARTISAN} storage:link --force
	${MAKE_ARTISAN} queue:restart
	${MAKE_ARTISAN} up

.PHONY: testing
testing: local

.PHONY: development
development: ./.env
	${MAKE_COMPOSER} install
	npm install --install-links
	${MAKE_ARTISAN} optimize:clear
	${MAKE_ARTISAN} cache:clear
	${MAKE_ARTISAN} config:clear
	${MAKE_ARTISAN} event:clear
	${MAKE_ARTISAN} route:clear
	${MAKE_ARTISAN} view:clear
	${MAKE_ARTISAN} clear-compiled
	${MAKE_ARTISAN} migrate --force
	${MAKE_ARTISAN} db:seed --force
	${MAKE_COMPOSER} install -a --no-dev
	npm install --omit dev --install-links
	${MAKE_ARTISAN} optimize
	${MAKE_ARTISAN} config:cache
	${MAKE_ARTISAN} event:cache
	${MAKE_ARTISAN} route:cache
	${MAKE_ARTISAN} view:cache
	${MAKE_ARTISAN} storage:link --force
	${MAKE_ARTISAN} queue:restart
	${MAKE_ARTISAN} up

.PHONY: staging
staging: development

.PHONY: production
production: development

.PHONY: serve
serve: ./vendor ./.env
	${MAKE_ARTISAN} serve --host=0.0.0.0 --port=8000

# Dependencies
./vendor ./composer.lock ./vendor/bin/phpstan ./vendor/bin/pint ./vendor/bin/phpunit:
	${MAKE_COMPOSER} install

./node_modules ./package-lock.json ./node_modules/.bin/prettier ./node_modules/.bin/playwright:
	npm install --include dev --install-links
