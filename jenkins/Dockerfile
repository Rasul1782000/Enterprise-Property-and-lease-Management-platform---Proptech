FROM jenkins/jenkins:lts

USER root

# Install plugins at build time so no manual wizard is needed
USER jenkins
RUN jenkins-plugin-cli --plugins \
    configuration-as-code \
    workflow-aggregator \
    git \
    docker-workflow \
    docker-plugin \
    blueocean \
    credentials-binding \
    ssh-agent \
    matrix-auth \
    role-strategy \
    job-dsl \
    pipeline-stage-view \
    timestamper

# Copy JCasC config outside the home volume so it is not shadowed by it
USER root
COPY casc.yaml /etc/jenkins/casc.yaml
RUN chown jenkins:jenkins /etc/jenkins/casc.yaml

# Install Node.js (for frontend npm stages), PHP CLI + Composer (for Laravel backend), and Docker CLI
USER root
RUN apt-get update && apt-get install -y --no-install-recommends curl ca-certificates \
    && curl -fsSL https://download.docker.com/linux/static/stable/x86_64/docker-27.5.1.tgz \
       | tar xz -C /tmp \
    && cp /tmp/docker/docker /usr/local/bin/docker \
    && rm -rf /tmp/docker /var/lib/apt/lists/*

# Node.js 22 LTS (Angular 22 requires Node ^22.22.3 || ^24.15.0 || >=26)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# PHP CLI, extensions commonly needed by Laravel, and Composer
RUN apt-get update && apt-get install -y --no-install-recommends \
        php-cli php-mbstring php-xml php-curl php-zip php-sqlite3 php-mysql unzip git \
    && rm -rf /var/lib/apt/lists/* \
    && curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php \
    && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm /tmp/composer-setup.php

# Run as root so Jenkins jobs can access the mounted docker socket
USER root
# Allow git to operate on workspaces owned by the jenkins user
RUN git config --system --add safe.directory '*'
