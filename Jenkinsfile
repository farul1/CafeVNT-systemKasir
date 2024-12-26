pipeline {
    agent any
    environment {
        DOCKER_TAG = 'test'
    }

    post {
        always {
            echo 'Cleaning up workspace...'
            cleanWs()
        }

        success {
            echo 'Pipeline completed successfully.'
            discordSend description: "✅ Build berhasil! Image Docker berhasil dibuat dengan tag: ${env.DOCKER_TAG}. 🚀 Cek log lengkap di Jenkins.", 
                        footer: 'Jenkins CI/CD - Build Sukses', 
                        webhookURL: 'https://discord.com/api/webhooks/1321705546398826496/RQ5vHgFAOBJJUqxlOQdJrRVoIUC5ZMbaYTJEXlKsA3Z2T7UkhcgSVL7NaeLz8NL-k7hU'
        }

        failure {
            echo 'Pipeline failed. Check logs for details.'
            discordSend description: '❌ Build gagal. Silakan cek detail error di Jenkins untuk penyebab kegagalan. ⚠️', 
                        footer: 'Jenkins CI/CD - Build Gagal', 
                        webhookURL: 'https://discord.com/api/webhooks/1321705546398826496/RQ5vHgFAOBJJUqxlOQdJrRVoIUC5ZMbaYTJEXlKsA3Z2T7UkhcgSVL7NaeLz8NL-k7hU'
        }
    }
}
