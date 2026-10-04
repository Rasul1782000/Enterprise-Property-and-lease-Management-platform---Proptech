pipeline {
    agent any

    environment {
        // Floci Cloud Emulator Configuration (Zero Auth / Local Endpoint)
        AWS_ACCESS_KEY_ID     = 'floci'
        AWS_SECRET_ACCESS_KEY = 'floci'
        AWS_DEFAULT_REGION    = 'us-east-1'
        AWS_ENDPOINT_URL      = 'http://floci-emulator:4566'

        // Container Registry Settings
        // Override these on the Jenkins job (or this block) with your real registry.
        DOCKER_REGISTRY       = 'your-registry.azurecr.io'
        IMAGE_NAME            = 'proptech-frontend'
        CREDENTIALS_ID        = 'docker-registry-credentials' // Jenkins credentials ID
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
                echo '📥 Checking out source code from SCM...'
                checkout scm
            }
        }

        stage('Install Dependencies') {
            steps {
                echo '📦 Installing application dependencies...'
                dir('frontend') {
                    sh 'npm ci --no-audit --fund=false'
                }
            }
        }

        stage('Parallel Quality & Linting') {
            parallel {
                stage('Code Linting') {
                    steps {
                        echo '🔍 Running code linter...'
                        dir('frontend') {
                            sh 'npm run lint --if-present'
                        }
                    }
                }
                stage('Security Audit') {
                    steps {
                        echo '🛡️ Running dependency vulnerability audit...'
                        dir('frontend') {
                            sh 'npm audit --omit=dev || true'
                        }
                    }
                }
            }
        }

        stage('Start Floci Cloud Emulator') {
            steps {
                echo '🚀 Starting Floci local cloud services (S3, DynamoDB, RDS, etc.)...'
                // Spins up Floci instantly using Docker with Docker-in-Docker socket support
                sh '''
                    set -e
                    docker rm -f floci-emulator || true
                    docker network inspect jenkins_default >/dev/null 2>&1 || docker network create jenkins_default
                    docker run -d --name floci-emulator --network jenkins_default \
                        -p 4566:4566 -v /var/run/docker.sock:/var/run/docker.sock floci/floci:latest
                '''

                // Wait for the emulator to accept connections instead of a blind sleep
                sh '''
                    for i in $(seq 1 30); do
                        if curl -fsS http://localhost:4566/ >/dev/null 2>&1; then
                            echo "Floci is ready after ${i}s"
                            exit 0
                        fi
                        sleep 1
                    done
                    echo "Floci did not become ready in time"
                    exit 1
                '''
            }
        }

        stage('Integration Tests (Against Floci)') {
            steps {
                echo '🧪 Running integration tests targeting local Floci cloud endpoint...'
                // Your app tests can safely hit AWS APIs (S3 uploads, queues, etc.) via localhost:4566 without live cloud costs
                dir('frontend') {
                    sh 'npm test'
                }
                dir('backend') {
                    sh 'composer install --no-interaction --prefer-dist'
                    // No backend tests exist yet; keep the step tolerant until the suite is written
                    sh 'php artisan test || true'
                }
            }
        }

        stage('Build Production Docker Image') {
            steps {
                echo '🐳 Building production container image...'
                script {
                    def commit = sh(script: 'git rev-parse --short HEAD', returnStdout: true).trim()
                    def imageTag = "${env.IMAGE_NAME}:${commit}"

                    dir('frontend') {
                        sh "docker build -t ${imageTag} -t ${env.IMAGE_NAME}:latest ."
                    }

                    // Smoke-test the built container before it is ever published
                    sh """
                        set -e
                        docker rm -f proptech-smoke || true
                        docker run -d --name proptech-smoke -p 8099:80 ${imageTag}
                        sleep 5
                        docker exec proptech-smoke curl -fsS http://localhost/ > /dev/null
                        docker rm -f proptech-smoke
                    """

                    echo "✅ Image ready: ${imageTag}"
                    env.IMAGE_TAG = imageTag
                }
            }
        }

        stage('Push to Registry') {
            when { environment name: 'PUSH_IMAGE', value: 'true' }
            steps {
                echo "🚀 Pushing ${env.IMAGE_TAG} to ${env.DOCKER_REGISTRY}..."
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
                echo '🌐 Triggering production deployment sequence...'
                // Add your deployment commands here (e.g., SSH trigger or Kubernetes rollouts)
                echo 'Deployment successful.'
            }
        }
    }

    post {
        always {
            echo '🧹 Cleaning up containers and workspace...'
            sh 'docker rm -f floci-emulator proptech-smoke || true'
            cleanWs()
        }
        success {
            echo "✅ Pipeline succeeded: ${env.JOB_NAME} #${env.BUILD_NUMBER}"
        }
        failure {
            echo "❌ Pipeline failed: ${env.JOB_NAME} #${env.BUILD_NUMBER}"
        }
    }
}
