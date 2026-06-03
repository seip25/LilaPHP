FROM nginx:alpine

# Copy nginx config
COPY app/docker/prod/nginx/default.conf /etc/nginx/conf.d/default.conf

# Copy project files
COPY . /var/www/html
