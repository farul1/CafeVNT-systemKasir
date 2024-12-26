pipeline {
    agent any
    tools {
        jdk 'JDK 21'
        maven 'maven3'
    }
    environment {
        DOCKER_TAG = ''
        TEAMS_WEBHOOK = 'https://telkomuniversityofficial.webhook.office.com/webhookb2/6a1bdddc-a025-4c59-b314-d4ca492ccdfd@90affe0f-c2a3-4108-bb98-6ceb4e94ef15/IncomingWebhook/73751f01c18546cb82b943f9a7c26896/a9372285-5933-4240-b618-f67784291e82/V2oT4SsOrjDLo8jc4dF05P5BGBIzeh33kFBmRRRFUN6XY1'
    }

    stages {
        stage('SCM') {
            steps {
                script {
                    try {
                        git credentialsId: 'cafevnt-github', 
                            url: 'https://github.com/farul1/CafeVNT-systemKasir'
                    } catch (Exception e) {
                        error "SCM checkout failed: ${e.message}"
                    }
                }
            }
        }

        stage('Set Version') {
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
            script {
                sendToTeams("✅ Build berhasil! Image Docker berhasil dibuat dengan tag: ${env.DOCKER_TAG}. 🚀 Cek log lengkap di Jenkins.")
            }
        }
        
        failure {
            script {
                sendToTeams("❌ Build gagal. Silakan cek detail error di Jenkins untuk penyebab kegagalan. ⚠️")
            }
        }
    }
}

def sendToTeams(message) {
    def payload = """{
        "text": "${message}"
    }"""

    httpRequest httpMode: 'POST', 
                contentType: 'APPLICATION_JSON', 
                requestBody: payload, 
                url: env.TEAMS_WEBHOOK
}
