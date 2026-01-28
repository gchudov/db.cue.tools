-- Users table for authentication
CREATE TABLE IF NOT EXISTS users (
    email TEXT PRIMARY KEY,
    role TEXT NOT NULL DEFAULT 'user',
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    last_login TIMESTAMP
);

-- Index for role-based queries
CREATE INDEX idx_users_role ON users(role);

-- Insert initial admin
-- TODO: Replace 'YOUR_GMAIL_ADDRESS@gmail.com' with your actual Gmail address
INSERT INTO users (email, role) VALUES
    ('YOUR_GMAIL_ADDRESS@gmail.com', 'admin')
ON CONFLICT (email) DO NOTHING;

-- Grant permissions to application user
GRANT SELECT, UPDATE ON users TO ctdb_user;
