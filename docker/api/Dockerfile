# Laravel-API: FrankenPHP (kann Antworten streamen, ohne andere Anfragen zu blockieren)
FROM dunglas/frankenphp:1.13.6-php8.5-bookworm

RUN install-php-extensions pdo_mysql pcntl intl zip bcmath

# pdftotext liest den Text hochgeladener PDFs
RUN apt-get update \
 && apt-get install -y --no-install-recommends poppler-utils \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.11.2 /usr/bin/composer /usr/bin/composer

# Gleiche UID/GID wie auf dem Host, damit Dateien in ./api nicht root gehören
ARG UID=1000
ARG GID=1000
RUN groupadd -g ${GID} app \
 && useradd -u ${UID} -g app -m app \
 && chown -R app:app /data/caddy /config/caddy

USER app
WORKDIR /app

EXPOSE 8000
CMD ["frankenphp", "php-server", "--root", "public", "--listen", ":8000"]
