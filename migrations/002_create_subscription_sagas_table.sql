CREATE TABLE IF NOT EXISTS subscription_sagas (
    id CHAR(32) NOT NULL PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    repo VARCHAR(255) NOT NULL,
    state VARCHAR(50) NOT NULL,
    confirm_token VARCHAR(64) NULL,
    unsubscribe_token VARCHAR(64) NULL,
    compensation_reason VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_subscription_sagas_state (state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
