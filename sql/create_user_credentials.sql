CREATE DATABASE IF NOT EXISTS fire_heureka_credentials;

GRANT ALL PRIVILEGES ON fire_heureka_credentials.* TO 'alex'@'localhost';

USE fire_heureka_credentials;

CREATE TABLE IF NOT EXISTS user_credentials (
      id                    INT                 AUTO_INCREMENT          PRIMARY KEY
    , username              VARCHAR(255)        NOT NULL                UNIQUE
    , password              VARCHAR(255)        NOT NULL
    , auth_token            VARCHAR(255)        DEFAULT NULL            UNIQUE
    , refresh_token         VARCHAR(255)        DEFAULT NULL
    , installation_id       VARCHAR(255)        DEFAULT NULL
);

INSERT INTO user_credentials(username, password) VALUES("mock_user", "b'$2b$12$wx9RitpuNcOJmLY.t5zlveBrlKMp.nHimig7ihMT4xDMPNzxIuytO'");