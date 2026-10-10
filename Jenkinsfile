pipeline {
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
        // Configure these in Jenkins credentials or job configuration:
        // DOCKER_REGISTRY  = 'ghcr.io' or 'your-registry.azurecr.io' or 'docker.io'
        // IMAGE_NAME_FRONTEND = 'proptech-frontend'
        // IMAGE_NAME_BACKEND  = 'proptech-backend'
        // CREDENTIALS_ID  = 'docker-registry-credentials'
        DOCKER_REGISTRY       = ''
        IMAGE_NAME_FRONTEND   = 'proptech-frontend'
        IMAGE_NAME_BACKEND    = 'proptech-backend'
        CREDENTIALS_ID        = 'docker-registry-credentials'

        // ---- Production deployment --------------------------------------
        // Set these for production deployment
        PROD_SERVER           = ''
        PROD_SSH_KEY          = 'prod-ssh-key'
        PROD_USER             = 'deploy'
    }

    /*
     * The pipeline-wide agent is the Jenkins controller itself (`agent any`).
     * The Jenkins image ships Node 22, PHP/Composer and the Docker CLI, and the
     * Docker socket is mounted into it, so one node can run every stage. It
     * also gives the `post` section the node context its cleanup steps need;
     * with `agent none` those steps abort with
     * "Attempted to execute a step that requires a node context".
     *
     * Stages that want an isolated toolchain still override this with their own
     * `agent { docker { ... } }`. The composer stages additionally join the
     * compose network so they can resolve and reach Floci.
     */
    agent any

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
            agent any
            steps {
                echo 'Checking out source code from SCM...'
                checkout scm
            }
        }

        stage('Install Frontend Dependencies') {
            agent {
                docker {
                    image 'node:22.22-alpine'
                    args '-u root -v $HOME/.npm:/root/.npm'
                }
            }
            steps {
                echo 'Installing frontend dependencies (npm ci)...'
                dir('frontend') {
                    sh 'npm ci --no-audit --fund=false'
                }
            }
        }

        stage('Install Backend Dependencies') {
            agent {
                docker {
                    image 'composer:latest'
                    args '-u root -v $HOME/.composer:/root/.composer'
                }
            }
            steps {
                echo 'Installing backend dependencies (composer)...'
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
            // A stage that contains parallel stages cannot declare its own
            // `agent`; each parallel branch below declares the agent it needs.
            parallel {
                stage('Code Linting') {
                    agent {
                        docker {
                            image 'node:22.22-alpine'
                            args '-u root -v $HOME/.npm:/root/.npm'
                        }
                    }
                    steps {
                        echo 'Running code linter...'
                        dir('frontend') {
                            sh 'npm run lint --if-present'
                        }
                    }
                }
                /*
                 * npm and composer are different toolchains, so these are
                 * split into sibling stages to keep one agent per stage.
                 */
                stage('Frontend Security Audit') {
                    agent {
                        docker {
                            image 'node:22.22-alpine'
                            args '-u root -v $HOME/.npm:/root/.npm'
                        }
                    }
                    steps {
                        echo 'Auditing frontend dependencies...'
                        dir('frontend') {
                            sh 'npm audit --omit=dev || true'
                        }
                    }
                }
                stage('Backend Security Audit') {
                    agent {
                        docker {
                            image 'composer:latest'
                            args '-u root -v $HOME/.composer:/root/.composer'
                        }
                    }
                    steps {
                        echo 'Auditing backend dependencies...'
                        dir('backend') {
                            sh 'composer audit || true'
                        }
                    }
                }
            }
        }

        stage('Start Floci') {
            // Runs `docker run` / `docker inspect`, so it must stay on the
            // Jenkins container, which is the only context with the socket.
            agent any
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
                            #
                            # The Go template emits one network name per line via
                            # the built-in `println`. Concatenating the names
                            # directly ({{$k}}{{end}}) produced values like
                            # "jenkins_defaultmy-shared-network" whenever the
                            # container is attached to more than one network,
                            # which is what the Floci stages then tried to
                            # `docker network connect`.
                            docker inspect -f '{{range $k,$v := .NetworkSettings.Networks}}{{println $k}}{{end}}' "$(hostname)"
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
            agent {
                docker {
                    image 'composer:latest'
                    // Docker agents start on Docker's default bridge network,
                    // where the `floci` service name does not resolve. Join the
                    // compose network (the one the Jenkins container is on) so
                    // the backend can reach http://floci:4566.
                    args "--network ${env.FLOCI_NETWORK ?: 'jenkins_default'} -u root -v \$HOME/.composer:/root/.composer"
                }
            }
            steps {
                echo "Ensuring the '${AWS_STORAGE_BUCKET}' bucket exists on the Floci endpoint..."
                dir('backend') {
                    sh 'php artisan storage:ensure-bucket'
                }
            }
        }

        stage('Backend Tests') {
            agent {
                docker {
                    image 'composer:latest'
                    // Same reason as Bootstrap Storage: the Laravel S3
                    // integration tests talk to http://floci:4566, which only
                    // resolves on the compose network.
                    args "--network ${env.FLOCI_NETWORK ?: 'jenkins_default'} -u root -v \$HOME/.composer:/root/.composer"
                }
            }
            steps {
                echo 'Running backend tests (Laravel + S3 integration against Floci)...'
                dir('backend') {
                    // No `|| true`: these tests assert real S3 behaviour and must
                    // fail the build when the storage integration regresses.
                    sh 'php artisan test'
                }
            }
        }

        stage('WhatsApp Gateway Check') {
            // Advisory: never fails the build. See the step body.
            // Needs the Docker CLI to inspect the openwa container.
            agent any
            steps {
                /*
                 * The WhatsApp suite mocks the OpenWA API, so a green test run
                 * proves nothing about the gateway itself. This stage asserts
                 * the container answers on the network the build uses.
                 *
                 * It deliberately does NOT require a linked WhatsApp session:
                 * pairing needs a QR scan and cannot happen unattended. Only
                 * reachability is asserted, so the stage is deterministic.
                 */
                script {
                    sh """
                        # Advisory only: this does NOT fail the build. The
                        # pipeline provisions Floci on demand but never starts
                        # OpenWA, so a clean CI agent legitimately has no
                        # gateway here. Failing would make this stage a
                        # permanent red build for anyone who has not run
                        # `docker compose up -d` locally.
                        #
                        # Promote to `exit 1` once OpenWA is deployed as part
                        # of the pipeline's own infrastructure.
                        if ! docker inspect openwa >/dev/null 2>&1; then
                            echo 'SKIP: no openwa container on this agent (expected on clean CI).'
                            exit 0
                        fi

                        if curl -fsS --max-time 10 http://openwa:2785/api/health >/dev/null 2>&1; then
                            echo 'OpenWA gateway reachable at http://openwa:2785'
                        else
                            echo 'WARN: openwa container exists but /api/health did not answer:'
                            docker logs --tail 30 openwa || true
                        fi
                    """
                }
            }
        }

        stage('Frontend Tests') {
            agent {
                docker {
                    image 'node:22.22-alpine'
                    args '-u root -v $HOME/.npm:/root/.npm'
                }
            }
            steps {
                echo 'Running frontend tests (Vitest via the Angular unit-test builder)...'
                dir('frontend') {
                    sh 'npm test'
                }
            }
        }

        stage('Build Production Docker Images') {
            // `docker build` / `docker run`: needs the Jenkins socket.
            agent any
            steps {
                echo 'Building production container images...'
                script {
                    def commit = sh(script: 'git rev-parse --short HEAD', returnStdout: true).trim()

                    // Build Frontend
                    def frontendTag = "${env.IMAGE_NAME_FRONTEND}:${commit}"
                    dir('frontend') {
                        sh "docker build -t ${frontendTag} -t ${env.IMAGE_NAME_FRONTEND}:latest ."
                    }

                    // Build Backend
                    def backendTag = "${env.IMAGE_NAME_BACKEND}:${commit}"
                    dir('backend') {
                        sh "docker build -t ${backendTag} -t ${env.IMAGE_NAME_BACKEND}:latest ."
                    }

                    // Smoke-test frontend container
                    sh """
                        set -e
                        docker rm -f proptech-frontend-smoke >/dev/null 2>&1 || true
                        docker run -d --name proptech-frontend-smoke -p 8099:80 ${frontendTag} >/dev/null
                        for i in \$(seq 1 30); do
                            if docker exec proptech-frontend-smoke curl -fsS http://localhost/ >/dev/null 2>&1; then
                                echo "Frontend image serves HTTP after \${i}s"
                                docker rm -f proptech-frontend-smoke >/dev/null
                                exit 0
                            fi
                            sleep 1
                        done
                        echo 'Frontend image did not serve HTTP in time'
                        docker logs --tail 50 proptech-frontend-smoke || true
                        exit 1
                    """

                    // Smoke-test backend container (health endpoint)
                    sh """
                        set -e
                        docker rm -f proptech-backend-smoke >/dev/null 2>&1 || true
                        docker run -d --name proptech-backend-smoke \
                            -e APP_ENV=production \
                            -e APP_DEBUG=false \
                            -e APP_URL=https://rasul17.indevs.in \
                            -e DB_CONNECTION=sqlite \
                            ${backendTag} >/dev/null
                        for i in \$(seq 1 60); do
                            if docker exec proptech-backend-smoke curl -fsS http://localhost:8000/up >/dev/null 2>&1; then
                                echo "Backend image health check passed after \${i}s"
                                docker rm -f proptech-backend-smoke >/dev/null
                                exit 0
                            fi
                            sleep 1
                        done
                        echo 'Backend image health check failed'
                        docker logs --tail 50 proptech-backend-smoke || true
                        exit 1
                    """

                    echo "Frontend image ready: ${frontendTag}"
                    echo "Backend image ready: ${backendTag}"
                    env.FRONTEND_IMAGE_TAG = frontendTag
                    env.BACKEND_IMAGE_TAG = backendTag
                }
            }
        }

        stage('Push to Registry') {
            // params, not environment: PUSH_IMAGE is a build parameter, so an
            // `environment name` check would never match.
            when {
                expression { params.PUSH_IMAGE == true && env.DOCKER_REGISTRY != '' }
            }
            // `docker login` / `docker push`: needs the Jenkins socket.
            agent any
            steps {
                echo "Pushing images to ${env.DOCKER_REGISTRY}..."
                withCredentials([usernamePassword(
                    credentialsId: env.CREDENTIALS_ID,
                    usernameVariable: 'DOCKER_USER',
                    passwordVariable: 'DOCKER_PASS'
                )]) {
                    sh """
                        set -e
                        echo "\$DOCKER_PASS" | docker login ${env.DOCKER_REGISTRY} -u "\$DOCKER_USER" --password-stdin

                        # Push Frontend
                        docker tag ${env.FRONTEND_IMAGE_TAG} ${env.DOCKER_REGISTRY}/${env.IMAGE_NAME_FRONTEND}:latest
                        docker push ${env.DOCKER_REGISTRY}/${env.IMAGE_NAME_FRONTEND}:latest
                        docker tag ${env.FRONTEND_IMAGE_TAG} ${env.DOCKER_REGISTRY}/${env.IMAGE_NAME_FRONTEND}:${env.FRONTEND_IMAGE_TAG.split(':')[1]}
                        docker push ${env.DOCKER_REGISTRY}/${env.IMAGE_NAME_FRONTEND}:${env.FRONTEND_IMAGE_TAG.split(':')[1]}

                        # Push Backend
                        docker tag ${env.BACKEND_IMAGE_TAG} ${env.DOCKER_REGISTRY}/${env.IMAGE_NAME_BACKEND}:latest
                        docker push ${env.DOCKER_REGISTRY}/${env.IMAGE_NAME_BACKEND}:latest
                        docker tag ${env.BACKEND_IMAGE_TAG} ${env.DOCKER_REGISTRY}/${env.IMAGE_NAME_BACKEND}:${env.BACKEND_IMAGE_TAG.split(':')[1]}
                        docker push ${env.DOCKER_REGISTRY}/${env.IMAGE_NAME_BACKEND}:${env.BACKEND_IMAGE_TAG.split(':')[1]}
                    """
                }
            }
        }

        stage('Deploy to Production') {
            when {
                branch 'main'
                expression { env.PROD_SERVER != '' }
            }
            agent any
            steps {
                echo 'Deploying to production server...'
                withCredentials([sshUserPrivateKey(
                    credentialsId: env.PROD_SSH_KEY,
                    keyFileVariable: 'SSH_KEY',
                    usernameVariable: 'SSH_USER'
                )]) {
                    // Deploy using docker-compose.prod.yml on the production server
                    sh """
                        set -e
                        # Create deployment archive
                        tar -czf deploy.tar.gz docker-compose.prod.yml .env.production deploy.sh

                        # Copy to production server
                        scp -o StrictHostKeyChecking=no -i \${SSH_KEY} deploy.tar.gz ${env.PROD_USER}@${env.PROD_SERVER}:/tmp/

                        # Execute deployment on production server
                        ssh -o StrictHostKeyChecking=no -i \${SSH_KEY} ${env.PROD_USER}@${env.PROD_SERVER} << 'EOF'
                            set -e
                            cd /opt/property-lease-portal
                            tar -xzf /tmp/deploy.tar.gz

                            # Pull latest images from registry
                            if [ -n "${env.DOCKER_REGISTRY}" ]; then
                                docker login ${env.DOCKER_REGISTRY} -u "\$DOCKER_USER" -p "\$DOCKER_PASS"
                                docker compose -f docker-compose.prod.yml pull
                            fi

                            # Build locally if no registry
                            docker compose -f docker-compose.prod.yml build --no-cache

                            # Run migrations
                            docker compose -f docker-compose.prod.yml run --rm backend php artisan migrate --force

                            # Create storage link
                            docker compose -f docker-compose.prod.yml run --rm backend php artisan storage:link

                            # Optimize
                            docker compose -f docker-compose.prod.yml run --rm backend php artisan config:cache
                            docker compose -f docker-compose.prod.yml run --rm backend php artisan route:cache
                            docker compose -f docker-compose.prod.yml run --rm backend php artisan view:cache

                            # Start services
                            docker compose -f docker-compose.prod.yml up -d

                            # Wait for health
                            sleep 15
                            docker compose -f docker-compose.prod.yml ps

                            echo 'Deployment complete!'
                            echo 'Frontend: https://rasul17.indevs.in'
                            echo 'API: https://rasul17.indevs.in/api'
                        EOF

                        # Cleanup
                        rm deploy.tar.gz
                    """
                }
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
            sh 'docker rm -f proptech-frontend-smoke proptech-backend-smoke >/dev/null 2>&1 || true'
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