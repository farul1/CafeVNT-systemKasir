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
        // Stages lainnya
    }

    post {
        always {
            echo 'Cleaning up workspace...'
            cleanWs()
        }
        
        success {
            echo 'Pipeline completed successfully.'
            script {
                def response = httpRequest(
                    acceptType: 'APPLICATION_JSON',
                    contentType: 'APPLICATION_JSON',
                    httpMode: 'POST',
                    url: 'https://discord.com/api/webhooks/1321705546398826496/RQ5vHgFAOBJJUqxlOQdJrRVoIUC5ZMbaYTJEXlKsA3Z2T7UkhcgSVL7NaeLz8NL-k7hU',
                    requestBody: '''{
                        "content": "✅ Build berhasil! Image Docker berhasil dibuat dengan tag: ${env.DOCKER_TAG}. 🚀 Cek log lengkap di Jenkins.",
                        "embeds": [
                            {
                                "footer": {
                                    "text": "Jenkins CI/CD - Build Sukses"
                                }
                            }
                        ]
                    }'''
                )
            }
        }

        failure {
            echo 'Pipeline failed. Check logs for details.'
            script {
                def response = httpRequest(
                    acceptType: 'APPLICATION_JSON',
                    contentType: 'APPLICATION_JSON',
                    httpMode: 'POST',
                    url: 'https://discord.com/api/webhooks/1321705546398826496/RQ5vHgFAOBJJUqxlOQdJrRVoIUC5ZMbaYTJEXlKsA3Z2T7UkhcgSVL7NaeLz8NL-k7hU',
                    requestBody: '''{
                        "content": "❌ Build gagal. Silakan cek detail error di Jenkins untuk penyebab kegagalan. ⚠️",
                        "embeds": [
                            {
                                "footer": {
                                    "text": "Jenkins CI/CD - Build Gagal"
                                }
                            }
                        ]
                    }'''
                )
            }
        }
    }
}
