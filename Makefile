.DEFAULT_GOAL := init

%:
	@:

init:
	echo "Specify an Action"

up:
	docker-compose -f .docker/docker-compose.yml up  --build --force-recreate -d &&  ./bin/docker-setup.sh

down:
	docker-compose -f .docker/docker-compose.yml  down


env-start:
	npm run env:start

env-stop:
	npm run env:stop

test:
	npm test

test-unit:
	npm run test:unit

lint:
	npm run lint

audit:
	npm run audit

build-production-js:
	npm run preuglify && npm run uglify

wp-format:
	npm run format

i18n-pot:
	composer run makepot

zip:
	rm rave-payment-forms.zip && npm run plugin-zip

inspection:
	composer run phpcompatibility:check

build:
	npm run build

release: build