# syntax=docker/dockerfile:1

# Builds PHP for the browser with the intl extension, and packs the application
# with it. The build context is the root of the repository.
#
# Based on https://github.com/derickr/php-wasm-builder

ARG EMSDK_VERSION=6.0.12

FROM emscripten/emsdk:${EMSDK_VERSION} AS emsdk

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

RUN apt-get update \
    && apt-get install -y --no-install-recommends pkg-config \
    && rm -rf /var/lib/apt/lists/*

FROM emsdk AS icu

ARG ICU_VERSION=78.3

RUN mkdir -p /src/icu \
    && curl -fsSL "https://github.com/unicode-org/icu/releases/download/release-${ICU_VERSION}/icu4c-${ICU_VERSION}-sources.tgz" \
        | tar -xz -C /src/icu --strip-components=1

# Without its tools, ICU cannot build its data, and libicudata is a stub. The
# data (~30 MB) is not needed: symfony/emoji only uses the \Transliterator
# class, the conversion itself is done with strtr().
RUN mkdir /src/icu-wasm \
    && cd /src/icu-wasm \
    && CFLAGS="-Os" CXXFLAGS="-Os" emconfigure /src/icu/source/configure \
        --prefix=/opt/icu \
        --with-data-packaging=archive \
        --enable-static \
        --disable-shared \
        --disable-extras \
        --disable-samples \
        --disable-tests \
        --disable-tools \
    && emmake make -j"$(nproc)" \
    && emmake make install

FROM emsdk AS php

ARG PHP_VERSION=8.5.11

RUN mkdir -p /src/php \
    && curl -fsSL "https://www.php.net/distributions/php-${PHP_VERSION}.tar.gz" \
        | tar -xz -C /src/php --strip-components=1

COPY --from=icu /opt/icu /opt/icu

WORKDIR /src/php

# emconfigure gives EM_PKG_CONFIG_PATH to pkg-config, intl needs it to detect
# the ICU version.
RUN EM_PKG_CONFIG_PATH=/opt/icu/lib/pkgconfig emconfigure ./configure \
        --host=wasm32-unknown-emscripten \
        --enable-embed=static \
        --disable-all \
        --disable-cgi \
        --disable-cli \
        --disable-fiber-asm \
        --disable-phpdbg \
        --without-pcre-jit \
        --enable-intl \
    && emmake make -j"$(nproc)"

COPY website/phpw.c /src/phpw.c

RUN emcc -O2 -I. -IZend -Imain -ITSRM -c /src/phpw.c -o /src/phpw.o

FROM composer:2.10 AS app

WORKDIR /app

COPY composer.json composer.lock ./

# intl is only needed at runtime, this image does not have it.
RUN composer install --no-dev --no-autoloader --ignore-platform-req=ext-intl

COPY src src
COPY website/src website/src

RUN composer dump-autoload --no-dev --classmap-authoritative

# The catalogs of symfony/emoji (~37 MB) are not packed with the application:
# the website loads the ones it needs on demand. They are served as text files,
# that web servers do not execute and GitHub Pages compresses.
RUN mkdir -p /dist/emoji \
    && for file in vendor/symfony/emoji/Resources/data/*.php; do \
        mv "$file" "/dist/emoji/$(basename "$file" .php).txt"; \
    done \
    && php -r 'echo json_encode(array_map(fn ($file) => basename($file, ".txt"), glob("/dist/emoji/*.txt")));' > /dist/emoji/index.json

FROM php AS wasm

COPY --from=app /app /app

# PHP references a few functions that emscripten does not provide (ucontext for
# fibers, posix_spawnp() for proc_open(), …): calling them aborts at runtime.
RUN mkdir /dist \
    && em++ -O2 -o /dist/php-web.mjs \
        -sENVIRONMENT=web \
        -sMODULARIZE \
        -sEXPORT_ES6 \
        -sEXPORT_NAME=createPhpModule \
        -sINVOKE_RUN=0 \
        -sEXPORTED_FUNCTIONS=_phpw_run \
        -sEXPORTED_RUNTIME_METHODS=ccall,FS \
        -sALLOW_MEMORY_GROWTH \
        -sERROR_ON_UNDEFINED_SYMBOLS=0 \
        --preload-file /app \
        /src/phpw.o \
        .libs/libphp.a \
        /opt/icu/lib/libicuio.a \
        /opt/icu/lib/libicui18n.a \
        /opt/icu/lib/libicuuc.a \
        /opt/icu/lib/libicudata.a

FROM scratch

COPY --from=wasm /dist /
COPY --from=app /dist/emoji /emoji
