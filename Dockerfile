FROM php:8.3-cli

# xhprof profiler installation
RUN pecl install xhprof && docker-php-ext-enable xhprof

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --no-interaction --no-progress
COPY . .

CMD ["php", "bin/example.php"]
