pipeline {
    agent any

    environment {
        // Floci Cloud Emulator Configuration (Zero Auth / Local Endpoint)
        AWS_ACCESS_KEY_ID     = 'floci'
        AWS_SECRET_ACCESS_KEY = 'floci'
        AWS_DEFAULT_REGION    = 'us-east-1'
        AWS_ENDPOINT_URL      = 'http://floci-emulator:4566'

        // Container Registry Settings
        DOCKER_REGISTRY       = 'your-registry.azurecr.io' // Change to your container registry URL
        IMAGE_NAME            = 'proptech-enterprise-app'
        CREDENTIALS_ID        = 'docker-registry-credentials' // Jenkins credentials ID
    }

    options {
        buildDiscarder(logRotator(numToKeepStr: '10'))
        timeout(time: 2, unit: 'HOURS')
        disableConcurrentBuilds()
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
                dir('backend') {
                    sh 'npm install'
                }
            }
        }

        stage('Parallel Quality & Linting') {
            parallel {
                stage('Code Linting') {
                    steps {
                        echo '🔍 Running code linter...'
                        dir('backend') {
                            sh 'npm run lint --if-present'
                        }
                    }
                }
                stage('Security Audit') {
                    steps {
                        echo '🛡️ Running dependency vulnerability audit...'
                        dir('backend') {
                            sh 'npm audit --production || true'
                        }
                    }
                }
            }
        }

        stage('Start Floci Cloud Emulator') {
            steps {
                echo '🚀 Starting Floci local cloud services (S3, DynamoDB, RDS, etc.)...'
                // Spins up Floci instantly using Docker with Docker-in-Docker socket support
                sh 'docker rm -f floci-emulator || true'
                sh 'docker run -d --name floci-emulator --network jenkins_default -p 4566:4566 -v /var/run/docker.sock:/var/run/docker.sock floci/floci:latest'

                // Allow the native application a split second to finalize bindings
                sleep(time: 3, unit: 'SECONDS')
            }
        }

        stage('Integration Tests (Against Floci)') {
            steps {
                echo '🧪 Running integration tests targeting local Floci cloud endpoint...'
                // Your app tests can safely hit AWS APIs (S3 uploads, queues, etc.) via localhost:4566 without live cloud costs
                dir('backend') {
                    sh 'npm test'
                }
            }
        }

        stage('Build Production Docker Image') {
            steps {
                echo '🐳 Building production container image...'
                script {
                    dir('backend') {
                        appImage = docker.build("${env.DOCKER_REGISTRY}/${env.IMAGE_NAME}:${env.BUILD_NUMBER}", "--build-arg NODE_ENV=production .")
                    }
                }
            }
        }

        stage('Push to Registry') {
            steps {
                echo '🚀 Pushing container image to registry...'
                script {
                    docker.withRegistry("https://${env.DOCKER_REGISTRY}", "${env.CREDENTIALS_ID}") {
                        appImage.push()
                        appImage.push('latest')
                    }
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
            echo '🧹 Cleaning up Floci container and workspace...'
            sh 'docker rm -f floci-emulator || true'
            cleanWs()
        }
    }
}
