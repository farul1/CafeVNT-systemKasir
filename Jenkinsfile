pipeline {
    agent any
    tools {
        jdk 'JDK 21'
        maven 'maven3'
    }
    environment {
        DOCKER_TAG = ''
    }

    stages {
        stage('SCM') { // Checkout repository first
            steps {
                script {
                    try {
                        git credentialsId: 'cafevnt-github', 
                            url: 'https://github.com/syafarul/VNT_mesinkasirr'
                    } catch (Exception e) {
                        error "SCM checkout failed: ${e.message}"
                    }
                }
            }
        }

        stage('Set Version') { // Moved after SCM
            steps {
                script {
                    try {
                        def commitHash = bat(script: 'git rev-parse --short HEAD', returnStdout: true).trim()
                        env.DOCKER_TAG = commitHash
                    } catch (Exception e) {
                        error "Failed to get commit hash: ${e.message}"
                    }
                }
            }
        }

        stage('Install Dependencies') {
            steps {
                script {
                    try {
                        def gdCheck = bat(script: 'php -m | findstr gd', returnStatus: true)
                        if (gdCheck != 0) {
                            error "PHP GD extension is not enabled. Enable it in php.ini."
                        }
                        bat 'composer install --no-dev --optimize-autoloader'
                    } catch (Exception e) {
                        error "Failed to install dependencies: ${e.message}"
                    }
                }
            }
        }

        stage('Docker Build') {
            when {
                expression { currentBuild.result == null }
            }
            steps {
                script {
                    try {
                        bat "docker build -t farul672/vnt_kasir:${env.DOCKER_TAG} ."
                    } catch (Exception e) {
                        error "Docker build failed: ${e.message}"
                    }
                }
            }
        }
    }

    post {
        always {
            echo 'Cleaning up workspace...'
            cleanWs()
        }
        success {
            echo 'Pipeline completed successfully.'
        }
        failure {
            echo 'Pipeline failed. Check logs for details.'
        }
    }
}