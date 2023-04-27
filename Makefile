.DEFAULT_GOAL := tests

PROJ_DIR := $(realpath $(CURDIR))

ifeq ($(IMAGE_TAG),)
	IMAGE_TAG := int-int-map-tests
endif

ifeq ($(PHP_VER),)
	PHP_VER := 8.1
endif

ifeq ($(PHP_SAPI),)
	PHP_SAPI := zts
endif

.PHONY: tests
tests:
	docker build -t $(IMAGE_TAG) \
 		--build-arg PHP_VER=$(PHP_VER) \
 		--build-arg PHP_SAPI=$(PHP_SAPI) \
 		-f $(PROJ_DIR)/docker/Dockerfile $(PROJ_DIR) \
		&& docker run -t $(IMAGE_TAG) composer tests

