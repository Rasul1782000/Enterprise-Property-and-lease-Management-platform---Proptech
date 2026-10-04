pipeline {
    agent any

    options {
        timestamps()
        disableConcurrentBuilds()
        buildDiscarder(logRotator(numToKeepStr: '20'))
    }

    environment {
        FRONTEND_DIR = 'frontend'
        BACKEND_DIR  = 'backend'
        NODE_VERSION = '20'
        PHP_VERSION  = '8.2'
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Backend: Install Dependencies') {
            steps {
                dir("${BACKEND_DIR}") {
                    sh 'composer install --no-interaction --prefer-dist --optimize-autoloader'
                    sh 'cp -n .env.example .env || true'
                    sh 'php artisan key:generate --force || true'
                }
            }
        }

        stage('Backend: Tests') {
            steps {
                dir("${BACKEND_DIR}") {
                    sh 'php artisan test --parallel || vendor/bin/phpunit'
                }
            }
        }

        stage('Frontend: Install Dependencies') {
            steps {
                dir("${FRONTEND_DIR}") {
                    sh 'npm ci'
                }
            }
        }

        stage('Frontend: Lint') {
            steps {
                dir("${FRONTEND_DIR}") {
                    sh 'npm run lint || npx ng lint || true'
                }
            }
        }

        stage('Frontend: Build') {
            steps {
                dir("${FRONTEND_DIR}") {
                    sh 'npm run build -- --configuration production || npx ng build --configuration production'
                }
            }
            post {
                success {
                    archiveArtifacts artifacts: 'frontend/dist/**', allowEmptyArchive: true
                }
            }
        }
    }

    post {
        success {
            echo 'Build completed successfully.'
        }
        failure {
            echo 'Build failed. Check logs above.'
        }
        always {
            cleanWs(deleteDirs: true, patterns: [[pattern: 'frontend/node_modules', type: 'INCLUDE'], [pattern: 'backend/vendor', type: 'INCLUDE']], notFailBuild: true)
        }
    }
}
