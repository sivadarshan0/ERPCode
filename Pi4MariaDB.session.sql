CREATE INDEX idx_users_username_active ON users(username, is_active);
CREATE INDEX idx_users_reset_token ON users(reset_token);