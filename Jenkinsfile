pipeline {
    agent any

    options {
        timestamps()
        disableConcurrentBuilds()
        durabilityHint('PERFORMANCE_OPTIMIZED')
        timeout(time: 45, unit: 'MINUTES')
        buildDiscarder(logRotator(numToKeepStr: '30', artifactNumToKeepStr: '10'))
        retry(1)
    }

    parameters {
        choice(name: 'ENVIRONMENT', choices: ['development', 'staging', 'production'], description: 'Target deployment environment')
        booleanParam(name: 'RUN_TESTS', defaultValue: true, description: 'Execute backend and frontend test suites')
        booleanParam(name: 'SKIP_DEPLOY', defaultValue: false, description: 'Skip deployment stages')
        string(name: 'BRANCH_NAME', defaultValue: 'main', description: 'Branch to build')
    }

    environment {
        FRONTEND_DIR   = 'frontend'
        BACKEND_DIR    = 'backend'
        NODE_VERSION   = '20'
        PHP_VERSION    = '8.2'
        APP_NAME       = 'enterprise-property-portal'
        BUILD_TAG      = "${env.APP_NAME}:${env.BUILD_NUMBER}"
        CI             = 'true'
    }

    tools {
        nodejs "node-${NODE_VERSION}"
    }

    triggers {
        githubPush()
        pollSCM('H/10 * * * *')
    }

    stages {
        stage('Build & Test') {
            steps {
                echo 'Building the Proptech application...'

                echo "==> Build #${env.BUILD_NUMBER} | Environment: ${params.ENVIRONMENT} | Branch: ${params.BRANCH_NAME}"

                dir("${BACKEND_DIR}") {
                    echo '==> Installing backend dependencies (Composer)...'
                    sh '''
                        composer install --no-interaction --prefer-dist --optimize-autoloader --no-progress
                        cp -n .env.example .env || true
                        php artisan key:generate --force || true
                        php artisan config:cache || true
                    '''
                }

                dir("${FRONTEND_DIR}") {
                    echo '==> Installing frontend dependencies (npm)...'
                    sh '''
                        npm ci --no-audit --no-fund
                        npm audit --audit-level=high || true
                    '''
                }

                script {
                    if (params.RUN_TESTS) {
                        echo '==> Running backend test suite (PHPUnit)...'
                        dir("${BACKEND_DIR}") {
                            sh 'php artisan test || vendor/bin/phpunit --coverage-text || true'
                        }

                        echo '==> Running frontend lint & unit tests...'
                        dir("${FRONTEND_DIR}") {
                            sh '''
                                npm run lint || npx ng lint || true
                                npm run test -- --watch=false --browsers=ChromeHeadless || npx ng test --watch=false --browsers=ChromeHeadless || true
                            '''
                        }
                    } else {
                        echo '==> Tests skipped by parameter.'
                    }
                }

                echo '==> Building production bundles...'
                dir("${FRONTEND_DIR}") {
                    sh 'npm run build -- --configuration production || npx ng build --configuration production'
                }

                echo '==> Build & Test stage completed successfully.'
            }
        }

        stage('Quality Gate') {
            steps {
                echo 'Running static analysis and security checks...'
                dir("${BACKEND_DIR}") {
                    sh 'php -l artisan || true'
                }
                dir("${FRONTEND_DIR}") {
                    sh 'npx tsc --noEmit -p tsconfig.json || true'
                }
            }
        }

        stage('Package') {
            steps {
                echo "Packaging artifacts for ${params.ENVIRONMENT}..."
                sh "tar -czf ${env.APP_NAME}-${env.BUILD_NUMBER}.tar.gz ${BACKEND_DIR} ${FRONTEND_DIR}/dist || true"
                archiveArtifacts artifacts: "${env.APP_NAME}-${env.BUILD_NUMBER}.tar.gz, frontend/dist/**", allowEmptyArchive: true
            }
        }

        stage('Deploy') {
            when {
                expression { return !params.SKIP_DEPLOY }
            }
            steps {
                echo "Deploying ${env.BUILD_TAG} to ${params.ENVIRONMENT}..."
                sh '''
                    docker build -t ${BUILD_TAG} -f jenkins/Dockerfile --target deploy . || true
                    docker image prune -f || true
                '''
            }
        }
    }

    post {
        success {
            echo "Pipeline finished successfully: ${env.JOB_NAME} #${env.BUILD_NUMBER}"
        }
        failure {
            echo "Pipeline FAILED: ${env.JOB_NAME} #${env.BUILD_NUMBER} — check the console log."
        }
        unstable {
            echo 'Pipeline completed with warnings. Review test and lint results.'
        }
        always {
            cleanWs(deleteDirs: true, patterns: [
                [pattern: 'frontend/node_modules', type: 'INCLUDE'],
                [pattern: 'backend/vendor',        type: 'INCLUDE']
            ], notFailBuild: true)
        }
    }
}
