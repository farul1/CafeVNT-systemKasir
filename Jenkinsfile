pipeline {
    agent any
    tools {
        jdk 'JDK 21'          
        maven 'maven3'         
        ansible 'Ansible 2.9'   
    }
    environment {
        DOCKER_TAG = ''
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

        stage('Setup Server with Ansible') { 
            steps {
                script {
                    try {
                        withCredentials([sshUserPrivateKey(credentialsId: 'ec2-ssh-key', keyFileVariable: 'SSH_KEY')]) {
                            ansiblePlaybook playbook: 'setup-server.yml', inventory: 'inventory.ini', extraVars: [ansible_ssh_private_key_file: "${SSH_KEY}"]
                        }
                    } catch (Exception e) {
                        error "Ansible setup failed: ${e.message}"
                    }
                }
            }
        }

        stage('Deploy Application with Ansible') { 
            steps {
                script {
                    try {
                        withCredentials([sshUserPrivateKey(credentialsId: 'ec2-ssh-key', keyFileVariable: 'SSH_KEY')]) {
                            ansiblePlaybook playbook: 'deploy-app.yml', inventory: 'inventory.ini', extraVars: [ansible_ssh_private_key_file: "${SSH_KEY}"]
                        }
                    } catch (Exception e) {
                        error "Ansible deploy failed: ${e.message}"
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
            discordSend description: "✅ Build berhasil! Image Docker berhasil dibuat dengan tag: ${env.DOCKER_TAG}. 🚀 Cek log lengkap di Jenkins.",
                        footer: 'Jenkins CI/CD - Build Sukses',
                        webhookURL: 'https://discord.com/api/webhooks/1321977592731144226/ua7asoAR0O5KAFQrUgZHrYunx-1L_mLBgV6hp08Xe960xDgAUXkMRh0FeJRjcrIypjr1'
        }

        failure {
            echo 'Pipeline failed. Check logs for details.'
            discordSend description: '❌ Build gagal. Silakan cek detail error di Jenkins untuk penyebab kegagalan. ⚠',
                        footer: 'Jenkins CI/CD - Build Gagal',
                        webhookURL: 'https://discord.com/api/webhooks/1321977592731144226/ua7asoAR0O5KAFQrUgZHrYunx-1L_mLBgV6hp08Xe960xDgAUXkMRh0FeJRjcrIypjr1'
        }
    }
}
