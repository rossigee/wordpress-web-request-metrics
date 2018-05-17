#!groovy

pipeline {
    agent {
        docker { 
            image 'rossigee/wordpress-cd-s3'
        }
    }

    options {
        // Only keep the 10 most recent builds
        buildDiscarder(logRotator(numToKeepStr:'10'))
    }


    stages {
        stage('Build') {
            steps {
                sh 'build-wp-plugin -v'
                archiveArtifacts artifacts: 'build/*.zip'
            }
        }
        stage('Deploy') {
            environment {
                WPCD_DRIVERS = 'wordpress_cd_s3'
                WPCD_PLATFORM = 's3'
            }
            steps {
                sh 'deploy-wp-plugin -v'
            }
        }
    }
}
