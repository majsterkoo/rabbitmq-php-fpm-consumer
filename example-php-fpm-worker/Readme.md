## example fpm worker
- from root path of this project, run:
    1) docker build -t test-fpm-worker -f example-php-fpm-worker/Dockerfile .
    2) docker run -p 9000:9000 test-fpm-worker