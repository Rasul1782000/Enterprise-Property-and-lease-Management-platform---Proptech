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
USER jenkins
