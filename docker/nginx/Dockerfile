# Construit la PWA (Vite) puis la sert avec nginx, qui relaie /api vers php-fpm.
FROM node:24-alpine AS front
WORKDIR /front
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
RUN npm run build

FROM nginx:1.29-alpine
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=front /front/dist /usr/share/nginx/html
