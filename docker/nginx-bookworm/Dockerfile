FROM nginx:latest as base

COPY --from=ochinchina/supervisord:latest /usr/local/bin/supervisord /usr/local/bin/supervisord
COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN curl -o /usr/local/bin/php https://dl.praswicaksono.pw/php-8.3-minimal-linux-x86_64 && \
    chmod +x /usr/local/bin/php

WORKDIR /app

CMD ["/usr/local/bin/supervisord", "-c", "/app/docker/supervisor/supervisor.conf"]

FROM base as prod

COPY --links . /app

RUN composer install && composer dumpautoload -o