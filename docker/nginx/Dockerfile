FROM nginxinc/nginx-unprivileged:stable-alpine-slim

ENV NGINX_ENTRYPOINT_QUIET_LOGS=1

RUN sed -E '/worker_processes\s+auto;/d' -i /etc/nginx/nginx.conf

COPY default.conf /etc/nginx/conf.d/
COPY include /etc/nginx/include
