# ---- Build stage -----------------------------------------------------------
# Node 22 is required by Angular 22 (^22.22.3 || ^24.15.0 || >=26).
FROM node:22.22-alpine AS build

WORKDIR /app

# Install dependencies from the lockfile first so this layer caches across builds.
COPY package.json package-lock.json ./
RUN npm ci --no-audit --fund=false

COPY . .
RUN npm run build:prod

# ---- Runtime stage ---------------------------------------------------------
FROM nginx:1.27-alpine AS runtime

RUN apk add --no-cache curl \
    && rm -rf /var/cache/apk/*

COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY nginx-security-headers.conf /etc/nginx/nginx-security-headers.conf
COPY --from=build /app/dist/frontend/browser /usr/share/nginx/html

# Fail fast on a malformed config rather than crash-looping at runtime
RUN nginx -t

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -fsS http://localhost/ || exit 1

CMD ["nginx", "-g", "daemon off;"]