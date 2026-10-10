/*
 * Simplified CI/CD pipeline for the Property & Lease Management Portal.
 *
 * Flow: checkout -> install -> start Floci (local AWS) -> test -> build
 *       -> push (optional) -> deploy (optional).
 *
 * The Jenkins container ships Node, PHP/Composer and the Docker socket, so
 * most stages run on `agent any`. Stages that want an isolated toolchain swap
 * in their own docker agent.
 */
pipeline {
    agent any

    options {
        buildDiscarder(logRotator(numToKeepStr: '10'))
        timeout(time: 2, unit: 'HOURS')
        disableConcurrentBuilds()
    }

    environment {
        // Floci (local AWS emulator) accepts any dummy credentials.
        AWS_ACCESS_KEY_ID     = 'test'
        AWS_SECRET_ACCESS_KEY = 'test'
        AWS_DEFAULT_REGION    = 'us-east-1'
        AWS_ENDPOINT_URL      = 'http://floci:4566'
        AWS_STORAGE_BUCKET    = 'property-lease-ci'
        AWS_STORAGE_PREFIX    = 'portal'

        FLOCI_CONTAINER       = 'floci'

        // Container registry (set these in the job / credentials for a push).
        DOCKER_REGISTRY       = ''
        IMAGE_NAME_FRONTEND   = 'proptech-frontend'
        IMAGE_NAME_BACKEND    = 'proptech-backend'
        CREDENTIALS_ID        = 'docker-registry-credentials'

        // ---- Vercel deployment -------------------------------------------
        // The frontend deploys to Vercel (project "frontend" in the
        // rasul-ahmed-khans-projects team). Both IDs are not secrets - they are
        // the identifiers from .vercel/project.json. Only the token is secret:
        // add it as a Jenkins "Secret text" credential with the id below. When
        // the IDs are set, every push to `main` deploys; other branches stop
        // after tests. See VERCEL_DEPLOYMENT.md.
        VERCEL_ORG_ID         = 'team_wpE0cAB9zBtXOEltZjBRtcqP'
        VERCEL_PROJECT_ID     = 'prj_XcprW7xNZSBZRAp9pwOfNX7NA7gY'
        VERCEL_TOKEN_ID       = 'vercel-token'
    }

    parameters {
        booleanParam(
            name: 'PUSH_IMAGE',
            defaultValue: false,
            description: 'Push the built images to the container registry.'
        )
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Install Dependencies') {
            parallel {
                stage('Frontend') {
                    agent { docker { image 'node:22.22-alpine'; args '-u root -v $HOME/.npm:/root/.npm' } }
                    steps {
                        dir('frontend') { sh 'npm ci --no-audit --fund=false' }
                    }
                }
                stage('Backend') {
                    agent { docker { image 'composer:latest'; args '-u root -v $HOME/.composer:/root/.composer' } }
                    steps {
                        dir('backend') {
                            sh 'composer install --no-interaction --prefer-dist --no-progress'
                            sh '[ -f .env ] || cp .env.example .env'
                        }
                    }
                }
            }
        }

        stage('Start Floci') {
            steps {
                script {
                    /*
                     * Floci and Jenkins share a user-defined Docker network, so
                     * the name `floci` resolves. Work the network out from the
                     * Jenkins container rather than hardcoding it, and never use
                     * the default `bridge` (no embedded DNS there).
                     */
                    def networks = sh(
                        script: '''docker inspect -f '{{range $k,$v := .NetworkSettings.Networks}}{{println $k}}{{end}}' "$(hostname)"''',
                        returnStdout: true
                    ).trim().split('\n')*.trim().findAll { it && !(it in ['bridge', 'host', 'none']) }

                    if (!networks) {
                        error 'Could not find a usable Docker network for Floci.'
                    }
                    env.FLOCI_NETWORK = networks[0]

                    // Reuse a running Floci, otherwise start one for this build.
                    def running = sh(
                        script: "docker inspect ${env.FLOCI_CONTAINER} >/dev/null 2>&1 && echo yes || echo no",
                        returnStdout: true
                    ).trim()

                    if (running == 'yes') {
                        sh "docker network connect ${env.FLOCI_NETWORK} ${env.FLOCI_CONTAINER} 2>/dev/null || true"
                    } else {
                        sh """
                            docker run -d --name ${env.FLOCI_CONTAINER} \\
                                --network ${env.FLOCI_NETWORK} \\
                                -v /var/run/docker.sock:/var/run/docker.sock \\
                                floci/floci:latest
                        """
                        env.FLOCI_STARTED_BY_BUILD = 'true'
                    }

                    // Wait for the AWS API to answer.
                    sh '''
                        for i in $(seq 1 60); do
                            curl -fsS --max-time 3 "$AWS_ENDPOINT_URL/" >/dev/null 2>&1 && exit 0
                            sleep 1
                        done
                        echo 'Floci did not become ready:'
                        docker logs --tail 50 "$FLOCI_CONTAINER" || true
                        exit 1
                    '''

                    // Ensure the S3 bucket this project uses exists (idempotent).
                    sh 'curl -fsS -X PUT "$AWS_ENDPOINT_URL/$AWS_STORAGE_BUCKET" >/dev/null 2>&1 || true'
                }
            }
        }

        stage('Test') {
            parallel {
                stage('Lint') {
                    agent { docker { image 'node:22.22-alpine'; args '-u root -v $HOME/.npm:/root/.npm' } }
                    steps { dir('frontend') { sh 'npm run lint --if-present' } }
                }
                stage('Frontend') {
                    agent { docker { image 'node:22.22-alpine'; args '-u root -v $HOME/.npm:/root/.npm' } }
                    steps { dir('frontend') { sh 'npm test' } }
                }
                stage('Backend') {
                    agent {
                        docker {
                            image 'composer:latest'
                            args "--network ${env.FLOCI_NETWORK ?: 'jenkins_default'} -u root -v \$HOME/.composer:/root/.composer"
                        }
                    }
                    steps { dir('backend') { sh 'php artisan test' } }
                }
            }
        }

        stage('Build Images') {
            steps {
                script {
                    def commit = sh(script: 'git rev-parse --short HEAD', returnStdout: true).trim()
                    env.FRONTEND_IMAGE_TAG = "${env.IMAGE_NAME_FRONTEND}:${commit}"
                    env.BACKEND_IMAGE_TAG  = "${env.IMAGE_NAME_BACKEND}:${commit}"

                    dir('frontend') { sh "docker build -t ${env.FRONTEND_IMAGE_TAG} -t ${env.IMAGE_NAME_FRONTEND}:latest ." }
                    dir('backend')  { sh "docker build -t ${env.BACKEND_IMAGE_TAG} -t ${env.IMAGE_NAME_BACKEND}:latest ." }
                }
            }
        }

        stage('Push Images') {
            when { expression { params.PUSH_IMAGE == true && env.DOCKER_REGISTRY != '' } }
            steps {
                withCredentials([usernamePassword(
                    credentialsId: env.CREDENTIALS_ID,
                    usernameVariable: 'DOCKER_USER',
                    passwordVariable: 'DOCKER_PASS'
                )]) {
                    sh '''
                        set -e
                        echo "$DOCKER_PASS" | docker login "$DOCKER_REGISTRY" -u "$DOCKER_USER" --password-stdin
                        docker push "$DOCKER_REGISTRY/$IMAGE_NAME_FRONTEND:latest"
                        docker push "$DOCKER_REGISTRY/$IMAGE_NAME_BACKEND:latest"
                    '''
                }
            }
        }

        stage('Deploy Frontend to Vercel') {
            /*
             * Only `main` deploys, and only once the Vercel project IDs are
             * set. Nothing else in this pipeline deploys anywhere: the Docker
             * stages are build/smoke-test only, and the registry push is an
             * explicit, opt-in build parameter.
             */
            when {
                branch 'main'
                expression { env.VERCEL_ORG_ID != '' && env.VERCEL_PROJECT_ID != '' }
            }
            // `npx vercel` needs Node/npm, which the Jenkins container ships.
            steps {
                script {
                    /*
                     * The only external prerequisite is a Jenkins secret-text
                     * credential named by VERCEL_TOKEN_ID holding a Vercel token.
                     * If it is missing (or the deploy fails) we mark the build
                     * UNSTABLE and explain what to do rather than failing the
                     * whole pipeline - a missing credential is a setup step, not
                     * a code regression.
                     */
                    try {
                        echo "Deploying the Angular frontend to Vercel (project ${env.VERCEL_PROJECT_ID})..."
                        withCredentials([string(credentialsId: env.VERCEL_TOKEN_ID, variable: 'VERCEL_TOKEN')]) {
                            withEnv([
                                "VERCEL_ORG_ID=${env.VERCEL_ORG_ID}",
                                "VERCEL_PROJECT_ID=${env.VERCEL_PROJECT_ID}"
                            ]) {
                                /*
                                 * Run from the repository root: the Vercel
                                 * project's Root Directory is `frontend/`, so the
                                 * CLI resolves the app (and frontend/vercel.json)
                                 * from here.
                                 *
                                 * Official Vercel CI flow: pull the project
                                 * settings, build locally (runs the install +
                                 * build commands from vercel.json), then ship the
                                 * prebuilt output. `--prod` promotes it to the
                                 * production domain.
                                 */
                                sh '''
                                    set -e
                                    npx --yes vercel@latest pull --yes --environment=production --token="$VERCEL_TOKEN"
                                    npx --yes vercel@latest build --prod --token="$VERCEL_TOKEN"
                                    npx --yes vercel@latest deploy --prebuilt --prod --token="$VERCEL_TOKEN"
                                '''
                            }
                        }
                    } catch (e) {
                        echo "Vercel deploy did not run: ${e.message}"
                        echo "Add a Jenkins secret-text credential with id '${env.VERCEL_TOKEN_ID}' (a Vercel token) to enable it - see VERCEL_DEPLOYMENT.md."
                        currentBuild.result = 'UNSTABLE'
                    }
                }
            }
        }
    }

    post {
        always {
            script {
                // Only remove a Floci this build created; keep a shared one.
                if (env.FLOCI_STARTED_BY_BUILD == 'true') {
                    sh "docker rm -f ${env.FLOCI_CONTAINER} || true"
                }
            }
            cleanWs()
        }
        success { echo "Pipeline succeeded: ${env.JOB_NAME} #${env.BUILD_NUMBER}" }
        failure { echo "Pipeline failed: ${env.JOB_NAME} #${env.BUILD_NUMBER}" }
    }
}
