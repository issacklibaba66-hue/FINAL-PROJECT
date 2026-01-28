-- Users table
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('buyer', 'seller') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Auctions table
CREATE TABLE auctions (
    id SERIAL PRIMARY KEY,
    seller_id INT REFERENCES users(id),
    title VARCHAR(255) NOT NULL,
    description TEXT,
    starting_bid DECIMAL(10,2) NOT NULL,
    current_bid DECIMAL(10,2) DEFAULT 0,
    reserve_price DECIMAL(10,2),
    end_time TIMESTAMP NOT NULL,
    status ENUM('active', 'ended', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Bids table
CREATE TABLE bids (
    id SERIAL PRIMARY KEY,
    auction_id INT REFERENCES auctions(id),
    bidder_id INT REFERENCES users(id),
    amount DECIMAL(10,2) NOT NULL,
    bid_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Transactions table (for payments)
CREATE TABLE transactions (
    id SERIAL PRIMARY KEY,
    auction_id INT REFERENCES auctions(id),
    winner_id INT REFERENCES users(id),
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'completed', 'failed'),
    processed_at TIMESTAMP
);