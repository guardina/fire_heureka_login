CREATE DATABASE IF NOT EXISTS fire_heureka_credentials;

GRANT ALL PRIVILEGES ON fire_heureka_credentials.* TO 'debian'@'localhost';

USE fire_heureka_credentials;

CREATE TABLE IF NOT EXISTS user_credentials (
      id                    INT                 AUTO_INCREMENT              PRIMARY KEY
    , username              VARCHAR(255)        NOT NULL                    UNIQUE
    , password              BLOB                NOT NULL
    , installation_id       VARCHAR(255)        DEFAULT NULL
    , role                  VARCHAR(255)        DEFAULT 'PRACTICE'
);


CREATE TABLE IF NOT EXISTS user_tokens (
      id                    INT                 AUTO_INCREMENT
    , user_id               INT                 NOT NULL
    , access_token          TEXT                NOT NULL
    , refresh_token         TEXT                NOT NULL
    , token_expiry          TIMESTAMP
    , created_at            TIMESTAMP           DEFAULT CURRENT_TIMESTAMP
    , updated_at            TIMESTAMP           DEFAULT CURRENT_TIMESTAMP   ON UPDATE CURRENT_TIMESTAMP
    
    , PRIMARY KEY (id, user_id)
    , FOREIGN KEY (user_id) REFERENCES user_credentials(id)
);
