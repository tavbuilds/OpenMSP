FROM nginx:1.27-alpine

# Config in de image bakken i.p.v. een fragiele bind-mount (robuuster in Portainer).
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
