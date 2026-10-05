pipeline {
    agent any

    environment {
        // ---- Floci (local AWS emulator) ---------------------------------
        // Floci accepts any dummy credentials, so `test`/`test` is used
        // everywhere rather than real keys.
        AWS_ACCESS_KEY_ID     = 'test'
        AWS_SECRET_ACCESS_KEY = 'test'
        AWS_DEFAULT_REGION    = 'us-east-1'

        // Container name of the Floci container. Because Jenkins and Floci share
        // a user-defined Docker network, Docker's embedded DNS resolves this
        // name. Do NOT use localhost here: from inside the Jenkins container
        // localhost is the Jenkins container itself, not the Docker host.
        FLOCI_CONTAINER       = 'floci'
        AWS_ENDPOINT_URL      = 'http://floci:4566'

        // Bucket the backend writes lease documents and photos into.
        AWS_STORAGE_BUCKET    = 'property-lease-ci'
        AWS_STORAGE_PREFIX    = 'portal'

        // ---- Container registry ----------------------------------------
        // Override these on the Jenkins job (or this block) with your real registry.
        DOCKER_REGISTRY       = 'your-registry.azurecr.io'
        IMAGE_NAME            = 'proptech-frontend'
        CREDENTIALS_ID        = 'docker-registry-credentials'
    }

    options {
        buildDiscarder(logRotator(numToKeepStr: '10'))
        timeout(time: 2, unit: 'HOURS')
        disableConcurrentBuilds()
    }

    parameters {
        booleanParam(
            name: 'PUSH_IMAGE',
            defaultValue: false,
            description: 'Push the built image to the container registry (requires DOCKER_REGISTRY + credentials to be configured).'
        )
    }

    stages {
        stage('Checkout Code') {
            steps {
                echo 'Checking out source code from SCM...'
                checkout scm
            }
        }

        stage('Install Dependencies') {
            steps {
                echo 'Installing application dependencies...'
                dir('frontend') {
                    sh 'npm ci --no-audit --fund=false'
                }
                dir('backend') {
                    sh 'composer install --no-interaction --prefer-dist --no-progress'

                    /*
                     * .env is gitignored, so a clean checkout has none. Seeding it
                     * from the example keeps the storage settings identical to a
                     * developer's machine; the Jenkins `environment` block
                     * overrides the AWS values for this build.
                     */
                    sh '''
                        if [ ! -f .env ]; then
                            cp .env.example .env
                            echo "Seeded backend/.env from .env.example"
                        fi
                    '''
                }
            }
        }

        stage('Parallel Quality & Linting') {
            parallel {
                stage('Code Linting') {
                    steps {
                        echo 'Running code linter...'
                        dir('frontend') {
                            sh 'npm run lint --if-present'
                        }
                    }
                }
                stage('Security Audit') {
                    steps {
                        echo 'Running dependency vulnerability audit...'
                        dir('frontend') {
                            sh 'npm audit --omit=dev || true'
                        }
                        dir('backend') {
                            sh 'composer audit || true'
                        }
                    }
                }
            }
        }

        stage('Start Floci') {
            steps {
                /*
                 * Reuse the developer's long-running `floci` container when one
                 * is already running, and only start a fresh one otherwise.
                 *
                 * The network is discovered from the Jenkins container itself
                 * rather than hardcoded, so this keeps working if the compose
                 * project name or directory changes.
                 */
                script {
                    def net = sh(
                        script: '''
                            set -e
                            # The Jenkins container's own primary network.
                            docker inspect -f '{{range $k,$v := .NetworkSettings.Networks}}{{$k}}{{end}}' "$(hostname)"
                        ''',
                        returnStdout: true
                    ).trim().split('\n')[0].trim()

                    if (!net) {
                        error 'Could not determine the Docker network the Jenkins container is attached to.'
                    }
                    echo "Jenkins container network: ${net}"

                    // Reuse an existing Floci, but only if it can actually be
                    // reached over that network. A container left on the default
                    // bridge network would resolve by name nowhere, so start one.
                    def reused = sh(
                        script: """
                            set -e
                            if ! docker inspect ${env.FLOCI_CONTAINER} >/dev/null 2>&1; then
                                echo 'no-container'
                                exit 0
                            fi
                            if ! docker inspect -f '{{range \$k,\$v := .NetworkSettings.Networks}}{{\$k}} {{end}}' ${env.FLOCI_CONTAINER} | grep -qw '${net}'; then
                                echo 'wrong-network'
                                exit 0
                            fi
                            if curl -fsS --max-time 5 ${env.AWS_ENDPOINT_URL}/ >/dev/null 2>&1; then
                                echo 'reachable'
                            else
                                echo 'not-ready'
                            fi
                        """,
                        returnStdout: true
                    ).trim()

                    switch (reused) {
                        case 'reachable':
                            echo "Reusing the existing '${env.FLOCI_CONTAINER}' container."
                            break
                        case 'wrong-network':
                            echo "Existing '${env.FLOCI_CONTAINER}' is not on '${net}'; attaching it."
                            sh "docker network connect ${net} ${env.FLOCI_CONTAINER} || true"
                            break
                        case 'not-ready':
                            echo "Existing '${env.FLOCI_CONTAINER}' is on '${net}' but not answering; restarting it."
                            sh """
                                docker restart ${env.FLOCI_CONTAINER}
                            """
                            break
                        default:
                            echo "No '${env.FLOCI_CONTAINER}' container found; starting one for this build."
                            /*
                             * No host port mapping on purpose. Jenkins reaches
                             * Floci over the shared Docker network, and leaving
                             * 4566 unbound on the host avoids colliding with a
                             * developer's already-running Floci.
                             *
                             * The socket mount is required for the services Floci
                             * emulates with real containers (RDS, Lambda,
                             * ElastiCache, OpenSearch). Pure API services such as
                             * S3, SQS and DynamoDB need no socket.
                             */
                            sh """
                                docker run -d --name ${env.FLOCI_CONTAINER} \
                                    --network ${net} \
                                    -v /var/run/docker.sock:/var/run/docker.sock \
                                    floci/floci:latest
                            """
                            env.FLOCI_STARTED_BY_BUILD = 'true'
                            break
                    }

                    env.FLOCI_NETWORK = net
                }

                echo 'Waiting for Floci to accept requests...'
                sh '''
                    set -e
                    for i in $(seq 1 60); do
                        if curl -fsS --max-time 3 "$AWS_ENDPOINT_URL/" >/dev/null 2>&1; then
                            echo "Floci ready after ${i}s at $AWS_ENDPOINT_URL"
                            exit 0
                        fi
                        sleep 1
                    done
                    echo "Floci did not become ready in time."
                    echo "--- container state ---"
                    docker ps -a --filter name="${FLOCI_CONTAINER}" || true
                    docker logs --tail 50 "${FLOCI_CONTAINER}" || true
                    exit 1
                '''
            }
        }

        stage('Bootstrap Storage') {
            steps {
                echo "Ensuring the '${AWS_STORAGE_BUCKET}' bucket exists on the Floci endpoint..."
                dir('backend') {
                    sh 'php artisan storage:ensure-bucket'
                }
            }
        }

        stage('Backend Tests') {
            steps {
                echo 'Running backend tests (Laravel + S3 integration against Floci)...'
                dir('backend') {
                    // No `|| true`: these tests assert real S3 behaviour and must
                    // fail the build when the storage integration regresses.
                    sh 'php artisan test'
                }
            }
        }

        stage('Frontend Tests') {
            steps {
                echo 'Running frontend tests (Vitest via the Angular unit-test builder)...'
                dir('frontend') {
                    sh 'npm test'
                }
            }
        }

        stage('Build Production Docker Image') {
            steps {
                echo 'Building production container image...'
                script {
                    def commit = sh(script: 'git rev-parse --short HEAD', returnStdout: true).trim()
                    def imageTag = "${env.IMAGE_NAME}:${commit}"

                    dir('frontend') {
                        sh "docker build -t ${imageTag} -t ${env.IMAGE_NAME}:latest ."
                    }

                    // Smoke-test the built container before it is ever published.
                    sh """
                        set -e
                        docker rm -f proptech-smoke >/dev/null 2>&1 || true
                        docker run -d --name proptech-smoke -p 8099:80 ${imageTag} >/dev/null
                        for i in \$(seq 1 30); do
                            if docker exec proptech-smoke curl -fsS http://localhost/ >/dev/null 2>&1; then
                                echo "Image serves HTTP after \${i}s"
                                docker rm -f proptech-smoke >/dev/null
                                exit 0
                            fi
                            sleep 1
                        done
                        echo 'Image did not serve HTTP in time'
                        docker logs --tail 50 proptech-smoke || true
                        exit 1
                    """

                    echo "Image ready: ${imageTag}"
                    env.IMAGE_TAG = imageTag
                }
            }
        }

        stage('Push to Registry') {
            // params, not environment: PUSH_IMAGE is a build parameter, so an
            // `environment name` check would never match.
            when { expression { params.PUSH_IMAGE == true } }
            steps {
                echo "Pushing ${env.IMAGE_TAG} to ${env.DOCKER_REGISTRY}..."
                withCredentials([usernamePassword(
                    credentialsId: env.CREDENTIALS_ID,
                    usernameVariable: 'DOCKER_USER',
                    passwordVariable: 'DOCKER_PASS'
                )]) {
                    sh """
                        set -e
                        echo "\$DOCKER_PASS" | docker login ${env.DOCKER_REGISTRY} -u "\$DOCKER_USER" --password-stdin
                        docker tag ${env.IMAGE_TAG} ${env.DOCKER_REGISTRY}/${env.IMAGE_NAME}:latest
                        docker push ${env.DOCKER_REGISTRY}/${env.IMAGE_NAME}:latest
                    """
                }
            }
        }

        stage('Deploy to Production') {
            when {
                branch 'main'
            }
            steps {
                echo 'Triggering production deployment sequence...'
                // Add your deployment commands here (e.g., SSH trigger or Kubernetes rollouts)
                echo 'Deployment successful.'
            }
        }
    }

    post {
        always {
            script {
                // Only tear down a Floci container this build created. A reused
                // developer container must survive the build.
                if (env.FLOCI_STARTED_BY_BUILD == 'true') {
                    sh "docker rm -f ${env.FLOCI_CONTAINER} || true"
                } else {
                    echo "Leaving the shared '${env.FLOCI_CONTAINER}' container running."
                }
            }
            sh 'docker rm -f proptech-smoke >/dev/null 2>&1 || true'
            cleanWs()
        }
        success {
            echo "Pipeline succeeded: ${env.JOB_NAME} #${env.BUILD_NUMBER}"
        }
        failure {
            echo "Pipeline failed: ${env.JOB_NAME} #${env.BUILD_NUMBER}"
        }
    }
}