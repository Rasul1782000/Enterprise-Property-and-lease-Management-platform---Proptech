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

# Install the Docker CLI so pipelines can run docker commands
RUN apt-get update && apt-get install -y --no-install-recommends curl ca-certificates \
    && curl -fsSL https://download.docker.com/linux/static/stable/x86_64/docker-27.5.1.tgz \
       | tar xz -C /tmp \
    && cp /tmp/docker/docker /usr/local/bin/docker \
    && rm -rf /tmp/docker /var/lib/apt/lists/*

# Run as root so Jenkins jobs can access the mounted docker socket
USER root
